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
$hidePageHeader = true;
$activeMenu = 'dashboard';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
    ['label' => $class['name'], 'url' => 'student/course.php?id=' . $classId],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';

$activityCount = count($activities);
$courseInitial = strtoupper(mb_substr($class['name'], 0, 1));
?>

<div class="course-view course-view--student">
    <section class="course-hero">
        <div class="course-hero-main">
            <a href="<?= url('student/dashboard.php') ?>" class="course-back-link"><i class="fa-solid fa-arrow-left"></i> My courses</a>
            <div class="course-hero-title-row">
                <div class="course-hero-avatar" aria-hidden="true"><?= e($courseInitial) ?></div>
                <div>
                    <h1 class="course-hero-title"><?= e($class['name']) ?></h1>
                    <div class="course-hero-tags">
                        <?php if ($class['section']): ?><span class="course-tag"><i class="fa-solid fa-layer-group"></i> Section <?= e($class['section']) ?></span><?php endif; ?>
                        <?php if ($class['academic_year']): ?><span class="course-tag"><i class="fa-solid fa-calendar"></i> <?= e($class['academic_year']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($class['description']): ?><p class="course-hero-desc"><?= e($class['description']) ?></p><?php endif; ?>
        </div>
        <div class="course-hero-stats">
            <div class="course-stat"><strong><?= $activityCount ?></strong><span>Activities</span></div>
        </div>
    </section>

    <section class="course-content-section course-content-section--full">
        <div class="course-content-header">
            <h2><i class="fa-solid fa-book-open"></i> Course content</h2>
            <?php if ($activityCount > 0): ?><span class="course-content-count"><?= $activityCount ?> item<?= $activityCount !== 1 ? 's' : '' ?></span><?php endif; ?>
        </div>

        <?php if (empty($activities)): ?>
        <div class="course-empty">
            <div class="course-empty-icon"><i class="fa-solid fa-folder-open"></i></div>
            <h3>Nothing here yet</h3>
            <p>Your teacher has not published any materials or activities for this class.</p>
        </div>
        <?php else: ?>
        <div class="activity-list">
            <?php foreach ($activities as $act):
                $item = $act['item'];
                if ($act['type'] === 'material'): ?>
            <article class="activity-card activity-card--material">
                <div class="activity-card-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div class="activity-card-body">
                    <span class="activity-card-type">Material</span>
                    <h3><?= e($item['title']) ?></h3>
                    <?php if ($item['body']): ?><p><?= e($item['body']) ?></p><?php endif; ?>
                    <div class="activity-card-meta">
                        <span class="activity-meta-chip muted"><i class="fa-solid fa-user"></i> <?= e($item['teacher_first'] . ' ' . $item['teacher_last']) ?></span>
                        <?php if ($item['file_path']): ?><a href="<?= e(uploadUrl($item['file_path'])) ?>" target="_blank" class="activity-meta-chip"><i class="fa-solid fa-download"></i> Download</a><?php endif; ?>
                        <?php if ($item['external_link']): ?><a href="<?= e($item['external_link']) ?>" target="_blank" class="activity-meta-chip"><i class="fa-solid fa-link"></i> Open link</a><?php endif; ?>
                    </div>
                </div>
            </article>
                <?php elseif ($act['type'] === 'assignment'): ?>
            <article class="activity-card activity-card--assignment">
                <div class="activity-card-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                <div class="activity-card-body">
                    <span class="activity-card-type">Assignment</span>
                    <h3><?= e($item['title']) ?></h3>
                    <div class="activity-card-meta">
                        <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> Due <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                        <span class="activity-meta-chip"><i class="fa-solid fa-star"></i> <?= e($item['max_points']) ?> pts</span>
                        <?php if ($item['my_status']): ?>
                            <span class="badge badge-<?= e($item['my_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $item['my_status']))) ?></span>
                            <?php if ($item['my_grade'] !== null): ?><span class="activity-meta-chip"><i class="fa-solid fa-check"></i> Grade: <?= e($item['my_grade']) ?></span><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="activity-card-actions">
                    <a href="<?= url('student/assignments.php?id=' . $item['id']) ?>" class="btn btn-sm btn-primary"><?= $item['my_status'] ? 'View' : 'Submit' ?></a>
                </div>
            </article>
                <?php else: ?>
            <article class="activity-card activity-card--quiz">
                <div class="activity-card-icon"><i class="fa-solid fa-circle-question"></i></div>
                <div class="activity-card-body">
                    <span class="activity-card-type">Quiz</span>
                    <h3><?= e($item['title']) ?></h3>
                    <div class="activity-card-meta">
                        <span class="activity-meta-chip"><i class="fa-solid fa-list"></i> <?= (int) $item['question_count'] ?> questions</span>
                        <span class="activity-meta-chip"><i class="fa-regular fa-calendar"></i> Due <?= formatDate($item['due_date'], 'M j, Y') ?></span>
                        <?php if ($item['my_attempt_status']): ?><span class="badge badge-submitted"><?= e(ucfirst(str_replace('_', ' ', $item['my_attempt_status']))) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="activity-card-actions">
                    <a href="<?= url('student/quiz-take.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-primary">Take quiz</a>
                    <?php if ($item['my_attempt_status'] && $item['my_attempt_status'] !== 'in_progress'): ?>
                    <a href="<?= url('student/quiz-results.php?quiz_id=' . $item['id']) ?>" class="btn btn-sm btn-secondary">Results</a>
                    <?php endif; ?>
                </div>
            </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
