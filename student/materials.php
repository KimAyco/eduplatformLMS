<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
requireSchoolActive();

$user = currentUser();
$classes = getStudentClasses();
$classIds = array_column($classes, 'id');
$filterClass = (int) ($_GET['class_id'] ?? 0);

$materials = [];
if (!empty($classIds)) {
    $sql = 'SELECT m.*, sub.name AS name, sub.name AS class_name, g.name AS group_name, u.first_name AS teacher_first, u.last_name AS teacher_last
            FROM materials m
            INNER JOIN classes c ON c.id = m.class_id
            INNER JOIN subjects sub ON sub.id = c.subject_id
            INNER JOIN class_groups g ON g.id = c.class_group_id
            INNER JOIN class_group_students cgs ON cgs.class_group_id = c.class_group_id AND cgs.student_id = ?
            INNER JOIN users u ON u.id = m.teacher_id
            WHERE cgs.student_id = ?';
    $params = [$user['id'], $user['id']];
    if ($filterClass && in_array($filterClass, $classIds)) {
        $sql .= ' AND m.class_id = ?';
        $params[] = $filterClass;
    }
    $sql .= ' ORDER BY m.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
}

$pageTitle = 'Materials';
$pageHeading = 'Class Materials';
$activeMenu = 'materials';
$menuItems = studentMenu();

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php if (!empty($classes)): ?>
<div class="filter-bar">
    <form method="get">
        <select name="class_id" class="form-control" onchange="this.form.submit()">
            <option value="">All classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClass === (int)$c['id'] ? 'selected' : '' ?>><?= e(classDisplayName($c)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Class</th><th>Teacher</th><th>Resources</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($materials)): ?>
            <tr><td colspan="5" class="text-muted">No materials available.</td></tr>
        <?php else: foreach ($materials as $m): ?>
            <tr>
                <td><strong><?= e($m['title']) ?></strong><?php if ($m['body']): ?><br><small class="text-muted"><?= e(mb_strimwidth($m['body'], 0, 80, '...')) ?></small><?php endif; ?></td>
                <td><?= e(classDisplayName($m)) ?></td>
                <td><?= e($m['teacher_first'] . ' ' . $m['teacher_last']) ?></td>
                <td>
                    <?php if ($m['file_path']): ?><a href="<?= e(uploadUrl($m['file_path'])) ?>" target="_blank">Download</a><?php endif; ?>
                    <?php if ($m['external_link']): ?><?= $m['file_path'] ? ' · ' : '' ?><a href="<?= e($m['external_link']) ?>" target="_blank">Link</a><?php endif; ?>
                </td>
                <td><?= formatDate($m['created_at'], 'M j, Y') ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
