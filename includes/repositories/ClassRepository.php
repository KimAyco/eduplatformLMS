<?php

class ClassRepository
{
    public static function forTeacher(int $teacherId, int $schoolId): array
    {
        return remember("teacher_classes_{$teacherId}", function () use ($teacherId, $schoolId) {
            $stmt = db()->prepare('SELECT c.* FROM classes c
                INNER JOIN class_teachers ct ON ct.class_id = c.id
                WHERE ct.teacher_id = ? AND c.school_id = ?
                ORDER BY c.name, c.section');
            $stmt->execute([$teacherId, $schoolId]);
            return $stmt->fetchAll();
        });
    }

    public static function forStudent(int $studentId, int $schoolId): array
    {
        return remember("student_classes_{$studentId}", function () use ($studentId, $schoolId) {
            $stmt = db()->prepare('SELECT c.* FROM classes c
                INNER JOIN class_students cs ON cs.class_id = c.id
                WHERE cs.student_id = ? AND c.school_id = ?
                ORDER BY c.name, c.section');
            $stmt->execute([$studentId, $schoolId]);
            return $stmt->fetchAll();
        });
    }

    public static function withCounts(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT c.*,
            (SELECT COUNT(*) FROM class_teachers WHERE class_id = c.id) AS teacher_count,
            (SELECT COUNT(*) FROM class_students WHERE class_id = c.id) AS student_count
            FROM classes c WHERE c.school_id = ? ORDER BY c.name, c.section');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }
}
