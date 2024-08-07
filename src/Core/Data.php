<?php

// Данные хранят что угодно, но с точки зрения бизнес логики являются пассивными (не имеют методов),
// хотя на системном уровне, совместно с глобальным кэшем обеспечивают прозрачное автосохранение на диск и кэширование.
// Представляют собой ассоциативные массивы. Допускают перебор функцией foreach и обращение как к массиву.
// Может, вообще, унаследоваться от ArrayObject?

// TODO: Сделать событие OnModified (метод, переопределяемый в дочерних классах).

namespace YY\Core;

use __PHP_Incomplete_Class;
use ArrayAccess;
use Countable;
use Exception;
use Iterator;
use LogicException;
use Serializable;
use Throwable;
use YY\System;
use YY\System\YY;

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    $msg = $errfile . "(" . $errline . ")" . "\n" . $errstr;
    YY::Log('error', $msg);
    if (isset(YY::$WORLD, YY::$WORLD['SYSTEM'], YY::$WORLD['SYSTEM']['error'])) {
        YY::$WORLD['SYSTEM']->error([
            'error' => new Exception($msg),
            'message' => $msg, // For backward compatability
        ]);
    }
    return false;
});

set_exception_handler(function (\Throwable $e) {
    YY::Log('error', CoreUtils::jTraceEx($e));
    if (isset(YY::$WORLD, YY::$WORLD['SYSTEM'], YY::$WORLD['SYSTEM']['error'])) {
        YY::$WORLD['SYSTEM']->error([
            'error' => $e,
            'message' => $e->getMessage(),
        ]);
    }
});


/**
 * @property string  _YYID
 * @property Ref     _REF
 * @property boolean _MODIFIED
 * @property boolean _DELETED
 * Iterator реализует только перебор необъектных свойств (индекс - скаляр).
 * Для перебора свойств, индексом в которых является ссылка на объект, можно использовать _object_keys().
 */
class Data implements Serializable, Iterator, ArrayAccess, Countable
{
    static private DataRepository $repository;
    static private ?DataLockerSemaphore $dataLocker = null;

    protected $properties
        = [
            false => [], // Для скалярных индексов
            true => [], // Для объектных индексов
        ];
    private $YYID;
    private $modified = false;
    private $ref;
    private $_state;
    private $iterator_index = null;
    private $_lockCount = 0;

    /**
     * @var DataRepository should be configured during bootstrap initialization
     */
    public static function useRepository(DataRepository $repository): void
    {
        self::$repository = $repository;
    }

    /**
     * @var DataLockerSemaphore|null should be configured during bootstrap initialization
     */
    public static function useLocker(DataLockerSemaphore $lockerSemaphore): void
    {
        self::$dataLocker = $lockerSemaphore;
    }

    /**
     * Data constructor.
     * @param array|Data|Ref|null $init
     * @throws Exception
     */
    public function __construct($init = null)
    {
        $this->modified = true;
        if (isset($init) && isset($init['_YYID'])) {
            $this->YYID = $init['_YYID'];
            unset($init['_YYID']);
        } else {
            $this->YYID = self::GenerateNewYYID();
        }
        YY::Log('core', $this->YYID . ' - ' . get_class($this) . ' created');
        if (isset($init)) {
            if (!is_array($init) && !($init instanceof Ref)
                && !($init instanceof Data)
            ) {
                throw new Exception("Invalid initialization in data constructor");
            }
            foreach ($init as $name => $value) {
                $this[$name] = $value;
            }
        }
        Cache::RegisterData($this);
    }

    static public function GenerateNewYYID()
    {
        $yyid = null;
        try {
            do {
                $yyid = md5(uniqid(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '', true));
            } while (
                Cache::Find($yyid) !== null
                || self::$repository->isObjectExists($yyid)
            );
        } catch (Exception $e) {
            ob_end_clean();
        }
        return $yyid;
    }

    public function _acquireExclusiveAccess()
    {
        if (!self::$dataLocker) {
            throw new LogicException('Data::$dataLocker interface is not configured');
        }
        if ($this->_lockCount) {
            $this->_lockCount++;
            return true;
        }
        $yyid = $this->_YYID;
        self::$dataLocker->Lock($this);
        try {
            $this->properties[false] = [];
            $this->properties[true] = [];
            /** @var Data $myActualCopy */
            $myActualCopy = self::_internalLoadObject($yyid);
            if ($myActualCopy) {
                $allKeys = $myActualCopy->_all_keys();
                foreach($allKeys as $key) {
                    $this[$key] = $myActualCopy->_DROP($key);
                }
            }
        } catch (Throwable $e) {
            self::$dataLocker->Unlock($this);
            throw $e;
        }
        $this->modified = false;
        $this->_lockCount = 1;
        YY::Log('system', 'LOCK ' . getmypid() . ': ' . $this->_full_name());
        return true;
    }

    public function _releaseExclusiveAccess()
    {
        if (!self::$dataLocker) {
            throw new LogicException('Data::$dataLocker interface is not configured');
        }
        if ($this->_lockCount > 1) {
            $this->_lockCount--;
            return;
        }
        YY::Log('system', 'RELEASE ' . getmypid() . ': ' . $this->_full_name());
        $this->_flush();
        self::$dataLocker->Unlock($this);
        $this->_lockCount = 0;
    }

    function doLocked(callable $code)
    {
        $this->_acquireExclusiveAccess();
        try {
            return $code();
        } finally {
            $this->_releaseExclusiveAccess();
        }
    }

    static public function InitializeStorage($writable = false)
    {
        self::$repository->initializeStorage($writable);
    }

    static public function FlushTempFiles()
    {
        self::$repository->tryProcessUncommitedChanges();
    }

    static public function DetachStorage()
    {
        self::$repository->detachStorage();
    }

    static public function GetStatistics()
    {
        return self::$repository->getStatistics();
    }

    static public function _isClass($obj, $className)
    {
        if (!is_object($obj)) return false;
        if ($obj instanceof Ref) $obj = $obj->_DAT;
        if (get_class($obj) === 'YY\Core\Shadow') $obj = $obj['_prototype']; // TODO: Получается, что Shadow нужно интегрировать в движок
        return get_class($obj) === $className;
    }

    public function _short_name()
    {
        if (!is_object($this)) return print_r($this, true);
        if (isset($this['name'])) {
            return $this['name'];
        } else if (isset($this['_path'])) {
            return $this['_path'];
        } else {
            $name = get_class($this);
            $first = true;
            $cnt = 0;
            foreach ($this as $key => $dummy) {
                if ($cnt++ > 3) {
                    $name .= ',...';
                    break;
                }
                if ($first) {
                    $name .= '(';
                    $first = false;
                } else {
                    $name .= ',';
                }
                $name .= $key;
            }
            if ($cnt) {
                $name .= ')';
            }
            return $name;
        }

    }

    public function __toString()
    {
        if (isset($this->properties[false]['_path'])) {
            return '[' . $this->properties[false]['_path'] . ']';
        } else {
            return '[' . $this->YYID . ':' . get_class($this) . ']';
        }
    }

    public function __serialize(): array
    {
        return $this->properties;
    }

    public function __unserialize(array $data): void
    {
        $this->properties = $data;
        $this->modified = false;
        $this->_state = 'assigned';
    }

    public function serialize()
    {
        return serialize($this->properties);
    }

    public function unserialize($serialized)
    {
        $this->properties = unserialize($serialized);
        $this->modified = false;
        $this->_state = 'assigned';
    }

    public function _delete_if_unasigned()
    {
        if ($this->_state === null) $this->_delete();
    }

    public function _delete()
    {
        if ($this->_state === 'deleted') return;
        $this->modified = true;
        $this->_state = 'deleted';
        YY::Log('core', 'DELETE:' . $this->_full_name());
        $this->_CLEAR();
    }

    public function _full_name()
    {
        if (!is_object($this)) return print_r($this, true);
        if (isset($this['name'])) {
            $name = $this['name'];
        } else if (isset($this['_path'])) {
            $name = $this['_path'];
        } else {
            $name = get_class($this);
        }
        $name = $name . '[' . $this->_YYID . ']';
        $name .= '(';
        $first = true;
        $cnt = 0;
        foreach ($this as $key => $dummy) {
            if ($cnt++ > 30) {
                $name .= ',...';
                break;
            }
            if ($first) {
                $first = false;
            } else {
                $name .= ',';
            }
            $name .= $key;
        }
        $name .= ')';
        return $name;
    }

// Пока делаем все в предположениях, что
// 3) Строки не могут начинаться на символ '['. Он зарезервирован для объектов.

// Ссылки загружают данные автоматически при обращении к любому свойству (в том числе, методу) объекта,
// на который ссылается эта ссылка.

    public function _CLEAR()
    {
        if (!count($this->properties[false]) && !count($this->properties[true])) return;
        $this->modified = true;
        foreach ([false, true] as $is_obj) {
            foreach ($this->properties[$is_obj] as $propValue) {
                self::_checkDeleteOwnerRef($propValue);
            }
        }
        $this->properties = array(
            false => [], // Для скалярных индексов
            true => [], // Для объектных индексов
        );
    }

    private static function _checkDeleteOwnerRef($old_value, $newValue = null, $reason_obj = null, $reason_prop = null)
    {
        if ($old_value && $old_value instanceof Ref && $old_value->_OWNER) {
//			if ($reason_obj) {
//              YY::Log('core', 'Value of ' . $reason_obj . '->' . $reason_prop . '  (old value: ' . $old_value . ') replaced with (new value: ' . $newValue . ')');
//			}
            $old_value->_delete();
        }
    }

    function _load_object(&$item, $key)
    {
        if (isset($item)) $item = Data::_load($item);
    }

    static protected function _internalLoadObject($YYID) {
        $stored_data = self::$repository->readSerializedObject($YYID);
        if ($stored_data === null) return null;
        if ($stored_data === '') {
            YY::Log('core', $YYID . ' - load failed: object deleted');
            return null;
        }
        try {
            $stored_data = @unserialize($stored_data);
        } catch (Exception $e) {
            YY::Log('error', $YYID . ' - load failed: ' . print_r($stored_data, true) . "\n" . $e->getMessage());
            return null;
        }
        if ($stored_data instanceof __PHP_Incomplete_Class) {
            YY::Log('error', $YYID . ' - load failed: undefined class');
            return null;
        }
        return $stored_data;
    }

    static public function _load($YYID, $force = false)
    {
        if (!$force) {
            $found_data = Cache::Find($YYID);
            if (isset($found_data)) {
                YY::Log('system', "$found_data found in cache");
                return $found_data;
            }
        }
        $stored_data = self::_internalLoadObject($YYID);
        if ($stored_data) {
            $stored_data->YYID = $YYID;
            Cache::RegisterData($stored_data);
            YY::Log('system', "$stored_data loaded");
        } else {
            YY::Log('system', "$YYID load failed!");
        }
        return $stored_data;
    }

    public function _index_of($value)
    {
        foreach ($this->properties[false] as $key => $val) {
            if (self::_isEqual($val, $value)) return $key;
        }
        foreach ($this->properties[true] as $key => $val) {
            if (self::_isEqual($val, $value)) return self::_load($key)->_REF;
        }
        return null;
    }

    public static function _isEqual($v1, $v2)
    {
        if (is_object($v1) && is_object($v2) && ($v1->_YYID === $v2->_YYID)) return true;
        return $v1 === $v2;
    }

    public function __get($name)
    {
        if ($name === '_YYID') {
            return $this->YYID;
        } else if ($name === '_REF') {
            return $this->get_REF();
        } else if ($name === '_MODIFIED') {
            return $this->modified === true;
        } else if ($name === '_DELETED') {
            return $this->_state === 'deleted';
            //    } else if (substr($name, 0, 1) === "_") {
            //      throw new Exception("Can not access system properties");
        } else return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        if (substr($name, 0, 1) === "_") {
            throw new Exception("Can not set system properties");
        } else {
            $this->offsetSet($name, $value);
        }
    }

    private function get_REF()
    {
        if ($this->_state === null) {
            $this->_state = 'assigned';
            return new Ref($this, true);
        } else { // TODO: Стоит ли обрабатывать случай удаленного объекта?
            if (!$this->ref) $this->ref = new Ref($this, false);
            return $this->ref;
        }
    }

    private static function _exists($YYID)
    {
        $found_data = Cache::Find($YYID);
        if (isset($found_data)) {
            return !$found_data->_DELETED;
        } else {
            return self::$repository->isObjectExists($YYID);
        }
    }

    public function __isset($name)
    {
        if (($name === '_REF') || ($name === '_YYID') || ($name === '_MODIFIED') || ($name === '_DELETED')) return true;
        $is_obj = is_object($name);
        if ($is_obj) $name = $name->_YYID;
        if (!array_key_exists($name, $this->properties[$is_obj])) return false;
        $val = $this->properties[$is_obj][$name];
        if ($val instanceof Ref && !$val->_OWNER) {
            // Разберемся, не удален ли объект
            if (!self::_exists($val->_YYID)) {
                $val = null;
                // Оптимизируем на будущее
                $this->properties[$is_obj][$name] = null;
            }
        }
        return isset($val);
    }

    public function __unset($name)
    {
        YY::Log('core', $this->YYID . ' - unset property $name');
        if (substr($name, 0, 1) === "_") {
            throw new Exception("Can not unset system properties");
        } else {
            $is_obj = is_object($name);
            if ($is_obj) {
                $name = $name->_YYID;
            }
            if (array_key_exists($name, $this->properties[$is_obj])) {
                self::_checkDeleteOwnerRef($this->properties[$is_obj][$name]);
                $this->properties[$is_obj][$name] = null;
            }
        }
    }

    public function __call($name, $arg)
    {
        if (!isset($this[$name])) return null;
        try {
            // TODO: В PHP версии 5.4 можно переделать гораздо элегантнее и безопаснее (в плане разделения переменных) через анонимную функцию.
            if (isset($arg[0])) {
                $_params = $arg[0];
            } else {
                $_params = [];
            }
            $code = $this[$name];
            if ($code) {
                $res = eval($code);
                if ($res === false) {
                    throw new Exception('Bad php-code: ' . $code);
                }
            } else {
                $res = null;
            }
            return $res;
        } catch (Exception $e) {
            throw $e;
            // Commented out due to autoload issue for an unknown classes
            // eval('throw new ' . get_class($e) . '($name . ": " . ' . json_encode($e->getMessage()) . ');');
        }
    }

    public function _DROP($key)
    { // Для владеющего свойства возвращает не ссылку, а сам свободный объект, который можно присвоить новому владельцу
        $is_obj = is_object($key);
        if ($is_obj) {
            $key = $key->_YYID;
        }
        if (array_key_exists($key, $this->properties[$is_obj])) {
            $val = $this->properties[$is_obj][$key];
            if ($val instanceof Ref && $val->_OWNER) {
                //        $val->_isOwner = false; // Чтобы не изменяло состояние на удаленное при присваивании нового значения
                $val = $val->_DAT;
                $this->properties[$is_obj][$key] = new Ref($val, false);
                $this->modified = true;
                $val->_free();
            }
        } else {
            $val = null;
        }
        return $val;
    }

    public function _free()
    {
        $this->_state = null;
    }

    public function _CLONE()
    {
        $myClass = get_class($this);
//    $clone = new $myClass($this); // Так не получается рекурсивное копирование
        $clone = new $myClass();
        $properties = $this->_all_keys();
        foreach ($properties as $prop) {
            if ($prop === '_path') {
                continue;
            }
            $val = $this[$prop];
            if (is_object($val)) {
                if ($val->_OWNER) {
                    $newVal = $val->_CLONE();
                } else {
                    $newVal = $val;
                }
            } else {
                $newVal = $val;
            }
            $clone[$prop] = $newVal;
        }
        return $clone;
    }

    public function _toArray()
    {
        $res = [];
        foreach ($this->properties[false] as $key => $val) {
            if (is_resource($val)) continue;
            if (is_object($val) && $val instanceof Ref && $val->_OWNER) $val = $val->_toArray();
            $res[$key] = $val;
        }
        return $res;
    }

    public function _all_keys()
    {
        return array_merge($this->_scalar_keys(), $this->_object_keys());
    }

    public function _scalar_keys()
    {
        return array_keys($this->properties[false]);
    }

    // TODO: Оптимизировать полностью! И разобраться, можно ли включить объектные ключи

    public function _object_keys()
    {
        $objects = array_keys($this->properties[true]);
        array_walk($objects, [$this, '_load_object']);
        // TODO: Кроме удаления из массива, хорошо бы удалить из индексов объекта. Особенно, где значения - владеющие ссылки
        $objects = array_diff($objects, array(null));
        return $objects;
    }

    /**
     * @param $from Data|array
     *
     * @throws Exception
     */

    public function _COPY($from)
    {
        if (is_array($from)) {
            foreach ($from as $key => $val) {
                $this[$key] = $val;
            }
        } else if ($from instanceof Ref || $from instanceof Data) {
            $keys = $from->_all_keys();
            // TODO: А итератор сейчас только скалярные свойства делает. Может объектные не надо копировать?
            foreach ($keys as $key) {
                if (!is_string($key) || substr($key, 0, 1) !== '_') { // Системные свойства не копируем
                    $this[$key] = $from[$key];
                }
            }
        } else {
            throw new Exception('Invalid copy source: ' . print_r($from, true));
        }
    }

    public function _OFFSET($way)
    {
        $object = $this;
        if ($way) {
            $way = explode('.', $way);
            while (count($way)) {
                $prop = array_shift($way);
                if ($prop !== '') $object = $object[$prop];
            }
        }
        return $object;
    }

    public function _flush()
    {
        if ($this->_state === null) return false; // Временные объекты не сохраняем
        if (!($this->modified)) return false;
        if ($this->_state === 'deleted') {
            self::$repository->deleteObject($this->YYID);
        } else {
            self::$repository->writeSerializedObject($this->YYID, serialize($this));
        }
        $this->modified = false; // Чтобы больше не лезть к файлам после промежуточного _flush (если таковые будут)
        return true;
    }

    ///////////////////////
    // Iterator
    ///////////////////////

    #[\ReturnTypeWillChange]
    public function current()
    {
        if ($this->valid()) {
            $res = $this->properties[false][$this->iterator_index];
            if ($res instanceof Ref && !$res->_OWNER) {
                // Разберемся, не удален ли объект.
                if (!self::_exists($res->_YYID)) {
                    $res = null;
                    // Оптимизируем на будущее. Может будет сохранено, а может, оптимизация только на текущий запрос.
                    $this->properties[false][$this->iterator_index] = null;
                }
            }
            return $res;
        } else {
            return null;
        }
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return isset($this->iterator_index) && array_key_exists($this->iterator_index, $this->properties[false]);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        if ($this->valid()) {
            return $this->iterator_index;
        } else {
            return null;
        }
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $keys = array_keys($this->properties[false]);
        if (count($keys)) {
            $this->iterator_index = $keys[0];
            // Временное решение, чтобы пропускать системные свойства
            if (is_string($this->iterator_index) && ($this->iterator_index === '_source' || $this->iterator_index === '_path')) {
                $this->next();
            }
        } else $this->iterator_index = null;
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        $keys = array_keys($this->properties[false]);
        if (isset($this->iterator_index)) {
            $pos = array_search($this->iterator_index, $keys, true);
            if ($pos === false) {
                $this->iterator_index = null;
            } else {
                if ($pos < count($keys) - 1) {
                    $this->iterator_index = $keys[$pos + 1];
                    // Временное решение, чтобы пропускать системные свойства
//                    if (is_string($this->iterator_index) && substr($this->iterator_index, 0, 1) === '_') {
                    if (is_string($this->iterator_index) && ($this->iterator_index === '_source' || $this->iterator_index === '_path')) {
                        $this->next();
                    }
                } else $this->iterator_index = null;
            }
        } else {
            $this->iterator_index = null;
        }
    }

    ///////////////////////
    // ArrayAccess
    ///////////////////////

    /**
     * Вызывается при проверке isset(), и поэтому раньше было так:
     * return isset($this->properties[$is_obj][$offset]);
     * А теперь надо иметь ввиду, что isset(Data[key]) работает не так как isset(array[key]),
     * а определяет, есть ли ключ key в этом объекте
     *
     * @param $offset
     *
     * @return bool
     */

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {

        $is_obj = is_object($offset);
        if ($is_obj) {
            $offset = $offset->_YYID;
        }

        return array_key_exists($offset, $this->properties[$is_obj]);

    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {

        $is_obj = is_object($offset);
        if ($is_obj) {
            $offset = $offset->_YYID;
        }

        if (!array_key_exists($offset, $this->properties[$is_obj])) return;

        $old_value = $this->properties[$is_obj][$offset];
        self::_checkDeleteOwnerRef($old_value, $this, $offset);

        unset($this->properties[$is_obj][$offset]);

        $this->modified = true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {

        $is_obj = is_object($offset);
        if ($is_obj) {
            $offset = $offset->_YYID;
            // TODO: Надо определять, не удален ли сам этот объект, использующйся в качестве индекса
        }

        if (array_key_exists($offset, $this->properties[$is_obj])) {
            $res = $this->properties[$is_obj][$offset];
            if ($res instanceof Ref && !$res->_OWNER) {
                // Разберемся, не удален ли объект.
                if (!self::_exists($res->_YYID)) {
                    $res = null;
                    // Оптимизируем на будущее. Может будет сохранено, а может, оптимизация только на текущий запрос.
                    $this->properties[$is_obj][$offset] = null;
                }
            }
            return $res;
        } else {
            $msg = "Property '$offset' absent in " . $this . '. Call stack:';
            $stack = debug_backtrace();
            foreach ($stack as $ctx) {
                if (isset($ctx['file'])) {
                    $msg .= "\n$ctx[file]($ctx[line])";
                }
            }
            throw new Exception($msg);
        }

    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {

        // Разбираемся с присваиваемым значением

        if (is_array($value)) { // Массивы оборачиваем в объекты
            $value = new Data($value);
        }
        if ($value instanceof Data) { // Храним всегда только ссылки на объекты
            $value = $value->_REF;
            // TODO: В случае получения здесь владеющей ссылки ($value->_OWNER === true)
            // TODO: нужно либо убедиться, что нет рекурсии (методом прохода по вводимому новому свойству parent),
            // TODO: либо убедиться, что нет рекурсии (методом полного рекурсивного обхода всех владеющих дочерних ссылок),
            // TODO: либо не делать ничего, но ввести периодический процесс удаления образующихся изолированных циклов.
            // TODO: А лучше всего - показать, что цикл не может возникнуть .
            /*
             * $first = new Data();
             * $second = new Data();
             * $first->prop = $second;
             * $second->prop = $first;
             * TODO: Как избавиться от такой рекурсии?
             */
        } else if ($value instanceof Ref && $value->_OWNER) { // Причем только копии владеющей ссылки
            $value = new Ref($value->_DAT, false);
        } else if (is_object($value) && !($value instanceof Ref)) {
            throw new Exception('Invalid property value: ' . get_class($value));
        }

        // Разбираемся с типом индекса

        $is_obj = is_object($offset);
        if ($is_obj) {
            $offset = $offset->_YYID;
        }

        // Если не добавление к массиву, то учитываем старое значение свойства.
        // Во-первых, если оно уже равно присваиваемому, то незачем модифицировать объект,
        // и, во-вторых, если оно - владеющая ссылка, то надо прибить старый объект.

        if ($offset !== null) {
            $propertyAlreadyExists = array_key_exists($offset, $this->properties[$is_obj]);
            if ($propertyAlreadyExists) {
                $old_value = $this->properties[$is_obj][$offset];
                //          if ($old_value instanceof \YY\Core\Ref && !$old_value->_OWNER) { // Нахрена это было в __get, совершенно непонятно
                //            $old_value = $this[$name];
                //          }
                if (self::_isEqual($old_value, $value)) {
                    return;
                }
                self::_checkDeleteOwnerRef($old_value, $this, $offset);
            }
        }

        $this->modified = true;
        if ($offset === null) {
            $this->properties[false][] = $value;
        } else {
            $this->properties[$is_obj][$offset] = $value;
        }

    }

    ///////////////////////
    // Countable
    ///////////////////////

    #[\ReturnTypeWillChange]
    public function count()
    {
        //    return count($this->properties[false]) + count($this->properties[true]);
        $res = count($this->properties[false]);
        if (isset($this->properties[0]['_source'])) $res--;
        if (isset($this->properties[0]['_path'])) $res--;
		return $res;
    }
}

