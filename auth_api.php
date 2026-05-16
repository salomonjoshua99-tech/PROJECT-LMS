<?php

declare(strict_types=1);

// Load the database configuration and helper functions.
require_once __DIR__ . '/db.php';

// Always return JSON from this authentication API endpoint.
header('Content-Type: application/json; charset=utf-8');

// Ensure the PHP session is started for auth state.
initializeSession();

// Boot the user API router to handle authentication requests.
require __DIR__ . '/src/APIs/UserApi.php';
