<?php
set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

// Instantiate the app
$settings = require __DIR__ . '/src/settings.php';
$server = new \Server\Server($settings);

$server->run();
