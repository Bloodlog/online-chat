<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        // Vk Api setting Apps
        'vk' => [
            // ID приложения 
            'app_id' => '5732838',
            //Защищённый ключ
            'client_secret' => 'S65H4awjMoWqkmSr9qxP',
            // Адрес сайта
            'redirect_uri' => 'http://debian-server.web/online-chat/public_html/login/', 
        ],
        // Memcache server
        'memcache' => [
            'host' => 'localhost',
            'port' => '11211',
        ],
        'socket' => [
            'host' => '0.0.0.0',
            'port' => '8000',
        ],
        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
