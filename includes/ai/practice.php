<?php

class PracticeQuizService
{
    public static function getBank(int $classId, ?int $sectionId, bool $courseWide = false): ?array
    {
        $sectionVal = ($sectionId === null || $sectionId === 0) ? null : $sectionId;
        $stmt = db()->prepare('SELECT * FROM practice_question_bank WHERE class_id = ? AND is_course_wide = ? AND '
            . ($sectionVal === null ? 'section_id IS NULL' : 'section_id = ?'));
        $params = [$classId, $courseWide ? 1 : 0];
        if ($sectionVal !== null) {
            $params[] = $sectionVal;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        if ($row && is_string($row['question_json'])) {
            $row['question_json'] = json_decode($row['question_json'], true);
        }
        return $row ?: null;
    }

    public static function saveBank(int $classId, ?int $sectionId, bool $courseWide, string $contextVersion, array $questions, int $quizId): void
    {
        $sectionVal = ($sectionId === null || $sectionId === 0) ? null : $sectionId;
        $json = json_encode($questions, JSON_UNESCAPED_UNICODE);
        $existing = self::getBank($classId, $sectionVal, $courseWide);

        if ($existing) {
            db()->prepare('UPDATE practice_question_bank SET question_json = ?, context_version = ?, item_count = ?, quiz_id = ?, updated_at = UTC_TIMESTAMP()
                WHERE id = ?')
                ->execute([$json, $contextVersion, count($questions), $quizId, (int) $existing['id']]);
        } else {
            db()->prepare('INSERT INTO practice_question_bank (class_id, section_id, is_course_wide, quiz_id, question_json, context_version, item_count)
                VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$classId, $sectionVal, $courseWide ? 1 : 0, $quizId, $json, $contextVersion, count($questions)]);
        }
    }

    public static function upsertPracticeQuiz(int $classId, ?int $sectionId, bool $courseWide, string $contextVersion, array $questions): int
    {
        $sectionVal = ($sectionId === null || $sectionId === 0) ? null : $sectionId;
        $bank = self::getBank($classId, $sectionVal, $courseWide);
        if ($bank && !empty($bank['quiz_id'])) {
            $quizId = (int) $bank['quiz_id'];
            self::replaceQuizQuestions($quizId, $questions);
            db()->prepare('UPDATE quizzes SET context_version = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$contextVersion, $quizId]);
            return $quizId;
        }

        $teacher = ClassRepository::getAssignedTeacher($classId);
        $teacherId = (int) ($teacher['id'] ?? 0);
        if ($teacherId <= 0) {
            $t = db()->prepare('SELECT teacher_id FROM materials WHERE class_id = ? LIMIT 1');
            $t->execute([$classId]);
            $teacherId = (int) ($t->fetchColumn() ?: 0);
        }
        if ($teacherId <= 0) {
            $t = db()->prepare('SELECT teacher_id FROM quizzes WHERE class_id = ? LIMIT 1');
            $t->execute([$classId]);
            $teacherId = (int) ($t->fetchColumn() ?: 1);
        }

        if ($courseWide) {
            $sectionTitle = 'All lessons';
        } elseif ($sectionVal) {
            $s = db()->prepare('SELECT title FROM course_sections WHERE id = ?');
            $s->execute([$sectionVal]);
            $sectionTitle = (string) ($s->fetchColumn() ?: 'Lesson');
        } else {
            $sectionTitle = 'Unassigned';
        }

        $title = 'Practice: ' . $sectionTitle;
        $hasCourseWideCol = self::quizzesHaveCourseWideColumn();
        if ($hasCourseWideCol) {
            db()->prepare("INSERT INTO quizzes (class_id, section_id, teacher_id, title, instructions, is_published, show_score_to_students,
                max_attempts, quiz_mode, source_section_id, is_course_wide, context_version, is_ai_generated, counts_toward_gradebook)
                VALUES (?, ?, ?, ?, ?, 1, 1, 999, 'practice', ?, ?, ?, 1, 0)")
                ->execute([
                    $classId,
                    $sectionVal,
                    $teacherId,
                    $title,
                    'AI-generated practice quiz. Scores are for self-study only and do not affect your grade.',
                    $sectionVal,
                    $courseWide ? 1 : 0,
                    $contextVersion,
                ]);
        } else {
            db()->prepare("INSERT INTO quizzes (class_id, section_id, teacher_id, title, instructions, is_published, show_score_to_students,
                max_attempts, quiz_mode, source_section_id, context_version, is_ai_generated, counts_toward_gradebook)
                VALUES (?, ?, ?, ?, ?, 1, 1, 999, 'practice', ?, ?, 1, 0)")
                ->execute([
                    $classId,
                    $sectionVal,
                    $teacherId,
                    $title,
                    'AI-generated practice quiz. Scores are for self-study only and do not affect your grade.',
                    $sectionVal,
                    $contextVersion,
                ]);
        }
        $quizId = (int) db()->lastInsertId();
        self::replaceQuizQuestions($quizId, $questions);
        return $quizId;
    }

    private static function quizzesHaveCourseWideColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $has = (bool) db()->query("SHOW COLUMNS FROM quizzes LIKE 'is_course_wide'")->fetch();
        } catch (PDOException) {
            $has = false;
        }
        return $has;
    }

    public static function createExamQuizFromAi(int $classId, int $teacherId, ?int $sectionId, string $title, array $questions): int
    {
        db()->prepare("INSERT INTO quizzes (class_id, section_id, teacher_id, title, instructions, is_published, show_score_to_students,
            max_attempts, quiz_mode, is_ai_generated, counts_toward_gradebook)
            VALUES (?, ?, ?, ?, ?, 0, 1, 1, 'exam', 1, 1)")
            ->execute([
                $classId,
                $sectionId,
                $teacherId,
                $title !== '' ? $title : 'AI Generated Quiz',
                'Review and edit questions before publishing.',
            ]);
        $quizId = (int) db()->lastInsertId();
        self::replaceQuizQuestions($quizId, $questions);
        return $quizId;
    }

    public static function replaceQuizQuestions(int $quizId, array $questions): void
    {
        $existing = db()->prepare('SELECT id FROM quiz_questions WHERE quiz_id = ?');
        $existing->execute([$quizId]);
        foreach ($existing->fetchAll() as $row) {
            db()->prepare('DELETE FROM quiz_options WHERE question_id = ?')->execute([(int) $row['id']]);
        }
        db()->prepare('DELETE FROM quiz_questions WHERE quiz_id = ?')->execute([$quizId]);

        $sort = 0;
        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $type = normalizeQuestionType((string) ($q['type'] ?? 'multiple_choice'));
            $settings = $q['settings'] ?? [];
            if (!is_array($settings)) {
                $settings = [];
            }
            if ($type === 'fill_blank' && empty($settings['blanks']) && !empty($q['correct_answer'])) {
                $settings['blanks'] = [['answers' => [(string) $q['correct_answer']]]];
            }
            db()->prepare('INSERT INTO quiz_questions (quiz_id, type, question_text, points, sort_order, settings, correct_answer)
                VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $quizId,
                    $type,
                    (string) ($q['question_text'] ?? 'Question'),
                    (float) ($q['points'] ?? 1),
                    $sort++,
                    json_encode($settings, JSON_UNESCAPED_UNICODE),
                    $q['correct_answer'] ?? null,
                ]);
            $questionId = (int) db()->lastInsertId();
            $options = $q['options'] ?? [];
            if (is_array($options) && $options !== []) {
                $optStmt = db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
                foreach ($options as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $optStmt->execute([
                        $questionId,
                        (string) ($opt['text'] ?? $opt['option_text'] ?? ''),
                        !empty($opt['is_correct']) ? 1 : 0,
                    ]);
                }
            } elseif ($type === 'true_false') {
                $optStmt = db()->prepare('INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
                $optStmt->execute([$questionId, 'True', 1]);
                $optStmt->execute([$questionId, 'False', 0]);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function ensurePracticeQuizForStudent(
        int $classId,
        ?int $sectionId,
        int $studentId,
        bool $courseWide = false,
        array $config = []
    ): array {
        requireClassAccess($classId, 'student');
        requireSchoolPracticeQuizzes();

        if (!aiPracticeTablesReady()) {
            throw new RuntimeException('Practice quizzes are not set up yet. Please ask your administrator to run database migrations.');
        }

        $sessionConfig = parsePracticeSessionConfig($config);

        $context = LessonContextService::ensureIndexed($classId, $sectionId, $courseWide);
        if (($context['status'] ?? '') === 'empty' || empty($context['context_text'])) {
            throw new RuntimeException('No lesson materials available yet for this practice quiz. Check back after your teacher adds content.');
        }

        $sectionVal = ($sectionId === null || $sectionId === 0) ? null : $sectionId;
        $bank = self::getBank($classId, $sectionVal, $courseWide);

        if (!$bank || ($bank['context_version'] ?? '') !== ($context['sources_hash'] ?? '')) {
            if (!aiIsEnabled()) {
                throw new RuntimeException('Practice questions are being prepared. Please try again later.');
            }
            $jobId = aiEnqueueJob('build_practice_bank', [
                'class_id' => $classId,
                'section_id' => $sectionVal,
                'is_course_wide' => $courseWide ? 1 : 0,
                'context_version' => $context['sources_hash'] ?? '',
                'item_count' => $sessionConfig['item_count'],
                'question_types' => $sessionConfig['question_types'],
                'difficulty' => $sessionConfig['difficulty'],
            ], $studentId, schoolId(), 'Practice bank for class ' . $classId);

            aiProcessPendingJobs(1);

            $job = AiQueueRepository::findById($jobId);
            if ($job && $job['status'] === 'completed') {
                $bank = self::getBank($classId, $sectionVal, $courseWide);
                if ($bank && is_array($bank['question_json'] ?? null)) {
                    return self::buildSessionQuizFromBank($classId, $sectionVal, $courseWide, $studentId, $bank['question_json'], $sessionConfig);
                }
                throw new RuntimeException('Practice quiz could not be created.');
            }
            if ($job && $job['status'] === 'failed') {
                throw new RuntimeException($job['error_message'] ?? 'Failed to generate practice quiz.');
            }
            return ['pending_job_id' => $jobId, 'status' => 'pending'];
        }

        $questions = $bank['question_json'] ?? [];
        if (!is_array($questions) || $questions === []) {
            throw new RuntimeException('Practice quiz not available yet.');
        }

        return self::buildSessionQuizFromBank($classId, $sectionVal, $courseWide, $studentId, $questions, $sessionConfig);
    }

    /**
     * @param list<array<string, mixed>> $questions
     * @param array{item_count: int, question_types: list<string>, difficulty: string} $config
     */
    public static function pickSessionQuestions(array $questions, array $config): array
    {
        $types = $config['question_types'];
        $filtered = array_values(array_filter($questions, static function ($q) use ($types) {
            if (!is_array($q)) {
                return false;
            }
            $type = normalizeQuestionType((string) ($q['type'] ?? 'multiple_choice'));
            return in_array($type, $types, true);
        }));

        shuffle($filtered);
        $picked = array_slice($filtered, 0, $config['item_count']);
        $minNeeded = min(3, $config['item_count']);
        if (count($picked) < $minNeeded) {
            throw new RuntimeException('Not enough practice questions for the selected types. Try including more question types.');
        }

        return $picked;
    }

    /**
     * @param list<array<string, mixed>> $bankQuestions
     * @param array{item_count: int, question_types: list<string>, difficulty: string} $config
     */
    public static function buildSessionQuizFromBank(
        int $classId,
        ?int $sectionId,
        bool $courseWide,
        int $studentId,
        array $bankQuestions,
        array $config
    ): array {
        $sessionQuestions = self::pickSessionQuestions($bankQuestions, $config);
        $quizId = self::createSessionQuiz($classId, $sectionId, $courseWide, $studentId, $sessionQuestions);
        $quiz = QuizRepository::getQuizById($quizId, $classId);
        if (!$quiz) {
            throw new RuntimeException('Practice quiz not found.');
        }
        return $quiz;
    }

    /**
     * @param list<array<string, mixed>> $questions
     */
    public static function createSessionQuiz(int $classId, ?int $sectionId, bool $courseWide, int $studentId, array $questions): int
    {
        $sectionVal = ($sectionId === null || $sectionId === 0) ? null : $sectionId;
        $teacher = ClassRepository::getAssignedTeacher($classId);
        $teacherId = (int) ($teacher['id'] ?? 0);
        if ($teacherId <= 0) {
            $t = db()->prepare('SELECT teacher_id FROM materials WHERE class_id = ? LIMIT 1');
            $t->execute([$classId]);
            $teacherId = (int) ($t->fetchColumn() ?: 1);
        }

        if ($courseWide) {
            $sectionTitle = 'All lessons';
        } elseif ($sectionVal) {
            $s = db()->prepare('SELECT title FROM course_sections WHERE id = ?');
            $s->execute([$sectionVal]);
            $sectionTitle = (string) ($s->fetchColumn() ?: 'Lesson');
        } else {
            $sectionTitle = 'Unassigned';
        }

        $title = 'Practice session: ' . $sectionTitle;
        $hasCourseWideCol = self::quizzesHaveCourseWideColumn();
        if ($hasCourseWideCol) {
            db()->prepare("INSERT INTO quizzes (class_id, section_id, teacher_id, title, instructions, is_published, show_score_to_students,
                max_attempts, quiz_mode, source_section_id, is_course_wide, is_ai_generated, counts_toward_gradebook)
                VALUES (?, ?, ?, ?, ?, 1, 1, 1, 'practice', ?, ?, 1, 0)")
                ->execute([
                    $classId,
                    $sectionVal,
                    $teacherId,
                    $title,
                    'Self-study practice session. This score does not affect your grade.',
                    $sectionVal,
                    $courseWide ? 1 : 0,
                ]);
        } else {
            db()->prepare("INSERT INTO quizzes (class_id, section_id, teacher_id, title, instructions, is_published, show_score_to_students,
                max_attempts, quiz_mode, source_section_id, is_ai_generated, counts_toward_gradebook)
                VALUES (?, ?, ?, ?, ?, 1, 1, 1, 'practice', ?, 1, 0)")
                ->execute([
                    $classId,
                    $sectionVal,
                    $teacherId,
                    $title,
                    'Self-study practice session. This score does not affect your grade.',
                    $sectionVal,
                ]);
        }
        $quizId = (int) db()->lastInsertId();
        self::replaceQuizQuestions($quizId, $questions);
        return $quizId;
    }

    public static function recordProficiency(int $studentId, int $classId, ?int $sectionId, float $scorePct, bool $courseWide = false): void
    {
        $sectionVal = ($sectionId === null || $sectionId === 0) ? null : $sectionId;
        $hasWide = self::proficiencyHasCourseWideColumn();

        if ($hasWide) {
            $stmt = db()->prepare('SELECT * FROM student_lesson_proficiency WHERE student_id = ? AND class_id = ? AND is_course_wide = ? AND '
                . ($sectionVal === null ? 'section_id IS NULL' : 'section_id = ?'));
            $params = [$studentId, $classId, $courseWide ? 1 : 0];
            if ($sectionVal !== null) {
                $params[] = $sectionVal;
            }
        } else {
            $stmt = db()->prepare('SELECT * FROM student_lesson_proficiency WHERE student_id = ? AND class_id = ? AND '
                . ($sectionVal === null ? 'section_id IS NULL' : 'section_id = ?'));
            $params = [$studentId, $classId];
            if ($sectionVal !== null) {
                $params[] = $sectionVal;
            }
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        if ($row) {
            $attempts = (int) $row['attempts'] + 1;
            $best = max((float) ($row['best_score_pct'] ?? 0), $scorePct);
            $avg = (((float) ($row['avg_score_pct'] ?? 0)) * (int) $row['attempts'] + $scorePct) / $attempts;
            $level = self::proficiencyLevel($avg);
            db()->prepare('UPDATE student_lesson_proficiency SET attempts = ?, best_score_pct = ?, avg_score_pct = ?,
                last_attempt_at = UTC_TIMESTAMP(), proficiency_level = ? WHERE id = ?')
                ->execute([$attempts, round($best, 2), round($avg, 2), $level, (int) $row['id']]);
        } elseif ($hasWide) {
            $level = self::proficiencyLevel($scorePct);
            db()->prepare('INSERT INTO student_lesson_proficiency (student_id, class_id, section_id, is_course_wide, attempts, best_score_pct, avg_score_pct, last_attempt_at, proficiency_level)
                VALUES (?, ?, ?, ?, 1, ?, ?, UTC_TIMESTAMP(), ?)')
                ->execute([$studentId, $classId, $sectionVal, $courseWide ? 1 : 0, round($scorePct, 2), round($scorePct, 2), $level]);
        } else {
            $level = self::proficiencyLevel($scorePct);
            db()->prepare('INSERT INTO student_lesson_proficiency (student_id, class_id, section_id, attempts, best_score_pct, avg_score_pct, last_attempt_at, proficiency_level)
                VALUES (?, ?, ?, 1, ?, ?, UTC_TIMESTAMP(), ?)')
                ->execute([$studentId, $classId, $sectionVal, round($scorePct, 2), round($scorePct, 2), $level]);
        }
    }

    private static function proficiencyHasCourseWideColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $has = (bool) db()->query("SHOW COLUMNS FROM student_lesson_proficiency LIKE 'is_course_wide'")->fetch();
        } catch (PDOException) {
            $has = false;
        }
        return $has;
    }

    public static function proficiencyLevel(float $avgPct): string
    {
        return match (true) {
            $avgPct >= 90 => 'mastery',
            $avgPct >= 75 => 'proficient',
            $avgPct >= 50 => 'developing',
            default => 'beginner',
        };
    }

    /** @return list<array<string, mixed>> */
    public static function proficiencyForStudent(int $studentId, int $classId): array
    {
        $stmt = db()->prepare('SELECT p.*, cs.title AS section_title
            FROM student_lesson_proficiency p
            LEFT JOIN course_sections cs ON cs.id = p.section_id
            WHERE p.student_id = ? AND p.class_id = ?
            ORDER BY p.is_course_wide DESC, cs.sort_order, p.section_id');
        $stmt->execute([$studentId, $classId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            if (!empty($row['is_course_wide'])) {
                $row['section_title'] = 'All lessons';
            } elseif (empty($row['section_id'])) {
                $row['section_title'] = 'Unassigned';
            }
        }
        unset($row);
        return $rows;
    }

    public static function proficiencyLabel(string $level): string
    {
        return match ($level) {
            'mastery' => 'Mastery',
            'proficient' => 'Proficient',
            'developing' => 'Developing',
            default => 'Beginner',
        };
    }
}
