<?php

namespace YY\Core;

use YY\System\YY;
use LogicException;

class DataLockerSemaphoreFile implements DataLockerSemaphore
{

    private $locks = [];
    /**
     * @param Data|Ref $data
     * @throws Exception
     */
    function Lock($data): void
    {
        $this->checkRequirements();
        $yyid = $data->_YYID;
        $lockFileName = $this->getLockFileName($yyid);
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
     * @throws Exception
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
        if (!defined('LOCK_DIR')) {
            throw new LogicException('Constant LOCK_DIR should be defined to use ' . __CLASS__);
        }
        if (!file_exists(LOCK_DIR)) {
            mkdir(LOCK_DIR, 0777, true);
        }
    }

    /**
     * @param string $yyid
     * @return string
     */
    private function getLockFileName(string $yyid): string
    {
        $lockFileName = LOCK_DIR . $yyid . ".lock";
        return $lockFileName;
    }

    /**
     * @param $data
     */
    private function throwLockException($data): void
    {
        throw new Exception('Can not acquire exclusive lock for ' . $data->_full_name());
    }
}