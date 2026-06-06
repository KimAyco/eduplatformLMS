<?php

class ClassRepository
{
    private const GROUP_SELECT = 'g.name AS group_name, g.academic_year AS group_academic_year';
    private const SUBJECT_SELECT = 's.name AS name, s.description AS description, s.id AS subject_id';

    public static function forTeacher(int $teacherId, int $schoolId): array
    {
        return remember("teacher_classes_{$teacherId}", function () use ($teacherId, $schoolId) {
            $stmt = db()->prepare('SELECT c.*, ' . self::SUBJECT_SELECT . ', ' . self::GROUP_SELECT . ' FROM classes c
                INNER JOIN subjects s ON s.id = c.subject_id
                INNER JOIN class_groups g ON g.id = c.class_group_id
                INNER JOIN class_teachers ct ON ct.class_id = c.id
                WHERE ct.teacher_id = ? AND c.school_id = ?
                ORDER BY g.name, s.name');
            $stmt->execute([$teacherId, $schoolId]);
            return $stmt->fetchAll();
        });
    }

    public static function forStudent(int $studentId, int $schoolId): array
    {
        return remember("student_classes_{$studentId}", function () use ($studentId, $schoolId) {
            $stmt = db()->prepare('SELECT c.*, ' . self::SUBJECT_SELECT . ', ' . self::GROUP_SELECT . ' FROM classes c
                INNER JOIN subjects s ON s.id = c.subject_id
                INNER JOIN class_groups g ON g.id = c.class_group_id
                INNER JOIN class_group_students cgs ON cgs.class_group_id = g.id
                WHERE cgs.student_id = ? AND c.school_id = ?
                ORDER BY g.name, s.name');
            $stmt->execute([$studentId, $schoolId]);
            return $stmt->fetchAll();
        });
    }

    public static function withCounts(int $schoolId, ?int $groupId = null): array
    {
        $sql = 'SELECT c.*, ' . self::SUBJECT_SELECT . ', ' . self::GROUP_SELECT . ',
            (SELECT COUNT(*) FROM class_teachers WHERE class_id = c.id) AS teacher_count,
            (SELECT COUNT(*) FROM class_group_students WHERE class_group_id = c.class_group_id) AS student_count
            FROM classes c
            INNER JOIN subjects s ON s.id = c.subject_id
            INNER JOIN class_groups g ON g.id = c.class_group_id
            WHERE c.school_id = ?';
        $params = [$schoolId];
        if ($groupId) {
            $sql .= ' AND c.class_group_id = ?';
            $params[] = $groupId;
        }
        $sql .= ' ORDER BY g.name, s.name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getWithGroup(int $classId, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT c.*, ' . self::SUBJECT_SELECT . ', ' . self::GROUP_SELECT . ' FROM classes c
            INNER JOIN subjects s ON s.id = c.subject_id
            INNER JOIN class_groups g ON g.id = c.class_group_id
            WHERE c.id = ? AND c.school_id = ?');
        $stmt->execute([$classId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    public static function assignTeacher(int $classId, int $teacherId, int $schoolId): bool
    {
        $class = self::getWithGroup($classId, $schoolId);
        if (!$class) {
            return false;
        }

        $tCheck = db()->prepare("SELECT u.id FROM users u
            INNER JOIN teacher_subjects ts ON ts.teacher_id = u.id
            WHERE u.id = ? AND u.school_id = ? AND u.role = 'teacher' AND u.status = 'active'
            AND ts.subject_id = ?");
        $tCheck->execute([$teacherId, $schoolId, $class['subject_id']]);
        if (!$tCheck->fetch()) {
            return false;
        }

        db()->prepare('DELETE FROM class_teachers WHERE class_id = ?')->execute([$classId]);
        db()->prepare('INSERT INTO class_teachers (class_id, teacher_id) VALUES (?, ?)')
            ->execute([$classId, $teacherId]);
        return true;
    }

    public static function removeTeacher(int $classId, int $schoolId): void
    {
        $check = db()->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
        $check->execute([$classId, $schoolId]);
        if ($check->fetch()) {
            db()->prepare('DELETE FROM class_teachers WHERE class_id = ?')->execute([$classId]);
        }
    }

    public static function getAssignedTeacher(int $classId): ?array
    {
        $stmt = db()->prepare('SELECT u.* FROM users u
            INNER JOIN class_teachers ct ON ct.teacher_id = u.id
            WHERE ct.class_id = ? LIMIT 1');
        $stmt->execute([$classId]);
        return $stmt->fetch() ?: null;
    }
}
