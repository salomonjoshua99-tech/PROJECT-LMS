<?php

declare(strict_types=1); // Enable strict type checking for scalar type declarations.

namespace App\Controllers; // Define the namespace for this controller class.

use PDO; // Import the PDO class used for database access.

/**
 * Placeholder for course-related API actions. Extend when a courses table exists.
 */
class CourseController
{
    /**
     * Store the PDO instance used for database communication.
     *
     * @param PDO $pdo Database connection object.
     */
    public function __construct(private PDO $pdo) {}

    /**
     * Provide a placeholder list response for courses.
     *
     * @return array<string, mixed> Response containing status and course data.
     */
    public function list(): array
    {
        // Return a placeholder response showing no course table is configured yet.
        return [
            'success' => true,
            'courses' => [],
            'message' => 'No courses table configured yet.',
        ];
    }
}
