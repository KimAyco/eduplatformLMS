<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();

$user = currentUser();
$id = (int) ($_GET['id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);

$mat = MaterialRepository::findForTeacher($id, $user['id']);
if (!$mat || $mat['type'] !== 'doc') {
    flash('error', 'Document not found.');
    redirect($classId ? 'teacher/course.php?id=' . $classId : 'teacher/dashboard.php');
}

$classId = $classId ?: (int) $mat['class_id'];
requireClassAccess($classId, 'teacher');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $content = sanitizeHtml($_POST['content'] ?? '');
    $sectionId = CourseSectionRepository::resolveSectionId((int) ($_POST['section_id'] ?? 0), $classId);

    if ($title === '') {
        $errors[] = 'Title is required.';
    } else {
        MaterialRepository::update($id, [
            'type' => 'doc',
            'title' => $title,
            'content' => $content,
            'body' => trim($_POST['body'] ?? '') ?: null,
            'file_path' => null,
            'external_link' => null,
            'file_access_mode' => 'downloadable',
            'section_id' => $sectionId,
        ]);
        flash('success', 'Document saved.');
        redirect('teacher/course.php?id=' . $classId);
    }
}

$sections = CourseSectionRepository::forClass($classId);

$pageTitle = 'Edit document — ' . $mat['title'];
$pageHeading = 'Document editor';
$activeMenu = 'classes';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => 'Course', 'url' => 'teacher/course.php?id=' . $classId],
    ['label' => 'Document', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">

<div class="material-editor-wrap">
    <div class="material-editor-head">
        <div>
            <h1>Document editor</h1>
            <p class="text-muted">Write and format content for your course.</p>
        </div>
        <a href="<?= url('teacher/course.php?id=' . $classId) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to course</a>
    </div>

    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" class="material-editor-form">
        <?= csrfField() ?>
        <div class="form-group"><label>Title</label><input name="title" class="form-control" value="<?= e($mat['title']) ?>" required></div>
        <?php if (!empty($sections)): ?>
        <div class="form-group">
            <label>Lesson section</label>
            <select name="section_id" class="form-control">
                <?= courseSectionOptions($sections, (int) ($mat['section_id'] ?? 0) ?: null) ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group"><label>Short description (optional)</label><input name="body" class="form-control" value="<?= e($mat['body'] ?? '') ?>"></div>
        <div class="form-group">
            <label>Content</label>
            <div id="quillEditor" class="material-quill-editor"><?= sanitizeHtml($mat['content'] ?? '') ?></div>
            <input type="hidden" name="content" id="docContentHidden">
        </div>
        <div class="course-form-actions">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save document</button>
            <a href="<?= url('teacher/course.php?id=' . $classId) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
(function () {
    var quill = new Quill('#quillEditor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'blockquote', 'code-block'],
                ['clean']
            ]
        }
    });
    document.querySelector('.material-editor-form').addEventListener('submit', function () {
        document.getElementById('docContentHidden').value = quill.root.innerHTML;
    });
})();
</script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
