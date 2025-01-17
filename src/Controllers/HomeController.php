<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\RedisService;

class HomeController
{
    private $redisService;

    public function __construct()
    {
        $this->redisService = new RedisService();
    }

    public function index(Request $request, Response $response)
    {
        $isConnected = $this->redisService->testConnection();
        
        $connectionInfo = sprintf(
            "Redis Connection Status:<br>" .
            "Host: %s<br>" .
            "Port: %s<br>" .
            "Status: %s",
            getenv('REDIS_HOST'),
            getenv('REDIS_PORT'),
            $isConnected ? '✅ Connected' : '❌ Failed'
        );

        // Test Redis operations if connected
        if ($isConnected) {
            $testKey = 'test_connection';
            $testValue = 'Connection test at ' . date('Y-m-d H:i:s');
            
            $writeSuccess = $this->redisService->setValue($testKey, $testValue);
            $readValue = $this->redisService->getValue($testKey);
            
            $connectionInfo .= sprintf(
                "<br><br>Redis Operations Test:<br>" .
                "Write Test: %s<br>" .
                "Read Test: %s",
                $writeSuccess ? '✅ Success' : '❌ Failed',
                $readValue ? '✅ Success' : '❌ Failed'
            );
        }

        $response->getBody()->write(
            "<h1>Welcome to Homepage!</h1><br>" .
            "<div style='font-family: monospace;'>" . 
            $connectionInfo . 
            "</div>"
        );
        
        return $response->withHeader('Content-Type', 'text/html');
    }
} 