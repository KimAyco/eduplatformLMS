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



$teacher = ClassRepository::getAssignedTeacher($classId);

$courseContent = CourseSectionRepository::loadCourseContent($classId, (int) $user['id']);

$activityCount = $courseContent['activity_count'];



$classTitle = classDisplayName($class);

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



$courseInitial = strtoupper(mb_substr($class['name'], 0, 1));
$coverPreviewUrl = classCoverImageUrl($class);
?>



<div class="course-view course-view--student">

    <section class="course-hero<?= classHasCustomCover($class) ? ' course-hero--custom-cover' : '' ?>" style="background-image: url('<?= e($coverPreviewUrl) ?>')">
        <div class="course-hero-overlay" aria-hidden="true"></div>
        <div class="course-hero-main">

            <a href="<?= url('student/classes.php') ?>" class="course-back-link"><i class="fa-solid fa-arrow-left"></i> My courses</a>

            <div class="course-hero-title-row">

                <div class="course-hero-avatar" aria-hidden="true"><?= e($courseInitial) ?></div>

                <div>

                    <h1 class="course-hero-title"><?= e($class['name']) ?></h1>

                    <div class="course-hero-tags">

                        <?php if ($teacher): ?><span class="course-tag"><i class="fa-solid fa-chalkboard-user"></i> <?= e(trim($teacher['first_name'] . ' ' . $teacher['last_name'])) ?></span><?php endif; ?>

                        <?php if ($class['group_name']): ?><span class="course-tag"><i class="fa-solid fa-layer-group"></i> <?= e($class['group_name']) ?></span><?php endif; ?>

                        <?php if ($class['group_academic_year']): ?><span class="course-tag"><i class="fa-solid fa-calendar"></i> <?= e($class['group_academic_year']) ?></span><?php endif; ?>

                    </div>

                </div>

            </div>

            <?php if ($class['description']): ?><p class="course-hero-desc"><?= e($class['description']) ?></p><?php endif; ?>

        </div>

        <div class="course-hero-stats">

            <div class="course-stat"><strong><?= $activityCount ?></strong><span>Activities</span></div>

            <?php if (!empty($courseContent['sections'])): ?>

            <div class="course-stat"><strong><?= count($courseContent['sections']) ?></strong><span>Lessons</span></div>

            <?php endif; ?>

        </div>

    </section>



    <section class="course-content-section course-content-section--full">
        <?php
        $lessonCount = count($courseContent['sections']);
        $summaryParts = [];
        if ($activityCount > 0) {
            $summaryParts[] = $activityCount . ' activit' . ($activityCount !== 1 ? 'ies' : 'y');
        }
        if ($lessonCount > 0) {
            $summaryParts[] = $lessonCount . ' lesson' . ($lessonCount !== 1 ? 's' : '');
        }
        $contentSummary = $summaryParts ? implode(' · ', $summaryParts) : '';
        ?>
        <div class="course-content-header">
            <div class="course-content-intro">
                <h2>Course content</h2>
                <?php if ($contentSummary): ?><p class="course-content-summary"><?= e($contentSummary) ?></p><?php endif; ?>
            </div>
        </div>

        <?php if ($activityCount === 0 && empty($courseContent['sections'])): ?>

        <div class="course-empty">

            <div class="course-empty-icon"><i class="fa-solid fa-folder-open"></i></div>

            <h3>Nothing here yet</h3>

            <p>Your teacher has not published any materials or activities for this class.</p>

        </div>

        <?php else: ?>

        <div class="course-lessons">

            <?php renderCourseLessonSections($courseContent, $classId, 'student'); ?>

        </div>

        <?php endif; ?>

    </section>

</div>



<?php require __DIR__ . '/../includes/layout/dashboard_footer.php'; ?>

