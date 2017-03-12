<?php
namespace YY\Core;

use ArrayAccess;
use Countable;
use Exception;
use Iterator;
use Serializable;
use YY\Core\Exception\EObjectDestroyed;

/**
 * @property string  _YYID
 * @property Data _DAT
 * @property boolean _EMPTY
 * @property boolean _OWNER
 */
class Ref implements Serializable, Iterator, ArrayAccess, Countable
{

	private $data;
	private $YYID;
	private $_isOwner;

	public function __construct(Data $toData, $lock)
	{
		if ($toData) {
			$this->data = $toData;
			$this->YYID = $toData->_YYID;
			$this->_isOwner = $lock;
		}
	}

	public function __toString()
	{
		return 'R:' . ($this->data ? $this->data : '[' . $this->YYID . ']');
	}

	public function _full_name()
	{
		return 'R:' . $this->_DAT->_full_name();
	}

	public function serialize()
	{
		$str = $this->YYID;
		if ($this->_isOwner) $str = '!' . $str;
		return serialize($str);
	}

	public function unserialize($data)
	{
		$yyid = unserialize($data);
		if (strlen($yyid) > 32) {
			$this->_isOwner = true;
			$yyid = substr($yyid, 1);
		} else {
			$this->_isOwner = false;
		}
		$this->YYID = $yyid;
	}

	public function __get($name)
	{
		if ($name === '_YYID') {
			return $this->YYID;
		} else if ($name === '_DAT') {
			return $this->get_DAT();
		} else if ($name === '_EMPTY') {
			return $this->get_EMPTY();
		} else if ($name === '_OWNER') {
			return $this->_isOwner;
		} else {
            $val = $this->get_DAT()->$name; // Используйте динамические (интерпретируемые) языки динамично!
            if ($val instanceof Ref) {
                if (!$val->_DAT) $val = null;
            }
            return $val;
        }
		// Использование свойства, а не индекса массива
		// позволяет использовать обычные (не динамические) публичные свойства через ссылку \YY\Core\Ref
		// (например, свойства insertId класса _Sql)
	}

	public function __set($name, $value)
	{
		$this->_DAT[$name] = $value; // Используйте динамические (интерпретируемые) языки динамично!
	}

	public function __call($_name, $arg)
	{
        $dat = $this->_DAT;
        if (!$dat) {
            throw new Exception("Call method $_name(" . ($arg ? print_r($arg, true) : '') . ") of deleted object.");
        }
		return call_user_func_array([$dat, $_name], $arg);
/*
		$_result = null;
		$txt = '$_result = $this->_DAT->' . $_name . '(';
		for ($idx = 0; $idx < count($arg); $idx++) {
			if ($idx > 0) $txt .= ',';
			$txt .= '$arg[' . $idx . ']';
		}
		$txt .= ');';
		eval($txt);
		return $_result;
*/
		// Может можно и без "eval", через _DATA->call_user_func_array() ?
	}

	private function get_EMPTY()
	{
		return !isset($this->YYID); // То есть не просто незагружена, а именно - вообще нет даже ссылки // TODO: А откуда бы такие взялись-то? По ходу, надо убрать это
	}

	private function get_DAT()
	{
		if ($this->_EMPTY) throw new Exception("Empty reference!");
		if (!isset($this->data)) {
			$this->data = Data::_load($this->YYID);
		}
		// TODO: Надо отслеживать удаленные объекты и для невладеющих ссылок
		if ($this->_isOwner && ($this->data === null || $this->data->_DELETED)) {
			// Ругаемся только для владеющих ссылок.
			throw new EObjectDestroyed($this->YYID);
		}
		return $this->data;
	}

	///////////////////////
	// Iterator
	///////////////////////

	public function current()
	{
		return $this->_DAT->current();
	}

	public function key()
	{
		return $this->_DAT->key();
	}

	public function next()
	{
		$this->_DAT->next();
	}

	public function rewind()
	{
		$this->_DAT->rewind();
	}

	public function valid()
	{
		return $this->_DAT->valid();
	}

	///////////////////////
	// ArrayAccess
	///////////////////////

	public function offsetExists($offset)
	{
		return $this->_DAT->offsetExists($offset);
	}

	public function offsetGet($offset)
	{
		return $this->_DAT->offsetGet($offset);
	}

	public function offsetSet($offset, $value)
	{
		$this->_DAT->offsetSet($offset, $value);
	}

	public function offsetUnset($offset)
	{
		$this->_DAT->offsetUnset($offset);
	}

	///////////////////////
	// Countable
	///////////////////////

	public function count()
	{
		return $this->_DAT->count();
	}

}
