<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');

$quizId = (int) ($_GET['id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);
$step = $_GET['step'] ?? 'questions';
$editQ = (int) ($_GET['edit_q'] ?? 0);

if (!$quizId) {
    redirect($classId ? 'teacher/course.php?id=' . $classId : 'teacher/dashboard.php');
}

if (!$classId) {
    $quiz = QuizRepository::getQuizById($quizId);
    $classId = $quiz ? (int) $quiz['class_id'] : 0;
}

redirect(quizEditUrl($quizId, $classId, $step, $editQ));
