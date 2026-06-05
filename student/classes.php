<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$classes = getStudentClasses();

$pageTitle = 'My Classes';
$pageHeading = 'My Classes';
$activeMenu = 'classes';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Class</th><th>Section</th><th>Description</th><th>Academic Year</th></tr></thead>
        <tbody>
        <?php if (empty($classes)): ?>
            <tr><td colspan="4" class="text-muted">No classes enrolled.</td></tr>
        <?php else: foreach ($classes as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><?= e($c['section'] ?: '—') ?></td>
                <td><?= e($c['description'] ?: '—') ?></td>
                <td><?= e($c['academic_year'] ?: '—') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
