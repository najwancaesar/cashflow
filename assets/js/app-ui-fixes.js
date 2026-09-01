(function () {
    'use strict';

    if (window.__cashflowUiFixesInitialized) {
        return;
    }
    window.__cashflowUiFixesInitialized = true;

    var lastModalTrigger = null;
    var modalTriggerSelector = [
        '[data-bs-toggle="modal"]',
        '.btneditpemasukan',
        '.btneditpengeluaran',
        '.btneditkategori',
        '.btnedituser',
        '.btnresetpassworduser',
        '.btnedithutang',
        '.btneditpiutang'
    ].join(',');

    function safeFocus(element) {
        if (!element || !document.contains(element) || element.disabled) {
            return;
        }

        try {
            element.focus({ preventScroll: true });
        } catch (error) {
            element.focus();
        }
    }

    function annotateResponsiveTable(table) {
        if (!table || table.dataset.skipResponsive === 'true' || table.classList.contains('cashflow-responsive-table')) {
            return;
        }

        var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (header) {
            return (header.textContent || '').replace(/\s+/g, ' ').trim();
        });

        if (!headers.length) {
            return;
        }

        table.classList.add('cashflow-responsive-table');

        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
            Array.prototype.forEach.call(row.children, function (cell, index) {
                if (cell.classList.contains('action-col') || cell.classList.contains('cashflow-action-col')) {
                    cell.setAttribute('data-label', 'Aksi');
                    return;
                }

                if (cell.hasAttribute('data-label')) {
                    return;
                }

                cell.setAttribute('data-label', headers[index] || '');
            });
        });
    }

    function setupDataTableToolbars() {
        if (!window.jQuery) {
            return;
        }

        var $ = window.jQuery;

        $(document).on('init.dt', function (event, settings) {
            annotateResponsiveTable(settings.nTable);
        });

        $(function () {
            $('table.dataTable, table[id="datatable"]').each(function () {
                annotateResponsiveTable(this);
            });
        });
    }

    function setupResponsiveTables() {
        document.querySelectorAll('.app-main-content table').forEach(function (table) {
            annotateResponsiveTable(table);
        });
    }

    function setupMobileMicroInteractions() {
        var tapSelector = [
            '.app-main-content .btn',
            '.app-main-content .dropdown-item',
            '.sidenav .nav-link',
            '.navbar-main .dropdown-toggle',
            '#iconNavbarSidenav',
            '#iconSidenav'
        ].join(',');

        function clearTapState(element) {
            if (!element) {
                return;
            }

            window.setTimeout(function () {
                element.classList.remove('cashflow-tap-active');
            }, 140);
        }

        document.addEventListener('pointerdown', function (event) {
            var target = event.target.closest(tapSelector);

            if (!target) {
                return;
            }

            target.classList.add('cashflow-tap-active');
        }, true);

        document.addEventListener('pointerup', function (event) {
            clearTapState(event.target.closest(tapSelector));
        }, true);

        document.addEventListener('pointercancel', function (event) {
            clearTapState(event.target.closest(tapSelector));
        }, true);

    }

    function setupMobileSidebar() {
        var body = document.body;
        var sidenav = document.getElementById('sidenav-main');
        var sidebarToggle = document.getElementById('iconNavbarSidenav');
        var sidebarClose = document.getElementById('iconSidenav');
        var backdrop = document.querySelector('[data-cashflow-sidenav-backdrop]');

        if (!body || !sidenav || !sidebarToggle || !backdrop) {
            return;
        }

        var resizeFrame = null;

        function setClassState(element, className, enabled) {
            if (element.classList.contains(className) !== enabled) {
                element.classList.toggle(className, enabled);
            }
        }

        function setAttributeValue(element, name, value) {
            if (element.getAttribute(name) !== value) {
                element.setAttribute(name, value);
            }
        }

        function isMobileSidebar() {
            return window.innerWidth < 1200;
        }

        function syncSidebarState() {
            if (!isMobileSidebar()) {
                setClassState(body, 'g-sidenav-pinned', false);
                setClassState(body, 'cashflow-sidenav-open', false);
                setClassState(sidebarToggle, 'cashflow-sidebar-open', false);
                setAttributeValue(sidebarToggle, 'aria-expanded', 'false');
                setAttributeValue(sidebarToggle, 'aria-label', 'Buka menu navigasi');
                setAttributeValue(backdrop, 'aria-hidden', 'true');
                return;
            }

            var isOpen = body.classList.contains('g-sidenav-pinned');

            setClassState(body, 'cashflow-sidenav-open', isOpen);
            setClassState(sidebarToggle, 'cashflow-sidebar-open', isOpen);
            setAttributeValue(sidebarToggle, 'aria-expanded', isOpen ? 'true' : 'false');
            setAttributeValue(sidebarToggle, 'aria-label', isOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi');
            setAttributeValue(backdrop, 'aria-hidden', isOpen ? 'false' : 'true');
        }

        function scheduleSidebarSync() {
            if (resizeFrame !== null) {
                return;
            }

            resizeFrame = window.requestAnimationFrame(function () {
                resizeFrame = null;
                syncSidebarState();
            });
        }

        function closeSidebar(restoreFocus) {
            body.classList.remove('g-sidenav-pinned', 'cashflow-sidenav-open');
            syncSidebarState();

            if (restoreFocus) {
                safeFocus(sidebarToggle);
            }
        }

        sidebarToggle.addEventListener('click', function () {
            scheduleSidebarSync();
        });

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function () {
                window.requestAnimationFrame(function () {
                    closeSidebar(true);
                });
            });
        }

        backdrop.addEventListener('click', function () {
            closeSidebar(true);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && body.classList.contains('cashflow-sidenav-open')) {
                event.preventDefault();
                closeSidebar(true);
            }
        });

        window.addEventListener('resize', scheduleSidebarSync, { passive: true });

        syncSidebarState();
    }

    function setupFormLoadingStates() {
        function restoreSubmitButtons() {
            document.querySelectorAll('[data-cashflow-loading="true"]').forEach(function (button) {
                button.disabled = false;
                button.classList.remove('cashflow-is-loading');
                button.removeAttribute('aria-busy');
                button.removeAttribute('data-cashflow-loading');
                if (button.dataset.cashflowOriginalHtml) {
                    button.innerHTML = button.dataset.cashflowOriginalHtml;
                    delete button.dataset.cashflowOriginalHtml;
                }
            });
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var button = event.submitter;

            if (!form || !button || form.dataset.noLoading === 'true' || form.target === '_blank') {
                return;
            }

            window.setTimeout(function () {
                if (event.defaultPrevented || button.disabled) {
                    return;
                }

                button.dataset.cashflowOriginalHtml = button.innerHTML;
                button.dataset.cashflowLoading = 'true';
                button.classList.add('cashflow-is-loading');
                button.setAttribute('aria-busy', 'true');
                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Memproses...';
            }, 0);
        });

        window.addEventListener('pageshow', restoreSubmitButtons);
    }

    function setupMaterialDashboardResizeGuard() {
        var vendorResizeHandler = window.navbarColorOnResize;
        var sidenav = document.getElementById('sidenav-main');
        var configuratorReference = document.querySelector('[data-class]');

        if (typeof vendorResizeHandler !== 'function' || configuratorReference) {
            return;
        }

        window.removeEventListener('resize', vendorResizeHandler);

        var resizeFrame = null;

        function applyNavbarColor() {
            if (!sidenav) {
                return;
            }

            if (window.innerWidth > 1200) {
                if (sidenav.classList.contains('bg-white')) {
                    sidenav.classList.remove('bg-white');
                }
                if (sidenav.classList.contains('bg-transparent')) {
                    sidenav.classList.remove('bg-transparent');
                }
                return;
            }

            if (!sidenav.classList.contains('bg-white')) {
                sidenav.classList.add('bg-white');
            }
            if (sidenav.classList.contains('bg-transparent')) {
                sidenav.classList.remove('bg-transparent');
            }
        }

        function safeNavbarColorOnResize() {
            if (resizeFrame !== null) {
                return;
            }

            resizeFrame = window.requestAnimationFrame(function () {
                resizeFrame = null;
                applyNavbarColor();
            });
        }

        window.addEventListener('resize', safeNavbarColorOnResize, { passive: true });
        applyNavbarColor();
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest(modalTriggerSelector);

        if (trigger) {
            lastModalTrigger = trigger;
        }
    }, true);

    document.addEventListener('show.bs.modal', function (event) {
        if (event.relatedTarget) {
            lastModalTrigger = event.relatedTarget;
        }
    });

    document.addEventListener('hide.bs.modal', function (event) {
        var modal = event.target;
        var activeElement = document.activeElement;

        if (activeElement && modal.contains(activeElement) && typeof activeElement.blur === 'function') {
            activeElement.blur();
        }
    });

    document.addEventListener('hidden.bs.modal', function (event) {
        var modal = event.target;
        var activeElement = document.activeElement;

        if (activeElement && modal.contains(activeElement) && typeof activeElement.blur === 'function') {
            activeElement.blur();
        }

        safeFocus(lastModalTrigger);
    });

    setupMaterialDashboardResizeGuard();
    setupDataTableToolbars();
    setupResponsiveTables();
    setupMobileMicroInteractions();
    setupMobileSidebar();
    setupFormLoadingStates();
})();
