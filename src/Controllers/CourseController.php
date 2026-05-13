<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

/**
 * Placeholder for course-related API actions. Extend when a courses table exists.
 */
class CourseController
{
    public function __construct(private PDO $pdo){
    }

    public function list(): array{
        return [
            'success' => true,
            'courses' => [],
            'message' => 'No courses table configured yet.',
        ];
    }
}
