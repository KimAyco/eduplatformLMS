<?php

function gradebookCategoryIcon(string $category): string
{
    return match ($category) {
        'quiz' => 'fa-circle-question',
        'exam' => 'fa-file-pen',
        'assignment' => 'fa-pen-to-square',
        'participation' => 'fa-hand',
        'project' => 'fa-folder-open',
        default => 'fa-tag',
    };
}

function gradebookCategoryTone(string $category): string
{
    return match ($category) {
        'quiz' => 'quiz',
        'exam' => 'exam',
        'assignment' => 'assignment',
        'participation' => 'participation',
        'project' => 'project',
        default => 'other',
    };
}

/** @param list<array<string, mixed>> $components @param array<int, array<string, mixed>> $links */
function gradebookSetupStats(array $components, array $links): array
{
    $autoSlots = 0;
    $linked = 0;
    $manual = 0;

    foreach ($components as $component) {
        if (GradebookRepository::isManualCategory($component['category'])) {
            $manual++;
            continue;
        }
        $autoSlots++;
        $componentId = (int) $component['id'];
        $link = $links[$componentId] ?? null;
        if ($link && (!empty($link['quiz_id']) || !empty($link['assignment_id']))) {
            $linked++;
        }
    }

    return [
        'total' => count($components),
        'auto_slots' => $autoSlots,
        'linked' => $linked,
        'manual' => $manual,
        'ready' => $autoSlots === 0 || $linked === $autoSlots,
    ];
}

function gradebookFormatScore(?float $value): string
{
    if ($value === null) {
        return '—';
    }
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

function gradebookFinalTone(?float $percent): string
{
    if ($percent === null) {
        return 'pending';
    }
    if ($percent >= 90) {
        return 'excellent';
    }
    if ($percent >= 75) {
        return 'good';
    }
    if ($percent >= 60) {
        return 'fair';
    }
    return 'low';
}

/** @param list<array<string, mixed>> $components */
function renderGradebookWeightBar(array $components, string $modifier = ''): void
{
    if ($components === []) {
        return;
    }
    ?>
    <div class="gb-weight-bar<?= $modifier ? ' ' . e($modifier) : '' ?>">
        <div class="gb-weight-bar__track" aria-hidden="true">
            <?php foreach ($components as $component):
                $tone = gradebookCategoryTone($component['category']);
                $w = (float) $component['weight_percent'];
            ?>
            <span class="gb-weight-bar__seg gb-weight-bar__seg--<?= e($tone) ?>" style="width: <?= e($w) ?>%" title="<?= e($component['label']) ?> (<?= e($w) ?>%)"></span>
            <?php endforeach; ?>
        </div>
        <ul class="gb-weight-bar__legend">
            <?php foreach ($components as $component):
                $tone = gradebookCategoryTone($component['category']);
            ?>
            <li>
                <span class="gb-weight-bar__dot gb-weight-bar__dot--<?= e($tone) ?>"></span>
                <span><?= e($component['label']) ?></span>
                <strong><?= e($component['weight_percent']) ?>%</strong>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * @param list<array<string, mixed>> $components
 * @param array<int, array<string, mixed>> $links
 * @param list<array<string, mixed>> $quizzes
 * @param list<array<string, mixed>> $assignments
 */
function renderGradebookSyncPanel(array $components, array $links, array $quizzes, array $assignments, int $classId): void
{
    $stats = gradebookSetupStats($components, $links);
    ?>
    <div class="gb-setup">
        <?php if (!$stats['ready'] && $stats['auto_slots'] > 0): ?>
        <div class="gb-setup-alert">
            <i class="fa-solid fa-plug-circle-exclamation" aria-hidden="true"></i>
            <div>
                <strong>Connect your activities</strong>
                <p>Link each grade slot to a quiz or assignment so scores flow into the gradebook automatically. <?= (int) $stats['linked'] ?> of <?= (int) $stats['auto_slots'] ?> slots connected.</p>
            </div>
            <div class="gb-setup-alert__meter" aria-hidden="true">
                <span style="width: <?= $stats['auto_slots'] > 0 ? round(($stats['linked'] / $stats['auto_slots']) * 100) : 0 ?>%"></span>
            </div>
        </div>
        <?php elseif ($stats['total'] > 0): ?>
        <div class="gb-setup-success">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span>All auto-sync slots are connected. Scores update when students complete linked activities.</span>
        </div>
        <?php endif; ?>

        <?php renderGradebookWeightBar($components, 'gb-weight-bar--compact'); ?>

        <form method="post" class="gb-sync-form">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="sync_grading_links">
            <div class="gb-sync-grid">
                <?php foreach ($components as $component):
                    $componentId = (int) $component['id'];
                    $link = $links[$componentId] ?? null;
                    $category = $component['category'];
                    $tone = gradebookCategoryTone($category);
                    $isManual = GradebookRepository::isManualCategory($category);
                    $isLinked = $link && (!empty($link['quiz_id']) || !empty($link['assignment_id']));
                ?>
                <article class="gb-sync-card gb-sync-card--<?= e($tone) ?><?= $isLinked ? ' is-linked' : '' ?><?= $isManual ? ' is-manual' : '' ?>">
                    <div class="gb-sync-card__head">
                        <span class="gb-sync-card__icon"><i class="fa-solid <?= e(gradebookCategoryIcon($category)) ?>" aria-hidden="true"></i></span>
                        <div class="gb-sync-card__title">
                            <strong><?= e($component['label']) ?></strong>
                            <span><?= e(GradebookRepository::categoryLabel($category)) ?> · <?= e($component['weight_percent']) ?>% of final</span>
                        </div>
                        <?php if ($isManual): ?>
                            <span class="gb-sync-card__status gb-sync-card__status--manual">Manual</span>
                        <?php elseif ($isLinked): ?>
                            <span class="gb-sync-card__status gb-sync-card__status--linked"><i class="fa-solid fa-link"></i> Linked</span>
                        <?php else: ?>
                            <span class="gb-sync-card__status gb-sync-card__status--pending">Not linked</span>
                        <?php endif; ?>
                    </div>
                    <div class="gb-sync-card__body">
                        <?php if ($isManual): ?>
                            <p class="gb-sync-card__hint">Enter scores directly in the gradebook table.</p>
                        <?php elseif (in_array($category, ['quiz', 'exam'], true)): ?>
                            <label class="sr-only" for="link_quiz_<?= $componentId ?>">Link quiz for <?= e($component['label']) ?></label>
                            <select id="link_quiz_<?= $componentId ?>" name="link_quiz[<?= $componentId ?>]" class="form-control">
                                <option value="">Choose a quiz…</option>
                                <?php foreach ($quizzes as $q): ?>
                                <option value="<?= (int) $q['id'] ?>" <?= $link && (int) ($link['quiz_id'] ?? 0) === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($quizzes === []): ?>
                                <p class="gb-sync-card__hint">No quizzes in this class yet. Create one in the course builder.</p>
                            <?php endif; ?>
                        <?php elseif ($category === 'assignment'): ?>
                            <label class="sr-only" for="link_assignment_<?= $componentId ?>">Link assignment for <?= e($component['label']) ?></label>
                            <select id="link_assignment_<?= $componentId ?>" name="link_assignment[<?= $componentId ?>]" class="form-control">
                                <option value="">Choose an assignment…</option>
                                <?php foreach ($assignments as $a): ?>
                                <option value="<?= (int) $a['id'] ?>" <?= $link && (int) ($link['assignment_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($assignments === []): ?>
                                <p class="gb-sync-card__hint">No assignments in this class yet.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <div class="gb-sync-form__actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Save &amp; sync scores</button>
            </div>
        </form>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $gradebook
 */
function renderGradebookTable(array $gradebook, int $classId, bool $editable = false): void
{
    $components = $gradebook['components'];
    $links = $gradebook['links'];
    $rows = $gradebook['rows'];

    if ($components === []) {
        echo '<div class="gb-empty-scheme"><div class="gb-empty-scheme__icon"><i class="fa-solid fa-scale-balanced"></i></div><h3>No grading scheme yet</h3><p>Ask your school admin to configure grade components for this subject under <strong>Subjects</strong>.</p></div>';
        return;
    }

    if ($rows === []) {
        echo '<p class="text-muted">No enrolled students.</p>';
        return;
    }
    ?>
    <div class="gb-table-panel">
        <div class="gb-table-toolbar">
            <label class="gb-table-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" class="form-control" placeholder="Search students…" data-gradebook-search autocomplete="off">
            </label>
            <span class="gb-table-toolbar__count"><?= count($rows) ?> student<?= count($rows) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="gb-table-wrap">
            <table class="gb-table">
                <thead>
                    <tr>
                        <th class="gb-table__student-col">Student</th>
                        <?php foreach ($components as $component):
                            $componentId = (int) $component['id'];
                            $link = $links[$componentId] ?? null;
                            $linked = $link && (!empty($link['quiz_id']) || !empty($link['assignment_id']));
                            $tone = gradebookCategoryTone($component['category']);
                        ?>
                        <th class="gb-table__comp-col">
                            <div class="gb-table__comp-head gb-table__comp-head--<?= e($tone) ?>">
                                <i class="fa-solid <?= e(gradebookCategoryIcon($component['category'])) ?>" aria-hidden="true"></i>
                                <span class="gb-table__comp-name"><?= e($component['label']) ?></span>
                                <span class="gb-table__comp-weight"><?= e($component['weight_percent']) ?>%</span>
                                <?php if (!$linked && !GradebookRepository::isManualCategory($component['category'])): ?>
                                <span class="gb-table__comp-warn" title="Not linked"><i class="fa-solid fa-link-slash"></i></span>
                                <?php endif; ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                        <th class="gb-table__final-col">Final</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $student = $row['student'];
                    $cells = $row['cells'];
                    $searchText = strtolower($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['email']);
                    $finalTone = gradebookFinalTone($row['final_percent']);
                ?>
                    <tr class="gb-table__row" data-gradebook-row="<?= e($searchText) ?>">
                        <td class="gb-table__student-col">
                            <div class="gb-table__student">
                                <?= userAvatarHtml($student, 'gb-table__avatar') ?>
                                <div>
                                    <strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong>
                                    <span><?= e($student['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($components as $component):
                            $componentId = (int) $component['id'];
                            $cell = $cells[$componentId] ?? null;
                            $isManualCategory = GradebookRepository::isManualCategory($component['category']);
                            $isOverridden = $cell && (int) ($cell['is_manual'] ?? 0) === 1 && !$isManualCategory;
                            $pct = ($cell && $cell['percent'] !== null && $cell['percent'] !== '') ? (float) $cell['percent'] : null;
                            $state = 'empty';
                            if ($pct !== null) {
                                $state = $pct >= 100 ? 'full' : ($pct > 0 ? 'partial' : 'zero');
                            }
                        ?>
                        <td class="gb-table__cell gb-table__cell--<?= e($state) ?><?= $isOverridden ? ' gb-table__cell--override' : '' ?>">
                            <?php if ($editable): ?>
                                <div class="gb-cell-edit<?= $isOverridden ? ' gb-cell-edit--override' : '' ?>">
                                    <input type="number" step="0.01" min="0" max="100"
                                        name="gradebook[<?= (int) $student['id'] ?>][<?= $componentId ?>]"
                                        class="form-control gb-manual-input<?= $isOverridden ? ' gb-manual-input--override' : '' ?>"
                                        value="<?= $pct !== null ? e($pct) : '' ?>"
                                        placeholder="—"
                                        aria-label="<?= e($component['label']) ?> for <?= e($student['first_name']) ?>">
                                    <?php if ($isOverridden): ?>
                                        <span class="gb-cell-badge gb-cell-badge--override" title="You entered this score manually">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edited
                                        </span>
                                    <?php elseif (!$isManualCategory && $pct !== null): ?>
                                        <span class="gb-cell-sync-hint" title="Synced from linked activity">
                                            <?php if ($cell && $cell['score'] !== null && $cell['max_score'] !== null): ?>
                                            <?= e(gradebookFormatScore((float) $cell['score'])) ?>/<?= e(gradebookFormatScore((float) $cell['max_score'])) ?>
                                            <?php else: ?>
                                            <?= e($pct) ?>%
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($pct !== null): ?>
                                <div class="gb-cell-score">
                                    <?php if ($cell && $cell['score'] !== null && $cell['max_score'] !== null): ?>
                                    <span class="gb-cell-score__raw"><?= e(gradebookFormatScore((float) $cell['score'])) ?>/<?= e(gradebookFormatScore((float) $cell['max_score'])) ?></span>
                                    <?php endif; ?>
                                    <span class="gb-cell-score__pct"><?= e($pct) ?>%</span>
                                    <?php if ($isOverridden): ?>
                                    <span class="gb-cell-badge gb-cell-badge--override"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edited</span>
                                    <?php endif; ?>
                                    <span class="gb-cell-bar" aria-hidden="true"><span style="width: <?= min(100, $pct) ?>%"></span></span>
                                </div>
                            <?php else: ?>
                                <span class="gb-cell-empty">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="gb-table__final-col">
                            <span class="gb-final gb-final--<?= e($finalTone) ?>">
                                <?= $row['final_percent'] !== null ? e($row['final_percent']) . '%' : '—' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * @param array<string, mixed> $gradebook
 * @param array<string, mixed> $class
 */
function renderStudentGradeCardView(array $gradebook, array $class, array $user): void
{
    $components = $gradebook['components'];
    $row = $gradebook['rows'][0] ?? null;
    $cells = $row['cells'] ?? [];
    $final = $row['final_percent'] ?? null;
    $finalTone = gradebookFinalTone($final);

    if ($components === []) {
        echo '<div class="gb-empty-scheme"><div class="gb-empty-scheme__icon"><i class="fa-solid fa-scale-balanced"></i></div><h3>Grade card not ready</h3><p>Your school has not set up a grading scheme for this subject yet.</p></div>';
        return;
    }
    ?>
    <div class="gb-student-card">
        <div class="gb-student-card__hero gb-student-card__hero--<?= e($finalTone) ?>">
            <div class="gb-student-card__ring" style="--gb-pct: <?= $final !== null ? (int) round($final) : 0 ?>">
                <span class="gb-student-card__ring-value"><?= $final !== null ? e($final) : '—' ?></span>
                <span class="gb-student-card__ring-label"><?= $final !== null ? '%' : 'pending' ?></span>
            </div>
            <div class="gb-student-card__hero-text">
                <p class="gb-student-card__subject"><?= e($class['name']) ?></p>
                <h2><?= e(classDisplayName($class)) ?></h2>
                <p><?= $final !== null ? 'Your current weighted grade based on completed components.' : 'Your grade will appear as teachers score each component.' ?></p>
            </div>
        </div>

        <?php renderGradebookWeightBar($components, 'gb-weight-bar--student'); ?>

        <div class="gb-student-breakdown">
            <?php foreach ($components as $component):
                $componentId = (int) $component['id'];
                $cell = $cells[$componentId] ?? null;
                $pct = ($cell && $cell['percent'] !== null && $cell['percent'] !== '') ? (float) $cell['percent'] : null;
                $tone = gradebookCategoryTone($component['category']);
                $weighted = $pct !== null ? round($pct * ((float) $component['weight_percent'] / 100), 2) : null;
                $isOverridden = $cell && (int) ($cell['is_manual'] ?? 0) === 1
                    && !GradebookRepository::isManualCategory($component['category']);
            ?>
            <article class="gb-breakdown-item gb-breakdown-item--<?= e($tone) ?><?= $pct === null ? ' is-pending' : '' ?>">
                <div class="gb-breakdown-item__icon"><i class="fa-solid <?= e(gradebookCategoryIcon($component['category'])) ?>"></i></div>
                <div class="gb-breakdown-item__main">
                    <div class="gb-breakdown-item__top">
                        <strong><?= e($component['label']) ?></strong>
                        <span><?= e(GradebookRepository::categoryLabel($component['category'])) ?> · <?= e($component['weight_percent']) ?>% weight<?php if ($isOverridden): ?> · <span class="gb-cell-badge gb-cell-badge--override">Teacher adjusted</span><?php endif; ?></span>
                    </div>
                    <?php if ($pct !== null): ?>
                    <div class="gb-breakdown-item__bar" aria-hidden="true"><span style="width: <?= min(100, $pct) ?>%"></span></div>
                    <div class="gb-breakdown-item__scores">
                        <?php if ($cell && $cell['score'] !== null && $cell['max_score'] !== null): ?>
                        <span><?= e(gradebookFormatScore((float) $cell['score'])) ?> / <?= e(gradebookFormatScore((float) $cell['max_score'])) ?> pts</span>
                        <?php endif; ?>
                        <span class="gb-breakdown-item__pct"><?= e($pct) ?>%</span>
                        <?php if ($weighted !== null): ?>
                        <span class="gb-breakdown-item__contrib">+<?= e($weighted) ?> to final</span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <p class="gb-breakdown-item__pending">Not scored yet</p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
