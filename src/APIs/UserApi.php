<?php

declare(strict_types=1); // Enforce strict typing for scalar values.

use App\Controllers\CourseController; // Import course controller for course-related operations.
use App\Controllers\UserController; // Import user controller for authentication and session actions.
use App\Models\UserModel; // Import user model used by the controller.

/**
 * User & session JSON API. Invoked from auth_api.php after bootstrap (session + DB).
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    // Send the HTTP status code and JSON-encoded response to the client.
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Initialize the database connection and controller dependencies.
$database = dbConnection();
$userModel = new UserModel($database);
$userController = new UserController($userModel);
$courseController = new CourseController($database);

// Determine which API action the client requested.
$action = $_GET['action'] ?? 'session';

try {
    // Return the current session data.
    if ($action === 'session') {
        jsonResponse($userController->session(), 200);
    }

    // Handle login requests and map error messages to HTTP status codes.
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

    // Handle registration requests and map validation failures to proper HTTP codes.
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

    // Handle logout requests and return the appropriate status code.
    if ($action === 'logout') {
        $data = $userController->logout();
        if (!empty($data['success'])) {
            jsonResponse($data, 200);
        }

        $msg = (string) ($data['message'] ?? '');
        $status = str_contains($msg, 'Invalid request method') ? 405 : 400;
        jsonResponse($data, $status);
    }

    // Return the list of courses using the course controller.
    if ($action === 'courses') {
        jsonResponse($courseController->list(), 200);
    }

    // If the action was not recognized, return a 404 error.
    jsonResponse(['success' => false, 'message' => 'Unknown action.'], 404);
} catch (\PDOException $exception) {
    // Handle database-specific errors gracefully.
    jsonResponse(['success' => false, 'message' => 'Database error. Check DB configuration and schema.'], 500);
} catch (Throwable $exception) {
    // Fallback for any other uncaught errors.
    jsonResponse(['success' => false, 'message' => 'Unexpected server error.'], 500);
}
