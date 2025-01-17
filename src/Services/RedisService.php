<?php

namespace App\Services;

use Predis\Client;
use Exception;

class RedisService
{
    private $redis;
    private $config;

    public function __construct()
    {
        $settings = require __DIR__ . '/../../config/settings.php';
        $this->config = $settings['settings']['redis'];

        $options = [
            'scheme' => 'tcp',
            'host'   => $this->config['host'],
            'port'   => $this->config['port'],
            'password' => $this->config['password'],
            'prefix' => $this->config['prefix'],
            'database' => $this->config['database'],
            'read_write_timeout' => 2,
            'connection_timeout' => 2,
        ];

        try {
            $this->redis = new Client($options);
        } catch (Exception $e) {
            error_log("Redis connection error: " . $e->getMessage());
        }
    }

    public function testConnection(): bool
    {
        try {
            if (!$this->redis) {
                return false;
            }
            
            $this->redis->ping();
            return true;
        } catch (Exception $e) {
            error_log("Redis test connection failed: " . $e->getMessage());
            return false;
        }
    }

    public function getRedis(): ?Client
    {
        return $this->redis;
    }

    public function getValue(string $key): ?string
    {
        try {
            if (!$this->redis) {
                return null;
            }
            
            return $this->redis->get($key);
        } catch (Exception $e) {
            error_log("Redis get value error: " . $e->getMessage());
            return null;
        }
    }

    public function setValue(string $key, string $value, ?int $ttl = null): bool
    {
        try {
            if (!$this->redis) {
                return false;
            }
            
            if ($ttl) {
                $this->redis->setex($key, $ttl, $value);
            } else {
                $this->redis->set($key, $value);
            }
            return true;
        } catch (Exception $e) {
            error_log("Redis set value error: " . $e->getMessage());
            return false;
        }
    }
} 