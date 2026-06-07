<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$classes = getStudentClasses();
$classIds = array_column($classes, 'id');
$stats = DashboardRepository::studentStats($user['id'], $classIds);
$upcomingTasks = DashboardRepository::studentUpcomingTasks($user['id'], $classIds);

$pageTitle = 'Dashboard';
$pageHeading = 'My learning';
$activeMenu = 'dashboard';
$menuItems = studentMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'student/dashboard.php'],
];
$welcomeSubtitle = 'Track your courses, assignments, and quizzes.';

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/dashboard_welcome.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-blue"><i class="fa-solid fa-book-open"></i></div>
        <div><div class="value"><?= $stats['classes'] ?></div><div class="label">Courses</div><div class="stat-card-term">This term</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-orange"><i class="fa-solid fa-pen-to-square"></i></div>
        <div><div class="value"><?= $stats['pending_assignments'] ?></div><div class="label">Pending assignments</div><div class="stat-card-term">This term</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-card-icon-green"><i class="fa-solid fa-circle-question"></i></div>
        <div><div class="value"><?= $stats['upcoming_quizzes'] ?></div><div class="label">Available quizzes</div><div class="stat-card-term">This term</div></div>
    </div>
</div>

<?php if (empty($classes)): ?>
<div class="stu-alert stu-alert--info">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        <strong>No courses enrolled</strong>
        <p>You are not enrolled in any classes yet. Contact your school administrator to get started.</p>
    </div>
</div>
<?php elseif ($stats['pending_assignments'] > 0): ?>
<div class="stu-alert stu-alert--action">
    <i class="fa-solid fa-pen-to-square"></i>
    <div style="flex:1">
        <strong><?= $stats['pending_assignments'] ?> assignment<?= $stats['pending_assignments'] !== 1 ? 's' : '' ?> pending</strong>
        <p>Review your upcoming tasks below and submit your work before the due dates.</p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($upcomingTasks)): ?>
<div class="panel tasks-panel">
    <div class="tasks-panel-header">
        <h2><i class="fa-solid fa-list-check"></i> Upcoming tasks</h2>
        <div class="tasks-controls">
            <input type="search" id="taskSearch" class="form-control" placeholder="Search tasks…">
            <select id="taskSort" class="form-control">
                <option value="due">Sort by due date</option>
                <option value="title">Sort by title</option>
            </select>
        </div>
    </div>
    <div class="tasks-list" id="studentTasksList">
        <?php foreach ($upcomingTasks as $task):
            $dueTs = $task['due_date'] ? strtotime($task['due_date']) : 0;
            $isAssignment = $task['task_type'] === 'assignment';
            $url = $isAssignment
                ? url('student/assignments.php?id=' . $task['id'])
                : url('student/quiz-take.php?quiz_id=' . $task['id']);
        ?>
        <a href="<?= e($url) ?>" class="task-item" data-title="<?= e($task['title']) ?>" data-due="<?= $dueTs ?>">
            <span class="task-item-icon task-item-icon--<?= $isAssignment ? 'assignment' : 'quiz' ?>">
                <i class="fa-solid <?= $isAssignment ? 'fa-pen-to-square' : 'fa-circle-question' ?>"></i>
            </span>
            <span class="task-item-body">
                <strong><?= e($task['title']) ?></strong>
                <small><?= e($task['class_name']) ?> · <?= $isAssignment ? 'Assignment' : 'Quiz' ?></small>
            </span>
            <span class="task-item-due">
                <?= $task['due_date'] ? 'Due ' . formatDate($task['due_date'], 'M j') : 'No due date' ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<h2 class="mb-1 page-section-title" id="courses">My courses</h2>

<?php if (empty($classes)): ?>
<div class="empty-state">
    <i class="fa-solid fa-book-open"></i>
    <h3>No courses yet</h3>
    <p>You are not enrolled in any classes. Contact your school administrator.</p>
</div>
<?php else: ?>
<div class="course-grid">
    <?php foreach ($classes as $c):
        if (!empty($c['description'])) {
            $bodyHtml = '<p class="text-muted course-card-desc">' . e(mb_strimwidth($c['description'], 0, 100, '…')) . '</p>';
        } else {
            $bodyHtml = '<p class="text-muted course-card-desc">' . e($c['group_academic_year'] ?: 'Open course') . '</p>';
        }
        renderCourseCard($c, studentCourseUrl((int) $c['id']), $bodyHtml);
    endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
