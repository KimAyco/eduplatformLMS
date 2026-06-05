<?php

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return remember('current_user_' . $_SESSION['user_id'], function () {
        $stmt = db()->prepare('SELECT u.*, s.status AS school_status, s.name AS school_name
            FROM users u
            LEFT JOIN schools s ON s.id = u.school_id
            WHERE u.id = ? AND u.status = ?');
        $stmt->execute([$_SESSION['user_id'], 'active']);
        $user = $stmt->fetch() ?: null;

        if (!$user) {
            logoutUser();
            return null;
        }

        if ((int) $user['id'] !== (int) ($_SESSION['user_id'] ?? 0)
            || $user['role'] !== ($_SESSION['role'] ?? '')
            || (string) $user['school_id'] !== (string) ($_SESSION['school_id'] ?? '')) {
            logoutUser();
            return null;
        }

        return $user;
    });
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function loginUser(array $user, ?string $schoolStatus = null): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['school_id'] = $user['school_id'];
    $_SESSION['school_status'] = $schoolStatus ?? ($user['school_status'] ?? null);
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function requireSuperAdmin(): void
{
    requireRole('super_admin');
}

function requireSchoolActive(): void
{
    requireLogin();
    $user = currentUser();
    if ($user['role'] === 'super_admin') {
        return;
    }
    if ($user['school_status'] !== 'active') {
        logoutUser();
        flash('error', 'Your school account is not active. Please contact the platform administrator.');
        redirect('login.php');
    }
}

function schoolId(): ?int
{
    $user = currentUser();
    return $user ? (int) $user['school_id'] : null;
}

function redirectByRole(): never
{
    $user = currentUser();
    match ($user['role']) {
        'super_admin'  => redirect('superadmin/dashboard.php'),
        'school_admin' => redirect('school/dashboard.php'),
        'teacher'      => redirect('teacher/dashboard.php'),
        'student'      => redirect('student/dashboard.php'),
        default        => redirect('login.php'),
    };
}

function authenticate(string $email, string $password, ?string $roleFilter = null, ?int $schoolId = null): ?array
{
    $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    $sql = 'SELECT u.*, s.status AS school_status, s.name AS school_name FROM users u
            LEFT JOIN schools s ON s.id = u.school_id
            WHERE u.email = ? AND u.status = ?';
    $params = [$email, 'active'];

    if ($roleFilter === 'super_admin') {
        $sql .= ' AND u.role = ? AND u.school_id IS NULL';
        $params[] = 'super_admin';
    } elseif ($roleFilter === 'school') {
        $sql .= ' AND u.role IN (?, ?, ?)';
        array_push($params, 'school_admin', 'teacher', 'student');
        if ($schoolId !== null) {
            $sql .= ' AND u.school_id = ?';
            $params[] = $schoolId;
        }
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch();

    $hash = $user['password_hash'] ?? $dummyHash;
    if (!password_verify($password, $hash)) {
        return null;
    }

    if (!$user) {
        return null;
    }

    if ($user['role'] !== 'super_admin') {
        if ($user['school_status'] !== 'active') {
            return ['error' => 'school_inactive', 'school_status' => $user['school_status']];
        }
    }

    return $user;
}

function teacherHasClass(int $classId, ?int $teacherId = null): bool
{
    $teacherId = $teacherId ?? currentUser()['id'];
    $stmt = db()->prepare('SELECT 1 FROM class_teachers WHERE class_id = ? AND teacher_id = ?');
    $stmt->execute([$classId, $teacherId]);
    return (bool) $stmt->fetch();
}

function studentHasClass(int $classId, ?int $studentId = null): bool
{
    $studentId = $studentId ?? currentUser()['id'];
    $stmt = db()->prepare('SELECT 1 FROM class_students WHERE class_id = ? AND student_id = ?');
    $stmt->execute([$classId, $studentId]);
    return (bool) $stmt->fetch();
}

function requireClassAccess(int $classId, string $role): void
{
    requireSchoolActive();
    $sid = schoolId();

    $stmt = db()->prepare('SELECT * FROM classes WHERE id = ? AND school_id = ?');
    $stmt->execute([$classId, $sid]);
    $class = $stmt->fetch();
    if (!$class) {
        http_response_code(404);
        die('Class not found.');
    }

    $user = currentUser();
    if ($user['role'] === 'school_admin') {
        return;
    }
    if ($role === 'teacher' && !teacherHasClass($classId)) {
        http_response_code(403);
        die('Access denied.');
    }
    if ($role === 'student' && !studentHasClass($classId)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function getClass(int $classId): ?array
{
    $stmt = db()->prepare('SELECT * FROM classes WHERE id = ? AND school_id = ?');
    $stmt->execute([$classId, schoolId()]);
    return $stmt->fetch() ?: null;
}

function getTeacherClasses(?int $teacherId = null): array
{
    $teacherId = $teacherId ?? currentUser()['id'];
    return ClassRepository::forTeacher($teacherId, schoolId());
}

function getStudentClasses(?int $studentId = null): array
{
    $studentId = $studentId ?? currentUser()['id'];
    return ClassRepository::forStudent($studentId, schoolId());
}

function userInitials(array $user): string
{
    return strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
}

function resolveLoginSchool(?string $slug, int $id = 0, ?string $code = null): ?array
{
    if ($code !== null && $code !== '') {
        $stmt = db()->prepare('SELECT * FROM schools WHERE UPPER(school_code) = UPPER(?)');
        $stmt->execute([trim($code)]);
        return $stmt->fetch() ?: null;
    }
    if ($slug !== null && $slug !== '') {
        $stmt = db()->prepare('SELECT * FROM schools WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM schools WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    return null;
}

function getActiveSchoolsForLogin(): array
{
    return db()->query("SELECT id, name, slug, school_code FROM schools WHERE status = 'active' ORDER BY name ASC")->fetchAll();
}
