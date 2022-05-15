<?php

namespace YY\System;

use ArrayAccess;
use ReflectionFunction;
use YY\Core\Data;

/**
 * Class Robot
 *
 * Main building block for user interface
 *
 * @package YY\System
 *
 */

class Robot extends Data
{

	public function __construct($init = null)
	{
		parent::__construct($init);
	}

	/**
	 * @param $asset
	 * To be called in robot constructor
	 */
	public function includeAsset($asset)
	{
		if (empty($this['include'])) {
			$this['include'] = $asset;
		} else {
			if (is_object($this['include'])) {
				$curr = $this['include'];
			} else {
				$curr = [$this['include']];
			}
			if (is_array($asset) || (is_object($asset) && $asset instanceof ArrayAccess)) {
				// That's OK already
			} else {
				$asset = [$asset];
			}
			foreach ($asset as $new) {
				$curr[] = $new;
			}
			$this['include'] = $curr;
		}
	}

	public function focusInput($objectParam)
	{
		$object = $this;
		if (is_string($objectParam)) {
			$param_name = $objectParam;
		} else {
			$object = $objectParam[0];
			$param_name = $objectParam[1];
		}
		$objectHandle = YY::GetHandle($object);
		$script = "setFocusElement(document.getElementById('${objectHandle}[${param_name}]'))";
		YY::clientExecute($script);
	}

	public function _delete()
	{
		YY::robotDeleting($this);
		parent::_delete();
	}

	public final function _SHOW()
	{
		YY::showRobot($this);
	}

	// При вызове этой функции можно генерировать ошибку при попытке записи в любое свойство любого объекта.
	// Никакого действия, только вывод объекта для пользователя!

	protected function _PAINT()
	{
	}


	/**
	 * @deprecated
	 *
	 * @param      $visual
	 * @param      $htmlCaption
	 * @param      $method
	 * @param null $params
	 *
	 * @return string
	 */
	public function HUMAN_COMMAND($visual, $htmlCaption, $method, $params = null)
	{
		return YY::drawCommand($visual, $htmlCaption, $this, $method, $params);
	}

	// Replacement for deprecated HUMAN_COMMAND

	public function CMD($htmlCaption, $objectMethodAndParams = null, $visual = null)
	{
		$object = $this;
		$method = null;
		$params = [];
		if (is_string($objectMethodAndParams) || is_callable($objectMethodAndParams)) {
			$method = $objectMethodAndParams;
		} elseif ($objectMethodAndParams) {
			foreach($objectMethodAndParams as $key => $value) {
				if ($method === null) {
					if (is_string($value) || is_callable($value) && !is_array($value)) {
						$method = $value;
					} else {
						$object = $value[0];
						$method = $value[1];
					}
				} else {
					$params[$key] = $value;
				}
			}
		}
		return YY::drawCommand($visual, $htmlCaption, $object, $method, $params);
	}

	/**
	 * @deprecated
	 *
	 * @param      $visual
	 * @param      $param_name
	 * @param null $object
	 *
	 * @return string
	 */
	protected function HUMAN_TEXT($visual, $param_name, $object = null)
	{
		if ($object === null) $object = $this;
		return YY::drawInput($visual, $object, $param_name);
	}

	// Replacement for deprecated HUMAN_TEXT

	protected function INPUT($objectParam, $visual = null)
	{
		$object = $this;
		if (is_string($objectParam)) {
			$param_name = $objectParam;
		} else {
			$object = $objectParam[0];
			$param_name = $objectParam[1];
		}
		return YY::drawInput($visual, $object, $param_name);
	}

	/**
	 * @deprecated
	 *
	 * @param $visual
	 * @param $htmlText
	 *
	 * @return string
	 */
	protected function MY_TEXT($visual, $htmlText)
	{
		return YY::drawText($visual, $htmlText);
	}

	// Replacement for deprecated MY_TEXT

	protected function TXT($htmlText, $visual = null)
	{
		return YY::drawText($visual, $htmlText);
	}


	/**
	 * @deprecated
	 *
	 * @param      $visual
	 * @param      $htmlCaption
	 * @param      $param
	 * @param null $method
	 *
	 * @return string
	 */
	protected function FLAG($visual, $htmlCaption, $param, $method = null)
	{
		return YY::drawFlag($visual, $htmlCaption, $this, $param, $method);
	}

	// Replacement for deprecated FLAG

	protected function CHK($htmlCaption, $objectParamAndMethod, $visual = null)
	{
		$object = $this;
		$param_name = '';
		$method_name = null;
		if (is_string($objectParamAndMethod)) {
			$param_name = $objectParamAndMethod;
			$method_name = null;
		} else {
			$idx = 0;
			foreach($objectParamAndMethod as $key => $value) {
				if ($idx === 0 && !is_string($value)) {
					$object = $value;
				} else {
					$param_name = $key;
					$method_name = $value; // TODO: Should we allow params even here?
				}
				$idx++;
			}
		}
		return YY::drawFlag($visual, $htmlCaption, $object, $param_name, $method_name);
	}

	public function LINK($visual, $htmlCaption, $params = null)
	{
		return YY::drawInternalLink($visual, $htmlCaption, $this, $params);
	}

	public function DOCUMENT($visual, $params = null)
	{
		return YY::drawDocument($visual, $this, $params);
	}

	// TODO: Можно добавить параметры, например, для возможности скачивать файл
	protected function FILE($visual, $param_name, $object = null)
	{
		if ($object === null) $object = $this;
		return YY::drawFile($visual, $object, $param_name);
	}

}
