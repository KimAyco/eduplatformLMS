(function () {
    var app = document.getElementById('aiAnalyticsApp');
    if (!app || typeof Chart === 'undefined') return;

    var apiUrl = app.dataset.apiUrl;
    var granularity = app.dataset.initialGranularity || 'day';
    var schoolId = 0;
    var chart = null;

    var rangeEl = document.getElementById('aiAnalyticsRange');
    var loadingEl = document.getElementById('aiAnalyticsLoading');
    var schoolSelect = document.getElementById('aiSchoolFilter');
    var tableBody = document.getElementById('aiSchoolTableBody');
    var tablePanel = document.getElementById('aiSchoolTablePanel');
    var trendSubtitle = document.getElementById('aiTrendSubtitle');

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function setLoading(on) {
        if (loadingEl) loadingEl.hidden = !on;
    }

    function updateStats(summary) {
        ['total', 'completed', 'failed', 'schools_active'].forEach(function (key) {
            var el = app.querySelector('[data-analytics-stat="' + key + '"]');
            if (el && summary) {
                el.textContent = summary[key] !== undefined ? String(summary[key]) : '0';
            }
        });
    }

    function populateSchools(schools, selected) {
        if (!schoolSelect) return;
        var html = '<option value="0">All schools</option>';
        (schools || []).forEach(function (s) {
            html += '<option value="' + s.id + '"' + (String(s.id) === String(selected) ? ' selected' : '') + '>' +
                escapeHtml(s.name) + '</option>';
        });
        schoolSelect.innerHTML = html;
    }

    function renderChart(trend) {
        var canvas = document.getElementById('aiUsageChart');
        if (!canvas) return;

        var labels = (trend || []).map(function (p) { return p.label; });
        var completed = (trend || []).map(function (p) { return p.completed; });
        var failed = (trend || []).map(function (p) { return p.failed; });

        if (chart) {
            chart.destroy();
        }

        chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Completed',
                        data: completed,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.12)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 40 ? 0 : 3,
                    },
                    {
                        label: 'Failed',
                        data: failed,
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: labels.length > 40 ? 0 : 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            footer: function (items) {
                                var idx = items[0] && items[0].dataIndex;
                                if (idx === undefined || !trend[idx]) return '';
                                return 'Total: ' + trend[idx].total;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }

    function renderSchoolTable(rows) {
        if (!tableBody) return;
        if (!rows || rows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-muted">No AI usage in this period.</td></tr>';
            return;
        }
        tableBody.innerHTML = rows.map(function (row) {
            var types = (row.job_types || []).slice(0, 3).map(function (t) {
                return '<code>' + escapeHtml(t.job_type) + '</code> (' + t.count + ')';
            }).join(', ') || '—';
            var schoolCell = row.school_id > 0
                ? '<a href="' + escapeHtml(buildSchoolUrl(row.school_id)) + '">' + escapeHtml(row.school_name) + '</a>'
                : escapeHtml(row.school_name);
            return '<tr>' +
                '<td>' + schoolCell + '</td>' +
                '<td>' + row.total + '</td>' +
                '<td>' + row.completed + '</td>' +
                '<td>' + row.failed + '</td>' +
                '<td>' + (row.pending + row.processing) + '</td>' +
                '<td class="ai-job-types-cell">' + types + '</td></tr>';
        }).join('');
    }

    var schoolUrlBase = app.dataset.schoolUrl || 'school-view.php';

    function buildSchoolUrl(id) {
        return schoolUrlBase + (schoolUrlBase.indexOf('?') >= 0 ? '&' : '?') + 'id=' + id;
    }

    function updateUrl() {
        var params = new URLSearchParams();
        params.set('granularity', granularity);
        if (schoolId > 0) {
            params.set('school_id', String(schoolId));
        }
        var next = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', next);
    }

    function load() {
        setLoading(true);
        var url = apiUrl + '?action=usage_analytics&granularity=' + encodeURIComponent(granularity);
        if (schoolId > 0) {
            url += '&school_id=' + schoolId;
        }

        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setLoading(false);
                if (!data.ok) return;

                updateStats(data.summary);
                renderChart(data.trend || []);

                if (rangeEl) {
                    rangeEl.textContent = (data.granularity_label || '') +
                        (data.from && data.to ? ' · ' + data.from + ' – ' + data.to + ' UTC' : '');
                    rangeEl.hidden = false;
                }
                if (trendSubtitle) {
                    trendSubtitle.textContent = data.granularity_label || 'AI requests over time';
                }

                populateSchools(data.schools, schoolId);

                if (schoolId > 0) {
                    if (tablePanel) tablePanel.hidden = true;
                } else {
                    if (tablePanel) tablePanel.hidden = false;
                    renderSchoolTable(data.by_school || []);
                }
            })
            .catch(function () {
                setLoading(false);
            });
    }

    app.querySelectorAll('.ai-granularity-pill').forEach(function (btn) {
        btn.addEventListener('click', function () {
            granularity = btn.getAttribute('data-granularity') || 'day';
            app.querySelectorAll('.ai-granularity-pill').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            updateUrl();
            load();
        });
    });

    if (schoolSelect) {
        schoolSelect.addEventListener('change', function () {
            schoolId = parseInt(schoolSelect.value, 10) || 0;
            updateUrl();
            load();
        });
    }

    var initialSchool = new URLSearchParams(window.location.search).get('school_id');
    if (initialSchool) {
        schoolId = parseInt(initialSchool, 10) || 0;
    }

    load();
})();
