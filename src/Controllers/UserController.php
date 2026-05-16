<?php

declare(strict_types=1); // Enforce strict typing for scalar values.

namespace App\Controllers; // Define the controller namespace.

use App\Helpers\Sanitizer; // Import sanitization helpers.
use App\Helpers\Validator; // Import validation helpers.
use App\Models\UserModel; // Import the user model for user operations.

class UserController
{
    /**
     * Inject the user model dependency.
     *
     * @param UserModel $users User data access object.
     */
    public function __construct(private UserModel $users) {}

    /**
     * Return the current authenticated user session.
     *
     * @return array{success: bool, user?: array<string, mixed>|null, message?: string}
     */
    public function session(): array
    {
        return [
            'success' => true,
            'user' => $this->currentUserFromSession(),
        ];
    }

    /**
     * Authenticate a user and establish a session.
     *
     * @return array{success: bool, user?: array<string, mixed>, message?: string}
     */
    public function login(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'success' => false,
                'message' => 'Invalid request method.'
            ];
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput ?: '{}', true);

        if (!is_array($input)) {
            return [
                'success' => false,
                'message' => 'Invalid request data.'
            ];
        }

        $email = Sanitizer::sanitizeEmail((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $missing = Validator::required($input, ['email', 'password']);
        if ($missing !== []) {
            return [
                'success' => false,
                'message' => 'Email and password are required.'
            ];
        }

        if (!Validator::validateEmail($email)) {
            return [
                'success' => false,
                'message' => 'Invalid email format.'
            ];
        }

        $foundUser = $this->users->findByEmail($email);

        if (!$foundUser || !password_verify($password, $foundUser['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        session_regenerate_id(true); // Protect against session fixation.

        $sessionUser = [
            'id' => (int) $foundUser['id'],
            'name' => $foundUser['name'],
            'email' => $foundUser['email'],
            'role' => $foundUser['role'],
        ];

        $_SESSION['user'] = $sessionUser;

        try {
            $this->users->recordLogin(
                $sessionUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            );
        } catch (\PDOException) {
            // Login is still valid if only the audit insert fails (e.g. missing login_records).
        }

        return ['success' => true, 'user' => $sessionUser];
    }

    /**
     * Register a new user account and start a session.
     *
     * @return array{success: bool, user?: array<string, mixed>, message?: string}
     */
    public function register(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'success' => false,
                'message' => 'Invalid request method.'
            ];
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput ?: '{}', true);

        if (!is_array($input)) {
            return [
                'success' => false,
                'message' => 'Invalid request payload.'
            ];
        }

        $missing = Validator::required($input, ['name', 'email', 'password', 'role']);
        if ($missing !== []) {
            return [
                'success' => false,
                'message' => 'Name, email, password, and role are required.'
            ];
        }

        $name = Sanitizer::plainText(trim((string) ($input['name'] ?? '')), 120);
        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Please enter your name.'
            ];
        }

        $email = Sanitizer::sanitizeEmail((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $role = (string) ($input['role'] ?? '');

        if (!Validator::validateEmail($email)) {
            return [
                'success' => false,
                'message' => 'Invalid email format.'
            ];
        }

        if (!Validator::inList($role, ['faculty', 'student'])) {
            return [
                'success' => false,
                'message' => 'Choose Faculty or Student.'
            ];
        }

        if (strlen($password) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters.'
            ];
        }

        $confirm = (string) ($input['password_confirm'] ?? $password);
        if ($confirm !== $password) {
            return [
                'success' => false,
                'message' => 'Passwords do not match.'
            ];
        }

        if ($this->users->findByEmail($email) !== null) {
            return [
                'success' => false,
                'message' => 'An account with that email already exists.'
            ];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $id = $this->users->create($name, $email, $hash, $role);
        } catch (\PDOException $exception) {
            $sqlState = (string) $exception->getCode();
            if ($sqlState === '23000' || str_contains($exception->getMessage(), 'Duplicate')) {
                return [
                    'success' => false,
                    'message' => 'An account with that email already exists.'
                ];
            }

            throw $exception;
        }

        session_regenerate_id(true); // Create a fresh session after registration.

        $sessionUser = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ];

        $_SESSION['user'] = $sessionUser;

        try {
            $this->users->recordLogin(
                $sessionUser['id'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            );
        } catch (\PDOException) {
            // Continue even if login audit cannot be recorded.
        }

        return [
            'success' => true,
            'user' => $sessionUser
        ];
    }

    /**
     * Log the current user out and destroy the session.
     *
     * @return array{success: bool, message?: string}
     */
    public function logout(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'success' => false,
                'message' => 'Invalid request method.'
            ];
        }

        $_SESSION = []; // Clear session data.

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

        session_destroy(); // Destroy the session on the server.

        return ['success' => true];
    }

    /**
     * Get the current user from the session if available.
     *
     * @return array<string, mixed>|null
     */
    private function currentUserFromSession(): ?array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return null;
        }

        return $_SESSION['user'];
    }
}
