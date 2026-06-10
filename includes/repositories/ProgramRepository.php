<?php

class ProgramRepository
{
    public static function forSchool(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT * FROM programs WHERE school_id = ? ORDER BY name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function withCounts(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT p.*,
            (SELECT COUNT(*) FROM program_levels pl WHERE pl.program_id = p.id) AS level_count,
            (SELECT COUNT(*) FROM student_program_enrollments spe
                WHERE spe.program_id = p.id AND spe.status = \'active\') AS enrolled_count,
            (SELECT COUNT(*) FROM class_groups g WHERE g.program_id = p.id) AS group_count
            FROM programs p WHERE p.school_id = ? ORDER BY p.name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function get(int $programId, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM programs WHERE id = ? AND school_id = ?');
        $stmt->execute([$programId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    public static function count(int $schoolId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM programs WHERE school_id = ?');
        $stmt->execute([$schoolId]);
        return (int) $stmt->fetchColumn();
    }

    public static function create(int $schoolId, string $name, ?string $code, ?string $description): int
    {
        $stmt = db()->prepare('INSERT INTO programs (school_id, name, code, description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$schoolId, $name, $code ?: null, $description ?: null]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $programId, int $schoolId, string $name, ?string $code, ?string $description): bool
    {
        $stmt = db()->prepare('UPDATE programs SET name = ?, code = ?, description = ? WHERE id = ? AND school_id = ?');
        $stmt->execute([$name, $code ?: null, $description ?: null, $programId, $schoolId]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $programId, int $schoolId): array
    {
        if (!self::get($programId, $schoolId)) {
            return ['ok' => false, 'error' => 'Program not found.'];
        }

        $groups = db()->prepare('SELECT COUNT(*) FROM class_groups WHERE program_id = ?');
        $groups->execute([$programId]);
        if ((int) $groups->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Cannot delete a program linked to class groups.'];
        }

        $enrolled = db()->prepare('SELECT COUNT(*) FROM student_program_enrollments WHERE program_id = ? AND status = \'active\'');
        $enrolled->execute([$programId]);
        if ((int) $enrolled->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Cannot delete a program with active student enrollments.'];
        }

        db()->prepare('DELETE FROM programs WHERE id = ? AND school_id = ?')->execute([$programId, $schoolId]);
        return ['ok' => true];
    }

    public static function levelsForProgram(int $programId, int $schoolId): array
    {
        if (!self::get($programId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT * FROM program_levels WHERE program_id = ? ORDER BY level_order, id');
        $stmt->execute([$programId]);
        return $stmt->fetchAll();
    }

    public static function getLevel(int $levelId, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT pl.*, p.school_id, p.name AS program_name
            FROM program_levels pl
            INNER JOIN programs p ON p.id = pl.program_id
            WHERE pl.id = ? AND p.school_id = ?');
        $stmt->execute([$levelId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    public static function addLevel(int $programId, int $schoolId, string $name): ?int
    {
        if (!self::get($programId, $schoolId)) {
            return null;
        }
        $order = db()->prepare('SELECT COALESCE(MAX(level_order), 0) + 1 FROM program_levels WHERE program_id = ?');
        $order->execute([$programId]);
        $levelOrder = (int) $order->fetchColumn();

        $stmt = db()->prepare('INSERT INTO program_levels (program_id, name, level_order) VALUES (?, ?, ?)');
        $stmt->execute([$programId, $name, $levelOrder]);
        return (int) db()->lastInsertId();
    }

    public static function updateLevel(int $levelId, int $schoolId, string $name): bool
    {
        $level = self::getLevel($levelId, $schoolId);
        if (!$level) {
            return false;
        }
        db()->prepare('UPDATE program_levels SET name = ? WHERE id = ?')->execute([$name, $levelId]);
        return true;
    }

    public static function deleteLevel(int $levelId, int $schoolId): array
    {
        $level = self::getLevel($levelId, $schoolId);
        if (!$level) {
            return ['ok' => false, 'error' => 'Level not found.'];
        }

        $groups = db()->prepare('SELECT COUNT(*) FROM class_groups WHERE program_level_id = ?');
        $groups->execute([$levelId]);
        if ((int) $groups->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Cannot delete a level linked to class groups.'];
        }

        db()->prepare('DELETE FROM program_levels WHERE id = ?')->execute([$levelId]);
        return ['ok' => true];
    }

    public static function termsForLevel(int $levelId, int $schoolId): array
    {
        if (!self::getLevel($levelId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT * FROM program_terms WHERE program_level_id = ? ORDER BY term_order, id');
        $stmt->execute([$levelId]);
        return $stmt->fetchAll();
    }

    public static function getTerm(int $termId, int $schoolId): ?array
    {
        $stmt = db()->prepare('SELECT pt.*, pl.program_id, pl.name AS level_name, p.school_id
            FROM program_terms pt
            INNER JOIN program_levels pl ON pl.id = pt.program_level_id
            INNER JOIN programs p ON p.id = pl.program_id
            WHERE pt.id = ? AND p.school_id = ?');
        $stmt->execute([$termId, $schoolId]);
        return $stmt->fetch() ?: null;
    }

    public static function addTerm(int $levelId, int $schoolId, string $name): ?int
    {
        if (!self::getLevel($levelId, $schoolId)) {
            return null;
        }
        $order = db()->prepare('SELECT COALESCE(MAX(term_order), 0) + 1 FROM program_terms WHERE program_level_id = ?');
        $order->execute([$levelId]);
        $termOrder = (int) $order->fetchColumn();

        $stmt = db()->prepare('INSERT INTO program_terms (program_level_id, name, term_order) VALUES (?, ?, ?)');
        $stmt->execute([$levelId, $name, $termOrder]);
        return (int) db()->lastInsertId();
    }

    public static function updateTerm(int $termId, int $schoolId, string $name): bool
    {
        if (!self::getTerm($termId, $schoolId)) {
            return false;
        }
        db()->prepare('UPDATE program_terms SET name = ? WHERE id = ?')->execute([$name, $termId]);
        return true;
    }

    public static function deleteTerm(int $termId, int $schoolId): bool
    {
        if (!self::getTerm($termId, $schoolId)) {
            return false;
        }
        db()->prepare('DELETE FROM program_terms WHERE id = ?')->execute([$termId]);
        return true;
    }

    public static function subjectsForTerm(int $termId, int $schoolId): array
    {
        if (!self::getTerm($termId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT s.*, pts.sort_order
            FROM program_term_subjects pts
            INNER JOIN subjects s ON s.id = pts.subject_id
            WHERE pts.program_term_id = ?
            ORDER BY pts.sort_order, s.name');
        $stmt->execute([$termId]);
        return $stmt->fetchAll();
    }

    public static function syncTermSubjects(int $termId, int $schoolId, array $subjectIds): bool
    {
        if (!self::getTerm($termId, $schoolId)) {
            return false;
        }

        db()->prepare('DELETE FROM program_term_subjects WHERE program_term_id = ?')->execute([$termId]);

        if ($subjectIds === []) {
            return true;
        }

        $ins = db()->prepare('INSERT INTO program_term_subjects (program_term_id, subject_id, sort_order) VALUES (?, ?, ?)');
        $sort = 0;
        foreach ($subjectIds as $subjectId) {
            $subjectId = (int) $subjectId;
            if ($subjectId <= 0) {
                continue;
            }
            $check = db()->prepare('SELECT id FROM subjects WHERE id = ? AND school_id = ?');
            $check->execute([$subjectId, $schoolId]);
            if ($check->fetch()) {
                $ins->execute([$termId, $subjectId, $sort++]);
            }
        }
        return true;
    }

    public static function subjectsForLevel(int $levelId, int $schoolId): array
    {
        if (!self::getLevel($levelId, $schoolId)) {
            return [];
        }
        $stmt = db()->prepare('SELECT DISTINCT s.*
            FROM program_term_subjects pts
            INNER JOIN program_terms pt ON pt.id = pts.program_term_id
            INNER JOIN subjects s ON s.id = pts.subject_id
            WHERE pt.program_level_id = ?
            ORDER BY s.name');
        $stmt->execute([$levelId]);
        return $stmt->fetchAll();
    }

    public static function curriculumTree(int $programId, int $schoolId): array
    {
        $program = self::get($programId, $schoolId);
        if (!$program) {
            return [];
        }

        $levels = self::levelsForProgram($programId, $schoolId);
        foreach ($levels as &$level) {
            $level['terms'] = self::termsForLevel((int) $level['id'], $schoolId);
            foreach ($level['terms'] as &$term) {
                $term['subjects'] = self::subjectsForTerm((int) $term['id'], $schoolId);
            }
            unset($term);
        }
        unset($level);

        $program['levels'] = $levels;
        return $program;
    }

    public static function missingSubjectsForGroup(int $groupId, int $schoolId): array
    {
        $group = ClassGroupRepository::get($groupId, $schoolId);
        if (!$group || empty($group['program_level_id'])) {
            return [];
        }

        $required = self::subjectsForLevel((int) $group['program_level_id'], $schoolId);
        $offered = ClassGroupRepository::offerings($groupId, $schoolId);
        $offeredIds = array_map(static fn ($r) => (int) $r['subject_id'], $offered);

        return array_values(array_filter($required, static fn ($s) => !in_array((int) $s['id'], $offeredIds, true)));
    }

    public static function applyCurriculumForLevel(int $groupId, int $levelId, int $schoolId): int
    {
        $subjects = self::subjectsForLevel($levelId, $schoolId);
        $added = 0;
        foreach ($subjects as $subject) {
            if (ClassGroupRepository::addSubject($groupId, (int) $subject['id'], $schoolId)) {
                $added++;
            }
        }
        return $added;
    }

    public static function studentPrograms(int $studentId, int $schoolId): array
    {
        $stmt = db()->prepare('SELECT p.*, spe.status, spe.enrolled_at
            FROM student_program_enrollments spe
            INNER JOIN programs p ON p.id = spe.program_id
            WHERE spe.student_id = ? AND p.school_id = ?
            ORDER BY p.name');
        $stmt->execute([$studentId, $schoolId]);
        return $stmt->fetchAll();
    }

    public static function enrollStudent(int $studentId, int $programId, int $schoolId): bool
    {
        if (!self::get($programId, $schoolId)) {
            return false;
        }
        $check = db()->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND role = 'student' AND status = 'active'");
        $check->execute([$studentId, $schoolId]);
        if (!$check->fetch()) {
            return false;
        }
        db()->prepare('INSERT INTO student_program_enrollments (student_id, program_id, status) VALUES (?, ?, \'active\')
            ON DUPLICATE KEY UPDATE status = \'active\'')
            ->execute([$studentId, $programId]);
        return true;
    }

    public static function withdrawStudent(int $studentId, int $programId, int $schoolId): bool
    {
        if (!self::get($programId, $schoolId)) {
            return false;
        }
        db()->prepare('UPDATE student_program_enrollments SET status = \'withdrawn\'
            WHERE student_id = ? AND program_id = ?')
            ->execute([$studentId, $programId]);
        return true;
    }
}
