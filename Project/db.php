<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use App\Helpers\Database;
use App\Helpers\EnvParser;

if (is_file(__DIR__ . '/.env')) {
    $env = new EnvParser();
    $env->load(__DIR__ . '/.env');
}

function dbConnection(){
    $database = Database::getInstance();
    return $database->getConnection();
}

function initializeSession(){
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = appConfig();
    $sessionName = $config['session']['name'];
    $sessionPath = ini_get('session.save_path');

    if (!$sessionPath || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        $sessionPath = __DIR__ . '/tmp/sessions';

        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0777, true);
        }

        session_save_path($sessionPath);
    }

    session_name($sessionName);
    session_start();

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = md5(session_id() . time());
    }
}
