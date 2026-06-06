<?php

class SubjectRepository
{
    public static function forSchool(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT * FROM subjects WHERE school_id = ? ORDER BY name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function withUsageCounts(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT s.*,
            (SELECT COUNT(*) FROM classes WHERE subject_id = s.id) AS usage_count
            FROM subjects s WHERE s.school_id = ? ORDER BY s.name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function get(int $subjectId, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM subjects WHERE id = ? AND school_id = ?');
        $stmt->execute([$subjectId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    public static function count(int $schoolId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM subjects WHERE school_id = ?');
        $stmt->execute([$schoolId]);
        return (int) $stmt->fetchColumn();
    }

    public static function forTeacher(int $teacherId, int $schoolId): array
    {
        $stmt = db()->prepare('SELECT s.* FROM subjects s
            INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
            WHERE ts.teacher_id = ? AND s.school_id = ?
            ORDER BY s.name');
        $stmt->execute([$teacherId, $schoolId]);
        return $stmt->fetchAll();
    }

    public static function syncTeacherSubjects(int $teacherId, int $schoolId, array $subjectIds): void
    {
        db()->prepare('DELETE ts FROM teacher_subjects ts
            INNER JOIN subjects s ON s.id = ts.subject_id
            WHERE ts.teacher_id = ? AND s.school_id = ?')
            ->execute([$teacherId, $schoolId]);

        if (empty($subjectIds)) {
            return;
        }

        $ins = db()->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)');
        foreach ($subjectIds as $subjectId) {
            $subjectId = (int) $subjectId;
            if ($subjectId <= 0) {
                continue;
            }
            $check = db()->prepare('SELECT id FROM subjects WHERE id = ? AND school_id = ?');
            $check->execute([$subjectId, $schoolId]);
            if ($check->fetch()) {
                $ins->execute([$teacherId, $subjectId]);
            }
        }
    }

    public static function teachersForSubject(int $subjectId, int $schoolId): array
    {
        $stmt = db()->prepare("SELECT u.* FROM users u
            INNER JOIN teacher_subjects ts ON ts.teacher_id = u.id
            INNER JOIN subjects s ON s.id = ts.subject_id
            WHERE ts.subject_id = ? AND s.school_id = ? AND u.role = 'teacher' AND u.status = 'active'
            ORDER BY u.last_name, u.first_name");
        $stmt->execute([$subjectId, $schoolId]);
        return $stmt->fetchAll();
    }

    public static function notInGroup(int $schoolId, int $groupId): array
    {
        $stmt = db()->prepare('SELECT s.* FROM subjects s
            WHERE s.school_id = ?
            AND s.id NOT IN (
                SELECT subject_id FROM classes WHERE class_group_id = ? AND school_id = ?
            )
            ORDER BY s.name');
        $stmt->execute([$schoolId, $groupId, $schoolId]);
        return $stmt->fetchAll();
    }

    public static function teachersWithSubjectsCount(int $schoolId): int
    {
        $stmt = db()->prepare("SELECT COUNT(DISTINCT u.id) FROM users u
            INNER JOIN teacher_subjects ts ON ts.teacher_id = u.id
            INNER JOIN subjects s ON s.id = ts.subject_id
            WHERE u.school_id = ? AND u.role = 'teacher' AND u.status = 'active' AND s.school_id = ?");
        $stmt->execute([$schoolId, $schoolId]);
        return (int) $stmt->fetchColumn();
    }
}
