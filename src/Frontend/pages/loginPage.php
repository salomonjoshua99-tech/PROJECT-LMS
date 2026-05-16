<?php
// Start or resume the PHP session for authentication state.
session_start();

// Check if user is already logged in and redirect them to the dashboard.
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';

// Import application helpers, models, and controllers.
use App\Controllers\UserController;
use App\Helpers\Database;
use App\Models\UserModel;
use App\Helpers\Sanitizer;
use App\Helpers\Validator;

// Initialize shared objects for database access and user operations.
$database = Database::getInstance();
$userModel = new UserModel($database->getConnection());
$userController = new UserController($userModel);

// Default response payload for form submissions and AJAX calls.
$response = ['success' => false, 'message' => ''];

// Handle login request submitted from the login form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = Sanitizer::sanitizeEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $missing = Validator::required($_POST, ['email', 'password']);
    if ($missing !== []) {
        $response = ['success' => false, 'message' => 'Email and password are required.'];
    } elseif (!Validator::validateEmail($email)) {
        $response = ['success' => false, 'message' => 'Invalid email format.'];
    } else {
        $foundUser = $userModel->findByEmail($email);

        if (!$foundUser || !password_verify($password, $foundUser['password_hash'])) {
            $response = ['success' => false, 'message' => 'Invalid email or password.'];
        } else {
            session_regenerate_id(true);

            $sessionUser = [
                'id' => (int) $foundUser['id'],
                'name' => $foundUser['name'],
                'email' => $foundUser['email'],
                'role' => $foundUser['role'],
                'birthdate' => $foundUser['birthdate'] ?? null,
                'sex' => $foundUser['sex'] ?? null,
            ];

            $_SESSION['user'] = $sessionUser;

            try {
                $userModel->recordLogin(
                    $sessionUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
                );
            } catch (\PDOException) {
                // Login is still valid if only the audit insert fails
            }

            $response = ['success' => true, 'message' => 'Login successful', 'redirect' => '../index.php'];
        }
    }
}

// Handle logout request submitted from the logout form.
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();

    $response = ['success' => true, 'message' => 'Logged out successfully'];
}

// Handle registration request submitted from the registration form.
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = Sanitizer::plainText(trim($_POST['name'] ?? ''), 120);
    $email = Sanitizer::sanitizeEmail($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $sex = $_POST['sex'] ?? '';

    $missing = Validator::required($_POST, ['name', 'email', 'password', 'role', 'birthdate', 'sex']);
    if ($missing !== []) {
        $response = ['success' => false, 'message' => 'Name, email, password, role, birthdate, and sex are required.'];
    } elseif ($name === '') {
        $response = ['success' => false, 'message' => 'Please enter your name.'];
    } elseif (!Validator::validateEmail($email)) {
        $response = ['success' => false, 'message' => 'Invalid email format.'];
    } elseif (!Validator::inList($role, ['faculty', 'student'])) {
        $response = ['success' => false, 'message' => 'Choose Faculty or Student.'];
    } elseif (!Validator::inList($sex, ['male', 'female', 'other'])) {
        $response = ['success' => false, 'message' => 'Please select your sex.'];
    } elseif (strlen($password) < 8) {
        $response = ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    } elseif ($confirm !== $password) {
        $response = ['success' => false, 'message' => 'Passwords do not match.'];
    } elseif ($userModel->findByEmail($email) !== null) {
        $response = ['success' => false, 'message' => 'An account with that email already exists.'];
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $id = $userModel->create($name, $email, $hash, $role, $birthdate, $sex);

            session_regenerate_id(true);

            $sessionUser = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'birthdate' => $birthdate,
                'sex' => $sex,
            ];

            $_SESSION['user'] = $sessionUser;

            try {
                $userModel->recordLogin(
                    $sessionUser['id'],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
                );
            } catch (\PDOException) {
                // Registration is still valid if only the audit insert fails
            }

            $response = ['success' => true, 'message' => 'Registration successful', 'redirect' => '../index.php'];
        } catch (\PDOException $exception) {
            $sqlState = (string) $exception->getCode();
            if ($sqlState === '23000' || str_contains($exception->getMessage(), 'Duplicate')) {
                $response = ['success' => false, 'message' => 'An account with that email already exists.'];
            } else {
                $response = ['success' => false, 'message' => 'Registration failed. Please try again.'];
            }
        }
    }
}

// Return JSON response for AJAX requests to avoid full page reload.
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// If the request was not AJAX and authentication succeeded, redirect browser to dashboard.
if ($response['success'] && isset($response['redirect'])) {
    header('Location: ' . $response['redirect']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | LEARNTEACH</title>
    <link rel="stylesheet" href="../assets/css/loginPage.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <script>
        // Store PHP response for JavaScript use on page load.
        const serverResponse = <?php echo json_encode($response); ?>;

        // Attach form handlers and UI logic when the DOM is ready.
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginPanel = document.getElementById('loginPanel');
            const registerPanel = document.getElementById('registerPanel');
            const showRegisterBtn = document.getElementById('showRegisterBtn');
            const showLoginBtn = document.getElementById('showLoginBtn');
            const loginError = document.getElementById('loginError');
            const registerError = document.getElementById('registerError');

            // Show any server-side errors on page load
            if (serverResponse.message && !serverResponse.success) {
                if (document.getElementById('loginForm').querySelector('input[name="action"]').value === 'login') {
                    loginError.textContent = serverResponse.message;
                    loginError.classList.remove('hidden');
                } else {
                    registerError.textContent = serverResponse.message;
                    registerError.classList.remove('hidden');
                }
            }

            // Toggle between login and register panels
            showRegisterBtn.addEventListener('click', function() {
                loginPanel.classList.add('hidden');
                registerPanel.classList.remove('hidden');
                loginError.classList.add('hidden');
                registerError.classList.add('hidden');
            });

            showLoginBtn.addEventListener('click', function() {
                registerPanel.classList.add('hidden');
                loginPanel.classList.remove('hidden');
                loginError.classList.add('hidden');
                registerError.classList.add('hidden');
            });

            // Handle login form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(loginForm);
                loginError.classList.add('hidden');

                // Disable submit button
                const submitBtn = loginForm.querySelector('.auth-submit');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Logging in...';

                fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect to dashboard
                            window.location.href = data.redirect;
                        } else {
                            loginError.textContent = data.message;
                            loginError.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        loginError.textContent = 'An error occurred. Please try again.';
                        loginError.classList.remove('hidden');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Login';
                    });
            });

            // Handle registration form submission
            registerForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(registerForm);
                registerError.classList.add('hidden');

                // Disable submit button
                const submitBtn = registerForm.querySelector('.auth-submit');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating account...';

                fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect to dashboard
                            window.location.href = data.redirect;
                        } else {
                            registerError.textContent = data.message;
                            registerError.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        registerError.textContent = 'An error occurred. Please try again.';
                        registerError.classList.remove('hidden');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Create account';
                    });
            });
        });
    </script>
</head>

<body>

    <div class="auth-overlay" id="authOverlay">
        <div class="auth-card">
            <p class="eyebrow">Login</p>
            <h2>Welcome to LEAR<span style="color: teal;">N</span>TEACH</h2>
            <p class="auth-copy">Log in with your own account or the seeded test accounts. Choose <strong>faculty</strong> or <strong>student</strong> when you register.</p>

            <div id="loginPanel">
                <form id="loginForm" class="auth-form" method="post">
                    <input type="hidden" name="action" value="login">
                    <label>
                        Email
                        <input type="email" name="email" id="loginEmail" placeholder="your.email@lntportal.com" required autocomplete="username">
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" id="loginPassword" placeholder="Your password" required autocomplete="current-password">
                    </label>
                    <button class="primary-btn auth-submit" type="submit">Login</button>
                    <p class="auth-error hidden" id="loginError"></p>
                </form>
                <p class="auth-switch">New here? <button type="button" class="link-btn" id="showRegisterBtn">Create an account</button></p>
            </div>

            <div id="registerPanel" class="hidden">
                <form id="registerForm" class="auth-form" method="post">
                    <input type="hidden" name="action" value="register">
                    <label>
                        Full name
                        <input type="text" name="name" id="registerName" placeholder="Your name" required maxlength="120" required autocomplete="name">
                    </label>
                    <label>
                        Email
                        <input type="email" name="email" id="registerEmail" placeholder="your.email@example.com" required autocomplete="email">
                    </label>
                    <label>
                        Password <span class="field-hint">(min. 8 characters)</span>
                        <input type="password" name="password" id="registerPassword" placeholder="Choose a password" required minlength="8" autocomplete="new-password">
                    </label>
                    <label>
                        Confirm password
                        <input type="password" name="password_confirm" id="registerPasswordConfirm" placeholder="Repeat password" required minlength="8" autocomplete="new-password">
                    </label>
                    <label>
                        Birthdate
                        <input type="date" name="birthdate" id="registerBirthdate" required>
                    </label>
                    <fieldset class="role-fieldset">
                        <legend>Sex</legend>
                        <label class="role-option"><input type="radio" name="sex" value="male" required> Male</label>
                        <label class="role-option"><input type="radio" name="sex" value="female"> Female</label>
                        <label class="role-option"><input type="radio" name="sex" value="other"> Other</label>
                    </fieldset>
                    <fieldset class="role-fieldset">
                        <legend>I am signing up as</legend>
                        <label class="role-option"><input type="radio" name="role" value="faculty"> Faculty</label>
                        <label class="role-option"><input type="radio" name="role" value="student" checked required> Student</label>
                    </fieldset>
                    <button class="primary-btn auth-submit" type="submit">Create account</button>
                    <p class="auth-error hidden" id="registerError"></p>
                </form>
                <p class="auth-switch">Already have an account? <button type="button" class="link-btn" id="showLoginBtn">Back to login</button></p>
            </div>

            <div class="demo-box">
                <p class="demo-box-title">Test accounts</p>
                <p><strong>Faculty dashboard:</strong><br>1234faculty@lntportal.edu — Lana123.</p>
                <p><strong>Student dashboard:</strong><br>6954321@lntportal.edu — Salomon123.</p>
            </div>
        </div>
    </div>


</body>

</html>