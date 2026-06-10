<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

if (!schoolPracticeQuizzesEnabled()) {
    flash('error', 'Practice quizzes are not enabled for your school.');
    redirect('student/dashboard.php');
}

$user = currentUser();
$classId = (int) ($_GET['class_id'] ?? 0);

if ($classId > 0) {
    requireClassAccess($classId, 'student');
    $class = getClass($classId);
    $rows = PracticeQuizService::proficiencyForStudent((int) $user['id'], $classId);
    $pageHeading = 'Practice stats: ' . classDisplayName($class);
} else {
    $classes = getStudentClasses();
    $rows = [];
    foreach ($classes as $c) {
        foreach (PracticeQuizService::proficiencyForStudent((int) $user['id'], (int) $c['id']) as $r) {
            $r['class_name'] = classDisplayName($c);
            $r['class_id'] = (int) $c['id'];
            $rows[] = $r;
        }
    }
    $pageHeading = 'Practice & proficiency';
}

$pageTitle = 'Practice stats';
$pageSubtitle = 'Your self-study progress per lesson.';
$activeMenu = 'practice';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
    ['label' => 'Practice stats', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="panel">
    <?php if ($rows === []): ?>
        <?= adminEmptyState('fa-chart-simple', 'No practice data yet', 'Take a practice quiz from any course lesson to see your proficiency here.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table practice-stats-table">
            <thead>
                <tr>
                    <?php if ($classId <= 0): ?><th>Course</th><?php endif; ?>
                    <th>Lesson</th>
                    <th>Level</th>
                    <th>Best</th>
                    <th>Average</th>
                    <th>Attempts</th>
                    <th>Last attempt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $level = $row['proficiency_level'] ?? 'beginner';
                ?>
                <tr>
                    <?php if ($classId <= 0): ?>
                    <td><a href="<?= url('student/practice.php?class_id=' . (int) $row['class_id']) ?>"><?= e($row['class_name'] ?? '') ?></a></td>
                    <?php endif; ?>
                    <td><?= e($row['section_title'] ?? 'General') ?></td>
                    <td><span class="practice-level practice-level--<?= e($level) ?>"><?= e(PracticeQuizService::proficiencyLabel($level)) ?></span></td>
                    <td><?= $row['best_score_pct'] !== null ? e($row['best_score_pct']) . '%' : '—' ?></td>
                    <td><?= $row['avg_score_pct'] !== null ? e($row['avg_score_pct']) . '%' : '—' ?></td>
                    <td><?= (int) $row['attempts'] ?></td>
                    <td><?= $row['last_attempt_at'] ? e(formatDate($row['last_attempt_at'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
