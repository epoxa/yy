<?php

namespace YY\System;

use Exception;
use YY\Core\Data;

class View extends Data
{

	/**
	 * @param $object
	 *
	 * @return int
	 */
	function makeObjectHandle($object)
	{
		$trans = $this['HANDLES'];
		if (isset($trans[$object])) {
			$transId = $trans[$object];
		} else {
			$transId = count($trans); // Scalar indexes only
			$trans[$object] = $transId;
			$trans[$transId] = $object;
		}
		return $transId;
	}

	/**
	 * @param $handle
	 *
	 * @return Data | null
	 */
	function findObjectByHandle($handle)
	{
		return isset($this['HANDLES'][$handle]) ? $this['HANDLES'][$handle] : null;
	}

	/**
	 * @param $haystack
	 *
	 * @return array
	 * @throws Exception
	 */
	function findNewHeaders($haystack)
	{
		$found = [];
		if ($haystack) {
			if (is_object($haystack) || is_array($haystack)) {
				foreach ($haystack as $key => $child) {
					$found = array_merge($found, $this->findNewHeaders($child));
				}
			} else if (is_string($haystack)) {
				if (!isset($this['HEADERS'][$haystack])) {
					$this['HEADERS'][$haystack] = null;
					$found[] = $haystack;
				}
			} else {
				throw new Exception('Invalid include: ' . print_r($haystack, true));
			}
		}
		return $found;
	}

	/**
	 * @param $robot
	 */
	function robotDeleting($robot)
	{
		$trans = $this['HANDLES'];
		if (empty($trans[$robot])) return;
		$handle = $trans[$robot];
		$this['DELETED'][] = $handle;
		unset($this['RENDERED'][$handle]);
	}

}
