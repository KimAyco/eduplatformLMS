<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();

$stmt = db()->prepare('SELECT q.*, c.name AS class_name, g.name AS group_name,
    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? AND status != ?) AS attempt_count,
    (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? AND status != ?) AS best_score
    FROM quizzes q
    INNER JOIN classes c ON c.id = q.class_id
    INNER JOIN class_groups g ON g.id = c.class_group_id
    INNER JOIN class_group_students cgs ON cgs.class_group_id = c.class_group_id AND cgs.student_id = ?
    ORDER BY q.due_date ASC, q.created_at DESC');
$stmt->execute([$user['id'], 'in_progress', $user['id'], 'in_progress', $user['id']]);
$quizzes = $stmt->fetchAll();

$pageTitle = 'Quizzes';
$pageHeading = 'Quizzes & Exams';
$activeMenu = 'quizzes';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Class</th><th>Due</th><th>Attempts</th><th>Best Score</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($quizzes)): ?>
            <tr><td colspan="6" class="text-muted">No quizzes available.</td></tr>
        <?php else: foreach ($quizzes as $q): ?>
            <?php $can = canStudentTakeQuiz($q, $user['id']); ?>
            <tr>
                <td><?= e($q['title']) ?></td>
                <td><?= e(classDisplayName($q)) ?></td>
                <td><?= formatDate($q['due_date']) ?></td>
                <td><?= (int)$q['attempt_count'] ?> / <?= (int)$q['max_attempts'] ?></td>
                <td><?= $q['best_score'] !== null ? e($q['best_score']) . ' / ' . getQuizTotalPoints($q['id']) : '—' ?></td>
                <td>
                    <?php if ($can['ok'] || ($can['resume'] ?? false)): ?>
                        <a href="<?= url('student/quiz-take.php?quiz_id='.$q['id']) ?>" class="btn btn-sm btn-primary"><?= ($can['resume'] ?? false) ? 'Resume' : 'Take Quiz' ?></a>
                    <?php else: ?>
                        <span class="text-muted"><?= e($can['reason'] ?? 'Unavailable') ?></span>
                    <?php endif; ?>
                    <?php if ((int)$q['attempt_count'] > 0): ?>
                        <a href="<?= url('student/quiz-results.php?quiz_id='.$q['id']) ?>" class="btn btn-sm btn-secondary">Results</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
