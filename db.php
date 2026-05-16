<?php

// Load Composer autoloader and application configuration.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use App\Helpers\Database;
use App\Helpers\EnvParser;

// If a local .env file exists, load its environment variables.
if (is_file(__DIR__ . '/.env')) {
    $env = new EnvParser();
    $env->load(__DIR__ . '/.env');
}

// Return the PDO connection object from the shared Database singleton.
function dbConnection()
{
    $database = Database::getInstance();
    return $database->getConnection();
}

// Ensure sessions are configured, started, and CSRF token is available.
function initializeSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = appConfig();
    $sessionName = $config['session']['name'];
    $sessionPath = ini_get('session.save_path');

    // Use default PHP session storage if available, otherwise create a local temp folder.
    if (!$sessionPath || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        $sessionPath = __DIR__ . '/tmp/sessions';

        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0777, true);
        }

        session_save_path($sessionPath);
    }

    session_name($sessionName);
    session_start();

    // Generate a CSRF token once per session if not already set.
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = md5(session_id() . time());
    }
}
