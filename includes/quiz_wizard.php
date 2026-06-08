<?php

/**
 * @return array<string, mixed>|null
 */
function loadQuizWizardContext(int $quizId, int $classId, int $teacherId, string $step, int $editQuestionId, array $errors = []): ?array
{
    $quiz = QuizRepository::getQuizById($quizId, $classId);
    if (!$quiz || (int) $quiz['teacher_id'] !== $teacherId) {
        return null;
    }

    if (!in_array($step, ['questions', 'settings'], true)) {
        $step = 'questions';
    }

    $editQuestion = null;
    if ($editQuestionId) {
        $step = 'questions';
        $stmt = db()->prepare('SELECT * FROM quiz_questions WHERE id=? AND quiz_id=?');
        $stmt->execute([$editQuestionId, $quizId]);
        $editQuestion = $stmt->fetch();
        if ($editQuestion) {
            QuizRepository::decodeQuestionSettings($editQuestion);
            $editQuestion['type'] = normalizeQuestionType($editQuestion['type']);
            $opts = db()->prepare('SELECT * FROM quiz_options WHERE question_id=? ORDER BY id');
            $opts->execute([$editQuestionId]);
            $editQuestion['options'] = $opts->fetchAll();
        }
    }

    $questions = QuizRepository::questionsWithOptions($quizId);

    return [
        'quizId' => $quizId,
        'classId' => $classId,
        'quiz' => $quiz,
        'step' => $step,
        'errors' => $errors,
        'editQuestion' => $editQuestion,
        'editQuestionId' => $editQuestionId,
        'questions' => $questions,
        'questionCount' => count($questions),
        'totalPoints' => getQuizTotalPoints($quizId),
        'sections' => CourseSectionRepository::forClass($classId),
    ];
}

/**
 * Handle quiz wizard POST actions. Returns true if handled (redirect or exit).
 *
 * @param array<int, string> $errors
 */
function handleQuizWizardPost(string $action, int $quizId, int $classId, int $teacherId, array &$errors): bool
{
    $quizActions = ['save_settings', 'save_question', 'delete_question', 'reorder_question'];
    if (!in_array($action, $quizActions, true)) {
        return false;
    }

    $quiz = QuizRepository::getQuizById($quizId, $classId);
    if (!$quiz || (int) $quiz['teacher_id'] !== $teacherId) {
        flash('error', 'Quiz not found.');
        redirect('teacher/course.php?id=' . $classId);
    }

    if ($action === 'save_settings') {
        QuizRepository::updateQuizSettings($quizId, $classId, [
            'title' => trim($_POST['title'] ?? ''),
            'instructions' => trim($_POST['instructions'] ?? '') ?: null,
            'time_limit_minutes' => $_POST['time_limit_minutes'] !== '' ? (int) $_POST['time_limit_minutes'] : null,
            'due_date' => trim($_POST['due_date'] ?? '') ?: null,
            'opens_at' => trim($_POST['opens_at'] ?? '') ?: null,
            'closes_at' => trim($_POST['closes_at'] ?? '') ?: null,
            'max_attempts' => max(1, (int) ($_POST['max_attempts'] ?? 1)),
            'is_published' => isset($_POST['is_published']),
            'randomize_questions_order' => isset($_POST['randomize_questions_order']),
            'show_score_to_students' => isset($_POST['show_score_to_students']),
            'section_id' => (int) ($_POST['section_id'] ?? 0),
        ]);
        if (!empty($_FILES['cover_image']['name'])) {
            try {
                if (!empty($quiz['cover_image'])) {
                    deleteUpload($quiz['cover_image']);
                }
                $cover = uploadQuizCover($_FILES['cover_image'], schoolId(), $quizId);
                db()->prepare('UPDATE quizzes SET cover_image=? WHERE id=?')->execute([$cover, $quizId]);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
                return true;
            }
        }
        if (empty($errors)) {
            flash('success', isset($_POST['finish']) ? 'Quiz saved and ready for students.' : 'Quiz settings saved.');
            if (isset($_POST['finish'])) {
                redirect('teacher/course.php?id=' . $classId);
            }
            redirect(quizEditUrl($quizId, $classId, 'settings'));
        }
        return true;
    }

    if ($action === 'save_question') {
        $qid = (int) ($_POST['question_id'] ?? 0) ?: null;
        $existing = null;
        if ($qid) {
            $stmt = db()->prepare('SELECT * FROM quiz_questions WHERE id=? AND quiz_id=?');
            $stmt->execute([$qid, $quizId]);
            $existing = $stmt->fetch() ?: null;
        }
        $result = saveQuizQuestion($quizId, $_POST, $qid, $existing);
        if ($result['ok']) {
            flash('success', $qid ? 'Question updated.' : 'Question added.');
            redirect(quizEditUrl($quizId, $classId, 'questions'));
        }
        $errors = $result['errors'];
        return true;
    }

    if ($action === 'delete_question') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        db()->prepare('DELETE FROM quiz_questions WHERE id=? AND quiz_id=?')->execute([$qid, $quizId]);
        flash('success', 'Question deleted.');
        redirect(quizEditUrl($quizId, $classId, 'questions'));
    }

    if ($action === 'reorder_question') {
        reorderQuizQuestion($quizId, (int) ($_POST['question_id'] ?? 0), $_POST['direction'] ?? 'up');
        redirect(quizEditUrl($quizId, $classId, 'questions'));
    }

    return false;
}
