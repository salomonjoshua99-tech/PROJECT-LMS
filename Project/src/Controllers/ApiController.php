<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ClassModel;
use App\Models\UserModel;

class ApiController
{
    public function __construct(private ClassModel $classes, private UserModel $users) {
    }

    public function getUserData(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        $userId = $user['id'];
        $userRole = $user['role'];

        try {
            $data = [
                'user' => $user,
                'classes' => $this->getUserClasses($userId, $userRole),
                'deadlines' => $userRole === 'student' ? $this->classes->getStudentDeadlines($userId) : [],
                'announcements' => $this->classes->getAnnouncements($userId, $userRole),
                'calendar' => $this->getCalendarData($userId, $userRole),
                'instructors' => $userRole === 'student' ? $this->classes->getStudentInstructors($userId) : []
            ];

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to fetch data: ' . $e->getMessage()];
        }
    }

    private function getUserClasses(int $userId, string $role): array
    {
        if ($role === 'faculty') {
            return $this->classes->getFacultyClasses($userId);
        } else {
            return $this->classes->getStudentClasses($userId);
        }
    }

    private function getCalendarData(int $userId, string $role): array
    {
        if ($role !== 'student') {
            return [];
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        
        $deadlines = $this->classes->getCalendarDeadlines($userId, $currentYear, $currentMonth);
        
        // Build calendar array with all days of current month
        $daysInMonth = (int) date('t');
        $calendar = [];
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $calendar[$day] = [
                'day' => $day,
                'has_deadline' => false,
                'title' => null
            ];
        }
        
        // Mark days with deadlines
        foreach ($deadlines as $deadline) {
            $calendar[$deadline['day']] = $deadline;
        }
        
        return array_values($calendar);
    }

    public function generateCode(): array
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return ['success' => true, 'code' => $code];
    }

    public function createClass(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can create classes'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        // Handle FormData instead of JSON
        $courseCode = $_POST['courseCode'] ?? '';
        $title = $_POST['title'] ?? '';
        $section = $_POST['section'] ?? '';
        $classCode = $_POST['class_code'] ?? '';

        $required = ['courseCode', 'title', 'section'];
        $missing = [];
        foreach ($required as $field) {
            if (trim((string) (${$field} ?? '')) === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return ['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)];
        }

        try {
            // Use provided class code or generate new one
            $finalClassCode = $classCode ?: $this->generateClassCode();
            
            $classId = $this->classes->create(
                $user['id'],
                trim($courseCode),
                trim($title),
                trim($section),
                $finalClassCode
            );

            return [
                'success' => true,
                'message' => 'Class created successfully',
                'class' => [
                    'id' => $classId,
                    'course_code' => trim($courseCode),
                    'title' => trim($title),
                    'section' => trim($section),
                    'class_code' => $finalClassCode,
                    'status' => 'active'
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to create class: ' . $e->getMessage()];
        }
    }

    public function joinClass(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'student') {
            return ['success' => false, 'message' => 'Only students can join classes'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        // Handle FormData instead of JSON
        $classCode = $_POST['class_code'] ?? '';

        if (trim($classCode) === '') {
            return ['success' => false, 'message' => 'Class code is required'];
        }

        try {
            $classInfo = $this->classes->getClassByCode(trim($classCode));
            
            if (!$classInfo) {
                return ['success' => false, 'message' => 'Invalid class code'];
            }

            $enrollmentId = $this->classes->enrollStudent($user['id'], $classInfo['id']);

            return [
                'success' => true,
                'message' => 'Class joined successfully',
                'enrollment' => [
                    'id' => $enrollmentId,
                    'class_id' => $classInfo['id'],
                    'class_code' => $classInfo['class_code'],
                    'title' => $classInfo['title'],
                    'section' => $classInfo['section']
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to join class: ' . $e->getMessage()];
        }
    }

    private function generateClassCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
}
