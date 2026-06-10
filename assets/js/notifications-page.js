(function () {
    var btn = document.getElementById('markAllReadBtn');
    if (!btn) return;

    var apiMeta = document.querySelector('meta[name="notifications-api"]');
    var apiUrl = apiMeta ? apiMeta.getAttribute('content') : '';
    if (!apiUrl) return;

    btn.addEventListener('click', function () {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        fetch(apiUrl + '?action=read_all', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-Token': csrf ? csrf.getAttribute('content') : '',
            },
        })
            .then(function () { window.location.reload(); })
            .catch(function () {});
    });
})();
