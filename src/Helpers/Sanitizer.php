<?php

declare(strict_types=1);

namespace App\Helpers;

class Sanitizer
{

    // Sanitize a generic string for safe output.
    public static function sanitizeString(string $value): string
    {
        if ($value === null) return '';

        $sanitized = trim($value);
        $sanitized = strip_tags($sanitized);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        return $sanitized;
    }

    /**
     * Sanitize username input by allowing only alphanumeric characters,
     * underscores and dots.
     */
    public static function sanitizeUsername($username)
    {
        if ($username === null) return '';

        // Trim whitespace and remove any embedded HTML.
        $sanitized = trim($username);
        $sanitized = strip_tags($sanitized);

        // Escape HTML special characters before filtering.
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        // Keep only allowed username characters.
        $sanitized = preg_replace('/[^a-zA-Z0-9_.]/', '', $sanitized);

        return $sanitized;
    }

    // Sanitize an email address and validate its structure.
    public static function sanitizeEmail(string $email): string
    {
        if ($email === null) return '';

        $sanitized = trim($email);
        $sanitized = strip_tags($sanitized);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        $sanitized = filter_var($sanitized, FILTER_SANITIZE_EMAIL);

        if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return ''; // Invalid emails are normalized to an empty string.
        }
        return $sanitized;
    }

    // Sanitize a password input by trimming whitespace only.
    public static function sanitizePassword($password)
    {
        if ($password === null) return '';

        return trim($password);
    }

    // Return plain text with HTML removed and optional length truncation.
    public static function plainText(string $value, int $maxLength = 65535): string
    {
        $value = strip_tags($value);
        if (strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength);
        }

        return $value;
    }

    // Sanitize an associative array based on provided field type hints.
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
