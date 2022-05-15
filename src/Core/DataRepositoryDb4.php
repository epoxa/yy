<?php

namespace YY\Core;

use Exception;

class DataRepositoryDb4 extends DataRepositoryFile implements DataRepository
{
    const DBA_HANDLER = 'db4';

    private ?resource $db = null;
    private ?bool $storageIsWritable = null;

    function initializeStorage(bool $writable): void
    {
        $this->checkRequirements();
        if ($this->db && $this->storageIsWritable === $writable) return;
        if ($this->db) {
            dba_close($this->db);
            $this->db = null;
        }
        $dbPath = $this->dataDir . DIRECTORY_SEPARATOR . "DATA.db";
        if (!file_exists($dbPath)) {
            $db = @dba_open($dbPath, 'cd', self::DBA_HANDLER);
            dba_close($db);
            $this->db = null;
        }
        if ($writable) {
            try {
                $db = @dba_open($dbPath, 'wdt', self::DBA_HANDLER);
                if ($db !== false) {
                    $this->db = $db;
                }
            } catch (Exception $e) {
                // do nothing
            }
            $this->storageIsWritable = !!$this->db;
            if (!$this->storageIsWritable) {
                $this->db = dba_open($dbPath, 'rd', self::DBA_HANDLER);
            }
        } else {
            $this->db = dba_open($dbPath, 'rd', self::DBA_HANDLER);
            $this->storageIsWritable = false;
        }
    }

    function isObjectExists(string $yyid): bool
    {
        $fileName = $this->getStoredFileName($yyid);
        if (file_exists($fileName)) {
            return !!filesize($fileName);
        } else {
            return dba_exists($yyid, $this->db);
        }
    }

    function writeSerializedObject(string $yyid, string $objectData): void
    {
        if ($this->storageIsWritable) {
            parent::deleteObject($yyid);
            dba_replace($this->YYID, $objectData, $this->db);
        } else {
            parent::writeSerializedObject($yyid, $objectData);
        }
    }

    function readSerializedObject(string $yyid): ?string
    {
        $fileName = $this->getStoredFileName($yyid);
        if (file_exists($fileName)) {
            $storedData = file_get_contents($fileName);
        } else {
            $storedData = dba_fetch($YYID, $this->db);
        }
        return $storedData ?: null;
    }

    function deleteObject(string $yyid): void
    {
        umask(0007);
        $persistFileName = $this->getStoredFileName($yyid);
        if ($this->storageIsWritable) {
            dba_delete($this->YYID, $this->db);
            if (file_exists($persistFileName)) unlink($persistFileName);
        } else {
            // Устанавливаем признак того, что объект удален. Когда база будет доступна на запись, он удалится из базы
            file_put_contents($persistFileName, '', LOCK_EX);
        }
    }

    function getStatistics(): array
    {
        $cnt = 0;
        $dataSum = 0;
        $keySum = 0;
        $maxDataSize = null;
        $minDataSize = null;
        if (!!$key = dba_firstkey($this->db)) {
            do {
                $data = dba_fetch($key, $this->db);
                $cnt++;
                $keySum += strlen($key);
                $currDataSize = strlen($data);
                $dataSum += $currDataSize;
                if (!isset($minDataSize) || $currDataSize < $minDataSize) $minDataSize = $currDataSize;
                if (!isset($maxDataSize) || $currDataSize > $maxDataSize) $maxDataSize = $currDataSize;
            } while (!!$key = dba_nextkey($this->db));
        }

        $dir = opendir($this->dataDir);
        while (($file = readdir($dir)) !== false) {
            if (substr($file, -3) === '.yy') {
                $key = substr($file, 0, -3);
                $data = $this->fileGetContentsGracefully($this->dataDir . DIRECTORY_SEPARATOR . $file);
                $cnt++;
                $keySum += strlen($key);
                $currDataSize = strlen($data);
                $dataSum += $currDataSize;
                if (!isset($minDataSize) || $currDataSize < $minDataSize) $minDataSize = $currDataSize;
                if (!isset($maxDataSize) || $currDataSize > $maxDataSize) $maxDataSize = $currDataSize;
            }
        }

        $frmt = function ($sz) {
            if ($sz < 0x400) {
                return sprintf('%d bytes', $sz);
            } else if ($sz < 0x100000) {
                return sprintf('%.1F KB', $sz / 0x400);
            } else if ($sz < 0x40000000) {
                return sprintf('%.1F MB', $sz / 0x100000);
            } else return sprintf('%.1F GB', $sz / 0x40000000);
        };
        if ($this->db) {
            $fileSize = filesize($this->dataDir . DIRECTORY_SEPARATOR . 'DATA.db');
        } else {
            $fileSize = null;
        }
        return [
            'objCount' => $cnt,
            'avgKeySize' => $frmt($keySum / $cnt),
            'avgDataSize' => $frmt($dataSum / $cnt),
            'totalKeySize' => $frmt($keySum),
            'totalDataSize' => $frmt($dataSum),
            'minDataSize' => $minDataSize . ' bytes',
            'maxDataSize' => $maxDataSize . ' bytes',
            'databaseSize' => $fileSize ? $frmt($fileSize) : 'N/A',
            'databaseFill' => $fileSize ? sprintf('%.1F', ($dataSum + $keySum) / $fileSize * 100) . ' %' : 'N/A',
        ];
    }

    function tryProcessUncommitedChanges(): void
    {
        if (!$this->storageIsWritable) return;
        // Ну раз нам повезло, почистим все временные файлы, сохранив их в локальную БД
        $dir = opendir($this->dataDir);
        while (($file = readdir($dir)) !== false) {
            if (substr($file, -3) === '.yy') {
                $key = substr($file, 0, -3);
                $fullFileName = $this->dataDir . DIRECTORY_SEPARATOR . $file;
                $data = $this->fileGetContentsGracefully($fullFileName);
                if (unlink($fullFileName)) {
                    if ($data === '') {
                        dba_delete($key,$this->db);
                    } else {
                        dba_replace($key, $data, $this->db);
                    }
                }
            }
        }
        closedir($dir);
    }

    function detachStorage(): void
    {
        if ($this->db) {
            dba_close($this->db);
            $this->db = null;
            $this->storageIsWritable = false;
        }
    }

    private function checkRequirements(): void
    {
        if (!function_exists('dba_handlers') || !in_array(self::DBA_HANDLER, dba_handlers())) {
            throw new Exception('db4 handlers are absent');
        };
    }


}