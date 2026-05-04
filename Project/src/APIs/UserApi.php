<?php

declare(strict_types=1);

use App\Controllers\CourseController;
use App\Controllers\UserController;
use App\Models\UserModel;

/**
 * User & session JSON API. Invoked from auth_api.php after bootstrap (session + DB).
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

$database = dbConnection();
$userModel = new UserModel($database);
$userController = new UserController($userModel);
$courseController = new CourseController($database);

$action = $_GET['action'] ?? 'session';

try {
    if ($action === 'session') {
        jsonResponse($userController->session(), 200);
    }

    if ($action === 'login') {
        $data = $userController->login();
        if (!empty($data['success'])) {
            jsonResponse($data, 200);
        }
        $msg = (string) ($data['message'] ?? '');
        $status = match (true) {
            str_contains($msg, 'Invalid request method') => 405,
            str_contains($msg, 'Invalid request data') => 400,
            str_contains($msg, 'required') => 422,
            str_contains($msg, 'Invalid email format') => 422,
            str_contains($msg, 'Invalid email or password') => 401,
            default => 400,
        };
        jsonResponse($data, $status);
    }

    if ($action === 'register') {
        $data = $userController->register();
        if (!empty($data['success'])) {
            jsonResponse($data, 200);
        }
        $msg = (string) ($data['message'] ?? '');
        $status = match (true) {
            str_contains($msg, 'Invalid request method') => 405,
            str_contains($msg, 'Invalid request data') => 400,
            str_contains($msg, 'required') => 422,
            str_contains($msg, 'Invalid email') => 422,
            str_contains($msg, 'at least 8') => 422,
            str_contains($msg, 'do not match') => 422,
            str_contains($msg, 'Choose Faculty') => 422,
            str_contains($msg, 'already exists') => 409,
            default => 400,
        };
        jsonResponse($data, $status);
    }

    if ($action === 'logout') {
        $data = $userController->logout();
        if (!empty($data['success'])) {
            jsonResponse($data, 200);
        }
        $msg = (string) ($data['message'] ?? '');
        $status = str_contains($msg, 'Invalid request method') ? 405 : 400;
        jsonResponse($data, $status);
    }

    if ($action === 'courses') {
        jsonResponse($courseController->list(), 200);
    }

    jsonResponse(['success' => false, 'message' => 'Unknown action.'], 404);
} catch (\PDOException $exception) {
    jsonResponse(['success' => false, 'message' => 'Database error. Check DB configuration and schema.'], 500);
} catch (Throwable $exception) {
    jsonResponse(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
