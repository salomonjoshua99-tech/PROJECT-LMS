<?php

declare(strict_types=1);

// Load database/session bootstrap helpers.
require_once __DIR__ . '/db.php';

// Ensure session state is initialized before serving the app.
initializeSession();

// Hand off request handling to the frontend index page.
require __DIR__ . '/src/Frontend/index.php';
