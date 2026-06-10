<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
requireSchoolActive();
requireLibraryAccess();

$user = currentUser();
$sid = schoolId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['form_action'] ?? '';

    if ($postAction === 'attach_to_class') {
        $libraryId = (int) ($_POST['library_id'] ?? 0);
        $classId = (int) ($_POST['class_id'] ?? 0);
        $sectionId = (int) ($_POST['section_id'] ?? 0) ?: null;

        requireClassAccess($classId, 'teacher');

        try {
            LibraryResourceRepository::attachToClass($libraryId, $classId, $sectionId, (int) $user['id'], $sid);
            flash('success', 'Resource added to your class.');
            redirect('teacher/course.php?id=' . $classId);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect('teacher/library.php');
        }
    }
}

$filters = [
    'browse_role' => 'teacher',
    'search' => trim($_GET['q'] ?? ''),
    'resource_kind' => $_GET['kind'] ?? '',
    'subject_id' => (int) ($_GET['subject_id'] ?? 0) ?: null,
    'type' => $_GET['type'] ?? '',
];
$resources = LibraryResourceRepository::forSchool($sid, $filters);
$subjects = SubjectRepository::forSchool($sid);
$teacherClasses = ClassRepository::forTeacher((int) $user['id'], $sid);
$attachClassId = (int) ($_GET['attach_class'] ?? 0);

$pageTitle = 'Virtual Library';
$pageHeading = 'Virtual Library';
$pageSubtitle = 'Browse school resources and add them to your classes.';
$activeMenu = 'library';
$menuItems = teacherMenu();
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'teacher/dashboard.php'],
    ['label' => 'Virtual Library', 'url' => ''],
];

require __DIR__ . '/../includes/layout/dashboard_header.php';
require __DIR__ . '/../includes/layout/library_grid.php';
?>

<?php renderLibraryFilters('teacher/library.php', $subjects, $filters); ?>
<?php renderLibraryGrid($resources, 'teacher'); ?>
<?php renderLibraryAttachModal($teacherClasses); ?>

<script>
(function () {
    var dialog = document.getElementById('libraryAttachDialog');
    if (!dialog) return;
    var idInput = document.getElementById('libraryAttachId');
    var titleEl = document.getElementById('libraryAttachTitle');
    var classSelect = document.getElementById('libraryAttachClass');
    var sectionSelect = document.getElementById('libraryAttachSection');

    function syncSections() {
        var opt = classSelect.options[classSelect.selectedIndex];
        sectionSelect.innerHTML = '<option value="">Unassigned</option>';
        if (!opt || !opt.dataset.sections) return;
        try {
            JSON.parse(opt.dataset.sections).forEach(function (section) {
                var o = document.createElement('option');
                o.value = section.id;
                o.textContent = section.title;
                sectionSelect.appendChild(o);
            });
        } catch (e) {}
    }

    document.querySelectorAll('[data-library-attach]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            idInput.value = btn.getAttribute('data-library-attach');
            titleEl.textContent = btn.getAttribute('data-library-title') || '';
            classSelect.value = '';
            syncSections();
            dialog.showModal();
        });
    });

    classSelect.addEventListener('change', syncSections);

    document.querySelectorAll('[data-close-attach]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.close(); });
    });

    <?php if ($attachClassId): ?>
    if (classSelect.querySelector('option[value="<?= $attachClassId ?>"]')) {
        classSelect.value = '<?= $attachClassId ?>';
        syncSections();
    }
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>
