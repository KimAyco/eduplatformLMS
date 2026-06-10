<?php

function renderDeckPlayer(string $deckJson, string $title = ''): void
{
    $deckJson = trim($deckJson);
    if ($deckJson === '') {
        echo '<p class="text-muted">No slide content available.</p>';
        return;
    }
    ?>
    <div class="deck-player" id="deckPlayer" data-deck="<?= e($deckJson) ?>" tabindex="0">
        <div class="deck-player-toolbar">
            <span class="deck-player-counter">1 / 1</span>
            <div class="deck-player-nav">
                <button type="button" class="btn btn-sm btn-secondary" data-deck-prev aria-label="Previous slide"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="btn btn-sm btn-secondary" data-deck-next aria-label="Next slide"><i class="fa-solid fa-chevron-right"></i></button>
                <button type="button" class="btn btn-sm btn-secondary" data-deck-fullscreen aria-label="Fullscreen"><i class="fa-solid fa-expand"></i></button>
            </div>
        </div>
        <div class="deck-player-canvas" aria-label="<?= e($title) ?>"></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/konva@9.3.6/konva.min.js"></script>
    <script src="<?= url('assets/js/resource-deck-player.js') ?>"></script>
    <?php
}
