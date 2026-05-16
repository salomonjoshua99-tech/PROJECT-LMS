<?php

declare(strict_types=1);

namespace App\Helpers;


class Validator
{
    // Collect validation error messages for the current request.
    private static $errors = [];

    // Validate email format, length, and basic structure.
    public static function validateEmail(string $email): bool
    {
        if (empty($email)) {
            self::$errors[] = "Email is required";
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$errors[] = "Invalid email format";
            return false;
        }

        // Additional regex validation for allowed email characters and domain structure.
        if (!preg_match("/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-z]{2,}$/", $email)) {
            self::$errors[] = "Invalid email format";
            return false;
        }

        if (strlen($email) > 100) {
            self::$errors[] = "Email cannot exceed 100 characters";
            return false;
        }

        return true;
    }

    // Validate username rules and reject patterns that look like SQL injection.
    public static function validateUsername($username)
    {
        if (empty($username)) {
            self::$errors[] = "Username is required";
            return false;
        }

        if (strlen($username) < 3) {
            self::$errors[] = "Username must be at least 3 characters long";
            return false;
        }

        if (strlen($username) > 50) {
            self::$errors[] = "Username cannot exceed 50 characters";
            return false;
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.]*$/', $username)) {
            self::$errors[] = "Username must start with a letter and can only contain letters, numbers, underscores and dots";
            return false;
        }

        // Reject usernames that include SQL keywords or common injection syntax.
        $sqlPatterns = ['/\bSELECT\b/i', '/\bINSERT\b/i', '/\bUPDATE\b/i', '/\bDELETE\b/i', '/\bDROP\b/i', '/--/', '/;\s*$/'];
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $username)) {
                self::$errors[] = "Username contains invalid characters or patterns";
                return false;
            }
        }

        return true;
    }

    // Validate password strength and optional confirmation match.
    public static function validatePassword($password, $confirmPassword = null)
    {
        if (empty($password)) {
            self::$errors[] = "Password is required";
            return false;
        }

        if (strlen($password) < 8) {
            self::$errors[] = "Password must be at least 8 characters long";
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            self::$errors[] = "Password must contain at least one uppercase letter";
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            self::$errors[] = "Password must contain at least one lowercase letter";
            return false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            self::$errors[] = "Password must contain at least one number";
            return false;
        }

        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            self::$errors[] = "Password must contain at least one special character";
            return false;
        }

        if ($confirmPassword !== null && $password !== $confirmPassword) {
            self::$errors[] = "Passwords do not match";
            return false;
        }

        return true;
    }

    // Return validation errors collected during the last checks.
    public static function getErrors()
    {
        return self::$errors;
    }

    /**
     * Clear stored validation errors.
     */
    public static function clearErrors()
    {
        self::$errors = [];
    }

    /**
     * Check if any validation errors were recorded.
     */
    public static function hasErrors()
    {
        return !empty(self::$errors);
    }

    /**
     * Validate that required keys are present and not empty in input data.
     */
    public static function required(array $data, array $keys): array
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Check whether a given value exists in an allowed list.
     */
    public static function inList(string $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }
}
