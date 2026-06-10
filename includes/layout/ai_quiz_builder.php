<?php

function renderAiQuizBuilderHero(array $class, string $courseUrl): void
{
    ?>
    <header class="ai-builder-hero">
        <div class="ai-builder-hero__icon" aria-hidden="true">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div class="ai-builder-hero__text">
            <p class="ai-builder-hero__eyebrow">AI-powered · <?= e(classDisplayName($class)) ?></p>
            <h2 class="ai-builder-hero__title">Generate an exam quiz in minutes</h2>
            <p class="ai-builder-hero__desc">Choose a content source, tune difficulty and question types, then review and edit before publishing to your class.</p>
        </div>
        <a href="<?= e($courseUrl) ?>" class="btn btn-secondary btn-sm ai-builder-hero__back">
            <i class="fa-solid fa-arrow-left"></i> Back to course
        </a>
    </header>
    <?php
}

function renderAiQuizBuilderForm(int $classId, array $sections, string $listUrl): void
{
    $types = array_values(array_filter(QUIZ_QUESTION_TYPES, static fn ($t) => $t !== 'file_response'));
    $typeIcons = [
        'multiple_choice' => 'fa-list-check',
        'true_false' => 'fa-toggle-on',
        'fill_blank' => 'fa-i-cursor',
        'matching' => 'fa-shuffle',
        'essay' => 'fa-align-left',
    ];
    ?>
    <form id="aiQuizBuilderForm" class="ai-builder-layout" enctype="multipart/form-data">
        <div class="ai-builder-main">
            <!-- Step 1: Basics -->
            <section class="ai-builder-card" aria-labelledby="ai-step-basics">
                <div class="ai-builder-card__head">
                    <span class="ai-builder-step-num">1</span>
                    <div>
                        <h3 id="ai-step-basics">Quiz details</h3>
                        <p class="text-muted">Name your quiz and optionally tie it to a lesson.</p>
                    </div>
                </div>
                <div class="ai-builder-card__body">
                    <label class="ai-field">
                        <span class="ai-field__label">Quiz title <span class="required">*</span></span>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 3 — Cell Structure Review" required data-preview="title">
                    </label>
                    <label class="ai-field">
                        <span class="ai-field__label">Lesson section <span class="ai-field__hint">(optional)</span></span>
                        <select name="section_id" class="form-control" data-preview="section">
                            <?= courseSectionOptions($sections, null, true) ?>
                        </select>
                    </label>
                </div>
            </section>

            <!-- Step 2: Content source -->
            <section class="ai-builder-card" aria-labelledby="ai-step-source">
                <div class="ai-builder-card__head">
                    <span class="ai-builder-step-num">2</span>
                    <div>
                        <h3 id="ai-step-source">Content source</h3>
                        <p class="text-muted">What should the AI use to write questions?</p>
                    </div>
                </div>
                <div class="ai-builder-card__body">
                    <div class="ai-source-cards" role="radiogroup" aria-label="Content source">
                        <label class="ai-source-card is-selected">
                            <input type="radio" name="source_type" value="lesson" checked>
                            <span class="ai-source-card__icon"><i class="fa-solid fa-book-open"></i></span>
                            <span class="ai-source-card__title">Class lessons</span>
                            <span class="ai-source-card__desc">Materials, library items &amp; exam topics from the selected lesson</span>
                        </label>
                        <label class="ai-source-card">
                            <input type="radio" name="source_type" value="topic">
                            <span class="ai-source-card__icon"><i class="fa-solid fa-pen-nib"></i></span>
                            <span class="ai-source-card__title">Describe a topic</span>
                            <span class="ai-source-card__desc">Type what you want covered — no upload needed</span>
                        </label>
                        <label class="ai-source-card">
                            <input type="radio" name="source_type" value="upload">
                            <span class="ai-source-card__icon"><i class="fa-solid fa-file-arrow-up"></i></span>
                            <span class="ai-source-card__title">Upload document</span>
                            <span class="ai-source-card__desc">PDF, DOCX, or TXT from your computer</span>
                        </label>
                    </div>

                    <div class="ai-source-panel" data-source-panel="lesson">
                        <div class="ai-source-panel__note">
                            <i class="fa-solid fa-circle-info"></i>
                            Uses indexed content from materials attached to the lesson section above. Add resources in course content first for richer questions.
                        </div>
                    </div>

                    <div class="ai-source-panel" data-source-panel="topic" hidden>
                        <label class="ai-field">
                            <span class="ai-field__label">Topic description</span>
                            <textarea name="topic" class="form-control" rows="5" placeholder="Example: Create questions about photosynthesis — light reactions, Calvin cycle, chloroplast structure, and factors affecting rate…" data-preview="topic"></textarea>
                        </label>
                    </div>

                    <div class="ai-source-panel" data-source-panel="upload" hidden>
                        <div class="ai-upload-zone" id="aiUploadZone">
                            <input type="file" name="document" id="aiDocumentInput" accept=".pdf,.docx,.doc,.txt" hidden>
                            <div class="ai-upload-zone__inner">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p><strong>Drop a file here</strong> or <button type="button" class="ai-upload-browse" id="aiUploadBrowse">browse</button></p>
                                <span class="text-muted">PDF, DOCX, TXT · max <?= (int) (MAX_UPLOAD_SIZE / 1048576) ?> MB</span>
                            </div>
                            <div class="ai-upload-file" id="aiUploadFile" hidden>
                                <i class="fa-solid fa-file-lines"></i>
                                <span id="aiUploadFileName"></span>
                                <button type="button" class="ai-upload-clear" id="aiUploadClear" aria-label="Remove file"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Step 3: Configuration -->
            <section class="ai-builder-card" aria-labelledby="ai-step-config">
                <div class="ai-builder-card__head">
                    <span class="ai-builder-step-num">3</span>
                    <div>
                        <h3 id="ai-step-config">Question settings</h3>
                        <p class="text-muted">Control how many items to generate and how hard they should be.</p>
                    </div>
                </div>
                <div class="ai-builder-card__body">
                    <div class="ai-config-row">
                        <label class="ai-field ai-field--grow">
                            <span class="ai-field__label">Number of questions</span>
                            <div class="ai-count-control">
                                <button type="button" class="ai-count-btn" data-count-delta="-1" aria-label="Decrease">−</button>
                                <input type="number" name="item_count" class="form-control ai-count-input" min="3" max="30" value="10" data-preview="count">
                                <button type="button" class="ai-count-btn" data-count-delta="1" aria-label="Increase">+</button>
                            </div>
                            <input type="range" class="ai-count-slider" min="3" max="30" value="10" aria-label="Question count slider">
                        </label>
                    </div>

                    <div class="ai-field">
                        <span class="ai-field__label">Difficulty</span>
                        <div class="ai-difficulty-pills" role="radiogroup" aria-label="Difficulty">
                            <?php foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'mixed' => 'Mixed'] as $val => $label): ?>
                            <label class="ai-difficulty-pill<?= $val === 'medium' ? ' is-selected' : '' ?>">
                                <input type="radio" name="difficulty" value="<?= e($val) ?>"<?= $val === 'medium' ? ' checked' : '' ?> data-preview="difficulty">
                                <span><?= e($label) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ai-field">
                        <div class="ai-field__row">
                            <span class="ai-field__label">Question types</span>
                            <button type="button" class="btn btn-sm btn-secondary" id="aiTypesToggleAll">Select all</button>
                        </div>
                        <div class="ai-type-chips">
                            <?php foreach ($types as $type):
                                $checked = in_array($type, ['multiple_choice', 'true_false'], true);
                                $icon = $typeIcons[$type] ?? 'fa-circle-question';
                            ?>
                            <label class="ai-type-chip<?= $checked ? ' is-selected' : '' ?>">
                                <input type="checkbox" name="question_types[]" value="<?= e($type) ?>"<?= $checked ? ' checked' : '' ?> data-preview-type="<?= e($type) ?>">
                                <i class="fa-solid <?= e($icon) ?>"></i>
                                <span><?= e(questionTypeLabel($type)) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="ai-builder-sidebar">
            <div class="ai-builder-preview panel">
                <h3><i class="fa-solid fa-eye"></i> Preview</h3>
                <dl class="ai-preview-list">
                    <div><dt>Title</dt><dd data-preview-out="title">—</dd></div>
                    <div><dt>Lesson</dt><dd data-preview-out="section">Any / unassigned</dd></div>
                    <div><dt>Source</dt><dd data-preview-out="source">Class lessons</dd></div>
                    <div><dt>Questions</dt><dd data-preview-out="count">10</dd></div>
                    <div><dt>Difficulty</dt><dd data-preview-out="difficulty">Medium</dd></div>
                    <div><dt>Types</dt><dd data-preview-out="types">Multiple choice, True / False</dd></div>
                </dl>
            </div>

            <div class="ai-builder-actions panel">
                <button type="submit" class="btn btn-primary btn-lg ai-generate-btn" id="aiQuizGenerateBtn">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>Generate quiz</span>
                </button>
                <a href="<?= e($listUrl) ?>" class="btn btn-secondary">Cancel</a>
                <p class="ai-builder-footnote text-muted">
                    <i class="fa-solid fa-shield-halved"></i>
                    Questions are AI-generated. Always review and edit before publishing.
                </p>
            </div>

            <div id="aiQuizBuilderStatus" class="ai-builder-progress" hidden>
                <div class="ai-builder-progress__spinner" aria-hidden="true"></div>
                <div>
                    <strong id="aiProgressTitle">Generating questions…</strong>
                    <p id="aiProgressDetail" class="text-muted"></p>
                </div>
            </div>
        </aside>
    </form>
    <?php
}
