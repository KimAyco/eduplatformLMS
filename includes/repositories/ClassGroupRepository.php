<?php

class ClassGroupRepository
{
    public static function forSchool(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT * FROM class_groups WHERE school_id = ? ORDER BY name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function withCounts(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT g.*,
            (SELECT COUNT(*) FROM classes WHERE class_group_id = g.id) AS class_count,
            (SELECT COUNT(*) FROM class_group_students WHERE class_group_id = g.id) AS student_count,
            (SELECT COUNT(*) FROM classes c
                WHERE c.class_group_id = g.id
                AND NOT EXISTS (SELECT 1 FROM class_teachers ct WHERE ct.class_id = c.id)
            ) AS unassigned_count
            FROM class_groups g WHERE g.school_id = ? ORDER BY g.name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function get(int $groupId, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM class_groups WHERE id = ? AND school_id = ?');
        $stmt->execute([$groupId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    public static function offerings(int $groupId, int $schoolId): array
    {
        $stmt = db()->prepare('SELECT c.*, s.name AS name, s.description AS description, s.id AS subject_id,
            u.id AS teacher_id, u.first_name AS teacher_first, u.last_name AS teacher_last, u.email AS teacher_email
            FROM classes c
            INNER JOIN subjects s ON s.id = c.subject_id
            LEFT JOIN class_teachers ct ON ct.class_id = c.id
            LEFT JOIN users u ON u.id = ct.teacher_id
            WHERE c.class_group_id = ? AND c.school_id = ?
            ORDER BY s.name');
        $stmt->execute([$groupId, $schoolId]);
        return $stmt->fetchAll();
    }

    public static function addSubject(int $groupId, int $subjectId, int $schoolId): bool
    {
        $group = self::get($groupId, $schoolId);
        $subject = SubjectRepository::get($subjectId, $schoolId);
        if (!$group || !$subject) {
            return false;
        }

        $dup = db()->prepare('SELECT id FROM classes WHERE class_group_id = ? AND subject_id = ?');
        $dup->execute([$groupId, $subjectId]);
        if ($dup->fetch()) {
            return false;
        }

        $stmt = db()->prepare('INSERT INTO classes (school_id, class_group_id, subject_id) VALUES (?, ?, ?)');
        $stmt->execute([$schoolId, $groupId, $subjectId]);
        return true;
    }

    public static function removeOffering(int $classId, int $groupId, int $schoolId): bool
    {
        $check = db()->prepare('SELECT id FROM classes WHERE id = ? AND class_group_id = ? AND school_id = ?');
        $check->execute([$classId, $groupId, $schoolId]);
        if (!$check->fetch()) {
            return false;
        }
        db()->prepare('DELETE FROM classes WHERE id = ? AND school_id = ?')->execute([$classId, $schoolId]);
        return true;
    }

    public static function enrolledStudents(int $groupId): array
    {
        $stmt = db()->prepare('SELECT u.* FROM users u
            INNER JOIN class_group_students cgs ON cgs.student_id = u.id
            WHERE cgs.class_group_id = ?
            ORDER BY u.last_name, u.first_name');
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }

    public static function availableStudents(int $groupId, int $schoolId): array
    {
        $stmt = db()->prepare("SELECT u.* FROM users u
            WHERE u.school_id = ? AND u.role = 'student' AND u.status = 'active'
            AND u.id NOT IN (SELECT student_id FROM class_group_students WHERE class_group_id = ?)
            ORDER BY u.last_name, u.first_name");
        $stmt->execute([$schoolId, $groupId]);
        return $stmt->fetchAll();
    }

    public static function enrollStudent(int $groupId, int $studentId, int $schoolId): bool
    {
        if (!self::get($groupId, $schoolId)) {
            return false;
        }
        $sCheck = db()->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND role = 'student' AND status = 'active'");
        $sCheck->execute([$studentId, $schoolId]);
        if (!$sCheck->fetch()) {
            return false;
        }
        db()->prepare('INSERT IGNORE INTO class_group_students (class_group_id, student_id) VALUES (?, ?)')
            ->execute([$groupId, $studentId]);
        return true;
    }

    public static function removeStudent(int $groupId, int $studentId, int $schoolId): void
    {
        if (!self::get($groupId, $schoolId)) {
            return;
        }
        db()->prepare('DELETE FROM class_group_students WHERE class_group_id = ? AND student_id = ?')
            ->execute([$groupId, $studentId]);
    }

    public static function groupsWithOfferingsCount(int $schoolId): int
    {
        $stmt = db()->prepare('SELECT COUNT(DISTINCT class_group_id) FROM classes WHERE school_id = ?');
        $stmt->execute([$schoolId]);
        return (int) $stmt->fetchColumn();
    }

    public static function enrolledStudentsCount(int $schoolId): int
    {
        $stmt = db()->prepare('SELECT COUNT(DISTINCT student_id) FROM class_group_students cgs
            INNER JOIN class_groups g ON g.id = cgs.class_group_id
            WHERE g.school_id = ?');
        $stmt->execute([$schoolId]);
        return (int) $stmt->fetchColumn();
    }
}
