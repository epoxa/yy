<?php
namespace YY\Core;

use YY\System\YY;
use YY\Core\Data;
use YY\Core\Ref;

class Cache
{

	static private $dataList = [];

	static public function RegisterData(Data $data)
	{
		$YUID = $data->_YYID;
		if (!array_key_exists($YUID, self::$dataList)) {
			self::$dataList[$YUID] = $data;
		}
	}

	static public function UpdateData($dataOrRef)
	{
		$YUID = $dataOrRef->_YYID;
		if ($dataOrRef instanceof Ref) $dataOrRef = $dataOrRef->_DAT;
		// Без проверки что уже содержится, чтобы заменять старое значение при загрузке нового с таким же YYID
		self::$dataList[$YUID] = $dataOrRef;
	}

	static public function Find($YYID)
	{
		if (isset(self::$dataList[$YYID])) {
			return self::$dataList[$YYID];
		} else return null;
	}

	static public function Flush($intermediate = true)
	{
		YY::Log('core', 'flush started');
        if (!$intermediate) {
            foreach (self::$dataList as $data) {
                $data->_delete_if_unasigned();
            }
        }
		$cnt = 0;
		Data::InitializeStorage(true);
		// TODO: По идее в момент сохранения нельзя использовать объекты (а они используются, например, в протоколировании)
		foreach (self::$dataList as $data) {
			if ($data->_flush()) $cnt++;
		}
		Data::FlushTempFiles();
		if ($intermediate) {
			Data::InitializeStorage(false); // TODO: Why?
		}
		YY::Log('core', 'flushed ' . $cnt . ' objects');
	}

	static public function  GetCount()
	{
		return count(self::$dataList);
	}

}
