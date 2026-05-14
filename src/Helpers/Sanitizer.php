<?php

declare(strict_types=1);

namespace App\Helpers;

class Sanitizer
{

    // String
    public static function sanitizeString(string $value): string
    {
        if ($value === null) return '';

        $sanitized = trim($value);
        $sanitized = strip_tags($sanitized);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        return $sanitized;
    }

    /**
     * Sanitize username - allow only letters, numbers, underscores, dots
     */
    public static function sanitizeUsername($username)
    {
        if ($username === null) return '';

        // Trim whitespace
        $sanitized = trim($username);

        // Remove HTML tags
        $sanitized = strip_tags($sanitized);

        // Convert special characters to HTML entities
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        // Allow only letters, numbers, underscores, and dots
        $sanitized = preg_replace('/[^a-zA-Z0-9_.]/', '', $sanitized);

        return $sanitized;
    }

    // Email
    public static function sanitizeEmail(string $email): string
    {
        if ($email === null) return '';

        $sanitized = trim($email);
        $sanitized = strip_tags($sanitized);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        $sanitized = filter_var($sanitized, FILTER_SANITIZE_EMAIL);

        if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return ''; // or throw an error / return false
        }
        return $sanitized;
    }

    // Password
    public static function sanitizePassword($password)
    {
        if ($password === null) return '';

        // Just trim whitespace, don't modify password content
        return trim($password);
    }

    // Return
    public static function plainText(string $value, int $maxLength = 65535): string
    {
        $value = strip_tags($value);
        if (strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength);
        }

        return $value;
    }

    // Array
    public static function sanitizeArray($data, $fieldTypes = [])
    {
        $sanitized = [];

        foreach ($data as $field => $value) {
            if (isset($fieldTypes[$field])) {
                switch ($fieldTypes[$field]) {
                    case 'username':
                        $sanitized[$field] = self::sanitizeUsername($value);
                        break;
                    case 'email':
                        $sanitized[$field] = self::sanitizeEmail($value);
                        break;
                    case 'password':
                        $sanitized[$field] = self::sanitizePassword($value);
                        break;
                    default:
                        $sanitized[$field] = self::sanitizeString($value);
                }
            } else {
                $sanitized[$field] = self::sanitizeString($value);
            }
        }

        return $sanitized;
    }
}
