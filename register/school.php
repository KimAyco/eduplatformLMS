<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) {
    redirectByRole();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $schoolName = trim($_POST['school_name'] ?? '');
    $schoolCode = normalizeSchoolCode($_POST['school_code'] ?? '');
    $schoolEmail = trim($_POST['school_email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $adminFirst = trim($_POST['admin_first_name'] ?? '');
    $adminLast = trim($_POST['admin_last_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($schoolName === '') $errors[] = 'School name is required.';
    $codeError = validateSchoolCode($schoolCode);
    if ($codeError !== null) {
        $errors[] = $codeError;
    } elseif (isSchoolCodeTaken($schoolCode)) {
        $errors[] = 'School code already taken. Please use another one.';
    }
    if ($schoolEmail === '' || !filter_var($schoolEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid school email is required.';
    if ($adminFirst === '' || $adminLast === '') $errors[] = 'Admin first and last name are required.';
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid admin email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $passwordConfirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $slug = generateSlug($schoolName);
        $baseSlug = $slug;
        $i = 1;
        while (true) {
            $check = db()->prepare('SELECT id FROM schools WHERE slug = ?');
            $check->execute([$slug]);
            if (!$check->fetch()) break;
            $slug = $baseSlug . '-' . $i++;
        }

        $checkEmail = db()->prepare('SELECT id FROM users WHERE email = ? AND school_id IS NOT NULL LIMIT 1');
        $checkEmail->execute([$adminEmail]);
        if ($checkEmail->fetch()) {
            $errors[] = 'An account with this admin email already exists.';
        }
    }

    if (empty($errors)) {
        try {
            db()->beginTransaction();

            $stmt = db()->prepare('INSERT INTO schools (name, slug, school_code, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$schoolName, $slug, $schoolCode, $schoolEmail, $phone ?: null, $address ?: null, 'pending']);
            $schoolId = (int) db()->lastInsertId();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (school_id, email, password_hash, role, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$schoolId, $adminEmail, $hash, 'school_admin', $adminFirst, $adminLast, 'active']);

            db()->commit();
            $success = true;
            clearOld();
        } catch (PDOException $e) {
            db()->rollBack();
            if ((int) $e->getCode() === 23000) {
                $errors[] = 'School code already taken. Please use another one.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }

    if (!$success) {
        setOld($_POST);
    }
}

$pageTitle = 'Register School — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <?php require __DIR__ . '/../includes/layout/favicon.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card wide">
        <h1>Register Your School</h1>
        <p class="subtitle">Create a school account. Your registration will be reviewed by the platform administrator.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Registration submitted successfully! You will be able to log in once your school is approved.
            </div>
            <a href="<?= url('login.php') ?>" class="btn btn-primary btn-block">Go to Login</a>
        <?php else: ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-error"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post">
                <?= csrfField() ?>
                <h3 style="margin-bottom:.75rem;font-size:1rem;">School Information</h3>
                <div class="form-group">
                    <label for="school_name">School Name *</label>
                    <input type="text" id="school_name" name="school_name" class="form-control" value="<?= old('school_name') ?>" required>
                </div>
                <div class="form-group">
                    <label for="school_code">School Code *</label>
                    <input type="text" id="school_code" name="school_code" class="form-control school-code-input"
                        value="<?= old('school_code') ?>" required maxlength="20" autocomplete="off" spellcheck="false"
                        placeholder="e.g. TEST-SCHOOL"
                        style="text-transform:uppercase; letter-spacing:0.08em; font-weight:600;">
                    <small class="text-muted">Choose a unique code for your school (letters, numbers, hyphens). Teachers and students use this to sign in.</small>
                </div>
                <div class="form-group">
                    <label for="school_email">School Email *</label>
                    <input type="email" id="school_email" name="school_email" class="form-control" value="<?= old('school_email') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= old('phone') ?>">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?= old('address') ?>">
                    </div>
                </div>

                <h3 style="margin:1.25rem 0 .75rem;font-size:1rem;">School Admin Account</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_first_name">First Name *</label>
                        <input type="text" id="admin_first_name" name="admin_first_name" class="form-control" value="<?= old('admin_first_name') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_last_name">Last Name *</label>
                        <input type="text" id="admin_last_name" name="admin_last_name" class="form-control" value="<?= old('admin_last_name') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="admin_email">Admin Email (login) *</label>
                    <input type="email" id="admin_email" name="admin_email" class="form-control" value="<?= old('admin_email') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="8" required>
                        <small>Minimum 8 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm Password *</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Submit Registration</button>
            </form>
        <?php endif; ?>

        <p class="mt-1 text-muted" style="text-align:center;font-size:.875rem;">
            Already registered? <a href="<?= url('login.php') ?>">Sign in</a> · <a href="<?= url('index.php') ?>">Home</a>
        </p>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
