<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

initializeSession();

require __DIR__ . '/src/APIs/UserApi.php';