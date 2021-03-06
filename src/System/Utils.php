<?php
namespace YY\System;

use ReflectionFunction;
use YY\Core\Cache;

/**
 * Created 27.03.13
 */
class Utils
{

    public static function ImportEnvironmentVariables($list) {
        if (!is_array($list)) {
            $list = [$list => null];
        }
        foreach ($list as $varName => $defaultValue) {
            if (defined($varName)) continue;
            $readValue = getenv($varName);
            if ($readValue === false) $readValue = $defaultValue;
            define($varName, $readValue);
        }

    }

	/**
	 * @param $text
	 *
	 * @return string
	 */
	public static function ToNativeFilesystemEncoding($text)
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$text = iconv('utf-8', 'cp1251', $text);
		}
		return $text;
	}

	static public function StartSession($IncarnationYYID = null)
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			list($domain_without_port, $port) = explode(':', DOMAIN_NAME . ':');
			session_set_cookie_params(DEFAULT_SESSION_LIFETIME, '/', $domain_without_port);
			// Домен и путь, похоже, нужны, чтобы IE не глючил
			session_name(COOKIE_NAME); // И только эта кука! Все остальное, в т. ч. идентификатор инкарнации, хранится в данных сессии.
			@session_start();
		}
		// Данные для постоянного хранения
		$_SESSION['TIMEOUT_INTERVAL'] = DEFAULT_SESSION_LIFETIME; // TODO: А зачем тогда куки на год?
		$_SESSION['IP_CHECK'] = DEFAULT_SESSION_IP_CHECKING;
		$_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
		$_SESSION['DEAL_TIME'] = time();
		if ($IncarnationYYID) {
			$_SESSION[YYID] = $IncarnationYYID;
		}
	}

	static public function UpdateSession($IncarnationYYID)
	{
		if (!self::IsSessionValid()) {
			self::StartSession();
		}
		$_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
		$_SESSION['DEAL_TIME'] = time();
		$_SESSION[YYID] = $IncarnationYYID;
	}

	static public function IsSessionValid()
	{
		if (!isset($_COOKIE[COOKIE_NAME])) return false; // Простая проверка на то, что куки вообще отсутствуют, чтобы не начинать сессию
        if (session_status() === PHP_SESSION_NONE) {
            session_name(COOKIE_NAME);
            session_set_cookie_params(DEFAULT_SESSION_LIFETIME, '/', DOMAIN_NAME);
            @session_start();
        }
		$sessionOk = isset($_SESSION);
		$sessionOk = $sessionOk && (empty($_SESSION['IP_CHECK']) || !$_SESSION['IP_CHECK'] || isset($_SESSION['IP']) && $_SERVER['REMOTE_ADDR'] == $_SESSION['IP']);
		$sessionOk = $sessionOk && isset($_SESSION['DEAL_TIME']) && isset($_SESSION['TIMEOUT_INTERVAL'])
			&& time() - $_SESSION['DEAL_TIME'] <= $_SESSION['TIMEOUT_INTERVAL'];
		// TODO: Здесь также можно проверить корректность пути и параметров в запросе, частоту предыдущих запросов (хранить несколько последних в сессии),
		// TODO: присутствие IP-адреса в списках "подозрительных" и др. В общем - обычная полицейская "проверка на дорогах", не более того.
		//      if (!$sessionOk) self::KillSession();
		return $sessionOk;
	}

	static public function KillSession()
	{
		$_SESSION = [];
		if (isset($_COOKIE[COOKIE_NAME])) {
//			session_name(COOKIE_NAME); // TODO: Always error - Cannot change session name when session is active
			if (session_status() === PHP_SESSION_ACTIVE) {
				@session_destroy();
			}
			unset($_COOKIE[COOKIE_NAME]);
		}
	}

	static private function _hash($chunk)
	{
		return md5(strrev($chunk) . '@' . $chunk);
	}

	static public function GenerateTempKey()
	{
		$chunk = floor(time() / 15);
		return self::_hash($chunk);
	}

	static public function CheckTempKey($key)
	{
		$chunk = floor(time() / 15);
		if (self::_hash($chunk) === $key) return true;
		$chunk--;
		return self::_hash($chunk) === $key;
	}

	static public function StoreParamsInSession()
	{
		if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] || !isset($_SESSION['queryString'])) {
			$queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
			$_SESSION['queryString'] = $queryString; // Store original query string (without referer, not split to separated params)
			$request = [];
			parse_str($queryString, $request);
			if (!isset($request['referer']) && isset($_SERVER['HTTP_REFERER'])) {
				$request['referer'] = $_SERVER['HTTP_REFERER'];
			}
			if (isset($_SESSION['request']) && is_array($_SESSION['request'])) { // Allow params propagation to child frames/windows
				$request = array_merge($_SESSION['request'], $request);
			}
			$_SESSION['request'] = $request; // Params extracted, referer added
		}
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] !== '/') {
            $_SESSION['pathInfo'] = $_SERVER['REQUEST_URI'];
        }
	}

	static public function RedirectRoot()
	{
		YY::Log(array('time', 'system'), '============REDIRECTED===========');
		Cache::Flush();
		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://';
		header("Location: " . $protocol . ROOT_URL);
		exit;
	}

    public static function GetClosureRepresentation($method)
    {
        $closure = new ReflectionFunction($method);
        return md5($closure->getFileName() . ':' . $closure->getStartLine() . $closure->getShortName());
    }

}
