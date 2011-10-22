<?php

namespace Beat;

require_once 'RequestReceiver.php';
require_once 'WorkerManager.php';
require_once 'Listener.php';

class Runner
{
    const BEAT_VERSION = 0.01;

    static function run($host = "127.0.0.1", $port = 1985, $router = null)
    {
        WorkerManager::$_router = $router;
        Listener::listen($host, $port);
    }
}
