<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['form_action'] ?? '';
    $id = (int) ($_POST['announcement_id'] ?? 0);

    try {
        if ($action === 'publish' && $id > 0) {
            $count = AnnouncementRepository::publish($id, $sid);
            flash('success', 'Announcement published to ' . $count . ' recipient(s).');
        } elseif ($action === 'archive' && $id > 0) {
            AnnouncementRepository::archive($id, $sid);
            flash('success', 'Announcement archived.');
        } elseif ($action === 'delete' && $id > 0) {
            AnnouncementRepository::delete($id, $sid);
            flash('success', 'Draft deleted.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
    redirect('school/announcements.php');
}

$filter = $_GET['status'] ?? '';
$announcements = AnnouncementRepository::forSchool($sid, $filter !== '' ? $filter : null);

$pageTitle = 'Announcements';
$pageHeading = 'Announcements';
$pageSubtitle = 'Send notifications and announcements to teachers, students, and groups.';
$activeMenu = 'announcements';
$menuItems = schoolAdminMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Announcements'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<div class="announcements-toolbar">
    <div class="announcements-filters">
        <a href="<?= url('school/announcements.php') ?>" class="btn btn-sm<?= $filter === '' ? ' btn-primary' : ' btn-secondary' ?>">All</a>
        <a href="<?= url('school/announcements.php?status=published') ?>" class="btn btn-sm<?= $filter === 'published' ? ' btn-primary' : ' btn-secondary' ?>">Published</a>
        <a href="<?= url('school/announcements.php?status=draft') ?>" class="btn btn-sm<?= $filter === 'draft' ? ' btn-primary' : ' btn-secondary' ?>">Drafts</a>
        <a href="<?= url('school/announcements.php?status=archived') ?>" class="btn btn-sm<?= $filter === 'archived' ? ' btn-primary' : ' btn-secondary' ?>">Archived</a>
    </div>
    <a href="<?= url('school/announcement.php') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New announcement</a>
</div>

<?php if ($announcements === []): ?>
<div class="panel">
    <p class="text-muted mb-0">No announcements yet. Create one to notify your school community.</p>
</div>
<?php else: ?>
<div class="panel announcements-list">
    <table class="admin-data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Recipients</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($announcements as $row):
                $status = $row['status'] ?? 'draft';
                $date = $row['published_at'] ?? $row['created_at'];
            ?>
            <tr>
                <td>
                    <strong><?= e($row['title']) ?></strong>
                    <div class="text-muted announcements-list-meta">By <?= e(trim(($row['author_first'] ?? '') . ' ' . ($row['author_last'] ?? ''))) ?></div>
                </td>
                <td><span class="announcement-priority announcement-priority--<?= e($row['priority']) ?>"><?= e(announcementPriorityLabel($row['priority'])) ?></span></td>
                <td><span class="status-chip status-chip--<?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
                <td><?= $status === 'published' ? (int) $row['recipient_count'] : '—' ?></td>
                <td><?= $date ? e(date('M j, Y g:i A', strtotime($date))) : '—' ?></td>
                <td class="table-actions">
                    <?php if ($status === 'draft'): ?>
                    <a href="<?= url('school/announcement.php?id=' . (int) $row['id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="post" class="inline-form"><?= csrfField() ?>
                        <input type="hidden" name="form_action" value="publish">
                        <input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Publish</button>
                    </form>
                    <form method="post" class="inline-form" onsubmit="return confirm('Delete this draft?');"><?= csrfField() ?>
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline">Delete</button>
                    </form>
                    <?php elseif ($status === 'published'): ?>
                    <form method="post" class="inline-form"><?= csrfField() ?>
                        <input type="hidden" name="form_action" value="archive">
                        <input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary">Archive</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
