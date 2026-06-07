<?php

function processSchoolStatusAction(string $redirectPath): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    verifyCsrf();

    $schoolId = (int) ($_POST['school_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $user = currentUser();

    $stmt = db()->prepare('SELECT * FROM schools WHERE id = ?');
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();

    if (!$school) {
        flash('error', 'School not found.');
        redirect($redirectPath);
    }

    if ($action === 'approve') {
        db()->prepare("UPDATE schools SET status = 'active', approved_at = NOW(), approved_by = ? WHERE id = ?")
            ->execute([$user['id'], $schoolId]);
        flash('success', 'School "' . $school['name'] . '" has been approved.');
    } elseif ($action === 'reject') {
        db()->prepare("UPDATE schools SET status = 'rejected' WHERE id = ?")->execute([$schoolId]);
        flash('success', 'School "' . $school['name'] . '" has been rejected.');
    } elseif ($action === 'suspend') {
        db()->prepare("UPDATE schools SET status = 'suspended' WHERE id = ?")->execute([$schoolId]);
        flash('success', 'School "' . $school['name'] . '" has been suspended.');
    } elseif ($action === 'reactivate') {
        db()->prepare("UPDATE schools SET status = 'active', approved_at = NOW(), approved_by = ? WHERE id = ?")
            ->execute([$user['id'], $schoolId]);
        flash('success', 'School "' . $school['name'] . '" has been reactivated.');
    } else {
        flash('error', 'Invalid action.');
    }

    redirect($redirectPath);
}

function schoolStatusActionButtons(array $school): string
{
    ob_start();

    if ($school['status'] === 'pending') {
        ?>
        <form method="post" class="inline-form superadmin-action-form">
            <?= csrfField() ?>
            <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
            <button type="submit" name="action" value="approve" class="superadmin-icon-btn superadmin-icon-btn--success" title="Approve">
                <i class="fa-solid fa-check"></i>
            </button>
            <button type="submit" name="action" value="reject" class="superadmin-icon-btn superadmin-icon-btn--danger" title="Reject">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </form>
        <?php
    } elseif ($school['status'] === 'active') {
        ?>
        <form method="post" class="inline-form superadmin-action-form">
            <?= csrfField() ?>
            <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
            <button type="submit" name="action" value="suspend" class="superadmin-icon-btn superadmin-icon-btn--danger" title="Suspend">
                <i class="fa-solid fa-ban"></i>
            </button>
        </form>
        <?php
    } elseif (in_array($school['status'], ['suspended', 'rejected'], true)) {
        ?>
        <form method="post" class="inline-form superadmin-action-form">
            <?= csrfField() ?>
            <input type="hidden" name="school_id" value="<?= (int) $school['id'] ?>">
            <button type="submit" name="action" value="reactivate" class="superadmin-icon-btn superadmin-icon-btn--success" title="Reactivate">
                <i class="fa-solid fa-rotate-right"></i>
            </button>
        </form>
        <?php
    }

    return ob_get_clean();
}
