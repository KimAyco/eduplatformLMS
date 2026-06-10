<?php

class ClassProgressRepository
{
    /**
     * @param list<array<string, mixed>> $students
     * @return array<int, array<string, mixed>>
     */
    public static function rosterSummaryForClass(int $classId, int $subjectId, int $schoolId, array $students): array
    {
        if ($students === []) {
            return [];
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM assignments WHERE class_id = ?');
        $stmt->execute([$classId]);
        $assignTotal = (int) $stmt->fetchColumn();

        $stmt = db()->prepare('SELECT COUNT(*) FROM quizzes WHERE class_id = ? AND is_published = 1');
        $stmt->execute([$classId]);
        $quizTotal = (int) $stmt->fetchColumn();

        $gradableTotal = $assignTotal + $quizTotal;

        $assignByStudent = [];
        $stmt = db()->prepare("SELECT s.student_id,
                COUNT(*) AS submitted,
                SUM(CASE WHEN s.status = 'graded' THEN 1 ELSE 0 END) AS graded
            FROM assignment_submissions s
            INNER JOIN assignments a ON a.id = s.assignment_id
            WHERE a.class_id = ?
            GROUP BY s.student_id");
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll() as $row) {
            $assignByStudent[(int) $row['student_id']] = $row;
        }

        $quizByStudent = [];
        $stmt = db()->prepare("SELECT qa.student_id,
                COUNT(DISTINCT qa.quiz_id) AS completed,
                COUNT(DISTINCT CASE WHEN qa.status = 'graded' THEN qa.quiz_id END) AS graded
            FROM quiz_attempts qa
            INNER JOIN quizzes q ON q.id = qa.quiz_id
            WHERE q.class_id = ? AND q.is_published = 1 AND qa.status != 'in_progress'
            GROUP BY qa.student_id");
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll() as $row) {
            $quizByStudent[(int) $row['student_id']] = $row;
        }

        $components = GradebookRepository::componentsForSubject($subjectId, $schoolId);
        $cells = GradebookRepository::gradeCellsForClass($classId);

        $summary = [];
        foreach ($students as $student) {
            $studentId = (int) $student['id'];
            $assignRow = $assignByStudent[$studentId] ?? null;
            $quizRow = $quizByStudent[$studentId] ?? null;

            $assignSubmitted = (int) ($assignRow['submitted'] ?? 0);
            $assignGraded = (int) ($assignRow['graded'] ?? 0);
            $quizCompleted = (int) ($quizRow['completed'] ?? 0);
            $quizGraded = (int) ($quizRow['graded'] ?? 0);

            $completedActivities = $assignSubmitted + $quizCompleted;
            $activityPercent = $gradableTotal > 0
                ? (int) round(min(100, ($completedActivities / $gradableTotal) * 100))
                : null;

            $pending = max(0, $assignTotal - $assignSubmitted) + max(0, $quizTotal - $quizCompleted);
            $gradePercent = $components !== []
                ? GradebookRepository::computeFinalPercent($components, $cells, $studentId)
                : null;

            $summary[$studentId] = [
                'assignments_total' => $assignTotal,
                'assignments_submitted' => $assignSubmitted,
                'assignments_graded' => $assignGraded,
                'quizzes_total' => $quizTotal,
                'quizzes_completed' => $quizCompleted,
                'quizzes_graded' => $quizGraded,
                'gradable_total' => $gradableTotal,
                'completed_activities' => $completedActivities,
                'activity_percent' => $activityPercent,
                'pending_count' => $pending,
                'grade_percent' => $gradePercent,
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public static function studentActivityProgress(int $classId, int $studentId): array
    {
        $content = CourseSectionRepository::loadCourseContent($classId, $studentId);

        $assignments = [];
        $quizzes = [];
        $materialCount = 0;

        $collect = static function (array $activities) use (&$assignments, &$quizzes, &$materialCount): void {
            foreach ($activities as $activity) {
                if ($activity['type'] === 'material') {
                    $materialCount++;
                    continue;
                }
                if ($activity['type'] === 'assignment') {
                    $assignments[] = $activity['item'];
                    continue;
                }
                if ($activity['type'] === 'quiz') {
                    $quizzes[] = $activity['item'];
                }
            }
        };

        foreach ($content['sections'] as $section) {
            $collect($section['activities']);
        }
        $collect($content['uncategorized']);

        $assignSubmitted = 0;
        $assignGraded = 0;
        foreach ($assignments as $assignment) {
            $status = $assignment['my_status'] ?? null;
            if ($status) {
                $assignSubmitted++;
                if ($status === 'graded') {
                    $assignGraded++;
                }
            }
        }

        $quizCompleted = 0;
        $quizGraded = 0;
        foreach ($quizzes as $quiz) {
            if (empty($quiz['is_published'])) {
                continue;
            }
            $status = $quiz['my_attempt_status'] ?? null;
            if ($status && $status !== 'in_progress') {
                $quizCompleted++;
                if ($status === 'graded') {
                    $quizGraded++;
                }
            }
        }

        $assignTotal = count($assignments);
        $quizTotal = count(array_filter($quizzes, static fn ($q) => !empty($q['is_published'])));
        $gradableTotal = $assignTotal + $quizTotal;
        $completedActivities = $assignSubmitted + $quizCompleted;

        return [
            'material_count' => $materialCount,
            'assignments' => $assignments,
            'quizzes' => $quizzes,
            'assignments_total' => $assignTotal,
            'assignments_submitted' => $assignSubmitted,
            'assignments_graded' => $assignGraded,
            'quizzes_total' => $quizTotal,
            'quizzes_completed' => $quizCompleted,
            'quizzes_graded' => $quizGraded,
            'gradable_total' => $gradableTotal,
            'completed_activities' => $completedActivities,
            'activity_percent' => $gradableTotal > 0
                ? (int) round(min(100, ($completedActivities / $gradableTotal) * 100))
                : null,
            'pending_count' => max(0, $assignTotal - $assignSubmitted) + max(0, $quizTotal - $quizCompleted),
        ];
    }

    public static function assignmentStatusLabel(?string $status): string
    {
        return match ($status) {
            'graded' => 'Graded',
            'submitted' => 'Submitted',
            'returned' => 'Returned',
            default => 'Not submitted',
        };
    }

    public static function quizStatusLabel(?string $status, bool $published): string
    {
        if (!$published) {
            return 'Draft';
        }
        return match ($status) {
            'graded' => 'Graded',
            'submitted' => 'Submitted',
            'in_progress' => 'In progress',
            default => 'Not started',
        };
    }

    public static function statusTone(?string $status, string $kind = 'assignment'): string
    {
        if ($kind === 'quiz' && $status === 'in_progress') {
            return 'partial';
        }
        return match ($status) {
            'graded' => 'full',
            'submitted' => 'partial',
            'returned' => 'partial',
            'in_progress' => 'partial',
            default => 'empty',
        };
    }
}
