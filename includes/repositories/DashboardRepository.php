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
            (SELECT COUNT(*) FROM classes WHERE school_id = ?) AS classes");
        $stmt->execute([$schoolId, $schoolId, $schoolId]);
        return $stmt->fetch() ?: ['teachers' => 0, 'students' => 0, 'classes' => 0];
    }
}
