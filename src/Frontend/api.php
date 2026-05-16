<?php
// Load Composer autoloader and application classes.
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controllers\ApiController;
use App\Helpers\Database;
use App\Models\ClassModel;
use App\Models\UserModel;

// Start or resume the session to track authenticated users.
session_start();

// Determine the requested API action from GET or POST parameters.
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Always return JSON from this API endpoint.
header('Content-Type: application/json');

// Handle generateCode before any session or database checks.
// This endpoint only needs to return a one-time random code.
if ($action === 'generateCode') {
    error_log("generateCode action received");
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    error_log("Generated code: " . $code);
    echo json_encode(['success' => true, 'code' => $code]);
    exit;
}

// Block unauthenticated users for all actions except logout.
if ($action !== 'logout' && (!isset($_SESSION['user']) || !is_array($_SESSION['user']))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Initialize database-backed models and API controller for authenticated actions.
$database = Database::getInstance();
$userModel = new UserModel($database->getConnection());
$classModel = new ClassModel($database->getConnection());
$apiController = new ApiController($classModel, $userModel);

// Dispatch the API action through the controller methods.
switch ($action) {
    case 'getUserData':
        // Return all available user dashboard data.
        echo json_encode($apiController->getUserData());
        break;

    case 'createClass':
        // Create a new class record for the logged-in faculty user.
        echo json_encode($apiController->createClass());
        break;

    case 'joinClass':
        // Enroll the logged-in student in a class by code.
        echo json_encode($apiController->joinClass());
        break;

    case 'createAnnouncement':
        // Create a class announcement with optional attachments.
        echo json_encode($apiController->createAnnouncement());
        break;

    case 'removeStudent':
        // Remove a student from a class (alternate action alias).
        echo json_encode($apiController->removeStudent());
        break;

    case 'removeStudentFromClass':
        // Map duplicate action name to the same remove student logic.
        echo json_encode($apiController->removeStudent());
        break;

    case 'deleteAnnouncement':
        // Delete a class announcement.
        echo json_encode($apiController->deleteAnnouncement());
        break;

    case 'createActivity':
        // Create a new activity for a class.
        echo json_encode($apiController->createActivity());
        break;

    case 'submitActivity':
        // Submit an activity with attachments.
        echo json_encode($apiController->submitActivity());
        break;

    case 'deleteActivity':
        // Delete an existing activity.
        echo json_encode($apiController->deleteActivity());
        break;

    case 'saveSubmissionGrade':
        // Save a grade for a student submission.
        echo json_encode($apiController->saveSubmissionGrade());
        break;

    case 'deleteSubmissionGrade':
        // Delete a previously saved submission grade.
        echo json_encode($apiController->deleteSubmissionGrade());
        break;

    case 'updateProfile':
        // Handle profile updates for the current user.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            break;
        }

        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $sex = $_POST['sex'] ?? '';

        if (trim($name) === '' || trim($email) === '') {
            echo json_encode(['success' => false, 'message' => 'Name and email are required']);
            break;
        }

        $user = $_SESSION['user'];
        $result = $userModel->updateProfile($user['id'], trim($name), trim($email), $sex ?: null);

        if ($result) {
            // Update session state after successful profile save.
            $_SESSION['user']['name'] = trim($name);
            $_SESSION['user']['email'] = trim($email);
            $_SESSION['user']['sex'] = $sex ?: null;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        break;

    case 'changePassword':
        // Handle password changes for the logged-in user.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            break;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';

        if (trim($currentPassword) === '' || trim($newPassword) === '') {
            echo json_encode(['success' => false, 'message' => 'Current and new password are required']);
            break;
        }

        $user = $_SESSION['user'];
        $userRecord = $userModel->findByEmail($user['email']);

        if (!$userRecord || !password_verify($currentPassword, $userRecord['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            break;
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = $userModel->changePassword($user['id'], $newPasswordHash);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to change password']);
        }
        break;

    case 'logout':
        // Clear session data and destroy the PHP session.
        $_SESSION = array();
        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        break;

    default:
        // Handle unknown or unsupported actions.
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
