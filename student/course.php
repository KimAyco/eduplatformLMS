<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$classId = (int) ($_GET['id'] ?? 0);
requireClassAccess($classId, 'student');

$class = getClass($classId);
if (!$class) {
    flash('error', 'Class not found.');
    redirect('student/dashboard.php');
}

$stmt = db()->prepare('SELECT m.*, u.first_name AS teacher_first, u.last_name AS teacher_last
    FROM materials m
    INNER JOIN users u ON u.id = m.teacher_id
    WHERE m.class_id = ?
    ORDER BY m.created_at DESC');
$stmt->execute([$classId]);
$materials = $stmt->fetchAll();

$stmt = db()->prepare('SELECT a.*,
    (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) AS my_status,
    (SELECT grade FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ? LIMIT 1) AS my_grade
    FROM assignments a WHERE a.class_id = ? ORDER BY a.created_at DESC');
$stmt->execute([$user['id'], $user['id'], $classId]);
$assignments = $stmt->fetchAll();

$stmt = db()->prepare('SELECT q.*,
    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count,
    (SELECT status FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ? ORDER BY id DESC LIMIT 1) AS my_attempt_status
    FROM quizzes q WHERE q.class_id = ? ORDER BY q.created_at DESC');
$stmt->execute([$user['id'], $classId]);
$quizzes = $stmt->fetchAll();

$activities = [];
foreach ($materials as $m) {
    $activities[] = ['type' => 'material', 'sort' => strtotime($m['created_at']), 'item' => $m];
}
foreach ($assignments as $a) {
    $activities[] = ['type' => 'assignment', 'sort' => strtotime($a['created_at']), 'item' => $a];
}
foreach ($quizzes as $q) {
    $activities[] = ['type' => 'quiz', 'sort' => strtotime($q['created_at']), 'item' => $q];
}
usort($activities, fn ($x, $y) => $y['sort'] <=> $x['sort']);

$classTitle = $class['name'] . ($class['section'] ? ' — Section ' . $class['section'] : '');
$pageTitle = $classTitle;
$pageHeading = $classTitle;
$activeMenu = 'dashboard';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
    ['label' => $class['name'], 'url' => 'student/course.php?id=' . $classId],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="course-page-header">
    <div>
        <a href="<?= url('student/dashboard.php') ?>" class="course-back-link"><i class="fa-solid fa-arrow-left"></i> Back to courses</a>
        <h2><?= e($class['name']) ?></h2>
        <?php if ($class['section']): ?><p class="course-page-meta">Section <?= e($class['section']) ?></p><?php endif; ?>
        <?php if ($class['academic_year']): ?><p class="course-page-meta"><?= e($class['academic_year']) ?></p><?php endif; ?>
        <?php if ($class['description']): ?><p class="course-page-desc"><?= e($class['description']) ?></p><?php endif; ?>
    </div>
</div>

<div class="panel">
    <h2><i class="fa-solid fa-list"></i> Course content</h2>
    <?php if (empty($activities)): ?>
        <div class="empty-state" style="padding:2rem 1rem;">
            <i class="fa-solid fa-folder-open"></i>
            <h3>No content yet</h3>
            <p>Your teacher has not added any materials or activities.</p>
        </div>
    <?php else: ?>
        <ul class="course-module-list">
            <?php foreach ($activities as $act):
                $item = $act['item'];
                if ($act['type'] === 'material'): ?>
            <li class="course-module-item">
                <div class="course-module-icon material"><i class="fa-solid fa-file-lines"></i></div>
                <div class="course-module-body">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="course-module-type">Material</span>
                    <?php if ($item['body']): ?><p class="text-muted"><?= e($item['body']) ?></p><?php endif; ?>
                    <div class="course-module-meta">
                        <span>By <?= e($item['teacher_first'] . ' ' . $item['teacher_last']) ?></span>
                        <?php if ($item['file_path']): ?><a href="<?= e(uploadUrl($item['file_path'])) ?>" target="_blank"><i class="fa-solid fa-download"></i> Download file</a><?php endif; ?>
                        <?php if ($item['external_link']): ?><a href="<?= e($item['external_link']) ?>" target="_blank"><i class="fa-solid fa-link"></i> Open link</a><?php endif; ?>
                    </div>
                </div>
            </li>
                <?php elseif ($act['type'] === 'assignment'): ?>
            <li class="course-module-item">
                <div class="course-module-icon assignment"><i class="fa-solid fa-pen-to-square"></i></div>
                <div class="course-module-body">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="course-module-type">Assignment</span>
                    <div class="course-module-meta">
                        <span>Due: <?= formatDate($item['due_date']) ?></span>
                        <span><?= e($item['max_points']) ?> pts</span>
                        <?php if ($item['my_status']): ?>
                            <span class="badge badge-<?= e($item['my_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $item['my_status']))) ?></span>
                            <?php if ($item['my_grade'] !== null): ?><span>Grade: <?= e($item['my_grade']) ?></span><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="course-module-actions">
                    <a href="<?= url('student/assignments.php?id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Open</a>
                </div>
            </li>
                <?php else: ?>
            <li class="course-module-item">
                <div class="course-module-icon quiz"><i class="fa-solid fa-circle-question"></i></div>
                <div class="course-module-body">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="course-module-type">Quiz</span>
                    <div class="course-module-meta">
                        <span><?= (int) $item['question_count'] ?> question(s)</span>
                        <span>Due: <?= formatDate($item['due_date']) ?></span>
                        <?php if ($item['my_attempt_status']): ?><span class="badge badge-submitted"><?= e(ucfirst(str_replace('_', ' ', $item['my_attempt_status']))) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="course-module-actions">
                    <a href="<?= url('student/quiz-take.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Take quiz</a>
                    <?php if ($item['my_attempt_status'] && $item['my_attempt_status'] !== 'in_progress'): ?>
                    <a href="<?= url('student/quiz-results.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Results</a>
                    <?php endif; ?>
                </div>
            </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
