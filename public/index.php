<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';

// Create Container
$container = new Container();

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add routing middleware
$app->addRoutingMiddleware();

// Add Error Middleware
$app->addErrorMiddleware(true, true, true);

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Add routes
$app->get('/', \App\Controllers\HomeController::class . ':index');
$app->post('/api/coasters', \App\Controllers\CoasterController::class . ':create');
$app->put('/api/coasters/{id}', \App\Controllers\CoasterController::class . ':update');
$app->post('/api/coasters/{coasterId}/wagons', \App\Controllers\CoasterController::class . ':addWagon');
$app->delete('/api/coasters/{coasterId}/wagons/{wagonId}', \App\Controllers\CoasterController::class . ':deleteWagon');
$app->post('/api/coasters/{coasterId}/wagons/{wagonId}/start', \App\Controllers\CoasterController::class . ':startWagonRide');
$app->get('/api/coasters/{coasterId}/wagons/{wagonId}/status', \App\Controllers\CoasterController::class . ':getWagonStatus');

// Run app
$app->run(); 