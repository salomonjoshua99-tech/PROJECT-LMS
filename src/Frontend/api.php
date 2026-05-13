<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controllers\ApiController;
use App\Helpers\Database;
use App\Models\ClassModel;
use App\Models\UserModel;

session_start();

// Route the request based on action parameter
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

// Handle generateCode without database connection
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

// Check if user is logged in (except for logout action)
if ($action !== 'logout' && (!isset($_SESSION['user']) || !is_array($_SESSION['user']))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = Database::getInstance();
$userModel = new UserModel($database->getConnection());
$classModel = new ClassModel($database->getConnection());
$apiController = new ApiController($classModel, $userModel);

switch ($action) {
    case 'getUserData':
        echo json_encode($apiController->getUserData());
        break;

    case 'createClass':
        echo json_encode($apiController->createClass());
        break;

    case 'joinClass':
        echo json_encode($apiController->joinClass());
        break;

    case 'createAnnouncement':
        echo json_encode($apiController->createAnnouncement());
        break;

    case 'removeStudent':
        echo json_encode($apiController->removeStudent());
        break;

    case 'removeStudentFromClass':
        echo json_encode($apiController->removeStudent());
        break;

    case 'deleteAnnouncement':
        echo json_encode($apiController->deleteAnnouncement());
        break;

    case 'createActivity':
        echo json_encode($apiController->createActivity());
        break;

    case 'submitActivity':
        echo json_encode($apiController->submitActivity());
        break;

    case 'deleteActivity':
        echo json_encode($apiController->deleteActivity());
        break;

    case 'saveSubmissionGrade':
        echo json_encode($apiController->saveSubmissionGrade());
        break;

    case 'deleteSubmissionGrade':
        echo json_encode($apiController->deleteSubmissionGrade());
        break;

    case 'updateProfile':
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
            $_SESSION['user']['name'] = trim($name);
            $_SESSION['user']['email'] = trim($email);
            $_SESSION['user']['sex'] = $sex ?: null;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        break;

    case 'changePassword':
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
        // Clear session data and destroy
        $_SESSION = array();
        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
