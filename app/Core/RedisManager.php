<?php
namespace Massango\Core;

use Redis;
use RedisException;

class RedisManager
{
    private static ?Redis $instance = null;

    public static function getInstance(): Redis
    {
        if (self::$instance === null) {
            $redis = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);

            $redis->connect($host, $port, 2.0); // timeout 2s
            self::$instance = $redis;
        }
        return self::$instance;
    }
}
