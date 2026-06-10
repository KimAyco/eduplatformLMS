(function () {
    'use strict';

    var app = document.getElementById('docEditorApp');
    if (!app || typeof Quill === 'undefined') return;

    var resourceId = parseInt(app.dataset.resourceId, 10);
    var apiUrl = app.dataset.apiUrl;
    var csrf = app.dataset.csrf;
    var saveStatus = document.getElementById('docSaveStatus');
    var saveTimer = null;
    var dirty = false;

    var quill = new Quill('#quillEditor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ indent: '-1' }, { indent: '+1' }],
                ['link', 'image', 'blockquote', 'code-block'],
                ['clean']
            ]
        }
    });

    quill.getModule('toolbar').addHandler('image', function () {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function () {
            var file = input.files[0];
            if (!file) return;
            var fd = new FormData();
            fd.append('action', 'upload_asset');
            fd.append('resource_id', String(resourceId));
            fd.append('file', file);
            fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf },
                body: fd
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.ok && res.url) {
                    var range = quill.getSelection(true);
                    quill.insertEmbed(range.index, 'image', res.url);
                    quill.setSelection(range.index + 1);
                    markDirty();
                }
            });
        };
        input.click();
    });

    function setSaveStatus(text, state) {
        if (!saveStatus) return;
        saveStatus.textContent = text;
        saveStatus.className = 'deck-save-status' + (state ? ' is-' + state : '');
    }

    function markDirty() {
        dirty = true;
        setSaveStatus('Unsaved changes', 'dirty');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveDoc, 3000);
    }

    function saveDoc() {
        if (!dirty) return;
        setSaveStatus('Saving…', 'saving');
        var title = document.getElementById('docTitleInput').value.trim() || 'Untitled document';
        var description = document.getElementById('docDescriptionInput').value.trim();

        fetch(apiUrl + '?action=save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({
                id: resourceId,
                title: title,
                description: description,
                content: quill.root.innerHTML
            })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) {
                setSaveStatus('Save failed', 'error');
                return;
            }
            dirty = false;
            setSaveStatus('Saved', 'saved');
        }).catch(function () {
            setSaveStatus('Save failed', 'error');
        });
    }

    quill.on('text-change', markDirty);
    document.getElementById('docTitleInput').addEventListener('input', markDirty);
    document.getElementById('docDescriptionInput').addEventListener('input', markDirty);

    window.addEventListener('beforeunload', function (e) {
        if (dirty) {
            saveDoc();
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
