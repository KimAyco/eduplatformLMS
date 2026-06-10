<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('school_admin');
requireSchoolActive();

$sid = schoolId();
$user = currentUser();
$id = (int) ($_GET['id'] ?? 0);
$announcement = $id > 0 ? AnnouncementRepository::get($id, $sid) : null;

if ($id > 0 && !$announcement) {
    flash('error', 'Announcement not found.');
    redirect('school/announcements.php');
}
if ($announcement && ($announcement['status'] ?? '') === 'published') {
    flash('error', 'Published announcements cannot be edited.');
    redirect('school/announcements.php');
}

$classGroups = ClassGroupRepository::forSchool($sid);
$subjects = SubjectRepository::forSchool($sid);
$programs = ProgramRepository::forSchool($sid);
$classes = ClassRepository::withCounts($sid);
$programLevels = [];
foreach ($programs as $program) {
    foreach (ProgramRepository::levelsForProgram((int) $program['id'], $sid) as $level) {
        $programLevels[] = [
            'id' => (int) $level['id'],
            'name' => $program['name'] . ' — ' . $level['name'],
        ];
    }
}

$existingTargets = $announcement ? AnnouncementRepository::targetsFor($id) : [
    ['target_type' => 'all_students', 'target_id' => null],
];

$errors = [];
$form = [
    'title' => $announcement['title'] ?? '',
    'body' => $announcement['body'] ?? '',
    'priority' => $announcement['priority'] ?? 'normal',
    'link_url' => $announcement['link_url'] ?? '',
    'link_label' => $announcement['link_label'] ?? '',
    'expires_at' => !empty($announcement['expires_at']) ? date('Y-m-d\TH:i', strtotime($announcement['expires_at'])) : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['title'] = trim($_POST['title'] ?? '');
    $form['body'] = trim($_POST['body'] ?? '');
    $form['priority'] = (string) ($_POST['priority'] ?? 'normal');
    $form['link_url'] = trim($_POST['link_url'] ?? '');
    $form['link_label'] = trim($_POST['link_label'] ?? '');
    $form['expires_at'] = trim($_POST['expires_at'] ?? '');
    $publishNow = ($_POST['form_action'] ?? '') === 'publish';
    $targets = $_POST['targets'] ?? [];
    $existingTargets = is_array($targets) ? $targets : [];

    if ($form['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($form['body'] === '') {
        $errors[] = 'Message body is required.';
    }

    $expiresAt = null;
    if ($form['expires_at'] !== '') {
        $ts = strtotime($form['expires_at']);
        if ($ts === false) {
            $errors[] = 'Invalid expiry date.';
        } else {
            $expiresAt = date('Y-m-d H:i:s', $ts);
        }
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                AnnouncementRepository::update(
                    $id,
                    $sid,
                    $form['title'],
                    $form['body'],
                    $form['priority'],
                    $form['link_url'] ?: null,
                    $form['link_label'] ?: null,
                    $expiresAt,
                    $targets
                );
                if ($publishNow) {
                    $count = AnnouncementRepository::publish($id, $sid);
                    flash('success', 'Announcement published to ' . $count . ' recipient(s).');
                } else {
                    flash('success', 'Draft saved.');
                }
            } else {
                $newId = AnnouncementRepository::create(
                    $sid,
                    (int) $user['id'],
                    $form['title'],
                    $form['body'],
                    $form['priority'],
                    $form['link_url'] ?: null,
                    $form['link_label'] ?: null,
                    $expiresAt,
                    $targets,
                    $publishNow
                );
                flash('success', $publishNow ? 'Announcement published.' : 'Draft created.');
                $id = $newId;
            }
            redirect('school/announcements.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$audienceData = [
    'class_groups' => array_map(static fn ($g) => ['id' => (int) $g['id'], 'name' => $g['name']], $classGroups),
    'subjects' => array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $subjects),
    'programs' => array_map(static fn ($p) => ['id' => (int) $p['id'], 'name' => $p['name']], $programs),
    'program_levels' => $programLevels,
    'classes' => array_map(static function ($c) {
        return [
            'id' => (int) $c['id'],
            'name' => ($c['group_name'] ?? 'Group') . ' — ' . ($c['name'] ?? 'Subject'),
        ];
    }, $classes),
];

$pageTitle = $id > 0 ? 'Edit announcement' : 'New announcement';
$pageHeading = $id > 0 ? 'Edit announcement' : 'New announcement';
$pageSubtitle = 'Configure who receives this notification.';
$activeMenu = 'announcements';
$menuItems = schoolAdminMenu();
$pageScripts = ['assets/js/school-announcements.js'];
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'school/dashboard.php'],
    ['label' => 'Announcements', 'url' => 'school/announcements.php'],
    ['label' => $id > 0 ? 'Edit' : 'New'],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" class="announcement-form panel" id="announcementForm"
    data-audience="<?= e(json_encode($audienceData, JSON_UNESCAPED_UNICODE)) ?>"
    data-api="<?= e(url('api/notifications.php')) ?>"
    data-target-options="<?= e(json_encode(announcementTargetOptions(), JSON_UNESCAPED_UNICODE)) ?>">
    <?= csrfField() ?>

    <div class="form-grid announcement-form-grid">
        <div class="form-group span-2">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" class="form-control" required value="<?= e($form['title']) ?>" placeholder="e.g. School holiday schedule">
        </div>

        <div class="form-group">
            <label for="priority">Priority</label>
            <select id="priority" name="priority" class="form-control">
                <?php foreach (ANNOUNCEMENT_PRIORITIES as $p): ?>
                <option value="<?= e($p) ?>"<?= $form['priority'] === $p ? ' selected' : '' ?>><?= e(announcementPriorityLabel($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="expires_at">Expires (optional)</label>
            <input type="datetime-local" id="expires_at" name="expires_at" class="form-control" value="<?= e($form['expires_at']) ?>">
        </div>

        <div class="form-group span-2">
            <label for="body">Message</label>
            <textarea id="body" name="body" class="form-control" rows="6" required placeholder="Write your announcement…"><?= e($form['body']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="link_url">Action link (optional)</label>
            <input type="url" id="link_url" name="link_url" class="form-control" value="<?= e($form['link_url']) ?>" placeholder="https://…">
        </div>

        <div class="form-group">
            <label for="link_label">Link label</label>
            <input type="text" id="link_label" name="link_label" class="form-control" value="<?= e($form['link_label']) ?>" placeholder="Learn more">
        </div>
    </div>

    <section class="announcement-audience">
        <div class="announcement-audience__head">
            <div>
                <h2><i class="fa-solid fa-users"></i> Recipients</h2>
                <p class="text-muted">Add one or more audience rules. People matching any rule will receive the announcement.</p>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="addAudienceRule"><i class="fa-solid fa-plus"></i> Add rule</button>
        </div>

        <div id="audienceRules" data-initial="<?= e(json_encode($existingTargets, JSON_UNESCAPED_UNICODE)) ?>"></div>

        <p class="announcement-recipient-estimate" id="recipientEstimate" hidden>
            <i class="fa-solid fa-user-check"></i> <span id="recipientEstimateCount">0</span> unique recipient(s) will receive this announcement.
        </p>
    </section>

    <div class="announcement-form-actions">
        <a href="<?= url('school/announcements.php') ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" name="form_action" value="draft" class="btn btn-secondary">Save draft</button>
        <button type="submit" name="form_action" value="publish" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish now</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
