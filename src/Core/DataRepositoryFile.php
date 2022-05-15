<?php

namespace YY\Core;

class DataRepositoryFile implements DataRepository
{
    protected string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, "/\\");
    }

    function initializeStorage(bool $writable): void
    {
        // Do nothing
    }

    function isObjectExists(string $yyid): bool
    {
        $fileName = $this->getStoredFileName($yyid);
        return file_exists($fileName) && filesize($fileName);
    }

    function writeSerializedObject(string $yyid, string $objectData): void
    {
        $fileName = $this->getStoredFileName($yyid);
        file_put_contents($fileName, $objectData, LOCK_EX);
    }


    function readSerializedObject(string $yyid): ?string
    {
        $fileName = $this->getStoredFileName($yyid);
        if (!file_exists($fileName)) return null;
        $storedData = $this->fileGetContentsGracefully($fileName);
        return $storedData ?: null;
    }

    function deleteObject(string $yyid): void
    {
        $persistFileName = $this->getStoredFileName($yyid);
        if (file_exists($persistFileName)) unlink($persistFileName);
    }

    function tryProcessUncommitedChanges(): void
    {
        // Nothing to do in plain files engine
    }

    function getStatistics(): array
    {
        return []; // TODO
    }

    function detachStorage(): void
    {
        // Nothing to do in plain files engine
    }

    protected function getStoredFileName(string $yyid)
    {
        return $this->dataDir . DIRECTORY_SEPARATOR . "$yyid.yy";
    }

    protected function fileGetContentsGracefully($path)
    {
        $fo = fopen($path, 'r');
        $wb = 1;
        $locked = flock($fo, LOCK_SH, $wb);
        if (!$locked) {
            return false;
        } else {
            $storedData = file_get_contents($path);
            flock($fo, LOCK_UN);
            fclose($fo);
            return $storedData;
        }
    }



}