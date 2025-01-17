<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\RedisService;
use App\Services\MonitoringService;
use React\EventLoop\Loop;

// Create event loop
$loop = Loop::get();

// Initialize services
$redisService = new RedisService();
$monitoringService = new MonitoringService($redisService, $loop);

// Start monitoring
$monitoringService->startMonitoring();

echo "Starting monitoring service...\n";

// Run the event loop
$loop->run(); 