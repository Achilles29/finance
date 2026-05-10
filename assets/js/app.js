/**
 * Finance App — app.js
 * Sidebar toggle dihandle oleh Materio main.js
 * File ini: AJAX favorit, auto-dismiss alerts, DataTables init
 */
'use strict';

$(function () {
    function loadStylesheetOnce(href) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('link[data-dynamic-href="' + href + '"]');
            if (existing) {
                resolve();
                return;
            }

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.setAttribute('data-dynamic-href', href);
            link.onload = function () { resolve(); };
            link.onerror = function () { reject(new Error('Failed to load stylesheet: ' + href)); };
            document.head.appendChild(link);
        });
    }

    function loadScriptOnce(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-dynamic-src="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-loaded') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); }, { once: true });
                existing.addEventListener('error', function () { reject(new Error('Failed to load script: ' + src)); }, { once: true });
                return;
            }

            var script = document.createElement('script');
            script.src = src;
            script.async = false;
            script.setAttribute('data-dynamic-src', src);
            script.onload = function () {
                script.setAttribute('data-loaded', '1');
                resolve();
            };
            script.onerror = function () { reject(new Error('Failed to load script: ' + src)); };
            document.body.appendChild(script);
        });
    }

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
                    var txt = (btn.textContent || '').replace(/\s+/g, ' ').trim();
                    var hasOnlyIcon = txt === '';
                    if (hasOnlyIcon) {
                        btn.classList.add('action-icon-btn');
                        btn.classList.remove('action-text-btn');
                    } else {
                        btn.classList.remove('action-icon-btn');
                        btn.classList.add('action-text-btn');
                    }
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

    function initDataTables() {
        if (typeof $.fn.DataTable === 'undefined' || !$('table.datatable').length) {
            return;
        }

        $('table.datatable').each(function () {
            if ($.fn.dataTable.isDataTable(this)) {
                return;
            }

            $(this).DataTable({
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                    infoEmpty: 'Menampilkan 0 sampai 0 dari 0 data',
                    infoFiltered: '(disaring dari _MAX_ total data)',
                    loadingRecords: 'Memuat...',
                    processing: 'Memproses...',
                    zeroRecords: 'Tidak ada data yang cocok',
                    emptyTable: 'Belum ada data',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Berikutnya',
                        previous: 'Sebelumnya'
                    }
                },
                pageLength: 25,
                responsive: true,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            });
        });
    }

    function bootDataTablesIfNeeded() {
        if (!$('table.datatable').length) {
            return;
        }

        if (typeof $.fn.DataTable !== 'undefined') {
            initDataTables();
            return;
        }

        loadStylesheetOnce(BASE_URL + 'assets/vendor/datatables/dataTables.bootstrap4.min.css')
            .then(function () {
                return loadScriptOnce(BASE_URL + 'assets/vendor/datatables/jquery.dataTables.min.js');
            })
            .then(function () {
                return loadScriptOnce(BASE_URL + 'assets/vendor/datatables/dataTables.bootstrap4.min.js');
            })
            .then(function () {
                initDataTables();
            })
            .catch(function (error) {
                if (window.console && typeof window.console.error === 'function') {
                    console.error(error);
                }
            });
    }

    function ensureUiModal() {
        var existing = document.getElementById('financeUiModal');
        if (existing) {
            return existing;
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML = ''
            + '<div class="modal fade finance-ui-modal" id="financeUiModal" tabindex="-1" aria-hidden="true">'
            + '  <div class="modal-dialog modal-dialog-centered">'
            + '    <div class="modal-content">'
            + '      <div class="modal-header">'
            + '        <h5 class="modal-title" id="financeUiModalTitle">Konfirmasi</h5>'
            + '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            + '      </div>'
            + '      <div class="modal-body">'
            + '        <p class="mb-0 finance-ui-modal-message" id="financeUiModalMessage"></p>'
            + '        <div class="mt-3 d-none" id="financeUiModalPromptWrap">'
            + '          <input type="text" class="form-control" id="financeUiModalPromptInput" autocomplete="off">'
            + '        </div>'
            + '      </div>'
            + '      <div class="modal-footer">'
            + '        <button type="button" class="btn btn-outline-secondary" id="financeUiModalCancelBtn">Batal</button>'
            + '        <button type="button" class="btn btn-primary" id="financeUiModalOkBtn">Lanjut</button>'
            + '      </div>'
            + '    </div>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(wrapper.firstChild);
        return document.getElementById('financeUiModal');
    }

    function openUiModal(options) {
        return new Promise(function (resolve) {
            var modalEl = ensureUiModal();
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static' });
            var titleEl = modalEl.querySelector('#financeUiModalTitle');
            var msgEl = modalEl.querySelector('#financeUiModalMessage');
            var promptWrapEl = modalEl.querySelector('#financeUiModalPromptWrap');
            var promptInputEl = modalEl.querySelector('#financeUiModalPromptInput');
            var cancelBtn = modalEl.querySelector('#financeUiModalCancelBtn');
            var okBtn = modalEl.querySelector('#financeUiModalOkBtn');

            var type = String(options.type || 'confirm');
            titleEl.textContent = options.title || (type === 'alert' ? 'Informasi' : 'Konfirmasi');
            msgEl.textContent = options.message || '';
            okBtn.textContent = options.okText || (type === 'alert' ? 'Tutup' : 'Lanjut');
            cancelBtn.textContent = options.cancelText || 'Batal';

            var promptMode = type === 'prompt';
            promptWrapEl.classList.toggle('d-none', !promptMode);
            promptInputEl.value = options.defaultValue || '';

            if (type === 'alert') {
                cancelBtn.classList.add('d-none');
            } else {
                cancelBtn.classList.remove('d-none');
            }

            var settled = false;
            var cleanup = function () {
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
                modalEl.removeEventListener('hidden.bs.modal', onHide);
            };
            var finish = function (value) {
                if (settled) return;
                settled = true;
                cleanup();
                resolve(value);
            };
            var onOk = function () {
                if (promptMode) {
                    finish(promptInputEl.value);
                } else if (type === 'alert') {
                    finish(undefined);
                } else {
                    finish(true);
                }
                modal.hide();
            };
            var onCancel = function () {
                finish(type === 'prompt' ? null : false);
                modal.hide();
            };
            var onHide = function () {
                if (!settled) {
                    finish(type === 'prompt' ? null : false);
                }
            };

            okBtn.addEventListener('click', onOk);
            cancelBtn.addEventListener('click', onCancel);
            modalEl.addEventListener('hidden.bs.modal', onHide);

            modal.show();
            if (promptMode) {
                setTimeout(function () {
                    promptInputEl.focus();
                    promptInputEl.select();
                }, 120);
            }
        });
    }

    function initUiDialogs() {
        window.FinanceUI = window.FinanceUI || {};
        if (!window.__financeNativeAlert) {
            window.__financeNativeAlert = window.alert.bind(window);
        }
        window.FinanceUI.alert = function (message, opts) {
            opts = opts || {};
            return openUiModal({
                type: 'alert',
                title: opts.title || 'Informasi',
                message: String(message || ''),
                okText: opts.okText || 'Tutup'
            });
        };
        window.FinanceUI.confirm = function (message, opts) {
            opts = opts || {};
            return openUiModal({
                type: 'confirm',
                title: opts.title || 'Konfirmasi',
                message: String(message || ''),
                okText: opts.okText || 'Lanjut',
                cancelText: opts.cancelText || 'Batal'
            });
        };
        window.FinanceUI.prompt = function (message, defaultValue, opts) {
            opts = opts || {};
            return openUiModal({
                type: 'prompt',
                title: opts.title || 'Input',
                message: String(message || ''),
                defaultValue: defaultValue || '',
                okText: opts.okText || 'Simpan',
                cancelText: opts.cancelText || 'Batal'
            });
        };
        window.alert = function (message) {
            window.FinanceUI.alert(String(message || ''));
        };
    }

    function extractConfirmMessage(handlerValue) {
        if (!handlerValue) return '';
        var regex = /^\s*return\s+confirm\((['"])([\s\S]*?)\1\)\s*;?\s*$/i;
        var match = String(handlerValue).match(regex);
        return match ? match[2] : '';
    }

    function normalizeInlineConfirmToDataAttr() {
        document.querySelectorAll('[onsubmit]').forEach(function (el) {
            var handler = el.getAttribute('onsubmit') || '';
            var message = extractConfirmMessage(handler);
            if (!message) return;
            el.setAttribute('data-confirm', message);
            el.removeAttribute('onsubmit');
        });
        document.querySelectorAll('[onclick]').forEach(function (el) {
            var handler = el.getAttribute('onclick') || '';
            var message = extractConfirmMessage(handler);
            if (!message) return;
            el.setAttribute('data-confirm', message);
            el.setAttribute('data-confirm-click', '1');
            el.removeAttribute('onclick');
        });
    }

    function setButtonLoading(button, label) {
        if (!button || button.dataset.loadingActive === '1') return;
        button.dataset.loadingActive = '1';
        button.dataset.originalHtml = button.innerHTML;
        button.disabled = true;
        button.classList.add('is-loading');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (label || 'Memproses...');
    }

    function bindUiConfirmAndLoading() {
        document.addEventListener('click', function (event) {
            var target = event.target.closest('[data-confirm][data-confirm-click]');
            if (!target) return;
            if (target.dataset.confirmed === '1') return;
            event.preventDefault();
            var message = target.getAttribute('data-confirm') || 'Lanjutkan aksi ini?';
            window.FinanceUI.confirm(message).then(function (ok) {
                if (!ok) return;
                target.dataset.confirmed = '1';
                if (target.tagName === 'A') {
                    window.location.href = target.getAttribute('href');
                } else if (target.tagName === 'BUTTON') {
                    target.click();
                }
            });
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            var confirmMessage = form.getAttribute('data-confirm');
            if (confirmMessage && form.dataset.confirmed !== '1') {
                event.preventDefault();
                window.FinanceUI.confirm(confirmMessage).then(function (ok) {
                    if (!ok) return;
                    form.dataset.confirmed = '1';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(event.submitter || undefined);
                    } else {
                        form.submit();
                    }
                });
                return;
            }

            var method = (form.getAttribute('method') || 'get').toLowerCase();
            if (method !== 'post') {
                return;
            }
            if (form.dataset.noLoading === '1') {
                return;
            }

            var submitter = event.submitter || form.querySelector('button[type="submit"],input[type="submit"]');
            var label = submitter ? (submitter.getAttribute('data-loading-label') || 'Memproses...') : 'Memproses...';
            if (submitter) {
                setButtonLoading(submitter, label);
            }
            form.querySelectorAll('button[type="submit"],input[type="submit"]').forEach(function (btn) {
                if (submitter && btn === submitter) return;
                btn.disabled = true;
            });
        });
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

    applyPageTitleIcon();
    formatTwoDecimals();
    harmonizeLegacyTables();
    initUiDialogs();
    normalizeInlineConfirmToDataAttr();
    bindUiConfirmAndLoading();
    reinforceSidebarActivePath();
    initTooltips();
    bootDataTablesIfNeeded();
});
