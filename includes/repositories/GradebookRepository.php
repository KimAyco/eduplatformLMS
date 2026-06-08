<?php

class GradebookRepository
{
    public const CATEGORIES = [
        'quiz' => 'Quiz',
        'exam' => 'Exam',
        'assignment' => 'Assignment',
        'participation' => 'Participation',
        'project' => 'Project',
        'other' => 'Other',
    ];

    public const AUTO_SYNC_CATEGORIES = ['quiz', 'exam', 'assignment'];

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category] ?? ucfirst($category);
    }

    public static function isManualCategory(string $category): bool
    {
        return !in_array($category, self::AUTO_SYNC_CATEGORIES, true);
    }

    /** @return list<array<string, mixed>> */
    public static function componentsForSubject(int $subjectId, int $schoolId): array
    {
        $stmt = db()->prepare('SELECT * FROM subject_grading_components
            WHERE subject_id = ? AND school_id = ?
            ORDER BY sort_order, id');
        $stmt->execute([$subjectId, $schoolId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public static function componentsWithSchemeStats(int $schoolId): array
    {
        $stmt = db()->prepare('SELECT s.id, s.name,
            (SELECT COUNT(*) FROM subject_grading_components c WHERE c.subject_id = s.id) AS component_count,
            (SELECT COALESCE(SUM(weight_percent), 0) FROM subject_grading_components c WHERE c.subject_id = s.id) AS total_weight
            FROM subjects s
            WHERE s.school_id = ?
            ORDER BY s.name');
        $stmt->execute([$schoolId]);
        return $stmt->fetchAll();
    }

    public static function totalWeightForSubject(int $subjectId, int $schoolId): float
    {
        $stmt = db()->prepare('SELECT COALESCE(SUM(weight_percent), 0) FROM subject_grading_components WHERE subject_id = ? AND school_id = ?');
        $stmt->execute([$subjectId, $schoolId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * @param list<array{category: string, label: string, weight_percent: float|int|string}> $rows
     */
    public static function saveSubjectScheme(int $subjectId, int $schoolId, array $rows): void
    {
        db()->prepare('DELETE FROM subject_grading_components WHERE subject_id = ? AND school_id = ?')
            ->execute([$subjectId, $schoolId]);

        if ($rows === []) {
            return;
        }

        $stmt = db()->prepare('INSERT INTO subject_grading_components
            (subject_id, school_id, category, label, weight_percent, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)');

        foreach ($rows as $i => $row) {
            $stmt->execute([
                $subjectId,
                $schoolId,
                $row['category'],
                $row['label'],
                $row['weight_percent'],
                $i,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> keyed by component_id */
    public static function linksForClass(int $classId): array
    {
        $stmt = db()->prepare('SELECT * FROM class_grading_links WHERE class_id = ?');
        $stmt->execute([$classId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['component_id']] = $row;
        }
        return $map;
    }

    /**
     * @param array<int, array{quiz_id?: int|null, assignment_id?: int|null}> $linksByComponentId
     */
    public static function saveClassLinks(int $classId, array $linksByComponentId): void
    {
        db()->prepare('DELETE FROM class_grading_links WHERE class_id = ?')->execute([$classId]);

        if ($linksByComponentId === []) {
            return;
        }

        $stmt = db()->prepare('INSERT INTO class_grading_links (class_id, component_id, quiz_id, assignment_id)
            VALUES (?, ?, ?, ?)');

        foreach ($linksByComponentId as $componentId => $link) {
            $quizId = !empty($link['quiz_id']) ? (int) $link['quiz_id'] : null;
            $assignmentId = !empty($link['assignment_id']) ? (int) $link['assignment_id'] : null;
            if ($quizId === null && $assignmentId === null) {
                continue;
            }
            $stmt->execute([$classId, (int) $componentId, $quizId, $assignmentId]);
        }
    }

    public static function linkByQuizId(int $quizId): ?array
    {
        $stmt = db()->prepare('SELECT l.*, c.category, c.label, c.weight_percent
            FROM class_grading_links l
            INNER JOIN subject_grading_components c ON c.id = l.component_id
            WHERE l.quiz_id = ? LIMIT 1');
        $stmt->execute([$quizId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function linkByAssignmentId(int $assignmentId): ?array
    {
        $stmt = db()->prepare('SELECT l.*, c.category, c.label, c.weight_percent
            FROM class_grading_links l
            INNER JOIN subject_grading_components c ON c.id = l.component_id
            WHERE l.assignment_id = ? LIMIT 1');
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsertStudentGrade(
        int $classId,
        int $studentId,
        int $componentId,
        ?float $score,
        ?float $maxScore,
        ?float $percent,
        bool $isManual,
        ?int $quizAttemptId = null,
        ?int $submissionId = null
    ): void {
        db()->prepare('INSERT INTO student_component_grades
            (class_id, student_id, component_id, score, max_score, percent, source_quiz_attempt_id, source_submission_id, is_manual)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                max_score = VALUES(max_score),
                percent = VALUES(percent),
                source_quiz_attempt_id = VALUES(source_quiz_attempt_id),
                source_submission_id = VALUES(source_submission_id),
                is_manual = VALUES(is_manual),
                updated_at = CURRENT_TIMESTAMP')
            ->execute([
                $classId,
                $studentId,
                $componentId,
                $score,
                $maxScore,
                $percent,
                $quizAttemptId,
                $submissionId,
                $isManual ? 1 : 0,
            ]);
    }

    public static function saveManualGrade(
        int $classId,
        int $studentId,
        int $componentId,
        float $percent
    ): void {
        $percent = max(0, min(100, round($percent, 2)));
        self::upsertStudentGrade($classId, $studentId, $componentId, $percent, 100.0, $percent, true);
    }

    public static function getGradeCell(int $classId, int $studentId, int $componentId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM student_component_grades
            WHERE class_id = ? AND student_id = ? AND component_id = ? LIMIT 1');
        $stmt->execute([$classId, $studentId, $componentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function isManualOverride(int $classId, int $studentId, int $componentId): bool
    {
        $cell = self::getGradeCell($classId, $studentId, $componentId);
        return $cell !== null && (int) $cell['is_manual'] === 1;
    }

    public static function deleteGrade(int $classId, int $studentId, int $componentId): void
    {
        db()->prepare('DELETE FROM student_component_grades WHERE class_id = ? AND student_id = ? AND component_id = ?')
            ->execute([$classId, $studentId, $componentId]);
    }

    /** @return array<int, array<int, array<string, mixed>>> [student_id][component_id] */
    public static function gradeCellsForClass(int $classId): array
    {
        $stmt = db()->prepare('SELECT * FROM student_component_grades WHERE class_id = ?');
        $stmt->execute([$classId]);
        $grid = [];
        foreach ($stmt->fetchAll() as $row) {
            $grid[(int) $row['student_id']][(int) $row['component_id']] = $row;
        }
        return $grid;
    }

    /**
     * @param list<array<string, mixed>> $components
     * @param array<int, array<int, array<string, mixed>>> $cells
     */
    public static function computeFinalPercent(array $components, array $cells, int $studentId): ?float
    {
        if ($components === []) {
            return null;
        }

        $total = 0.0;
        $hasAny = false;

        foreach ($components as $component) {
            $componentId = (int) $component['id'];
            $weight = (float) $component['weight_percent'];
            $cell = $cells[$studentId][$componentId] ?? null;
            if ($cell === null || $cell['percent'] === null || $cell['percent'] === '') {
                continue;
            }
            $hasAny = true;
            $total += ((float) $cell['percent']) * ($weight / 100);
        }

        return $hasAny ? round($total, 2) : null;
    }

    /**
     * @param list<array<string, mixed>> $students
     * @param list<array<string, mixed>> $components
     * @return array<string, mixed>
     */
    public static function gradebookForClass(int $classId, array $students, array $components): array
    {
        $links = self::linksForClass($classId);
        $cells = self::gradeCellsForClass($classId);
        $rows = [];

        foreach ($students as $student) {
            $studentId = (int) $student['id'];
            $final = self::computeFinalPercent($components, $cells, $studentId);
            $rows[] = [
                'student' => $student,
                'cells' => $cells[$studentId] ?? [],
                'final_percent' => $final,
            ];
        }

        return [
            'components' => $components,
            'links' => $links,
            'rows' => $rows,
        ];
    }
}
