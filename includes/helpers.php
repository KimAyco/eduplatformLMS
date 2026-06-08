<?php

function siteLogoPath(): string
{
    return 'assets/img/Gemini_Generated_Image_lt629klt629klt62-removebg-preview.png';
}

function siteLogoUrl(): string
{
    return url(siteLogoPath());
}

function siteLogoImg(string $class = 'site-logo', ?string $alt = null): string
{
    return '<img src="' . e(siteLogoUrl()) . '" alt="' . e($alt ?? APP_NAME) . '" class="' . e($class) . '">';
}

function authPanelBrandHtml(?array $school = null): string
{
    if ($school !== null && ($school['status'] ?? '') === 'active') {
        $logoUrl = schoolLogoImageUrl($school);
        $name = (string) ($school['name'] ?? 'School');
        if ($logoUrl !== null) {
            return '<div class="auth-panel-school-logo">'
                . '<img src="' . e($logoUrl) . '" alt="' . e($name) . ' logo" class="auth-panel-school-logo-img">'
                . '</div>';
        }

        return '<div class="auth-panel-school-avatar" aria-hidden="true">' . e(schoolAvatarInitial($school)) . '</div>';
    }

    return siteLogoImg('site-logo site-logo--auth-panel');
}

function siteFaviconPath(): string
{
    return 'assets/img/tab icon.png';
}

function siteFaviconUrl(): string
{
    return url(str_replace(' ', '%20', siteFaviconPath()));
}

function renderSiteFavicon(): void
{
    $faviconUrl = siteFaviconUrl();
    echo '<link rel="icon" type="image/png" href="' . e($faviconUrl) . '">' . "\n";
    echo '    <link rel="apple-touch-icon" href="' . e($faviconUrl) . '">';
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $query = '';
    if (($pos = strpos($path, '?')) !== false) {
        $query = substr($path, $pos);
        $path = substr($path, 0, $pos);
    }

    if ($path === '') {
        return BASE_URL . $query;
    }

    return BASE_URL . '/' . $path . $query;
}

function teacherCourseUrl(int $classId, string $query = ''): string
{
    $url = 'teacher/course.php?id=' . $classId;
    if ($query !== '') {
        $url .= '&' . ltrim($query, '&');
    }
    return url($url);
}

function studentCourseUrl(int $classId): string
{
    return url('student/course.php?id=' . $classId);
}

function schoolPageUrl(?string $schoolCode): string
{
    $code = normalizeSchoolCode($schoolCode ?? '');
    if ($code === '') {
        return url('index.php#schools');
    }

    return url('schools/' . rawurlencode($code));
}

function schoolLoginUrl(?string $schoolCode): string
{
    $code = normalizeSchoolCode($schoolCode ?? '');
    if ($code === '') {
        return url('login.php');
    }

    return url('login.php?code=' . urlencode($code));
}

/** @deprecated Use schoolPageUrl() */
function schoolEnrollUrl(?string $schoolCode): string
{
    return schoolPageUrl($schoolCode);
}

/** @return list<string> */
function schoolDefaultCoverUrls(): array
{
    return [
        url('assets/img/school-covers/default-1.jpg'),
        url('assets/img/school-covers/default-2.jpg'),
        url('assets/img/school-covers/default-3.jpg'),
        url('assets/img/school-covers/default-4.jpg'),
    ];
}

function schoolCoverImageUrl(array $school): string
{
    $custom = trim((string) ($school['cover_image'] ?? ''));
    if ($custom !== '') {
        return url('school-cover.php?file=' . rawurlencode(ltrim($custom, '/')));
    }

    $defaults = schoolDefaultCoverUrls();
    $index = abs((int) ($school['id'] ?? 0)) % count($defaults);

    return $defaults[$index];
}

function uploadSchoolCover(array $file, int $schoolId): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Cover image upload failed.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Cover image must be 5 MB or smaller.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        throw new RuntimeException('Cover image must be JPG, PNG, WebP, or GIF.');
    }

    $dir = UPLOAD_DIR . '/' . $schoolId . '/covers';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'cover-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save cover image.');
    }

    return $schoolId . '/covers/' . $filename;
}

function schoolLogoImageUrl(array $school): ?string
{
    $custom = trim((string) ($school['logo_image'] ?? ''));
    if ($custom !== '') {
        return url('school-logo.php?file=' . rawurlencode(ltrim($custom, '/')));
    }

    return null;
}

function schoolAvatarInitial(array $school): string
{
    return strtoupper(mb_substr($school['name'] ?? 'S', 0, 1));
}

function schoolAvatarHtml(array $school, string $class): string
{
    $logoUrl = schoolLogoImageUrl($school);
    if ($logoUrl !== null) {
        return '<div class="' . $class . ' school-avatar--image" aria-hidden="true">'
            . '<img src="' . e($logoUrl) . '" alt="" class="school-avatar-img"></div>';
    }

    return '<div class="' . $class . '" aria-hidden="true">' . e(schoolAvatarInitial($school)) . '</div>';
}

function uploadSchoolLogo(array $file, int $schoolId): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo image upload failed.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Logo image must be 2 MB or smaller.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        throw new RuntimeException('Logo image must be JPG, PNG, WebP, or GIF.');
    }

    $dir = UPLOAD_DIR . '/' . $schoolId . '/logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'logo-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save logo image.');
    }

    return $schoolId . '/logos/' . $filename;
}

function userUploadPrefix(?int $schoolId): string
{
    return ($schoolId !== null && (int) $schoolId > 0) ? (string) (int) $schoolId : 'platform';
}

function userProfileImageUrl(array $user): ?string
{
    $custom = trim((string) ($user['profile_image'] ?? ''));
    if ($custom === '') {
        return null;
    }

    return url('user-photo.php?file=' . rawurlencode(ltrim($custom, '/')));
}

function uploadUserProfile(array $file, int $userId, ?int $schoolId): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Profile photo upload failed.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Profile photo must be 2 MB or smaller.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        throw new RuntimeException('Profile photo must be JPG, PNG, WebP, or GIF.');
    }

    $prefix = userUploadPrefix($schoolId);
    $dir = UPLOAD_DIR . '/' . $prefix . '/profiles';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'profile-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save profile photo.');
    }

    return $prefix . '/profiles/' . $filename;
}

function removeUserProfileImage(array $user): void
{
    if (!empty($user['profile_image'])) {
        deleteUpload($user['profile_image']);
        db()->prepare('UPDATE users SET profile_image = NULL WHERE id = ?')->execute([(int) $user['id']]);
    }
}

function userAvatarHtml(array $user, string $class = 'user-avatar'): string
{
    $photoUrl = userProfileImageUrl($user);
    $tag = (str_contains($class, 'dashboard-welcome') || str_contains($class, 'user-profile-avatar')) ? 'div' : 'span';

    if ($photoUrl !== null) {
        return '<' . $tag . ' class="' . e($class) . ' user-avatar--image" aria-hidden="true">'
            . '<img src="' . e($photoUrl) . '" alt="" class="user-avatar-img"></' . $tag . '>';
    }

    return '<' . $tag . ' class="' . e($class) . '" aria-hidden="true">' . e(userInitials($user)) . '</' . $tag . '>';
}

function classDisplayName(array $class): string
{
    $name = $class['name'] ?? '';
    $group = $class['group_name'] ?? null;
    return $group ? $name . ' (' . $group . ')' : $name;
}

/** @return list<string> */
function classDefaultCoverUrls(): array
{
    return schoolDefaultCoverUrls();
}

function classCoverImageUrl(array $class): string
{
    $custom = trim((string) ($class['cover_image'] ?? ''));
    if ($custom !== '') {
        return url('class-cover.php?file=' . rawurlencode(ltrim($custom, '/')));
    }

    $defaults = classDefaultCoverUrls();
    $index = abs((int) ($class['id'] ?? 0)) % count($defaults);

    return $defaults[$index];
}

function classHasCustomCover(array $class): bool
{
    return trim((string) ($class['cover_image'] ?? '')) !== '';
}

function uploadClassCover(array $file, int $schoolId, int $classId): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Cover image upload failed.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Cover image must be 5 MB or smaller.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        throw new RuntimeException('Cover image must be JPG, PNG, WebP, or GIF.');
    }

    $dir = UPLOAD_DIR . '/' . $schoolId . '/class-covers';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'cover-' . $classId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save cover image.');
    }

    return $schoolId . '/class-covers/' . $filename;
}

function clearClassCoverCaches(int $classId, int $schoolId): void
{
    $class = ClassRepository::getWithGroup($classId, $schoolId);
    if (!$class) {
        return;
    }

    $teacher = ClassRepository::getAssignedTeacher($classId);
    if ($teacher) {
        forgetCache('teacher_classes_' . $teacher['id']);
    }

    $stmt = db()->prepare('SELECT student_id FROM class_group_students WHERE class_group_id = ?');
    $stmt->execute([(int) $class['class_group_id']]);
    foreach ($stmt->fetchAll() as $row) {
        forgetCache('student_classes_' . $row['student_id']);
    }
}

function redirect(string $path): never
{
    if (preg_match('#^https?://#i', $path) || str_starts_with($path, '/')) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function setOld(array $data): void
{
    $_SESSION['old'] = $data;
}

function clearOld(): void
{
    unset($_SESSION['old']);
}

function formatDate(?string $datetime, string $format = 'M j, Y g:i A'): string
{
    if (!$datetime) {
        return '—';
    }
    return date($format, strtotime($datetime));
}

function generateSlug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function normalizeSchoolCode(string $code): string
{
    return strtoupper(trim($code));
}

function suggestSchoolCodeFromName(string $name): string
{
    $code = strtoupper(trim($name));
    $code = preg_replace('/[^A-Z0-9]+/', '-', $code);
    $code = trim($code, '-');

    return substr($code, 0, 20);
}

function validateSchoolCode(string $code): ?string
{
    $code = normalizeSchoolCode($code);

    if ($code === '') {
        return 'School code is required.';
    }
    if (strlen($code) < 3) {
        return 'School code must be at least 3 characters.';
    }
    if (strlen($code) > 20) {
        return 'School code must be 20 characters or fewer.';
    }
    if (!preg_match('/^[A-Z0-9-]+$/', $code)) {
        return 'School code may only contain letters, numbers, and hyphens.';
    }
    if ($code[0] === '-' || substr($code, -1) === '-') {
        return 'School code cannot start or end with a hyphen.';
    }

    return null;
}

function isSchoolCodeTaken(string $code, ?int $excludeSchoolId = null): bool
{
    $sql = 'SELECT id FROM schools WHERE UPPER(school_code) = ?';
    $params = [normalizeSchoolCode($code)];

    if ($excludeSchoolId !== null && $excludeSchoolId > 0) {
        $sql .= ' AND id != ?';
        $params[] = $excludeSchoolId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

function getSchoolCode(int $schoolId): string
{
    $stmt = db()->prepare('SELECT school_code FROM schools WHERE id = ?');
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch();

    return ($row && !empty($row['school_code'])) ? $row['school_code'] : '';
}

function uploadFile(array $file, string $subdir): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('File exceeds maximum upload size.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_UPLOAD_EXTENSIONS, true)) {
        throw new RuntimeException('File type not allowed.');
    }

    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    return $subdir . '/' . $filename;
}

function deleteUpload(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = UPLOAD_DIR . '/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        unlink($full);
    }
}

function uploadUrl(?string $relativePath, string $type = 'material'): ?string
{
    if (!$relativePath) {
        return null;
    }
    return downloadUrl($relativePath, $type);
}

function downloadUrl(string $relativePath, string $type = 'material'): string
{
    return url('download.php?type=' . urlencode($type) . '&file=' . urlencode(ltrim($relativePath, '/')));
}

function paginate(int $total, int $page, int $perPage = 20): array
{
    $totalPages = (int) ceil($total / max(1, $perPage));
    $page = max(1, min($page, max(1, $totalPages)));

    return [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

function renderPagination(array $pager, string $baseUrl): string
{
    if ($pager['total_pages'] <= 1) {
        return '';
    }

    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    ob_start();
    echo '<nav class="pagination" aria-label="Pagination">';
    if ($pager['has_prev']) {
        echo '<a href="' . e($baseUrl . $sep . 'page=' . ($pager['page'] - 1)) . '" class="btn btn-sm btn-secondary">Previous</a>';
    }
    echo '<span class="pagination-info">Page ' . (int) $pager['page'] . ' of ' . (int) $pager['total_pages'] . '</span>';
    if ($pager['has_next']) {
        echo '<a href="' . e($baseUrl . $sep . 'page=' . ($pager['page'] + 1)) . '" class="btn btn-sm btn-secondary">Next</a>';
    }
    echo '</nav>';
    return ob_get_clean();
}

function tableUserCell(string $firstName, string $lastName, ?array $user = null): string
{
    $name = trim($firstName . ' ' . $lastName);
    if ($user !== null) {
        return '<div class="table-user-cell">' . userAvatarHtml($user, 'table-avatar') . '<span class="table-user-name">' . e($name) . '</span></div>';
    }

    $initials = strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
    return '<div class="table-user-cell"><span class="table-avatar" aria-hidden="true">' . e($initials) . '</span><span class="table-user-name">' . e($name) . '</span></div>';
}

function tableUserCellLink(string $firstName, string $lastName, string $href): string
{
    $name = trim($firstName . ' ' . $lastName);
    $initials = strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
    return '<a href="' . e($href) . '" class="table-user-link">'
        . '<span class="table-user-cell">'
        . '<span class="table-avatar" aria-hidden="true">' . e($initials) . '</span>'
        . '<span class="table-user-name">' . e($name) . '</span>'
        . '</span></a>';
}

function tableSubjectCell(string $name): string
{
    return '<div class="table-subject-cell"><span class="table-icon-badge"><i class="fa-solid fa-book"></i></span><span>' . e($name) . '</span></div>';
}

function tableGroupCell(string $name, ?string $year = null): string
{
    $html = '<div class="table-group-cell"><span class="table-icon-badge table-icon-badge-group"><i class="fa-solid fa-layer-group"></i></span><span><strong>' . e($name) . '</strong>';
    if ($year) {
        $html .= '<small class="text-muted">' . e($year) . '</small>';
    }
    $html .= '</span></div>';
    return $html;
}

function adminEmptyState(string $icon, string $title, string $text, ?string $ctaHref = null, ?string $ctaLabel = null): string
{
    ob_start();
    ?>
    <div class="admin-empty-state">
        <div class="admin-empty-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
        <h3><?= e($title) ?></h3>
        <p><?= e($text) ?></p>
        <?php if ($ctaHref && $ctaLabel): ?>
            <a href="<?= url($ctaHref) ?>" class="btn btn-primary"><?= e($ctaLabel) ?></a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function sanitizeHtml(?string $html): string
{
    if ($html === null || $html === '') {
        return '';
    }

    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><a><table><thead><tbody><tr><th><td><span><div><hr><sub><sup><code><pre><img>';
    $clean = strip_tags($html, $allowed);

    return preg_replace('/\s(on\w+|style|javascript:|data:)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
}

function materialTypeLabel(string $type): string
{
    return match (normalizeMaterialType($type)) {
        'link' => 'Link',
        'doc' => 'Document',
        default => 'File',
    };
}

function normalizeMaterialType(string $type): string
{
    $type = strtolower(trim($type));
    return in_array($type, ['file', 'link', 'doc'], true) ? $type : 'file';
}

function normalizeExternalUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function youtubeEmbedUrl(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{11})#i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return null;
}

function uploadFileWithMeta(array $file, string $subdir): array
{
    $path = uploadFile($file, $subdir);
    if ($path === null) {
        return ['path' => null, 'original_name' => null, 'mime_type' => null, 'file_size' => 0];
    }
    $full = UPLOAD_DIR . '/' . ltrim($path, '/');
    return [
        'path' => $path,
        'original_name' => $file['name'] ?? basename($path),
        'mime_type' => is_file($full) ? (mime_content_type($full) ?: null) : null,
        'file_size' => is_file($full) ? filesize($full) : 0,
    ];
}

function materialViewUrl(int $materialId): string
{
    return url('material-view.php?id=' . $materialId);
}

function materialDownloadUrl(array $material, bool $inline = false): ?string
{
    if (empty($material['file_path'])) {
        return null;
    }
    $base = downloadUrl($material['file_path'], 'material') . '&material_id=' . (int) $material['id'];
    if ($inline || (($material['file_access_mode'] ?? 'downloadable') === 'view_only')) {
        $base .= '&disposition=inline';
    }
    return $base;
}

function quizAttachmentUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    return downloadUrl($path, 'quiz_attachment');
}

function uploadQuizCover(array $file, int $schoolId, int $quizId): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        throw new RuntimeException('Cover must be an image (JPG, PNG, GIF, or WebP).');
    }
    return uploadFile($file, $schoolId . '/quiz_covers');
}

function quizCoverServeUrl(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    return quizCoverImageUrl(['cover_image' => $path]);
}

function quizEditUrl(int $quizId, int $classId, string $step = 'questions', int $editQuestionId = 0): string
{
    $step = in_array($step, ['questions', 'settings'], true) ? $step : 'questions';
    $query = 'teacher/course.php?id=' . $classId . '&quiz=' . $quizId . '&step=' . $step;
    if ($editQuestionId > 0 && $step === 'questions') {
        $query .= '&edit_q=' . $editQuestionId;
    }
    return url($query);
}

function quizCoverImageUrl(array $quiz): ?string
{
    $custom = trim((string) ($quiz['cover_image'] ?? ''));
    if ($custom === '') {
        return null;
    }

    return url('download.php?type=quiz_cover&file=' . rawurlencode(ltrim($custom, '/')));
}
