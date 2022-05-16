<?php

namespace YY\Core;

use Exception;
use Redis;

class DataRepositoryRedis implements DataRepository
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
        $this->redis->select(0);
    }

    function initializeStorage(bool $writable): void
    {
        // Do nothing
    }

    function isObjectExists(string $yyid): bool
    {
        return !!$this->redis->exists($yyid);
    }

    function writeSerializedObject(string $yyid, string $objectData): void
    {
        if (!$this->redis->set($yyid, $objectData)) {
            throw new Exception("Can not save object $yyid");
        };
    }

    function readSerializedObject(string $yyid): ?string
    {
        return $this->redis->get($yyid) ?: null;
    }

    function deleteObject(string $yyid): void
    {
        $this->redis->del($yyid);
    }

    function tryProcessUncommitedChanges(): void
    {
        // Do nothing
    }

    function getStatistics(): array
    {
        return []; // TODO
    }

    function detachStorage(): void
    {
        // Do nothing
    }
}