<?php
namespace YY\System;

use Exception;
use YY\Core\Cache;
use YY\Core\Data;
use YY\Core\Importer;
use YY\System\Exception\EReloadSignal;

/**
 * @property Data CONFIG
 * @property Data VIEWS
 */
class YY extends Robot // Странно, похоже, такое наследование позволяет вызвать защищенный метод _PAINT у (другого) экземпляра класса YY\System\Robot
{

	// TODO: Может $WORLD и $ME сделать функциями?
	static public $WORLD;
	static public $ME;

	static public $RELOAD_URL;
	static public $RELOAD_TOP;

	/**
	 * @var View $CURRENT_VIEW
	 */
	static public $CURRENT_VIEW;
	/**
	 * @var Data $OUTGOING
	 */
	static private $OUTGOING;
	static private $DELETED;
	static private $ADD_HEADERS;
	static private $EXECUTE_BEFORE;
	static private $EXECUTE_AFTER;

	static public function Log($kind, $msg = null)
	{
		if (DEBUG_MODE) { // TODO: Чой-то так? Кое-какие логи могут быть всегда, например, gatekeeper. Надо отдельно для каждого регулировать, видимо.
			if ($msg === null) { // Отладочный вывод можно одним аргументом передавать
				$msg = $kind;
				$kind = 'debug';
			}
			Log::Log($kind, $msg);
		}
	}

	static public function Config($way = null)
	{
		return self::$WORLD['CONFIG']->_OFFSET($way);
	}

	static public function Local($way = null)
	{
		return self::$WORLD['LOCAL']->_OFFSET($way);
	}

	static public function LoadWorld()
	{
		if (isset(self::$WORLD)) return;
        Data::InitializeStorage();
		$fname = DATA_DIR . "world.id";
		if (file_exists($fname)) {
			$world_id = file_get_contents($fname);
			self::$WORLD = Data::_load($world_id);
		} else {
			$world_id = null;
		}
		if (!self::$WORLD) {
			YY::Log('system', 'Will create World...');
			$init = [];
			if ($world_id) {
				$init['_YYID'] = $world_id;
			}
			self::$WORLD = new Data($init);
			self::$WORLD->_REF; // Lock in persistent storage
			Importer::reloadWorld();
            if (!file_exists(DATA_DIR)) {
                mkdir(DATA_DIR, 0777, true);
            }
			file_put_contents($fname, self::$WORLD->_YYID);
			YY::Log('system', 'World created!');
			if (!file_exists(SESSIONS_DIR)) {
				mkdir(SESSIONS_DIR, 0777, true);
			}
		}
	}

	static public function createNewView($YYID = null)
	{
		assert(!isset(YY::$CURRENT_VIEW));

		// Проверяем на превышение максимального количества. При превышении убиваем самое старое или которое вообще без даты доступа.
		$views = self::$ME->VIEWS;
		$maxViews = self::$WORLD['SYSTEM']['maxViewsPerIncarnation'];
		while (count($views) >= $maxViews) {
			$oldestViewKey = null;
			$oldestAccess = null;
			foreach ($views as $key => $view) {
				if ($oldestAccess === null || !isset($view['lastAccess']) || $view['lastAccess'] < $oldestAccess) {
					$oldestViewKey = $key;
					$oldestAccess = isset($view['lastAccess']) ? $view['lastAccess'] : 0;
				}
			}
			if (isset($oldestViewKey)) {
				$oldestView = $views->_DROP($oldestViewKey);
				unset($views[$oldestViewKey]);
				$oldestView->_delete();
			}
		}

		// Создаем новый сеанс
		$init = [
			'RENDERED' => [],
			'HEADERS' => [],
			'DELETED' => [],
			'HANDLES' => [],
			'created' => time(), // Нужно для протоколирования
		];
		if ($YYID) { // TODO: Не помню, зачем создавать новый объект с таким-же YYID, который был? Может, поэтому и теряются объекты?
			// TODO: Надо это или прокомментировать, или удалить
			$init['_YYID'] = $YYID;
		}
		$newView = new View($init);
		$views[$newView->_YYID] = $newView;
		self::$CURRENT_VIEW = $newView;
		if (isset($_SESSION['request'])) {
			// Перемещаем изначальный запрос из сессии PHP в свой сеанс
			$request = $_SESSION['request'];
			$queryString = $_SESSION['queryString'];
			unset($_SESSION['request']);
			unset($_SESSION['queryString']);
		} else {
			$request = [];
			$queryString = '';
		}
		YY::$CURRENT_VIEW['request'] = $request;
		YY::$CURRENT_VIEW['queryString'] = $queryString;
		YY::$CURRENT_VIEW['secure'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'];

		YY::Log('system', 'New view created: ' . $newView->_full_name());

		// Пользовательская бизнес-логика
		YY::$WORLD['SYSTEM']->viewCreated();
	}

	/**
	 * @param      $templateName
	 * @param null $params - DO NOT REMOVE. May be used in template.
	 */
	static public function DrawEngine($templateName, $params = null)
	{
		YY::Log('system', 'Draw engine ' . $templateName);

		$debugOutput = Log::GetScreenOutput();
		if (isset(self::$CURRENT_VIEW, self::$CURRENT_VIEW['ROBOT']) && is_object(self::$CURRENT_VIEW['ROBOT'])) {
			self::$CURRENT_VIEW['ROBOT']['_debugOutput'] = $debugOutput;
		}

		self::DisableCaching();
		header('Content-Type: text/html; charset=utf-8');
		include TEMPLATES_DIR . $templateName;
	}

	static public function TryRestore()
	{
		if (isset(self::$ME)) { // TODO: По-хорошему не должно быть, но почему-то бывают повторные входы
			YY::Log('error', 'Duplicate TryRestore');
			return;
		}
		// Загружаем инкарнацию, если есть
		if (Utils::IsSessionValid() && isset($_SESSION[YYID])) {
			self::$ME = Data::_load($_SESSION[YYID]); // Может и не существовать - тогда останется null
		}
	}

	static public function createNewIncarnation($YYID = null, $startSession = true)
	{
		assert(!self::$ME);
		$init = ['VIEWS' => []];
		if ($YYID) {
			$init['_YYID'] = $YYID;
		}
		self::$ME = new YY($init);
		self::$ME->_REF; // Блокирует объект, чтобы он записался в постоянную память
		if ($startSession) {
			Utils::StartSession(YY::$ME->_YYID); // TODO: А разве к этому моменту сессия не должна быть всегда уже создана?
		}
		if (!$YYID) {
			YY::$WORLD['SYSTEM']->incarnationCreated();
		}
	}

	static public function sendJson($data)
	{
		$json = json_encode($data);
		if ($json === false) {
			throw new Exception('Error json encode data: ' . print_r($data, true));
		}
		header('Content-Type: application/json; charset=utf-8');
		//    header('Content-Type: text/plain; charset=utf-8');
		header('Content-Length: ' . strlen($json));
		echo $json;
	}

	/**
	 * @param string|null $url
	 * @param bool        $top
	 *
	 * @throws EReloadSignal
	 * Allow to be called from inside event handlers as well as inside _PAINT
	 */
	static public function redirectUrl($url = null, $top = false)
	{
		YY::Log('system', 'Reload signal initiated');
		if ($url) {
			self::$RELOAD_URL = $url;
		} else {
			self::$RELOAD_URL = PROTOCOL . ROOT_URL . (YY::$CURRENT_VIEW['queryString'] ? '?' . YY::$CURRENT_VIEW['queryString'] : '');
		}
		self::$RELOAD_TOP = $top;
		throw new EReloadSignal();
	}

	static private function drawReload($message = null)
	{
		$json = [];
		if ($message) {
			$message = json_encode($message);
			$json['<'] = "alert($message)";
		}
		$signal = self::$RELOAD_TOP ? '!!' : '!';
		if (self::$RELOAD_URL) {
			$url = self::$RELOAD_URL;
			self::$RELOAD_URL = null;
		} else {
			$url = null;
		}
		$json[$signal] = $url;
		self::sendJson($json);
		YY::Log('system', 'Reload signal send');
	}

	static public function Run()
	{
		self::Log(['time', 'system'], '============START============');

		self::LoadWorld();

		self::$WORLD['SYSTEM']->started();

		self::$ME = null;

		$view = null;

		if (CRON_MODE) {

			self::Log('system', '=========CHILD START=========');

			global $argv;
			$query = array_slice($argv, 1);
			parse_str(implode('&', $query), $_GET);

			self::_GET($_GET);

			self::Log('system', '=========CHILD STOP=========');

		} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {

			if (isset($_GET['who'])) { // В этом случае who содержит код сеанса и дескриптор (внутри сеанся) робота, склеенные через дефис

				self::_GET($_GET); // Самостоятельно ставит заголовки, в т. ч. управляющие кэшем

			} else {

				YY::$WORLD['SYSTEM']->processGetRequest();

			}

		} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {

			if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
				header("Location: http://" . ROOT_URL, null, 300);
				exit;
			}

			if (!isset($_POST['view'])) {
				YY::Log('system', 'Invalid POST request: ' . print_r($_POST, true));
				self::drawReload();
				Cache::Flush(); // TODO: А зачем? Что могло поменяться в мире? Разве что SYSTEM->started выполнено. Но там сейчас пусто
				return;
			}

			self::$RELOAD_URL = false;
			self::$OUTGOING = new Data();
			self::$ADD_HEADERS = [];
			self::$EXECUTE_BEFORE = null;
			self::$EXECUTE_AFTER = null; // По крайней мере, clientExecute может вызываться в обработчике, а не только в PAINT

			$viewId = $_POST['view'];
			$isFirstPost = count($_POST) === 1;

			self::TryRestore();

			if ($isFirstPost) {
				self::$WORLD['SYSTEM']->incarnationRequired();
				if (!isset(self::$ME)) {
					YY::createNewIncarnation();
				}
			} else if (!isset(self::$ME)) {
				self::drawReload();
				return;
			}

			YY::$CURRENT_VIEW = null;
			$views = YY::$ME['VIEWS'];
			if (isset($views[$viewId])) {
				$view = $views[$viewId];
				if ($view === null || !isset($view['ROBOT']) || $view['ROBOT'] === null) { // Видимо, сильно старый, удален.
					unset($views[$viewId]); // Без куратора нет смысла в сеансе.
					Cache::Flush();
					self::drawReload();
					return;
				}
				YY::$CURRENT_VIEW = $view;
			} else if ($isFirstPost) {
				try {
					YY::createNewView($viewId);
				} catch (EReloadSignal $e) {
					Cache::Flush();
					self::drawReload();
					return;
				}
				if (isset(YY::$CURRENT_VIEW['ROBOT'])) { // Устанавливается в SYSTEM->viewCreated()
					$robot = YY::$CURRENT_VIEW['ROBOT'];
					$tag = isset($robot['tag']) ? $robot['tag'] : 'div';
					$robotAttributes = isset($robot['attributes']) ? YY::GetAttributesText($robot['attributes']) : "";
					$robotText = '<' . $tag . ' id="_YY_' . YY::GetHandle($robot) . '"' . $robotAttributes . '></' . $tag . '>';
					YY::clientExecute("document.body.insertAdjacentHTML('afterBegin','$robotText');", true);
				}
			} else {
				// Ненормальная ситуация
				Cache::Flush();
				self::drawReload();
				return;
			}
			self::$CURRENT_VIEW['lastAccess'] = time();

			YY::$WORLD['SYSTEM']->initializePostRequest();

			if (count($_FILES)) { // Вот такие странные соглашения!

				self::_UPLOAD(array_pop($_FILES), $_GET);
				YY::Log('system', 'File uploaded');
				// TODO: Call some kind of user code to reflect success uploading (event or whatnot)

			} else {

				self::DisableCaching();

				if ($isFirstPost) { // Специальный случай - инициализация после обновления.

					YY::$WORLD['SYSTEM']->viewRetrieved();

				} else {

					ob_start();
					try {
						self::_DO($_POST);
						$debugOutput = Log::GetScreenOutput();
						if (isset(self::$CURRENT_VIEW, self::$CURRENT_VIEW['ROBOT']) && is_object(self::$CURRENT_VIEW['ROBOT'])) {
							self::$CURRENT_VIEW['ROBOT']['_debugOutput'] = $debugOutput;
						}
						$output = ob_get_clean();
						if ($output > "") {
							YY::Log(array('system', 'error'), "Output during method execution:\n" . $output);
						}
					} catch (Exception $e) {
						ob_end_clean();
						YY::Log('error', $e->getMessage());
						$msg = null;
						if (DEBUG_MODE && DEBUG_ALLOWED_IP) {
							$msg = $e->getMessage();
						}
						self::drawReload($msg);
//						Cache::Flush();
						return;
					}
				}

				if (YY::$RELOAD_URL) {
					self::drawReload();
				} else {
					ob_start();
					try {
						$robot = isset(self::$CURRENT_VIEW['ROBOT']) ? self::$CURRENT_VIEW['ROBOT'] : null;
						if ($robot) $robot->_SHOW();
						ob_end_clean();
					} catch (Exception $e) {
						if (get_class($e) !== 'YY\System\Exception\EReloadSignal') {
							YY::Log('system,error', $e->getMessage());
						}
						ob_end_clean();
					}
					if (self::$RELOAD_URL) { // TODO: It's a bit weird. How can it come within _SHOW call?
						self::drawReload();
					} else {
						self::sendJson(self::receiveChanges());
					}
				}
			}

		} else if (in_array($_SERVER['REQUEST_METHOD'], ['HEAD', 'OPTIONS'])) {

			// Just ignore for now

		} else {

			YY::Log('system', "Unexpected HTTP request method: " . $_SERVER['REQUEST_METHOD']);

		}

		Cache::Flush();

		self::Log(['time', 'system'], '============FINISH===========');
	}

	static public function DisableCaching()
	{
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Expires: Mon, 26 Jul 1997 05:05:05 GMT");
		header("Cache-Control: no-cache, must-revalidate");
		header("Cache-Control: post-check=0,pre-check=0", false);
		header("Cache-Control: max-age=0", false);
		header("Pragma: no-cache");
	}

	static public function GetHandle($object, View $view = null)
	{
		if (!$view) {
			$view = self::$CURRENT_VIEW;
		}
		return $view ? $view->makeObjectHandle($object) : null;
	}

	static private function GetObjectByHandle($handle, View $view = null)
	{
		if (!$view) {
			$view = self::$CURRENT_VIEW;
		}
		return $view->findObjectByHandle($handle);
	}

	static public function GetAttributesText($attributes)
	{
		if (is_string($attributes)) {
			return ' ' . $attributes;
		} else {
			$res = '';
			foreach ($attributes as $name => $value) {
				if ($name === 'style' && !is_string($value)) {
					$val = '';
					foreach ($value as $k => $v) {
						$val .= ';' . $k . ':' . $v;
					}
					$value = substr($val, 1);
				}
				$res .= ' ' . $name . '="' . htmlspecialchars($value) . '"';
			}
		}
		return $res;
	}

	static private function modifyVisual($visual, &$htmlBefore, &$htmlBeforeContent, &$htmlAfterContent, &$htmlAfter, &$attributes, &$styles, &$classes)
	{
		if (!isset($visual)) return;
		if (is_string($visual)) {
			if (isset(self::$WORLD['SYSTEM']['defaultStyles'])) {
				$visual = self::$WORLD['SYSTEM']['defaultStyles']->_OFFSET($visual);
			} else {
				throw new Exception('Named style not found: ' . $visual);
			}
		} else if (!$visual) {
			$visual = [];
		}
		foreach ($visual as $name => $value) {
			if (substr($name, 0, 1) === '_' || in_array($name, ['class', 'style', 'before', 'after', 'beforeContent', 'afterContent'], true)) {
				continue;
			}
			if ($name === '@' || is_int($name)) {
				self::modifyVisual($value, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributes, $styles, $classes);
			} else if ($name === '@@') {
				foreach ($value as $vis) {
					self::modifyVisual($vis, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributes, $styles, $classes);
				}
			} else {
				$attributes[$name] = $value;
			}
		}
		if (isset($visual['before'])) $htmlBefore = $visual['before'] . $htmlBefore;
		if (isset($visual['beforeContent'])) $htmlBeforeContent = $htmlBeforeContent . $visual['beforeContent'];
		if (isset($visual['afterContent'])) $htmlAfterContent = $visual['afterContent'] . $htmlAfterContent;
		if (isset($visual['after'])) $htmlAfter = $htmlAfter . $visual['after'];
		if (isset($visual['class'])) {
			$cls = $visual['class'];
			if (is_string($cls)) {
				$cls = explode(' ', $cls);
			} else if (!$cls) {
				$cls = [];
			}
			foreach ($cls as $className) {
				if ($className !== '') {
					$classes[$className] = null;
				}
			}
		}
		if (isset($visual['style'])) {
			$thisStyle = $visual['style'];
			if (is_string($thisStyle)) {
				$styles[] = $thisStyle;
			} else {
				foreach ($thisStyle as $key => $val) {
					if (substr($key, 0, 1) === '_') continue;
					$styles[$key] = $val;
				}
			}
		}
	}

	static private function parseVisual(
		$visual,
		&$htmlBefore,
		&$htmlBeforeContent,
		&$htmlAfterContent,
		&$htmlAfter,
		&$attributesText,
		&$content = null
	) {
		$htmlBefore = '';
		$htmlBeforeContent = '';
		$htmlAfterContent = '';
		$htmlAfter = '';
		$attributes = null;
		$styles = null;
		$classes = null;
		self::modifyVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributes, $styles, $classes);
		if ($content) {
			self::Translate($content, $attributes);
		}
		$attributesText = '';
		if ($attributes) {
			foreach ($attributes as $name => $value) {
				$attributesText .= ' ' . $name . '="' . htmlspecialchars($value) . '"'; // TODO: А может тут другую какую-то функцию надо.
			}
		}
		if ($classes) {
			$attributesText .= ' class="' . implode(' ', array_keys($classes)) . '"';
		}
		if ($styles) {
			$attributesText .= ' style="';
			$firstStyle = true;
			foreach ($styles as $key => $val) {
				if ($firstStyle) {
					$firstStyle = false;
				} else {
					$attributesText .= ';';
				}
				if (is_int($key)) {
					$attributesText .= $val;
				} else {
					$attributesText .= $key . ': ' . $val; // TODO: А тут типа не надо ни htmlspecialchars ни другого ничего?
				}
			}
			$attributesText .= '"';
		}
	}

	/**
	 * @param $visual
	 * @param null|'before'|'after' $part
	 *
	 * @return string
	 */

	static public function drawVisual($visual, $part = null)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText);
		if ($attributesText > '') {
			YY::Log('error', "Can not use attributes in 'drawVisual' (" . $attributesText . ")");
		}
		$res = '';
		if ($part === null || $part === 'before') $res .= $htmlBefore . $htmlBeforeContent;
		if ($part === null || $part === 'after') $res .= $htmlAfterContent . $htmlAfter;
		return $res;
	}

	static private function packParams($params, $isScript)
	{
		$res = '';
		if ($params) {
			foreach ($params as $paramName => $paramValue) {
				if ($res) $res .= $isScript ? ',' : '&';
				$paramType = null;
				if (is_array($paramValue)) { // Ссылка на параметр какого-то робота (возможно, себя), который будет передаваться как строка
					$paramType = "r_";
					$paramValue = self::GetHandle($paramValue[0]) . '.' . $paramValue[1];
				} else if (is_object($paramValue)) {
					$paramType = "o_";
					$paramValue = self::GetHandle($paramValue);
				} else if (is_bool($paramValue)) {
					$paramType = "b_";
					$paramValue = ($paramValue ? "1" : "0");
				} else if (is_string($paramValue)) {
					$paramType = "s_";
				} else if (is_int($paramValue)) {
					$paramType = "i_";
				} else if (is_numeric($paramValue)) {
					$paramType = "d_";
				} else if ($paramValue === null) {
					$paramType = "z_";
					$paramValue = "null";
				} else {
					$paramType = "e_"; // Заведомо ошибочный префикс, будет ошибка при распаковке
				}
				if ($paramType) {
					if ($isScript) {
						$res .= $paramType . $paramName . ":'" . htmlspecialchars($paramValue) . "'";
					} else {
						if ($paramType) $res .= $paramType . $paramName . "=" . urlencode($paramValue);
					}
				}
			}
		}
		return $res;
	}

	static public function drawCommand($visual, $htmlCaption, $robot, $method, $params = null)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText, $htmlCaption);
		$otherParams = self::packParams($params, true);
		return $htmlBefore . '<a' . $attributesText . ' href="javascript:void(0);" onclick="go(' . self::GetHandle($robot) . ',\'' . htmlspecialchars($method)
		. '\',{' . $otherParams . '}); return false;">' . $htmlBeforeContent . $htmlCaption . $htmlAfterContent . '</a>' . $htmlAfter;
	}

	static public function drawSwitch($visual, $htmlCaption, $robot, $param, $value, $method = null)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText, $htmlCaption);
		$handle = self::GetHandle($robot);
		$id = $handle . '[' . $param . ']=' . urlencode($value);
		$action = $method ? ";go($handle,\"$method\")" : '';
		$checked = isset($robot[$param]) && $robot[$param] == $value ? ' checked' : '';
		$element = "<input$attributesText$checked type='radio' name='$handle' id='$id' onclick='changed(this)$action; return false;'>";
		return "$htmlBefore<label for='$id'>$element$htmlBeforeContent$htmlCaption$htmlAfterContent</label>$htmlAfter";
	}

	static public function drawFlag($visual, $htmlCaption, $robot, $param, $method = null)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText, $htmlCaption);
		$handle = self::GetHandle($robot);
		$id = $handle . '[#' . $param . ']';
		$action = $method ? ";go($handle,\"$method\")" : '';
		$checked = isset($robot[$param]) && $robot[$param] ? ' checked' : '';
		$element = "<input$checked type='checkbox' name='$id' id='$id' onclick='changed(this)$action; return false;'>";
		return "$htmlBefore<label$attributesText for='$id'>$htmlBeforeContent$element&nbsp;$htmlCaption$htmlAfterContent</label>$htmlAfter";
	}

	static public function drawInternalLink($visual, $htmlCaption, $robot, $params = null)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText, $htmlCaption);
		$otherParams = self::packParams($params, false);
		return $htmlBefore . '<a' . $attributesText . ' href="?who=' . self::$CURRENT_VIEW->_YYID . '-' . self::GetHandle($robot) . '&' . $otherParams
		. '" target="_blank">' . $htmlBeforeContent . $htmlCaption . $htmlAfterContent . '</a>' . $htmlAfter;
	}

	static public function drawDocument($visual, $robot, $params = null)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText);
		$otherParams = self::packParams($params, false);
		return $htmlBefore . '<iframe src="?who=' . self::$CURRENT_VIEW->_YYID . '-' . self::GetHandle($robot) . '&' . $otherParams . '"' . $attributesText
		. '>' . $htmlBeforeContent . $htmlAfterContent . '</iframe>' . $htmlAfter;
	}

	static public function drawExternalLink($visual, $htmlCaption, $href)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText, $htmlCaption);
		return $htmlBefore . '<a' . $attributesText . ' href="' . $href . '">' . $htmlBeforeContent . $htmlCaption . $htmlAfterContent . '</a>' . $htmlAfter;
	}

	static public function drawInput($visual, $robot, $propertyName)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText);
		//    if ($htmlBeforeContent > '' || $htmlAfterContent > '') YY\System\YY::Log('error', "Can not use 'beforeContent' or 'afterContent' in 'drawInput'");
		if (substr($propertyName, 0, 1) === '#') {
			$realPropName = substr($propertyName, 1);
		} else {
			$realPropName = $propertyName;
		}
		$handle = self::GetHandle($robot);
		if (isset($visual['multiline']) && $visual['multiline']) {
			return $htmlBefore . $htmlBeforeContent . '<textarea' . $attributesText . ' name="' . $handle . '" id="' . $handle . '[' . $propertyName
			. ']" onchange="changed(this)" />' . htmlspecialchars($robot[$realPropName]) . '</textarea>' . $htmlAfterContent . $htmlAfter;
		} else {
			return $htmlBefore . $htmlBeforeContent . '<input' . $attributesText . ' type="text" name="' . $handle . '" id="' . $handle . '[' . $propertyName
			. ']" value="' . htmlspecialchars($robot[$realPropName]) . '" onchange="changed(this)" />' . $htmlAfterContent . $htmlAfter;
		}
	}

	static public function drawText($visual, $htmlText)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText, $htmlText);
		$htmlText = $htmlBeforeContent . $htmlText . $htmlAfterContent;
		if ($attributesText) $htmlText = '<span' . $attributesText . '>' . $htmlText . '</span>';
		return $htmlBefore . $htmlText . $htmlAfter;
	}

	// TODO: Надо кардинально переделать. Таким образом даже два аплоада не будут работать.
	static public function drawFile($visual, $robot, $propertyName)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText);
		$html = '<form style="display:inline" id="file_upload_form" method="post" enctype="multipart/form-data" action="?who=' . self::GetHandle($robot)
			. '&what=' . $propertyName . '">';
		$html .= $htmlBeforeContent;
		$html .= '<input type="file"' . $attributesText . ' name="' . $propertyName . '" id="' . self::$CURRENT_VIEW->_YYID . '-' . self::GetHandle($robot)
			. '[' . $propertyName . ']" onchange="changed(this)" />';
		$html .= $htmlAfterContent;
		$html .= '<input type="hidden" name="view" value="' . self::$CURRENT_VIEW->_YYID . '" />'; // TODO: Не нужно, наверное!
		$html .= '</form>';
		return $htmlBefore . $html . $htmlAfter;
	}

	/**
	 * @param $visual Data
	 * @param $robot  Robot
	 *
	 * @return string
	 */
	static public function drawSlaveRobot($visual, $robot)
	{
		/**@var $htmlBefore string
		 * @var $htmlBeforeContent string
		 * @var $htmlAfterContent  string
		 * @var $htmlAfter         string
		 * @var $attributesText    string
		 */
		self::parseVisual($visual, $htmlBefore, $htmlBeforeContent, $htmlAfterContent, $htmlAfter, $attributesText);
		ob_start();
		$robot->_SHOW();
		$htmlText = ob_get_clean();
		$htmlText = $htmlBeforeContent . $htmlText . $htmlAfterContent;
		// TODO: Сделать единый механизм для showRobot и drawSlaveRobot вместо вкладывания в div
		if ($attributesText) $htmlText = '<div' . $attributesText . '>' . $htmlText . '</div>';
		return $htmlBefore . $htmlText . $htmlAfter;
	}

	static public function clientExecute($script, $immidiate = false)
	{
		if ($immidiate) {
			self::$EXECUTE_BEFORE .= "\n" . $script;
		} else self::$EXECUTE_AFTER .= "\n" . $script;
	}

	//  static public function  getCurrentCurator()
	//  {
	//    if (!isset(self::$CURRENT_VIEW)) return null;
	//    if (!isset(self::$CURRENT_VIEW['ROBOT'])) return null;
	//    return self::$CURRENT_VIEW['ROBOT'];
	//  }

	/**
	 * @static
	 *
	 * @param Robot $robot
	 *
	 * @throws Exception
	 */
	static public final function showRobot($robot)
	{

		assert(self::$CURRENT_VIEW);

		$handle = self::$CURRENT_VIEW->makeObjectHandle($robot);

		// HEAD and attributes

		$firstTime = !isset(self::$CURRENT_VIEW['HEADERS'][$robot]);

		if (isset($robot['include'])) // TODO: Is there a reason to parse includes every time not only initial ($firstTime == true)?
		{
			self::$ADD_HEADERS = array_merge(self::$ADD_HEADERS, self::$CURRENT_VIEW->findNewHeaders($robot['include']));
		}

		if ($firstTime) { // Element attributes are persistent
			if (isset($robot['attributes'])) {
				$attributes = self::GetAttributesText($robot['attributes']);
			} else {
				$attributes = '';
			}
			self::$CURRENT_VIEW['HEADERS'][$robot] = $attributes;
		} else {
			$attributes = self::$CURRENT_VIEW['HEADERS'][$robot];
		}

		// BODY

		$faceExists = isset(self::$CURRENT_VIEW['RENDERED'][$handle]);
		if ($faceExists) {
			$wasFace = self::$CURRENT_VIEW['RENDERED'][$handle];
		} else {
			$wasFace = '';
			//self::$OUTGOING[$robot] = null; // Резервируем место, чтобы выдавались от старших к младшим
		}
		ob_start();
		try {
			$robot->_PAINT();
			$newFace = ob_get_clean();
		} catch (Exception $e) {
			if (get_class($e) === 'YY\System\Exception\EReloadSignal') throw $e;
			$errorMessage = $e->getMessage();
			if (DEBUG_MODE && DEBUG_ALLOWED_IP) {
				$newFace = ob_get_clean() . '<br/>' . $errorMessage;
				if (isset($robot['_debugOutput'])) {
					$newFace .= '<pre class="debug">';
					$newFace .= htmlspecialchars($robot['_debugOutput']);
					$newFace .= "</pre>";
				}
			} else {
				ob_end_clean();
				$newFace = 'Error :(';
			}
			YY::Log('error', $errorMessage);
		}

		$tag = isset($robot['tag']) ? $robot['tag'] : 'div';

		echo "<$tag id=_YY_" . $handle . $attributes . ">";
		if ($newFace !== $wasFace) {
			self::$OUTGOING[$handle] = $newFace;
		}
		echo "</$tag>";
	}

	static public function robotDeleting($robot)
	{
		if (self::$ME && !self::$ME->_DELETED) {
			/** @var View $view */
			foreach (self::$ME['VIEWS'] as $view) {
				$view->robotDeleting($robot);
			}
		}
	}

	static private function receiveChanges()
	{
		$json = [];
		if (self::$EXECUTE_BEFORE !== null) {
			$json['<'] = self::$EXECUTE_BEFORE;
			self::$EXECUTE_BEFORE = null;
		}
		foreach (self::$CURRENT_VIEW->DELETED as $yyid) {
			$json['-_YY_' . $yyid] = null;
		}
		self::$CURRENT_VIEW->DELETED->_CLEAR();
		foreach (self::$ADD_HEADERS as $idx => $head) {
			$json['^' . $idx] = $head;
		}
		foreach (self::$OUTGOING as $handle => $view) {
			$json['_YY_' . $handle] = $view;
			self::$CURRENT_VIEW->RENDERED[$handle] = $view;
		}
		if (self::$EXECUTE_AFTER !== null) {
			$json['>'] = self::$EXECUTE_AFTER;
			self::$EXECUTE_AFTER = null;
		}
		return $json;
	}

	// При вызове этой функции надо подавлять вывод на экран. Только действие, никакого отображения!

	static private final function _DO($_DATA)
	{
		$who = $_DATA['who'];
		assert(isset($who));
		$who = self::GetObjectByHandle($who);
		assert(isset($who));
		$do = $_DATA['do'];
		if (substr($do, 0, 1) === "_") throw new Exception("Can not call system methods"); // Это что еще за юный хакер тут?

		self::Log('system', 'DO ' . $who . '->' . $do);

		foreach ($_DATA as $key => $val) {
			if ($key === 'do' || $key === 'who' || $key === 'view') {
				// Уже обработаны
			} else if (is_array($val)) {
				// Изменившиеся свойства объектов
				$obj = self::GetObjectByHandle($key);
				foreach ($val as $prop => $prop_val) {
					if (substr($prop, 0, 1) === '#') {
						$prop = substr($prop, 1);
						$prop_val = $prop_val ? intval($prop_val) : null;
					}
					$obj[$prop] = $prop_val;
				}
			}
		}

		if (isset($do)) {
			$params = [];
			foreach ($_DATA as $key => $val) {
				if ($key === 'do' || $key === 'who' || $key == 'view' || is_array($val)) {
					// Уже обработаны
				} else {
					$type = substr($key, 0, 2);
					switch ($type) {
						case 'r_':
							$val = preg_split('/\./', $val);
							if (count($val) === 1) {
								//                $obj = $this; // TODO: Разобраться, может ли быть такой случай
								$paramName = $val[0];
							} else {
								$obj = self::GetObjectByHandle($val[0]);
								$paramName = $val[1];
							}
							$val = $obj->$paramName;
							break;
						case 'o_':
							if ($val == "") {
								$val = null;
							} else {
								$val = self::GetObjectByHandle($val);
							}
							break;
						case 'b_':
							if ($val == "") {
								$val = null;
							} else $val = ($val == "1");
							break;
						case 's_':
							break;
						case 'i_':
							if ($val == "") {
								$val = null;
							} else $val = intval($val);
							break;
						case 'd_':
							if ($val == "") {
								$val = null;
							} else $val = floatval($val);
							break;
						case 'z_':
							$val = null;
							break;
						default:
							throw new Exception("Untyped parameter: " . $key . "(" . $val . ")");
					}
					$key = substr($key, 2);
					$params[$key] = $val;
				}
			}
			try {
				$who->$do($params);
			} catch (Exception $e) {
				if (get_class($e) !== 'YY\System\Exception\EReloadSignal') throw($e);
			}
		}
	}

	static private final function _UPLOAD($file, $_DATA)
	{
		$who = $_DATA['who'];
		assert(isset($who));
		$who = explode('-', $who);
		assert(count($who) === 2);
		$view = Data::_load($who[0]);
		assert(isset($view));
		$who = self::GetObjectByHandle($who[1], $view);
		assert(isset($who));
		$prop_name = $_DATA['what'];
		assert(isset($prop_name));
		$who[$prop_name] = file_get_contents($file['tmp_name']);
	}

	static private final function _GET($_DATA)
	{
		$who = $_DATA['who'];
		assert(isset($who));

		// Передается или YYID сеанса и хэндл внутри сеанса через тире, или общеизвестный YYID конкретного объекта (поддержка старого кода)
		$who = explode('-', $who);
		if (count($who) === 2) {
			$view = Data::_load($who[0]);
			assert(isset($view));
			$who = self::GetObjectByHandle($who[1], $view);
		} else {
			$who = Data::_load($who[0]);
		}
		assert(isset($who));

		$methodName = isset($_DATA['get']) ? $_DATA['get'] : 'get';

		$params = [];
		foreach ($_DATA as $key => $val) {
			if ($key === 'who' || $key === 'get') {
				// Уже обработано
			} else {
				$type = substr($key, 0, 2);
				switch ($type) {
					case 'r_':
						$val = preg_split('/\./', $val);
						if (count($val) === 1) {
							//              $obj = $this; // TODO: Разобраться
							$paramName = $val[0];
						} else {
							$obj = self::GetObjectByHandle($val[0], $view);
							$paramName = $val[1];
						}
						$val = $obj->$paramName;
						break;
					case 'o_':
						if ($val == "") {
							$val = null;
						} else {
							$val = self::GetObjectByHandle($val, $view);
						}
						break;
					case 'b_':
						if ($val == "") {
							$val = null;
						} else $val = ($val == "1");
						break;
					case 's_':
						break;
					case 'i_':
						if ($val == "") {
							$val = null;
						} else $val = intval($val);
						break;
					case 'd_':
						if ($val == "") {
							$val = null;
						} else $val = floatval($val);
						break;
					case 'z_':
						$val = null;
						break;
					default:
						throw new Exception("Untyped parameter: " . $key);
				}
				$key = substr($key, 2);
				$params[$key] = $val;
			}
		}

		$who->$methodName($params);
	}

	///////////////////////////////////
	//
	// Translation
	//
	///////////////////////////////////

	/**
	 * @param $htmlCaption null|string|array|\Iterator
	 * @param $attributes
	 *
	 * @return void
	 *
	 */
	protected static function Translate(&$htmlCaption, &$attributes)
	{

		$translation = isset(self::$CURRENT_VIEW, self::$CURRENT_VIEW['TRANSLATION']) ? self::$CURRENT_VIEW['TRANSLATION'] : null;

		if ($htmlCaption) {

			if (is_scalar($htmlCaption)) {

				if (!$translation) return; // Just optimization

				// Make surrogate slug from text

				if (strlen($htmlCaption) <= 200) {
					$slug = md5($htmlCaption);
				} else {
					$slug = md5(substr($htmlCaption, 0, 100) . substr($htmlCaption, -100));
				}
				$original = $htmlCaption;
				$args = [];

			} else { // Assume array or Iterator

				$slug = null;
				$original = null;
				$args = [];
				foreach ($htmlCaption as $key => $val) {
					if ($slug === null) {
						// First item
						$slug = $key;
						if (is_int($slug)) {
							if (strlen($val) <= 200) {
								$slug = md5($val);
							} else {
								$slug = md5(substr($val, 0, 100) . substr($val, -100));
							}
						}
						$original = $val;
					} else {
						// Other items
						$args[] = $val;
					}
				}
			}


			$current = $original;
			if ($slug !== '' && $translation) {
				if (isset($translation[$slug])) {
					$current = $translation[$slug];
				}
			}
			if (count($args)) {
				$htmlCaption = vsprintf($current, $args);
			} else {
				$htmlCaption = $current;
			}

			if (isset(YY::$CURRENT_VIEW['TRANSLATOR'])) {
				$attributes['data-translate-slug'] = $slug;
				$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
				$trace = md5(print_r($stack, true));
				YY::$CURRENT_VIEW['TRANSLATOR']->registerTranslatable($trace, $slug, $original);
			}

		}

	}

	///////////////////////////////////
	//
	// Async method execution
	//
	///////////////////////////////////

	public static function Async($object, $method)
	{
		if (!preg_match("/[0-9A-Za-z]+/", $method)) throw new Exception("Invalid method name: $method");
		$yyid = $object->_YYID;
        $entry = CRON_MODE ? WEB_DIR . 'index.php' : $_SERVER['SCRIPT_FILENAME'];
		$cmd = "php $entry who=$yyid get=$method > /dev/null &";
		YY::Log("system", $cmd);
		exec($cmd, $output, $ret);
	}

}
