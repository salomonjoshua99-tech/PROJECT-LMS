<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ClassModel
{
    public function __construct(private PDO $pdo){
    }

    public function getFacultyClasses(int $facultyId): array{
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.faculty_id, c.course_code, c.title, c.section, c.class_code, c.status,
                    COUNT(e.student_id) as student_count
             FROM classes c
             LEFT JOIN enrollments e ON c.id = e.class_id
             WHERE c.faculty_id = :faculty_id AND c.status = "active"
             GROUP BY c.id
             ORDER BY c.created_at DESC'
        );
        $stmt->execute(['faculty_id' => $facultyId]);
        return $stmt->fetchAll();
    }

    public function getStudentClasses(int $studentId): array{
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.faculty_id, c.course_code, c.title, c.section, c.class_code, c.status
             FROM classes c
             JOIN enrollments e ON c.id = e.class_id
             WHERE e.student_id = :student_id AND c.status = "active"
             ORDER BY c.title'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public function getStudentDeadlines(int $studentId): array{
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

    public function getAnnouncements(int $userId, string $userRole): array{
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
                'SELECT a.id, a.title, a.message, a.tag, a.created_at, a.class_id
                 FROM announcements a
                 JOIN enrollments e ON a.class_id = e.class_id OR a.class_id IS NULL
                 WHERE e.student_id = :student_id OR a.class_id IS NULL
                 ORDER BY a.created_at DESC
                 LIMIT 10'
            );
            $stmt->execute(['student_id' => $userId]);
        }
        return $stmt->fetchAll();
    }

    public function getCalendarDeadlines(int $studentId, int $year, int $month): array{
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

    public function create(int $facultyId, string $courseCode, string $title, string $section, string $classCode): int{
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
             WHERE e.student_id = :student_id AND c.status = "active"
             ORDER BY u.name'
        );
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }
}
