<?php

function renderPracticeConfigModal(): void
{
    $types = practiceAllowedQuestionTypes();
    $typeIcons = [
        'multiple_choice' => 'fa-list-check',
        'true_false' => 'fa-toggle-on',
        'fill_blank' => 'fa-i-cursor',
        'matching' => 'fa-shuffle',
        'essay' => 'fa-align-left',
    ];
    ?>
    <dialog class="practice-config-dialog" id="practiceConfigDialog">
        <form id="practiceConfigForm" class="practice-config-form">
            <header class="practice-config-header">
                <div>
                    <h2 id="practiceConfigTitle">Configure practice quiz</h2>
                    <p class="text-muted" id="practiceConfigSubtitle"></p>
                </div>
                <button type="button" class="practice-config-close" data-close-practice-config aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>

            <div class="practice-config-body">
                <label class="ai-field">
                    <span class="ai-field__label">Number of questions</span>
                    <div class="ai-count-control">
                        <button type="button" class="ai-count-btn" data-practice-count-delta="-1" aria-label="Decrease">−</button>
                        <input type="number" name="item_count" class="form-control ai-count-input" id="practiceItemCount" min="3" max="30" value="10">
                        <button type="button" class="ai-count-btn" data-practice-count-delta="1" aria-label="Increase">+</button>
                    </div>
                    <input type="range" class="ai-count-slider" id="practiceItemSlider" min="3" max="30" value="10" aria-label="Question count">
                </label>

                <div class="ai-field">
                    <div class="ai-field__row">
                        <span class="ai-field__label">Question types</span>
                        <button type="button" class="btn btn-sm btn-secondary" id="practiceTypesToggleAll">Select all</button>
                    </div>
                    <div class="ai-type-chips" id="practiceTypeChips">
                        <?php foreach ($types as $type):
                            $checked = in_array($type, ['multiple_choice', 'true_false'], true);
                            $icon = $typeIcons[$type] ?? 'fa-circle-question';
                        ?>
                        <label class="ai-type-chip<?= $checked ? ' is-selected' : '' ?>">
                            <input type="checkbox" name="question_types[]" value="<?= e($type) ?>"<?= $checked ? ' checked' : '' ?>>
                            <i class="fa-solid <?= e($icon) ?>"></i>
                            <span><?= e(questionTypeLabel($type)) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <footer class="practice-config-footer">
                <button type="button" class="btn btn-secondary" data-close-practice-config>Cancel</button>
                <button type="submit" class="btn btn-primary" id="practiceConfigSubmit">
                    <i class="fa-solid fa-play"></i> Start practice
                </button>
            </footer>
        </form>
    </dialog>
    <?php
}
