/**
 * Finance App — app.js
 * Sidebar toggle dihandle oleh Materio main.js
 * File ini: AJAX favorit, auto-dismiss alerts, DataTables init
 */
'use strict';

$(function () {

    function initTooltips() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function applyPageTitleIcon() {
        var wrapper = document.querySelector('.layout-wrapper[data-active-menu]');
        var activeMenu = wrapper ? (wrapper.getAttribute('data-active-menu') || '') : '';
        var heading = document.querySelector('.container-xxl h4, .container-xxl h5');
        if (!heading) return;
        if (heading.querySelector('.page-title-icon')) return;

        var iconClass = 'ri-file-list-3-line';
        if (/dashboard/.test(activeMenu)) iconClass = 'ri-dashboard-line';
        else if (/master/.test(activeMenu)) iconClass = 'ri-database-2-line';
        else if (/role/.test(activeMenu)) iconClass = 'ri-shield-keyhole-line';
        else if (/user/.test(activeMenu)) iconClass = 'ri-user-settings-line';
        else if (/relation/.test(activeMenu)) iconClass = 'ri-links-line';

        var icon = document.createElement('i');
        icon.className = 'ri ' + iconClass + ' page-title-icon';
        heading.insertBefore(icon, heading.firstChild);
    }

    function formatTwoDecimals() {
        document.querySelectorAll('[data-decimal="1"]').forEach(function (el) {
            var raw = (el.getAttribute('data-value') || el.textContent || '').trim();
            if (raw === '') return;
            var normalized = raw.replace(/,/g, '');
            var num = Number(normalized);
            if (!Number.isFinite(num)) return;
            el.textContent = num.toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            el.classList.add('number-cell');
        });
    }

    function harmonizeLegacyTables() {
        document.querySelectorAll('table').forEach(function (table) {
            var headerCells = Array.prototype.slice.call(table.querySelectorAll('thead th'));
            if (!headerCells.length) return;

            var actionIndex = -1;
            headerCells.forEach(function (th, idx) {
                var txt = (th.textContent || '').trim().toLowerCase();
                if (txt === 'aksi' || txt === 'action') {
                    actionIndex = idx;
                    th.classList.add('action-cell');
                }
            });

            if (actionIndex < 0) return;

            table.querySelectorAll('tbody tr').forEach(function (row) {
                var cells = row.querySelectorAll('td');
                if (!cells.length || !cells[actionIndex]) return;

                var actionCell = cells[actionIndex];
                actionCell.classList.add('action-cell');

                actionCell.querySelectorAll('a.btn, button.btn').forEach(function (btn) {
                    btn.classList.add('action-icon-btn');
                    if (!btn.classList.contains('btn-sm')) {
                        btn.classList.add('btn-sm');
                    }
                });
            });
        });
    }

    function reinforceSidebarActivePath() {
        var wrapper = document.querySelector('.layout-wrapper[data-current-url]');
        var currentUrl = wrapper ? (wrapper.getAttribute('data-current-url') || '').replace(/^\/+|\/+$/g, '') : '';

        if (currentUrl) {
            function normalizePath(urlText) {
                if (!urlText) return '';
                try {
                    var u = new URL(urlText, window.location.origin);
                    var p = (u.pathname || '').replace(/^\/+|\/+$/g, '');
                    if (p && currentUrl && !currentUrl.startsWith(p) && p.indexOf('/') > -1) {
                        var parts = p.split('/');
                        if (parts.length > 1) {
                            p = parts.slice(1).join('/');
                        }
                    }
                    return p;
                } catch (e) {
                    return String(urlText).replace(/^\/+|\/+$/g, '');
                }
            }

            document.querySelectorAll('.layout-menu .menu-item > .menu-link[href]').forEach(function (link) {
                var href = normalizePath(link.getAttribute('href') || '');
                if (!href) return;

                if (currentUrl === href || currentUrl.indexOf(href + '/') === 0) {
                    var li = link.closest('.menu-item');
                    if (li) {
                        li.classList.add('active');
                    }
                }
            });
        }

        var activeLeaf = document.querySelector('.layout-menu .menu-item.active:last-of-type') || document.querySelector('.layout-menu .menu-item.active');
        if (!activeLeaf) return;

        var parent = activeLeaf.parentElement;
        while (parent) {
            if (parent.classList && parent.classList.contains('menu-sub')) {
                var owner = parent.closest('.menu-item');
                if (owner) {
                    owner.classList.add('open', 'active', 'current-path');
                    parent = owner.parentElement;
                    continue;
                }
            }
            parent = parent.parentElement;
        }

        var menuScroll = document.querySelector('.layout-menu .menu-inner');
        var activeLink = activeLeaf.querySelector('.menu-link');
        if (menuScroll && activeLink) {
            var top = activeLink.offsetTop - menuScroll.clientHeight * 0.35;
            menuScroll.scrollTop = Math.max(0, top);
        }
    }

    // ---------------------------------------------------------------
    // Toggle favorit sidebar via AJAX (persist per user)
    // ---------------------------------------------------------------
    $(document).on('click', '.sidebar-pin-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var menuId = Number($btn.data('menu-id') || 0);
        if (!menuId) return;

        var pinned = String($btn.data('pinned') || '0') === '1';
        var endpoint = pinned ? 'sidebar/unpin' : 'sidebar/pin';

        $.post(BASE_URL + endpoint, { menu_id: menuId }, function () {
            location.reload();
        });
    });

    // ---------------------------------------------------------------
    // Auto-dismiss flash alerts setelah 4 detik
    // ---------------------------------------------------------------
    setTimeout(function () {
        $('.alert-dismissible').each(function () {
            var alertEl = this;
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                var bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                bsAlert.close();
            } else {
                $(alertEl).fadeOut(400, function () { $(this).remove(); });
            }
        });
    }, 4000);

    // ---------------------------------------------------------------
    // DataTables — auto-init semua table.datatable
    // ---------------------------------------------------------------
    if (typeof $.fn.DataTable !== 'undefined') {
        $('table.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            },
            pageLength: 25,
            responsive: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        });
    }

    applyPageTitleIcon();
    formatTwoDecimals();
    harmonizeLegacyTables();
    reinforceSidebarActivePath();
    initTooltips();

});
