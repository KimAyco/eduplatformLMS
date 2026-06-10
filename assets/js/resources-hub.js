(function () {
    const grid = document.getElementById('resourcesGrid');
    const bulkBar = document.getElementById('resourcesBulkBar');
    const bulkCount = bulkBar ? bulkBar.querySelector('[data-bulk-count]') : null;
    const bulkIds = document.getElementById('resourcesBulkIds');
    const searchInput = document.querySelector('.resources-filters input[name="q"]');

    if (!grid) return;

    function updateBulk() {
        const checked = grid.querySelectorAll('.resources-bulk-check:checked');
        if (bulkBar) bulkBar.hidden = checked.length === 0;
        if (bulkCount) bulkCount.textContent = String(checked.length);
        if (bulkIds) {
            bulkIds.innerHTML = '';
            checked.forEach(function (cb) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'resource_ids[]';
                input.value = cb.value;
                bulkIds.appendChild(input);
            });
        }
    }

    grid.addEventListener('change', function (e) {
        if (e.target.classList.contains('resources-bulk-check')) updateBulk();
    });

    document.querySelectorAll('[data-resources-view]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const mode = btn.getAttribute('data-resources-view');
            document.querySelectorAll('[data-resources-view]').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            grid.classList.toggle('resources-grid--list', mode === 'list');
        });
    });

    if (searchInput) {
        let timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const q = searchInput.value.trim().toLowerCase();
                grid.querySelectorAll('.resources-card').forEach(function (card) {
                    const title = card.getAttribute('data-resource-title') || '';
                    card.style.display = !q || title.indexOf(q) >= 0 ? '' : 'none';
                });
            }, 200);
        });
    }
})();
