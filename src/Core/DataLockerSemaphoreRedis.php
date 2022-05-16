<?php

namespace YY\Core;

use YY\System\YY;
use LogicException;
use Exception;
use Redis;

class DataLockerSemaphoreRedis implements DataLockerSemaphore
{
    private Redis $redis;

    public function __construct(
        string $redisHost = 'localhost', int $redisPort = 6379,
        ?string $redisUserName = null, ?string $redisPassword = null
    )
    {
        $this->redis = new Redis();
        $this->redis->pconnect($redisHost, $redisPort);
        $auth = [];
        if ($redisUserName) {
            $auth['user'] = $redisUserName;
        }
        if ($redisPassword) {
            $auth['pass'] = $redisPassword;
        }
        if ($auth) {
            $this->redis->auth($auth);
        }
        $this->redis->select(1);
    }

    /**
     * @param Data|Ref $data
     * @throws Exception
     */
    function Lock($data): void
    {
        $yyid = $data->_YYID;
        $redis = $this->redis;
        for ($i = 0; $i < 200; $i++) {
            $redis->watch($yyid);
            if ($redis->exists($yyid)) {
                $redis->unwatch();
                usleep(50000);
            } else {
                $redis->multi()->set($yyid, true);
                if ($redis->exec() !== false) return;
            }
        }
        $this->throwLockException($data);
    }

    /**
     * @param Data|Ref $data
     * @throws Exception
     */
    function Unlock($data): void
    {
        $yyid = $data->_YYID;
        $redis = $this->redis;
        $redis->watch($yyid);
        if (!$redis->exists($yyid)) {
            $redis->unwatch();
            throw new LogicException("Lock absent for " . $data->_full_name());
        }
        $result = $redis->multi()->del($yyid)->exec();
        if ($result === false) {
            throw new LogicException("Lock modified while unlocking for " . $data->_full_name());
        }
    }

    /**
     * @param $data
     */
    private function throwLockException($data): void
    {
        throw new Exception('Can not acquire exclusive lock for ' . $data->_full_name());
    }
}