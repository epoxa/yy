<?php

namespace YY\Core;

use RuntimeException;
use YY\System\YY;

class DataLockerSemaphoreFile implements DataLockerSemaphore
{
    protected string $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = rtrim($dataDir, "/\\");
    }

    /**
     * @var array<string, resource>
     */
    private array $locks = [];
    /**
     * @param Data|Ref $data
     * @throws RuntimeException
     */
    function Lock($data): void
    {
        $this->checkRequirements();
        $yyid = $data->_YYID;
        $lockFileName = $this->getLockFileName($yyid);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $fo = null;
        for ($i = 0; $i < 200; $i++) {
            $fo = @fopen($lockFileName, 'x');
            if ($fo) break;
            YY::Log('system', 'WAIT ' . getmypid() . ': ' . $yyid);
            usleep(50000);
        }
        if (!$fo) {
            $this->throwLockException($data);
        }
        $lock = flock($fo, LOCK_EX);
        if (!$lock) {
            fclose($this->locks[$yyid]);
            unset($this->locks[$yyid]);
            @unlink($lockFileName);
            $this->throwLockException($data);
        }
        $this->locks[$yyid] = $fo;
    }

    /**
     * @param Data|Ref $data
     * @throws RuntimeException
     */
    function Unlock($data): void
    {
        $this->checkRequirements();
        $yyid = $data->_YYID;
        $lockFileName = $this->getLockFileName($yyid);
        if (file_exists($lockFileName)) {
            @unlink($lockFileName);
        } else {
            YY::Log('error', 'Lock file absent in ' . getmypid() . ' for ' . $data->_full_name() . "\nSTACK:\n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
        }
        flock($this->locks[$yyid], LOCK_UN);
        fclose($this->locks[$yyid]);
        unset($this->locks[$yyid]);
    }

    private function checkRequirements(): void
    {
        if (!file_exists($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    /**
     * @param string $yyid
     * @return string
     */
    private function getLockFileName(string $yyid): string
    {
        return $this->dataDir . DIRECTORY_SEPARATOR . $yyid . ".lock";
    }

    /**
     * @param $data
     * @throws RuntimeException
     */
    private function throwLockException($data): void
    {
        throw new RuntimeException("Can not acquire exclusive lock for " . $data->_full_name());
    }
}