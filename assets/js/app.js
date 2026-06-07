document.addEventListener('DOMContentLoaded', function () {
    hidePageLoader();
    initDrawer();
    initUserMenu();
    initNotificationMenu();
    initToasts();
    initAlertAutoDismiss();
    initPageLoader();
    initConfirmForms();
    initPasswordToggles();
    initSchoolCodeSuggestion();
    initSchoolSearch();
    initSchoolCarousel();
    initLandingAnimations();
    initStudentTasks();
    initCourseAccordions();
    initActivityModal();
    initCourseSettingsModal();
    initSamePageHashLinks();
    initCoursePage();
    initClickableTableRows();
    initImageUploadPreviews();
});

function initDrawer() {
    var drawer = document.getElementById('moodleDrawer');
    var overlay = document.getElementById('drawerOverlay');
    var toggle = document.getElementById('drawerToggle');

    if (toggle && drawer) {
        toggle.addEventListener('click', function () {
            drawer.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open');
        });
    }
    if (overlay && drawer) {
        overlay.addEventListener('click', function () {
            drawer.classList.remove('open');
            overlay.classList.remove('open');
        });
    }
}

function initUserMenu() {
    var userBtn = document.getElementById('userMenuBtn');
    var dropdown = document.getElementById('userDropdown');

    if (userBtn && dropdown) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('open');
            var notifDrop = document.getElementById('notifDropdown');
            if (notifDrop) notifDrop.classList.remove('open');
        });
        document.addEventListener('click', function () {
            dropdown.classList.remove('open');
        });
    }
}

function initNotificationMenu() {
    var btn = document.getElementById('notifBtn');
    var dropdown = document.getElementById('notifDropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
        var userDrop = document.getElementById('userDropdown');
        if (userDrop) userDrop.classList.remove('open');
    });
    document.addEventListener('click', function () {
        dropdown.classList.remove('open');
    });
}

function initToasts() {
    document.querySelectorAll('[data-toast]').forEach(function (toast) {
        var close = toast.querySelector('.toast-close');
        if (close) {
            close.addEventListener('click', function () {
                toast.remove();
            });
        }
        setTimeout(function () {
            toast.classList.add('fade-out');
            setTimeout(function () { toast.remove(); }, 400);
        }, 5000);
    });
}

function initAlertAutoDismiss() {
    document.querySelectorAll('.alert-success[data-auto-dismiss]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 300);
        }, 6000);
    });
}

function showPageLoader() {
    var loader = document.getElementById('pageLoader');
    if (loader) loader.classList.add('is-active');
}

function hidePageLoader() {
    var loader = document.getElementById('pageLoader');
    if (loader) loader.classList.remove('is-active');
}

function initPageLoader() {
    document.querySelectorAll('a[href]').forEach(function (link) {
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || link.target === '_blank' || link.hasAttribute('download')) return;
        if (href.indexOf('javascript:') === 0) return;
        if (link.hostname && link.hostname !== window.location.hostname) return;
        if (isSamePageHashLink(link)) return;

        link.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            showPageLoader();
        });
    });

    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (form) {
        if (form.hasAttribute('data-no-loader')) return;
        form.addEventListener('submit', function () {
            showPageLoader();
        });
    });
}

function showConfirm(message, onConfirm) {
    var overlay = document.getElementById('confirmOverlay');
    var msgEl = document.getElementById('confirmMessage');
    var okBtn = document.getElementById('confirmOk');
    var cancelBtn = document.getElementById('confirmCancel');
    if (!overlay || !msgEl || !okBtn || !cancelBtn) {
        if (window.confirm(message)) onConfirm();
        return;
    }

    msgEl.textContent = message;
    overlay.hidden = false;

    function cleanup() {
        overlay.hidden = true;
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onOverlay);
    }

    function onOk() {
        cleanup();
        onConfirm();
    }
    function onCancel() { cleanup(); }
    function onOverlay(e) {
        if (e.target === overlay) cleanup();
    }

    okBtn.addEventListener('click', onOk);
    cancelBtn.addEventListener('click', onCancel);
    overlay.addEventListener('click', onOverlay);
}

function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = form.getAttribute('data-confirm') || 'Are you sure?';
            showConfirm(msg, function () {
                form.removeAttribute('data-confirm');
                form.submit();
            });
        });
    });
}

function initCoursePage() {
    var formPanel = document.getElementById('courseFormPanel');
    if (formPanel) {
        formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        var firstInput = formPanel.querySelector('input[name="title"], textarea');
        if (firstInput) {
            firstInput.focus();
        }
    }
}

function initSchoolSearch() {
    var input = document.getElementById('schoolSearch');
    var grid = document.getElementById('schoolGrid');
    var emptyState = document.getElementById('schoolSearchEmpty');
    if (!input || !grid) return;

    var PIN_KEY = 'lms_pinned_schools';
    var wraps = Array.from(grid.querySelectorAll('.school-card-wrap'));

    function getPinnedIds() {
        try {
            var raw = JSON.parse(localStorage.getItem(PIN_KEY) || '[]');
            return Array.isArray(raw) ? raw.map(String) : [];
        } catch (e) {
            return [];
        }
    }

    function setPinnedIds(ids) {
        localStorage.setItem(PIN_KEY, JSON.stringify(ids));
    }

    function isPinned(wrap) {
        return wrap.classList.contains('is-pinned');
    }

    function cardMatches(wrap, query) {
        var card = wrap.querySelector('[data-search]');
        var haystack = (card && card.getAttribute('data-search') || '').toLowerCase();
        return query === '' || haystack.indexOf(query) !== -1;
    }

    function updatePinButton(wrap) {
        var btn = wrap.querySelector('.school-card-pin');
        if (!btn) return;
        var pinned = isPinned(wrap);
        btn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        btn.setAttribute('aria-label', pinned ? 'Unpin school' : 'Pin school to front');
        btn.title = pinned ? 'Unpin' : 'Pin to front';
        btn.classList.toggle('is-active', pinned);
    }

    function reorderGrid() {
        var pinnedIds = getPinnedIds();
        var items = Array.from(grid.querySelectorAll(':scope > .school-card-wrap'));

        items.sort(function (a, b) {
            var aId = String(a.getAttribute('data-school-id') || '');
            var bId = String(b.getAttribute('data-school-id') || '');
            var aPin = pinnedIds.indexOf(aId);
            var bPin = pinnedIds.indexOf(bId);

            if (aPin !== -1 && bPin !== -1) return aPin - bPin;
            if (aPin !== -1) return -1;
            if (bPin !== -1) return 1;
            return Number(a.getAttribute('data-order') || 0) - Number(b.getAttribute('data-order') || 0);
        });

        items.forEach(function (item) {
            grid.appendChild(item);
        });
    }

    function applyPinState() {
        var pinnedIds = getPinnedIds();

        wraps.forEach(function (wrap) {
            var id = String(wrap.getAttribute('data-school-id') || '');
            wrap.classList.toggle('is-pinned', pinnedIds.indexOf(id) !== -1);
            updatePinButton(wrap);
        });

        reorderGrid();
    }

    function togglePin(wrap) {
        var id = String(wrap.getAttribute('data-school-id') || '');
        if (!id) return;

        var pinnedIds = getPinnedIds();
        var idx = pinnedIds.indexOf(id);
        if (idx === -1) {
            pinnedIds.push(id);
        } else {
            pinnedIds.splice(idx, 1);
        }
        setPinnedIds(pinnedIds);
        applyPinState();
        filterSchools();
        grid.scrollLeft = 0;
    }

    function filterSchools() {
        var query = input.value.trim().toLowerCase();
        var visibleCount = 0;

        Array.from(grid.querySelectorAll(':scope > .school-card-wrap')).forEach(function (wrap) {
            var card = wrap.querySelector('[data-search]');
            var visible = cardMatches(wrap, query);
            wrap.hidden = !visible;
            wrap.classList.toggle('school-card--filtered-out', !visible);
            if (card && visible) {
                card.classList.add('is-visible');
                visibleCount++;
            }
        });

        grid.classList.toggle('is-filtering', query !== '');

        if (emptyState) {
            emptyState.hidden = visibleCount > 0 || query === '';
        }
        grid.hidden = visibleCount === 0 && query !== '';

        if (query !== '' && visibleCount > 0) {
            grid.scrollLeft = 0;
        }
    }

    wraps.forEach(function (wrap) {
        var btn = wrap.querySelector('.school-card-pin');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            togglePin(wrap);
        });
    });

    applyPinState();
    filterSchools();

    input.addEventListener('input', filterSchools);
    input.addEventListener('search', filterSchools);
}

function initSchoolCarousel() {
    var grid = document.getElementById('schoolGrid');
    var prev = document.getElementById('schoolCarouselPrev');
    var next = document.getElementById('schoolCarouselNext');
    if (!grid || !prev || !next) return;

    var scrollAmount = 340;
    prev.addEventListener('click', function () {
        grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    });
    next.addEventListener('click', function () {
        grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    });

    grid.addEventListener('wheel', function (e) {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
        window.scrollBy(0, e.deltaY);
        e.preventDefault();
    }, { passive: false });
}

function initLandingAnimations() {
    if (!document.body.classList.contains('landing-page')) return;

    var header = document.querySelector('.public-header');
    if (header) {
        var onScroll = function () {
            document.body.classList.toggle('is-scrolled', window.scrollY > 12);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    var revealEls = document.querySelectorAll('[data-landing-reveal]');
    if (!revealEls.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }

    if (!('IntersectionObserver' in window)) {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { root: null, rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    revealEls.forEach(function (el) { observer.observe(el); });
}

function initStudentTasks() {
    var list = document.getElementById('studentTasksList');
    var search = document.getElementById('taskSearch');
    var sort = document.getElementById('taskSort');
    if (!list) return;

    var items = list.querySelectorAll('.task-item');

    function filterTasks() {
        var query = search ? search.value.trim().toLowerCase() : '';
        var sortVal = sort ? sort.value : 'due';

        var visible = [];
        items.forEach(function (item) {
            var title = (item.dataset.title || '').toLowerCase();
            var matches = query === '' || title.indexOf(query) !== -1;
            item.hidden = !matches;
            if (matches) visible.push(item);
        });

        visible.sort(function (a, b) {
            var dueA = parseInt(a.dataset.due || '0', 10);
            var dueB = parseInt(b.dataset.due || '0', 10);
            if (sortVal === 'title') {
                return (a.dataset.title || '').localeCompare(b.dataset.title || '');
            }
            return dueA - dueB;
        });

        visible.forEach(function (item) {
            list.appendChild(item);
        });
    }

    if (search) search.addEventListener('input', filterTasks);
    if (sort) sort.addEventListener('change', filterTasks);
}

function initCourseAccordions() {
    var storageKey = 'courseAccordionState:' + window.location.pathname + window.location.search;
    var saved = {};
    try {
        saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
    } catch (e) { saved = {}; }

    document.querySelectorAll('.course-section, .course-lesson').forEach(function (section) {
        var id = section.dataset.section || section.dataset.lesson;
        var btn = section.querySelector('[data-accordion-btn]');
        if (!btn) return;

        if (saved[id] !== undefined) {
            section.classList.toggle('is-open', saved[id]);
            btn.setAttribute('aria-expanded', saved[id] ? 'true' : 'false');
        }

        btn.addEventListener('click', function () {
            section.classList.toggle('is-open');
            var isOpen = section.classList.contains('is-open');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            saved[id] = isOpen;
            sessionStorage.setItem(storageKey, JSON.stringify(saved));
        });
    });
}

function isSamePageHashLink(link) {
    var href = link.getAttribute('href');
    if (!href || href.indexOf('#') === -1) return false;

    var url;
    try {
        url = new URL(link.href, window.location.href);
    } catch (e) {
        return false;
    }

    return url.pathname === window.location.pathname
        && url.search === window.location.search;
}

function scrollToPageHash(hash, behavior) {
    if (!hash || hash === '#') return false;
    var target = document.querySelector(hash);
    if (!target) return false;
    target.scrollIntoView({ behavior: behavior || 'smooth', block: 'start' });
    return true;
}

function closeDrawerIfOpen() {
    var drawer = document.getElementById('moodleDrawer');
    var overlay = document.getElementById('drawerOverlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
}

function initSamePageHashLinks() {
    document.querySelectorAll('a[href*="#"]').forEach(function (link) {
        if (!isSamePageHashLink(link)) return;

        link.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            var url = new URL(link.href, window.location.href);
            var hash = url.hash;
            if (!hash) return;

            e.preventDefault();
            closeDrawerIfOpen();
            if (scrollToPageHash(hash)) {
                history.pushState(null, '', hash);
            }
        });
    });

    if (window.location.hash) {
        requestAnimationFrame(function () {
            scrollToPageHash(window.location.hash, 'auto');
        });
    }
}

function initActivityModal() {
    var openBtn = document.getElementById('openActivityModal');
    var overlay = document.getElementById('activityModal');
    var closeBtn = document.getElementById('closeActivityModal');
    if (!openBtn || !overlay) return;

    openBtn.addEventListener('click', function () {
        overlay.hidden = false;
    });

    function close() { overlay.hidden = true; }

    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });
}

function initCourseSettingsModal() {
    var openBtn = document.getElementById('openCourseSettings');
    var overlay = document.getElementById('courseSettingsModal');
    var closeBtn = document.getElementById('closeCourseSettingsModal');
    if (!overlay) return;

    function open() {
        overlay.hidden = false;
        document.body.classList.add('modal-open');
    }

    function close() {
        overlay.hidden = true;
        document.body.classList.remove('modal-open');
    }

    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) close();
    });

    if (new URLSearchParams(window.location.search).get('settings') === '1') {
        open();
    }
}

function initSchoolCodeSuggestion() {
    var nameInput = document.getElementById('school_name');
    var codeInput = document.getElementById('school_code');
    if (!nameInput || !codeInput) return;

    var codeTouched = codeInput.value.trim() !== '';

    codeInput.addEventListener('input', function () {
        codeTouched = true;
        codeInput.value = codeInput.value.toUpperCase();
    });

    nameInput.addEventListener('input', function () {
        if (codeTouched) return;
        var code = nameInput.value.toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 20);
        codeInput.value = code;
    });
}

function initPasswordToggles() {
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.dataset.toggleBound === '1') return;
        input.dataset.toggleBound = '1';

        var parent = input.parentElement;
        if (parent && (parent.classList.contains('password-field') || parent.classList.contains('input-wrap'))) {
            if (parent.classList.contains('input-wrap')) {
                var existing = parent.querySelector('.password-toggle, .input-toggle');
                if (!existing) {
                    addToggleBtn(parent, input);
                }
            }
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'password-field';
        parent.insertBefore(wrap, input);
        wrap.appendChild(input);
        addToggleBtn(wrap, input);
    });
}

function addToggleBtn(wrap, input) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle';
    btn.setAttribute('aria-label', 'Show password');
    btn.innerHTML = '<i class="fa-solid fa-eye"></i>';

    btn.addEventListener('click', function () {
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        btn.innerHTML = visible
            ? '<i class="fa-solid fa-eye"></i>'
            : '<i class="fa-solid fa-eye-slash"></i>';
        btn.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
    });

    wrap.appendChild(btn);
}

function initClickableTableRows() {
    document.querySelectorAll('tr.table-row-link[data-href]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (isTableRowInteractiveTarget(e.target, row)) return;
            window.location.href = row.getAttribute('data-href');
        });
        row.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (isTableRowInteractiveTarget(e.target, row)) return;
            e.preventDefault();
            window.location.href = row.getAttribute('data-href');
        });
    });
}

function isTableRowInteractiveTarget(target, row) {
    var interactive = target.closest('a, button, input, select, textarea, label, .actions');
    return interactive && row.contains(interactive);
}

function initImageUploadPreviews() {
    document.querySelectorAll('input[data-preview-input]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            var panel = input.closest('.panel, .panel-nested, .user-profile-photo-panel, .course-settings-modal, .course-settings-section') || document.body;
            var form = input.closest('[data-upload-preview]') || panel;
            var note = form.querySelector('[data-preview-note]');

            if (!file || !file.type.match(/^image\//)) {
                if (note) note.hidden = true;
                panel.querySelectorAll('[data-preview-upload]').forEach(function (el) {
                    el.hidden = true;
                });
                panel.querySelectorAll('[data-preview-image]').forEach(function (img) {
                    img.removeAttribute('src');
                });
                return;
            }

            var reader = new FileReader();
            reader.onload = function (ev) {
                var url = ev.target.result;
                var type = input.getAttribute('data-preview-type') || 'avatar';
                var scope = input.getAttribute('data-preview-scope') || 'local';

                if (type === 'cover') {
                    document.querySelectorAll('[data-preview-cover]').forEach(function (el) {
                        el.style.backgroundImage = "url('" + url + "')";
                        el.classList.add('is-previewing');
                        if (el.classList.contains('course-hero')) {
                            el.classList.add('course-hero--custom-cover');
                        }
                    });
                    panel.querySelectorAll('.course-appearance-preview-card').forEach(function (el) {
                        el.classList.add('course-card-has-cover');
                    });
                    panel.querySelectorAll('[data-preview-upload]').forEach(function (el) {
                        el.hidden = false;
                    });
                    panel.querySelectorAll('[data-preview-image]').forEach(function (img) {
                        img.src = url;
                    });
                }

                if (type === 'avatar') {
                    var avatarTargets = scope === 'school-brand'
                        ? document.querySelectorAll('[data-preview-avatar]')
                        : panel.querySelectorAll('[data-preview-avatar]');

                    avatarTargets.forEach(function (el) {
                        applyAvatarPreview(el, url);
                        el.classList.add('is-previewing');
                    });
                }

                if (note) note.hidden = false;
            };
            reader.readAsDataURL(file);
        });
    });
}

function applyAvatarPreview(container, url) {
    var el = container;
    if (!el.matches('.school-avatar, .user-profile-avatar, .user-avatar, .table-avatar, .dashboard-welcome-avatar')) {
        el = container.querySelector('.school-avatar, .user-profile-avatar, .user-avatar, .table-avatar, .dashboard-welcome-avatar');
    }
    if (!el) return;

    var isSchool = el.classList.contains('school-avatar');
    el.classList.add(isSchool ? 'school-avatar--image' : 'user-avatar--image');

    var img = el.querySelector('img');
    if (!img) {
        img = document.createElement('img');
        img.className = isSchool ? 'school-avatar-img' : 'user-avatar-img';
        img.alt = '';
        el.textContent = '';
        el.appendChild(img);
    }
    img.src = url;
}
