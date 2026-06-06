<?php

class DashboardRepository
{
    public static function teacherStats(int $teacherId, array $classIds): array
    {
        if (empty($classIds)) {
            return ['classes' => 0, 'materials' => 0, 'assignments' => 0, 'quizzes' => 0];
        }

        $ph = implode(',', array_fill(0, count($classIds), '?'));
        $params = [...$classIds, $teacherId];

        $stmt = db()->prepare("SELECT
            (SELECT COUNT(*) FROM materials WHERE class_id IN ($ph) AND teacher_id = ?) AS materials,
            (SELECT COUNT(*) FROM assignments WHERE class_id IN ($ph) AND teacher_id = ?) AS assignments,
            (SELECT COUNT(*) FROM quizzes WHERE class_id IN ($ph) AND teacher_id = ?) AS quizzes");
        $stmt->execute([...$classIds, $teacherId, ...$classIds, $teacherId, ...$classIds, $teacherId]);
        $row = $stmt->fetch();

        return [
            'classes'     => count($classIds),
            'materials'   => (int) ($row['materials'] ?? 0),
            'assignments' => (int) ($row['assignments'] ?? 0),
            'quizzes'     => (int) ($row['quizzes'] ?? 0),
        ];
    }

    public static function studentStats(int $studentId, array $classIds): array
    {
        if (empty($classIds)) {
            return ['classes' => 0, 'pending_assignments' => 0, 'upcoming_quizzes' => 0];
        }

        $ph = implode(',', array_fill(0, count($classIds), '?'));

        $stmt = db()->prepare("SELECT
            (SELECT COUNT(*) FROM assignments a
                LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
                WHERE a.class_id IN ($ph) AND s.id IS NULL) AS pending_assignments,
            (SELECT COUNT(*) FROM quizzes q
                WHERE q.class_id IN ($ph) AND (q.due_date IS NULL OR q.due_date > NOW())) AS upcoming_quizzes");
        $stmt->execute([$studentId, ...$classIds, ...$classIds]);
        $row = $stmt->fetch();

        return [
            'classes'             => count($classIds),
            'pending_assignments' => (int) ($row['pending_assignments'] ?? 0),
            'upcoming_quizzes'    => (int) ($row['upcoming_quizzes'] ?? 0),
        ];
    }

    public static function schoolAdminStats(int $schoolId): array
    {
        $stmt = db()->prepare("SELECT
            (SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND status = 'active') AS teachers,
            (SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'student' AND status = 'active') AS students,
            (SELECT COUNT(*) FROM class_groups WHERE school_id = ?) AS class_groups,
            (SELECT COUNT(*) FROM subjects WHERE school_id = ?) AS subjects,
            (SELECT COUNT(*) FROM classes WHERE school_id = ?) AS offerings");
        $stmt->execute([$schoolId, $schoolId, $schoolId, $schoolId, $schoolId]);
        $row = $stmt->fetch() ?: [];
        return [
            'teachers'     => (int) ($row['teachers'] ?? 0),
            'students'     => (int) ($row['students'] ?? 0),
            'class_groups' => (int) ($row['class_groups'] ?? 0),
            'subjects'     => (int) ($row['subjects'] ?? 0),
            'classes'      => (int) ($row['offerings'] ?? 0),
        ];
    }

    public static function schoolSetupProgress(int $schoolId): array
    {
        return [
            'subjects'        => SubjectRepository::count($schoolId) > 0,
            'teachers'        => SubjectRepository::teachersWithSubjectsCount($schoolId) > 0,
            'groups_offered'  => ClassGroupRepository::groupsWithOfferingsCount($schoolId) > 0,
            'students_enrolled' => ClassGroupRepository::enrolledStudentsCount($schoolId) > 0,
        ];
    }

    public static function teacherPendingGradingCount(int $teacherId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM assignment_submissions s
            INNER JOIN assignments a ON a.id = s.assignment_id
            WHERE a.teacher_id = ? AND s.status = 'submitted' AND s.grade IS NULL");
        $stmt->execute([$teacherId]);
        return (int) $stmt->fetchColumn();
    }

    public static function studentUpcomingTasks(int $studentId, array $classIds): array
    {
        if (empty($classIds)) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($classIds), '?'));
        $tasks = [];

        $stmt = db()->prepare("SELECT a.id, a.title, a.due_date, sub.name AS class_name, 'assignment' AS task_type
            FROM assignments a
            INNER JOIN classes c ON c.id = a.class_id
            INNER JOIN subjects sub ON sub.id = c.subject_id
            LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
            WHERE a.class_id IN ($ph) AND s.id IS NULL
            ORDER BY a.due_date IS NULL, a.due_date ASC
            LIMIT 10");
        $stmt->execute([$studentId, ...$classIds]);
        foreach ($stmt->fetchAll() as $row) {
            $tasks[] = $row;
        }

        $stmt = db()->prepare("SELECT q.id, q.title, q.due_date, sub.name AS class_name, 'quiz' AS task_type
            FROM quizzes q
            INNER JOIN classes c ON c.id = q.class_id
            INNER JOIN subjects sub ON sub.id = c.subject_id
            LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ? AND qa.status != 'in_progress'
            WHERE q.class_id IN ($ph) AND qa.id IS NULL
            AND (q.due_date IS NULL OR q.due_date > NOW())
            ORDER BY q.due_date IS NULL, q.due_date ASC
            LIMIT 10");
        $stmt->execute([$studentId, ...$classIds]);
        foreach ($stmt->fetchAll() as $row) {
            $tasks[] = $row;
        }

        usort($tasks, function ($a, $b) {
            $dueA = $a['due_date'] ? strtotime($a['due_date']) : PHP_INT_MAX;
            $dueB = $b['due_date'] ? strtotime($b['due_date']) : PHP_INT_MAX;
            return $dueA <=> $dueB;
        });

        return array_slice($tasks, 0, 10);
    }
}
