<?php

class CourseSectionRepository
{
    public static function forClass(int $classId): array
    {
        $stmt = db()->prepare('SELECT * FROM course_sections WHERE class_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }

    public static function get(int $sectionId, int $classId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM course_sections WHERE id = ? AND class_id = ?');
        $stmt->execute([$sectionId, $classId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $classId, string $title, ?string $description = null): int
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Section title is required.');
        }

        $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM course_sections WHERE class_id = ?');
        $stmt->execute([$classId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = db()->prepare('INSERT INTO course_sections (class_id, title, description, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$classId, $title, $description ?: null, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $sectionId, int $classId, string $title, ?string $description = null): bool
    {
        $title = trim($title);
        if ($title === '') {
            return false;
        }

        $stmt = db()->prepare('UPDATE course_sections SET title = ?, description = ? WHERE id = ? AND class_id = ?');
        $stmt->execute([$title, $description ?: null, $sectionId, $classId]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $sectionId, int $classId): bool
    {
        if (!self::get($sectionId, $classId)) {
            return false;
        }

        db()->prepare('UPDATE materials SET section_id = NULL WHERE section_id = ? AND class_id = ?')->execute([$sectionId, $classId]);
        db()->prepare('UPDATE assignments SET section_id = NULL WHERE section_id = ? AND class_id = ?')->execute([$sectionId, $classId]);
        db()->prepare('UPDATE quizzes SET section_id = NULL WHERE section_id = ? AND class_id = ?')->execute([$sectionId, $classId]);
        db()->prepare('DELETE FROM course_sections WHERE id = ? AND class_id = ?')->execute([$sectionId, $classId]);
        return true;
    }

    public static function resolveSectionId(?int $sectionId, int $classId): ?int
    {
        if (!$sectionId) {
            return null;
        }
        return self::get($sectionId, $classId) ? $sectionId : null;
    }

    public static function moveItem(string $type, int $itemId, int $classId, ?int $sectionId): bool
    {
        $table = match ($type) {
            'material' => 'materials',
            'assignment' => 'assignments',
            'quiz' => 'quizzes',
            default => null,
        };
        if (!$table) {
            return false;
        }

        $resolvedSection = self::resolveSectionId($sectionId, $classId);
        if ($sectionId && $resolvedSection === null) {
            return false;
        }

        $stmt = db()->prepare("UPDATE {$table} SET section_id = ? WHERE id = ? AND class_id = ?");
        $stmt->execute([$resolvedSection, $itemId, $classId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array{
     *   sections: list<array<string, mixed>>,
     *   uncategorized: list<array{type: string, item: array}>,
     *   activity_count: int,
     *   material_count: int,
     *   assignment_count: int,
     *   quiz_count: int
     * }
     */
    public static function loadCourseContent(int $classId, ?int $studentId = null, ?int $teacherId = null): array
    {
        $sections = self::forClass($classId);
        $sectionMap = [];
        foreach ($sections as $section) {
            $sectionMap[(int) $section['id']] = [
                'id' => (int) $section['id'],
                'title' => $section['title'],
                'description' => $section['description'],
                'sort_order' => (int) $section['sort_order'],
                'activities' => [],
            ];
        }

        $uncategorized = [];
        $materialCount = 0;
        $assignmentCount = 0;
        $quizCount = 0;

        if ($studentId) {
            $stmt = db()->prepare('SELECT m.*, u.first_name AS teacher_first, u.last_name AS teacher_last
                FROM materials m
                INNER JOIN users u ON u.id = m.teacher_id
                WHERE m.class_id = ?
                ORDER BY m.created_at ASC');
            $stmt->execute([$classId]);
            $materials = $stmt->fetchAll();

            $stmt = db()->prepare('SELECT a.*,
                (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) AS my_status,
                (SELECT grade FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) AS my_grade
                FROM assignments a WHERE a.class_id = ? ORDER BY a.created_at ASC');
            $stmt->execute([$studentId, $studentId, $classId]);
            $assignments = $stmt->fetchAll();

            $stmt = db()->prepare('SELECT q.*,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
                (SELECT status FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? ORDER BY id DESC LIMIT 1) AS my_attempt_status
                FROM quizzes q WHERE q.class_id = ? ORDER BY q.created_at ASC');
            $stmt->execute([$studentId, $classId]);
            $quizzes = $stmt->fetchAll();
        } else {
            $teacherFilter = $teacherId ? ' AND teacher_id = ?' : '';
            $params = [$classId];
            if ($teacherId) {
                $params[] = $teacherId;
            }

            $stmt = db()->prepare('SELECT * FROM materials WHERE class_id = ?' . $teacherFilter . ' ORDER BY created_at ASC');
            $stmt->execute($params);
            $materials = $stmt->fetchAll();

            $stmt = db()->prepare('SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count
                FROM assignments a WHERE a.class_id = ?' . $teacherFilter . ' ORDER BY a.created_at ASC');
            $stmt->execute($params);
            $assignments = $stmt->fetchAll();

            $stmt = db()->prepare('SELECT q.*,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
                (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND status != ?) AS attempt_count
                FROM quizzes q WHERE q.class_id = ?' . $teacherFilter . ' ORDER BY q.created_at ASC');
            $stmt->execute(array_merge(['in_progress'], $params));
            $quizzes = $stmt->fetchAll();
        }

        $addActivity = function (string $type, array $item) use (&$sectionMap, &$uncategorized, &$materialCount, &$assignmentCount, &$quizCount): void {
            if ($type === 'material') {
                $materialCount++;
            } elseif ($type === 'assignment') {
                $assignmentCount++;
            } else {
                $quizCount++;
            }

            $activity = ['type' => $type, 'item' => $item, 'sort' => strtotime($item['created_at']) ?: 0];
            $sectionId = !empty($item['section_id']) ? (int) $item['section_id'] : 0;
            if ($sectionId && isset($sectionMap[$sectionId])) {
                $sectionMap[$sectionId]['activities'][] = $activity;
            } else {
                $uncategorized[] = $activity;
            }
        };

        foreach ($materials as $m) {
            $addActivity('material', $m);
        }
        foreach ($assignments as $a) {
            $addActivity('assignment', $a);
        }
        foreach ($quizzes as $q) {
            $addActivity('quiz', $q);
        }

        $orderedSections = array_values($sectionMap);

        return [
            'sections' => $orderedSections,
            'uncategorized' => $uncategorized,
            'activity_count' => $materialCount + $assignmentCount + $quizCount,
            'material_count' => $materialCount,
            'assignment_count' => $assignmentCount,
            'quiz_count' => $quizCount,
        ];
    }
}
