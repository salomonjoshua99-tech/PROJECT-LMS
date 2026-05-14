<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ClassModel
{
    public function __construct(private PDO $pdo) {}

    private function ensureAnnouncementAttachmentsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS announcement_attachments (
                id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                announcement_id INT(10) UNSIGNED NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) DEFAULT NULL,
                file_size INT(10) UNSIGNED DEFAULT 0,
                file_path VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_announcement_attachments_post (announcement_id),
                CONSTRAINT fk_announcement_attachments_post
                    FOREIGN KEY (announcement_id) REFERENCES announcements (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    private function ensureClassworkTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS class_activities (
                id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                class_id INT(10) UNSIGNED NOT NULL,
                faculty_id INT(10) UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                type VARCHAR(60) NOT NULL,
                instructions TEXT NOT NULL,
                due_date DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_class_activities_class (class_id),
                CONSTRAINT fk_class_activities_class
                    FOREIGN KEY (class_id) REFERENCES classes (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_class_activities_faculty
                    FOREIGN KEY (faculty_id) REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS activity_attachments (
                id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                activity_id INT(10) UNSIGNED NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) DEFAULT NULL,
                file_size INT(10) UNSIGNED DEFAULT 0,
                file_path VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_activity_attachments_activity (activity_id),
                CONSTRAINT fk_activity_attachments_activity
                    FOREIGN KEY (activity_id) REFERENCES class_activities (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS activity_submissions (
                id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                activity_id INT(10) UNSIGNED NOT NULL,
                student_id INT(10) UNSIGNED NOT NULL,
                grade_score DECIMAL(6,2) DEFAULT NULL,
                grade_max DECIMAL(6,2) DEFAULT NULL,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_activity_student (activity_id, student_id),
                INDEX idx_activity_submissions_activity (activity_id),
                CONSTRAINT fk_activity_submissions_activity
                    FOREIGN KEY (activity_id) REFERENCES class_activities (id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_activity_submissions_student
                    FOREIGN KEY (student_id) REFERENCES users (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
        $this->pdo->exec('ALTER TABLE activity_submissions ADD COLUMN IF NOT EXISTS grade_score DECIMAL(6,2) DEFAULT NULL');
        $this->pdo->exec('ALTER TABLE activity_submissions ADD COLUMN IF NOT EXISTS grade_max DECIMAL(6,2) DEFAULT NULL');

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS submission_attachments (
                id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submission_id INT(10) UNSIGNED NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) DEFAULT NULL,
                file_size INT(10) UNSIGNED DEFAULT 0,
                file_path VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_submission_attachments_submission (submission_id),
                CONSTRAINT fk_submission_attachments_submission
                    FOREIGN KEY (submission_id) REFERENCES activity_submissions (id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    public function getFacultyClasses(int $facultyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.faculty_id, c.course_code, c.title, c.section, c.class_code, c.status,
                    COUNT(e.student_id) as student_count
             FROM classes c
             LEFT JOIN enrollments e ON c.id = e.class_id
             WHERE c.faculty_id = :faculty_id AND c.status = \'active\'
             GROUP BY c.id
             ORDER BY c.created_at DESC'
        );
        $stmt->execute(['faculty_id' => $facultyId]);
        return $stmt->fetchAll();
    }

    public function getStudentClasses(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.faculty_id, c.course_code, c.title, c.section, c.class_code, c.status,
                    COUNT(DISTINCT e2.student_id) as student_count
             FROM classes c
             JOIN enrollments e ON c.id = e.class_id
             LEFT JOIN enrollments e2 ON c.id = e2.class_id
             WHERE e.student_id = :student_id AND c.status = \'active\'
             GROUP BY c.id
             ORDER BY c.title'
        );
        $stmt->execute(['student_id' => $studentId]);
        $classes = $stmt->fetchAll();
        error_log("getStudentClasses - Student ID: $studentId, Classes found: " . count($classes));
        error_log("getStudentClasses - Classes data: " . json_encode($classes));
        return $classes;
    }

    public function getStudentDeadlines(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.title, d.description, d.type, d.due_date, d.class_id
             FROM deadlines d
             JOIN enrollments e ON d.class_id = e.class_id
             WHERE e.student_id = :student_id AND d.due_date > NOW()
             ORDER BY d.due_date ASC
             LIMIT 10'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public function getAnnouncements(int $userId, string $userRole): array
    {
        $this->ensureAnnouncementAttachmentsTable();

        if ($userRole === 'faculty') {
            $stmt = $this->pdo->prepare(
                'SELECT a.id, a.title, a.message, a.tag, a.created_at, a.class_id
                 FROM announcements a
                 WHERE a.faculty_id = :faculty_id
                 ORDER BY a.created_at DESC
                 LIMIT 10'
            );
            $stmt->execute(['faculty_id' => $userId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT a.id, a.title, a.message, a.tag, a.created_at, a.class_id
                 FROM announcements a
                 LEFT JOIN enrollments e ON a.class_id = e.class_id
                 WHERE a.class_id IS NULL OR e.student_id = :student_id
                 ORDER BY a.created_at DESC
                 LIMIT 10'
            );
            $stmt->execute(['student_id' => $userId]);
        }

        $announcements = $stmt->fetchAll();
        $this->attachFilesToAnnouncements($announcements);
        return $announcements;
    }

    private function attachFilesToAnnouncements(array &$announcements): void
    {
        if ($announcements === []) {
            return;
        }

        $ids = array_map(static fn($announcement) => (int) $announcement['id'], $announcements);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, announcement_id, filename, original_name, mime_type, file_size, file_path
             FROM announcement_attachments
             WHERE announcement_id IN ($placeholders)
             ORDER BY created_at ASC"
        );
        $stmt->execute($ids);

        $attachmentsByAnnouncement = [];
        foreach ($stmt->fetchAll() as $attachment) {
            $attachment['url'] = $attachment['file_path'];
            $attachmentsByAnnouncement[(int) $attachment['announcement_id']][] = $attachment;
        }

        foreach ($announcements as &$announcement) {
            $announcement['attachments'] = $attachmentsByAnnouncement[(int) $announcement['id']] ?? [];
        }
    }

    public function getCalendarDeadlines(int $studentId, int $year, int $month): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DAY(d.due_date) as day, 
                    true as has_deadline, 
                    d.title
             FROM deadlines d
             JOIN enrollments e ON d.class_id = e.class_id
             WHERE e.student_id = :student_id 
               AND YEAR(d.due_date) = :year 
               AND MONTH(d.due_date) = :month
             ORDER BY d.due_date'
        );
        $stmt->execute([
            'student_id' => $studentId,
            'year' => $year,
            'month' => $month
        ]);

        $deadlines = [];
        foreach ($stmt->fetchAll() as $deadline) {
            $deadlines[] = [
                'day' => (int) $deadline['day'],
                'has_deadline' => true,
                'title' => $deadline['title']
            ];
        }
        return $deadlines;
    }

    public function getActivities(int $userId, string $userRole): array
    {
        $this->ensureClassworkTables();

        if ($userRole === 'faculty') {
            $stmt = $this->pdo->prepare(
                'SELECT a.id, a.class_id, a.faculty_id, a.title, a.type, a.instructions, a.due_date, a.created_at
                 FROM class_activities a
                 JOIN classes c ON a.class_id = c.id
                 WHERE c.faculty_id = :faculty_id AND c.status = \'active\'
                 ORDER BY a.created_at DESC'
            );
            $stmt->execute(['faculty_id' => $userId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT a.id, a.class_id, a.faculty_id, a.title, a.type, a.instructions, a.due_date, a.created_at
                 FROM class_activities a
                 JOIN enrollments e ON a.class_id = e.class_id
                 JOIN classes c ON a.class_id = c.id
                 WHERE e.student_id = :student_id AND c.status = \'active\'
                 ORDER BY a.created_at DESC'
            );
            $stmt->execute(['student_id' => $userId]);
        }

        $activities = $stmt->fetchAll();
        $this->attachFilesToActivities($activities);

        if ($userRole === 'student') {
            $this->attachStudentSubmissionsToActivities($activities, $userId);
        } else {
            $this->attachFacultySubmissionsToActivities($activities);
        }

        return $activities;
    }

    private function attachFilesToActivities(array &$activities): void
    {
        if ($activities === []) {
            return;
        }

        $ids = array_map(static fn($activity) => (int) $activity['id'], $activities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, activity_id, filename, original_name, mime_type, file_size, file_path
             FROM activity_attachments
             WHERE activity_id IN ($placeholders)
             ORDER BY created_at ASC"
        );
        $stmt->execute($ids);

        $attachmentsByActivity = [];
        foreach ($stmt->fetchAll() as $attachment) {
            $attachment['url'] = $attachment['file_path'];
            $attachmentsByActivity[(int) $attachment['activity_id']][] = $attachment;
        }

        foreach ($activities as &$activity) {
            $activity['attachments'] = $attachmentsByActivity[(int) $activity['id']] ?? [];
        }
    }

    private function attachStudentSubmissionsToActivities(array &$activities, int $studentId): void
    {
        if ($activities === []) {
            return;
        }

        $ids = array_map(static fn($activity) => (int) $activity['id'], $activities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, activity_id, student_id, grade_score, grade_max, submitted_at
             FROM activity_submissions
             WHERE student_id = ? AND activity_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$studentId], $ids));

        $submissions = $stmt->fetchAll();
        if ($submissions === []) {
            foreach ($activities as &$activity) {
                $activity['submission'] = null;
            }
            return;
        }

        $submissionIds = array_map(static fn($submission) => (int) $submission['id'], $submissions);
        $submissionPlaceholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $attachmentStmt = $this->pdo->prepare(
            "SELECT id, submission_id, filename, original_name, mime_type, file_size, file_path
             FROM submission_attachments
             WHERE submission_id IN ($submissionPlaceholders)
             ORDER BY created_at ASC"
        );
        $attachmentStmt->execute($submissionIds);

        $attachmentsBySubmission = [];
        foreach ($attachmentStmt->fetchAll() as $attachment) {
            $attachment['url'] = $attachment['file_path'];
            $attachmentsBySubmission[(int) $attachment['submission_id']][] = $attachment;
        }

        $submissionsByActivity = [];
        foreach ($submissions as $submission) {
            $submission['submittedAt'] = $submission['submitted_at'];
            $submission['grade'] = $submission['grade_score'] !== null && $submission['grade_max'] !== null
                ? [
                    'score' => (float) $submission['grade_score'],
                    'max' => (float) $submission['grade_max'],
                    'percent' => (float) $submission['grade_max'] > 0
                        ? round(((float) $submission['grade_score'] / (float) $submission['grade_max']) * 100, 2)
                        : null,
                ]
                : null;
            $submission['attachments'] = $attachmentsBySubmission[(int) $submission['id']] ?? [];
            $submissionsByActivity[(int) $submission['activity_id']] = $submission;
        }

        foreach ($activities as &$activity) {
            $activity['submission'] = $submissionsByActivity[(int) $activity['id']] ?? null;
        }
    }

    private function attachFacultySubmissionsToActivities(array &$activities): void
    {
        if ($activities === []) {
            return;
        }

        $activityIds = array_map(static fn($activity) => (int) $activity['id'], $activities);
        $activityPlaceholders = implode(',', array_fill(0, count($activityIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT
                a.id AS activity_id,
                u.id AS student_id,
                u.name AS student_name,
                u.email AS student_email,
                s.id AS submission_id,
                s.grade_score,
                s.grade_max,
                s.submitted_at
             FROM class_activities a
             JOIN enrollments e ON a.class_id = e.class_id
             JOIN users u ON e.student_id = u.id
             LEFT JOIN activity_submissions s
                ON s.activity_id = a.id AND s.student_id = u.id
             WHERE a.id IN ($activityPlaceholders)
             ORDER BY a.id DESC, u.name ASC"
        );
        $stmt->execute($activityIds);

        $rows = $stmt->fetchAll();
        $submissionIds = [];
        foreach ($rows as $row) {
            if (!empty($row['submission_id'])) {
                $submissionIds[] = (int) $row['submission_id'];
            }
        }

        $attachmentsBySubmission = [];
        if ($submissionIds !== []) {
            $submissionPlaceholders = implode(',', array_fill(0, count($submissionIds), '?'));
            $attachmentStmt = $this->pdo->prepare(
                "SELECT id, submission_id, filename, original_name, mime_type, file_size, file_path
                 FROM submission_attachments
                 WHERE submission_id IN ($submissionPlaceholders)
                 ORDER BY created_at ASC"
            );
            $attachmentStmt->execute($submissionIds);

            foreach ($attachmentStmt->fetchAll() as $attachment) {
                $attachment['url'] = $attachment['file_path'];
                $attachmentsBySubmission[(int) $attachment['submission_id']][] = $attachment;
            }
        }

        $submissionsByActivity = [];
        foreach ($rows as $row) {
            $submissionId = $row['submission_id'] ? (int) $row['submission_id'] : null;
            $submissionsByActivity[(int) $row['activity_id']][] = [
                'student_id' => (int) $row['student_id'],
                'student_name' => $row['student_name'],
                'student_email' => $row['student_email'],
                'submission_id' => $submissionId,
                'submitted' => $submissionId !== null,
                'submitted_at' => $row['submitted_at'],
                'submittedAt' => $row['submitted_at'],
                'grade_score' => $row['grade_score'],
                'grade_max' => $row['grade_max'],
                'grade' => $row['grade_score'] !== null && $row['grade_max'] !== null
                    ? [
                        'score' => (float) $row['grade_score'],
                        'max' => (float) $row['grade_max'],
                        'percent' => (float) $row['grade_max'] > 0
                            ? round(((float) $row['grade_score'] / (float) $row['grade_max']) * 100, 2)
                            : null,
                    ]
                    : null,
                'attachments' => $submissionId ? ($attachmentsBySubmission[$submissionId] ?? []) : [],
            ];
        }

        foreach ($activities as &$activity) {
            $submissions = $submissionsByActivity[(int) $activity['id']] ?? [];
            $submittedCount = count(array_filter($submissions, static fn($submission) => $submission['submitted']));
            $activity['submissions'] = $submissions;
            $activity['submission_count'] = $submittedCount;
            $activity['student_count'] = count($submissions);
        }
    }

    public function create(int $facultyId, string $courseCode, string $title, string $section, string $classCode): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO classes (faculty_id, course_code, title, section, class_code) 
             VALUES (:faculty_id, :course_code, :title, :section, :class_code)'
        );
        $stmt->execute([
            'faculty_id' => $facultyId,
            'course_code' => $courseCode,
            'title' => $title,
            'section' => $section,
            'class_code' => $classCode,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function facultyOwnsClass(int $facultyId, int $classId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM classes WHERE id = :class_id AND faculty_id = :faculty_id AND status = \'active\' LIMIT 1'
        );
        $stmt->execute([
            'class_id' => $classId,
            'faculty_id' => $facultyId,
        ]);

        return (bool) $stmt->fetch();
    }

    public function createAnnouncement(int $facultyId, int $classId, string $title, string $message, string $tag = 'notice'): int
    {
        $this->ensureAnnouncementAttachmentsTable();

        $stmt = $this->pdo->prepare(
            'INSERT INTO announcements (class_id, faculty_id, title, message, tag)
             VALUES (:class_id, :faculty_id, :title, :message, :tag)'
        );
        $stmt->execute([
            'class_id' => $classId,
            'faculty_id' => $facultyId,
            'title' => $title,
            'message' => $message,
            'tag' => $tag,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addAnnouncementAttachment(
        int $announcementId,
        string $filename,
        string $originalName,
        ?string $mimeType,
        int $fileSize,
        string $filePath
    ): int {
        $this->ensureAnnouncementAttachmentsTable();

        $stmt = $this->pdo->prepare(
            'INSERT INTO announcement_attachments (announcement_id, filename, original_name, mime_type, file_size, file_path)
             VALUES (:announcement_id, :filename, :original_name, :mime_type, :file_size, :file_path)'
        );
        $stmt->execute([
            'announcement_id' => $announcementId,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_path' => $filePath,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createActivity(
        int $facultyId,
        int $classId,
        string $title,
        string $type,
        string $instructions,
        ?string $dueDate
    ): int {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'INSERT INTO class_activities (class_id, faculty_id, title, type, instructions, due_date)
             VALUES (:class_id, :faculty_id, :title, :type, :instructions, :due_date)'
        );
        $stmt->execute([
            'class_id' => $classId,
            'faculty_id' => $facultyId,
            'title' => $title,
            'type' => $type,
            'instructions' => $instructions,
            'due_date' => $dueDate ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function addActivityAttachment(
        int $activityId,
        string $filename,
        string $originalName,
        ?string $mimeType,
        int $fileSize,
        string $filePath
    ): int {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_attachments (activity_id, filename, original_name, mime_type, file_size, file_path)
             VALUES (:activity_id, :filename, :original_name, :mime_type, :file_size, :file_path)'
        );
        $stmt->execute([
            'activity_id' => $activityId,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_path' => $filePath,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function studentCanSubmitActivity(int $studentId, int $activityId): bool
    {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'SELECT a.id
             FROM class_activities a
             JOIN enrollments e ON a.class_id = e.class_id
             WHERE a.id = :activity_id AND e.student_id = :student_id
             LIMIT 1'
        );
        $stmt->execute([
            'activity_id' => $activityId,
            'student_id' => $studentId,
        ]);

        return (bool) $stmt->fetch();
    }

    public function createActivitySubmission(int $activityId, int $studentId): int
    {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_submissions (activity_id, student_id)
             VALUES (:activity_id, :student_id)
             ON DUPLICATE KEY UPDATE submitted_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'activity_id' => $activityId,
            'student_id' => $studentId,
        ]);

        $findStmt = $this->pdo->prepare(
            'SELECT id FROM activity_submissions WHERE activity_id = :activity_id AND student_id = :student_id LIMIT 1'
        );
        $findStmt->execute([
            'activity_id' => $activityId,
            'student_id' => $studentId,
        ]);

        return (int) $findStmt->fetchColumn();
    }

    public function addSubmissionAttachment(
        int $submissionId,
        string $filename,
        string $originalName,
        ?string $mimeType,
        int $fileSize,
        string $filePath
    ): int {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'INSERT INTO submission_attachments (submission_id, filename, original_name, mime_type, file_size, file_path)
             VALUES (:submission_id, :filename, :original_name, :mime_type, :file_size, :file_path)'
        );
        $stmt->execute([
            'submission_id' => $submissionId,
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_path' => $filePath,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function facultyCanGradeSubmission(int $facultyId, int $submissionId): bool
    {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'SELECT s.id
             FROM activity_submissions s
             JOIN class_activities a ON s.activity_id = a.id
             JOIN classes c ON a.class_id = c.id
             WHERE s.id = :submission_id AND c.faculty_id = :faculty_id
             LIMIT 1'
        );
        $stmt->execute([
            'submission_id' => $submissionId,
            'faculty_id' => $facultyId,
        ]);

        return (bool) $stmt->fetch();
    }

    public function saveSubmissionGrade(int $submissionId, float $score, float $maxScore): bool
    {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'UPDATE activity_submissions
             SET grade_score = :grade_score, grade_max = :grade_max
             WHERE id = :submission_id'
        );
        return $stmt->execute([
            'grade_score' => $score,
            'grade_max' => $maxScore,
            'submission_id' => $submissionId,
        ]);
    }

    public function deleteSubmissionGrade(int $submissionId): bool
    {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'UPDATE activity_submissions
             SET grade_score = NULL, grade_max = NULL
             WHERE id = :submission_id'
        );
        return $stmt->execute(['submission_id' => $submissionId]);
    }

    public function deleteActivity(int $activityId, int $facultyId): bool
    {
        $this->ensureClassworkTables();

        $stmt = $this->pdo->prepare(
            'DELETE a FROM class_activities a
             JOIN classes c ON a.class_id = c.id
             WHERE a.id = :activity_id AND c.faculty_id = :faculty_id'
        );
        return $stmt->execute([
            'activity_id' => $activityId,
            'faculty_id' => $facultyId,
        ]);
    }

    public function getClassByCode(string $classCode): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, course_code, title, section, class_code 
             FROM classes 
             WHERE class_code = :class_code AND status = 'active' 
             LIMIT 1"
        );

        $stmt->execute(['class_code' => $classCode]);

        return $stmt->fetch() ?: null;
    }

    public function enrollStudent(int $userId, int $classId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO enrollments (student_id, class_id, enrolled_at) 
             VALUES (:student_id, :class_id, NOW())"
        );

        $stmt->execute([
            'student_id' => $userId,
            'class_id' => $classId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getStudentInstructors(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT u.id, u.name, u.email, c.course_code, c.title
             FROM users u
             JOIN classes c ON u.id = c.faculty_id
             JOIN enrollments e ON c.id = e.class_id
             WHERE e.student_id = :student_id AND c.status = \'active\'
             ORDER BY u.name'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public function getFacultyStudents(int $facultyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, c.course_code, c.title, c.section, c.class_code, e.class_id
             FROM users u
             JOIN enrollments e ON u.id = e.student_id
             JOIN classes c ON e.class_id = c.id
             WHERE c.faculty_id = :faculty_id AND c.status = \'active\' AND u.role = \'student\'
             ORDER BY c.title, u.name'
        );
        $stmt->execute(['faculty_id' => $facultyId]);
        return $stmt->fetchAll();
    }

    public function getStudentClassmates(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email, c.course_code, c.title, c.section
            FROM users u
             JOIN enrollments e1 ON u.id = e1.student_id
             JOIN classes c ON e1.class_id = c.id
             WHERE e1.class_id IN (
                 SELECT e2.class_id FROM enrollments e2 WHERE e2.student_id = :student_id
             )
             AND u.id != :current_student_id
             AND c.status = 'active'
        ");
        $stmt->execute([
            'student_id' => $studentId,
            'current_student_id' => $studentId
        ]);
        return $stmt->fetchAll();
    }

    public function removeStudentFromClass(int $studentId, int $classId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM enrollments WHERE student_id = :student_id AND class_id = :class_id'
        );
        return $stmt->execute([
            'student_id' => $studentId,
            'class_id' => $classId
        ]);
    }

    public function deleteAnnouncement(int $announcementId, int $facultyId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE a FROM announcements a 
             JOIN classes c ON a.class_id = c.id 
             WHERE a.id = :announcement_id AND c.faculty_id = :faculty_id'
        );
        return $stmt->execute([
            'announcement_id' => $announcementId,
            'faculty_id' => $facultyId
        ]);
    }
}
