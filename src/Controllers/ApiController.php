<?php

declare(strict_types=1); // Enable strict type checking for scalar types.

namespace App\Controllers; // Define the controller namespace.

use App\Models\ClassModel; // Import the class model for class-related data operations.
use App\Models\UserModel; // Import the user model for user-related data operations.

/**
 * API controller that handles class, announcement, activity, and submission endpoints.
 */
class ApiController
{
    /**
     * Inject required model dependencies.
     *
     * @param ClassModel $classes Class-related database operations.
     * @param UserModel $users User-related database operations.
     */
    public function __construct(private ClassModel $classes, private UserModel $users) {}

    /**
     * Maximum attachment size in bytes (10 MB).
     */
    private const MAX_ATTACHMENT_SIZE = 10485760;

    /**
     * Retrieve the current logged-in user data and related dashboard details.
     *
     * @return array<string, mixed>
     */
    public function getUserData(): array
    {
        // Check that a valid user session is available.
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        $userId = $user['id'];
        $userRole = $user['role'];

        error_log("getUserData - User ID: $userId, Role: $userRole");

        try {
            $classes = $this->getUserClasses($userId, $userRole);
            error_log("getUserData - Classes count: " . count($classes));
            error_log("getUserData - Classes: " . json_encode($classes));

            $data = [
                'user' => $user,
                'classes' => $classes,
                'deadlines' => $userRole === 'student' ? $this->classes->getStudentDeadlines($userId) : [],
                'announcements' => $this->classes->getAnnouncements($userId, $userRole),
                'activities' => $this->classes->getActivities($userId, $userRole),
                'calendar' => $this->getCalendarData($userId, $userRole),
                'students' => $userRole === 'faculty' ? $this->classes->getFacultyStudents($userId) : $this->classes->getStudentClassmates($userId),
            ];

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            error_log("getUserData error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch data: ' . $e->getMessage()];
        }
    }

    /**
     * Determine classes for the user based on role.
     *
     * @param int $userId The ID of the user.
     * @param string $role The role of the user.
     * @return array<int, mixed>
     */
    private function getUserClasses(int $userId, string $role): array
    {
        if ($role === 'faculty') {
            return $this->classes->getFacultyClasses($userId);
        } else {
            return $this->classes->getStudentClasses($userId);
        }
    }

    /**
     * Build calendar data for student deadlines.
     *
     * @param int $userId The ID of the student.
     * @param string $role The role of the user.
     * @return array<int, mixed>
     */
    private function getCalendarData(int $userId, string $role): array
    {
        if ($role !== 'student') {
            return [];
        }

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $deadlines = $this->classes->getCalendarDeadlines($userId, $currentYear, $currentMonth);

        // Build calendar array with all days of current month.
        $daysInMonth = (int) date('t');
        $calendar = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $calendar[$day] = [
                'day' => $day,
                'has_deadline' => false,
                'title' => null,
            ];
        }

        // Mark days with actual deadlines in the calendar.
        foreach ($deadlines as $deadline) {
            $calendar[$deadline['day']] = $deadline;
        }

        return array_values($calendar);
    }

    /**
     * Generate a random 6-character alphanumeric code.
     *
     * @return array<string, mixed>
     */
    public function generateCode(): array
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return ['success' => true, 'code' => $code];
    }

    /**
     * Create a new class when a faculty user submits the form.
     *
     * @return array<string, mixed>
     */
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

        // Handle FormData instead of JSON.
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
            // Use provided class code or generate a new one.
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
                    'status' => 'active',
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to create class: ' . $e->getMessage()];
        }
    }

    /**
     * Enroll a student in a class using a provided class code.
     *
     * @return array<string, mixed>
     */
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

        // Handle FormData instead of JSON.
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
                    'section' => $classInfo['section'],
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to join class: ' . $e->getMessage()];
        }
    }

    /**
     * Create an announcement for a class and store optional attachments.
     *
     * @return array<string, mixed>
     */
    public function createAnnouncement(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can create posts'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $classId = (int) ($_POST['class_id'] ?? 0);
        $message = trim((string) ($_POST['message'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? 'Class Announcement'));
        $tag = trim((string) ($_POST['tag'] ?? 'notice'));

        if ($classId === 0 || $message === '') {
            return ['success' => false, 'message' => 'Class and message are required'];
        }

        if (!in_array($tag, ['notice', 'reminder', 'update', 'new'], true)) {
            $tag = 'notice';
        }

        try {
            if (!$this->classes->facultyOwnsClass((int) $user['id'], $classId)) {
                return ['success' => false, 'message' => 'You can only post to your own classes'];
            }

            $announcementId = $this->classes->createAnnouncement((int) $user['id'], $classId, $title, $message, $tag);
            $attachments = $this->storeAnnouncementAttachments($announcementId);

            return [
                'success' => true,
                'message' => 'Post created successfully',
                'announcement' => [
                    'id' => $announcementId,
                    'class_id' => $classId,
                    'title' => $title,
                    'message' => $message,
                    'tag' => $tag,
                    'created_at' => date('Y-m-d H:i:s'),
                    'attachments' => $attachments,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to create post: ' . $e->getMessage()];
        }
    }

    /**
     * Save announcement file attachments and return stored metadata.
     *
     * @param int $announcementId The announcement ID to associate with files.
     * @return array<int, mixed>
     */
    private function storeAnnouncementAttachments(int $announcementId): array
    {
        if (!isset($_FILES['attachments'])) {
            return [];
        }

        $files = $this->normalizeUploadedFiles($_FILES['attachments']);
        if ($files === []) {
            return [];
        }

        $uploadDirectory = dirname(__DIR__) . '/Frontend/uploads/class_attachments';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('Unable to create attachment folder.');
        }

        $storedAttachments = [];
        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('A file failed to upload.');
            }

            if ((int) $file['size'] > self::MAX_ATTACHMENT_SIZE) {
                throw new \RuntimeException('Each attachment must be 10 MB or smaller.');
            }

            $originalName = basename((string) $file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $safeExtension = preg_replace('/[^a-z0-9]/', '', $extension);
            $filename = bin2hex(random_bytes(16)) . ($safeExtension ? '.' . $safeExtension : '');
            $targetPath = $uploadDirectory . '/' . $filename;

            if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
                throw new \RuntimeException('Unable to save uploaded file.');
            }

            $publicPath = 'uploads/class_attachments/' . $filename;
            $attachmentId = $this->classes->addAnnouncementAttachment(
                $announcementId,
                $filename,
                $originalName,
                $file['type'] ? (string) $file['type'] : null,
                (int) $file['size'],
                $publicPath
            );

            $storedAttachments[] = [
                'id' => $attachmentId,
                'announcement_id' => $announcementId,
                'filename' => $filename,
                'original_name' => $originalName,
                'mime_type' => $file['type'] ? (string) $file['type'] : null,
                'file_size' => (int) $file['size'],
                'file_path' => $publicPath,
                'url' => $publicPath,
            ];
        }

        return $storedAttachments;
    }

    /**
     * Normalize PHP uploaded file arrays to a consistent file list.
     *
     * @param array<string, mixed> $files The raw $_FILES entry.
     * @return array<int, array<string, mixed>>
     */
    private function normalizeUploadedFiles(array $files): array
    {
        if (!is_array($files['name'] ?? null)) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    /**
     * Create a new activity for students with optional attachments.
     *
     * @return array<string, mixed>
     */
    public function createActivity(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can create activities'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $classId = (int) ($_POST['class_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'Assignment'));
        $instructions = trim((string) ($_POST['instructions'] ?? ''));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($classId === 0 || $title === '' || $instructions === '') {
            return ['success' => false, 'message' => 'Class, title, and instructions are required'];
        }

        try {
            if (!$this->classes->facultyOwnsClass((int) $user['id'], $classId)) {
                return ['success' => false, 'message' => 'You can only create activities for your own classes'];
            }

            $activityId = $this->classes->createActivity((int) $user['id'], $classId, $title, $type, $instructions, $dueDate ?: null);
            $attachments = $this->storeUploadedAttachments(
                'attachments',
                dirname(__DIR__) . '/Frontend/uploads/activity_attachments',
                'uploads/activity_attachments',
                fn(array $file, string $filename, string $publicPath) => $this->classes->addActivityAttachment(
                    $activityId,
                    $filename,
                    basename((string) $file['name']),
                    $file['type'] ? (string) $file['type'] : null,
                    (int) $file['size'],
                    $publicPath
                ),
                $activityId
            );

            return [
                'success' => true,
                'message' => 'Activity created successfully',
                'activity' => [
                    'id' => $activityId,
                    'class_id' => $classId,
                    'faculty_id' => (int) $user['id'],
                    'title' => $title,
                    'type' => $type,
                    'instructions' => $instructions,
                    'due_date' => $dueDate ?: null,
                    'attachments' => $attachments,
                    'submission' => null,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to create activity: ' . $e->getMessage()];
        }
    }

    /**
     * Submit an activity with optional attachments for a student.
     *
     * @return array<string, mixed>
     */
    public function submitActivity(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'student') {
            return ['success' => false, 'message' => 'Only students can submit activities'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $activityId = (int) ($_POST['activity_id'] ?? 0);
        if ($activityId === 0) {
            return ['success' => false, 'message' => 'Activity is required'];
        }

        try {
            if (!$this->classes->studentCanSubmitActivity((int) $user['id'], $activityId)) {
                return ['success' => false, 'message' => 'You can only submit activities from your enrolled classes'];
            }

            $submissionId = $this->classes->createActivitySubmission($activityId, (int) $user['id']);
            $attachments = $this->storeUploadedAttachments(
                'attachments',
                dirname(__DIR__) . '/Frontend/uploads/submission_attachments',
                'uploads/submission_attachments',
                fn(array $file, string $filename, string $publicPath) => $this->classes->addSubmissionAttachment(
                    $submissionId,
                    $filename,
                    basename((string) $file['name']),
                    $file['type'] ? (string) $file['type'] : null,
                    (int) $file['size'],
                    $publicPath
                ),
                $submissionId
            );

            return [
                'success' => true,
                'message' => 'Activity submitted successfully',
                'submission' => [
                    'id' => $submissionId,
                    'activity_id' => $activityId,
                    'student_id' => (int) $user['id'],
                    'submittedAt' => date('Y-m-d H:i:s'),
                    'attachments' => $attachments,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to submit activity: ' . $e->getMessage()];
        }
    }

    /**
     * Delete an activity created by a faculty user.
     *
     * @return array<string, mixed>
     */
    public function deleteActivity(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can delete activities'];
        }

        $activityId = (int) ($_POST['activity_id'] ?? 0);
        if ($activityId === 0) {
            return ['success' => false, 'message' => 'Activity ID is required'];
        }

        try {
            if ($this->classes->deleteActivity($activityId, (int) $user['id'])) {
                return ['success' => true, 'message' => 'Activity deleted successfully'];
            }
            return ['success' => false, 'message' => 'Failed to delete activity or you do not have permission'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete activity: ' . $e->getMessage()];
        }
    }

    /**
     * Save a grade for a student submission.
     *
     * @return array<string, mixed>
     */
    public function saveSubmissionGrade(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can grade submissions'];
        }

        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $score = (float) ($_POST['score'] ?? -1);
        $maxScore = (float) ($_POST['max_score'] ?? 0);

        if ($submissionId === 0 || $score < 0 || $maxScore <= 0 || $score > $maxScore) {
            return ['success' => false, 'message' => 'Enter a valid grade and max score'];
        }

        try {
            if (!$this->classes->facultyCanGradeSubmission((int) $user['id'], $submissionId)) {
                return ['success' => false, 'message' => 'You can only grade submissions in your classes'];
            }

            $this->classes->saveSubmissionGrade($submissionId, $score, $maxScore);
            return [
                'success' => true,
                'grade' => [
                    'score' => $score,
                    'max' => $maxScore,
                    'percent' => round(($score / $maxScore) * 100, 2),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to save grade: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a grade record from a student submission.
     *
     * @return array<string, mixed>
     */
    public function deleteSubmissionGrade(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can delete grades'];
        }

        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        if ($submissionId === 0) {
            return ['success' => false, 'message' => 'Submission ID is required'];
        }

        try {
            if (!$this->classes->facultyCanGradeSubmission((int) $user['id'], $submissionId)) {
                return ['success' => false, 'message' => 'You can only edit grades in your classes'];
            }

            $this->classes->deleteSubmissionGrade($submissionId);
            return ['success' => true, 'message' => 'Grade deleted successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete grade: ' . $e->getMessage()];
        }
    }

    /**
     * Save uploaded files for activities, submissions, or announcements.
     *
     * @param string $fieldName The file input field name.
     * @param string $uploadDirectory Local directory path for storage.
     * @param string $publicDirectory Public-facing path prefix.
     * @param callable $persistAttachment Callback to persist attachment metadata.
     * @param int $parentId ID of the parent record.
     * @return array<int, mixed>
     */
    private function storeUploadedAttachments(
        string $fieldName,
        string $uploadDirectory,
        string $publicDirectory,
        callable $persistAttachment,
        int $parentId
    ): array {
        if (!isset($_FILES[$fieldName])) {
            return [];
        }

        $files = $this->normalizeUploadedFiles($_FILES[$fieldName]);
        if ($files === []) {
            return [];
        }

        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('Unable to create attachment folder.');
        }

        $storedAttachments = [];
        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('A file failed to upload.');
            }

            if ((int) $file['size'] > self::MAX_ATTACHMENT_SIZE) {
                throw new \RuntimeException('Each attachment must be 10 MB or smaller.');
            }

            $originalName = basename((string) $file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $safeExtension = preg_replace('/[^a-z0-9]/', '', $extension);
            $filename = bin2hex(random_bytes(16)) . ($safeExtension ? '.' . $safeExtension : '');
            $targetPath = $uploadDirectory . '/' . $filename;

            if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
                throw new \RuntimeException('Unable to save uploaded file.');
            }

            $publicPath = $publicDirectory . '/' . $filename;
            $attachmentId = $persistAttachment($file, $filename, $publicPath);

            $storedAttachments[] = [
                'id' => $attachmentId,
                'parent_id' => $parentId,
                'filename' => $filename,
                'original_name' => $originalName,
                'mime_type' => $file['type'] ? (string) $file['type'] : null,
                'file_size' => (int) $file['size'],
                'file_path' => $publicPath,
                'url' => $publicPath,
            ];
        }

        return $storedAttachments;
    }

    /**
     * Generate a random class code string.
     *
     * @return string Generated class code.
     */
    private function generateClassCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Remove a student from a class.
     *
     * @return array<string, mixed>
     */
    public function removeStudent(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can remove students'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $studentId = (int) ($_POST['student_id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);

        if ($studentId === 0 || $classId === 0) {
            return ['success' => false, 'message' => 'Student ID and Class ID are required'];
        }

        try {
            $result = $this->classes->removeStudentFromClass($studentId, $classId);

            if ($result) {
                return ['success' => true, 'message' => 'Student removed from class successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to remove student from class'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to remove student: ' . $e->getMessage()];
        }
    }

    /**
     * Delete an announcement from a class.
     *
     * @return array<string, mixed>
     */
    public function deleteAnnouncement(): array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $user = $_SESSION['user'];
        if ($user['role'] !== 'faculty') {
            return ['success' => false, 'message' => 'Only faculty can delete posts'];
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $announcementId = (int) ($_POST['announcement_id'] ?? 0);

        if ($announcementId === 0) {
            return ['success' => false, 'message' => 'Announcement ID is required'];
        }

        try {
            $result = $this->classes->deleteAnnouncement($announcementId, (int) $user['id']);

            if ($result) {
                return ['success' => true, 'message' => 'Post deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete post or you do not have permission'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete post: ' . $e->getMessage()];
        }
    }
}
