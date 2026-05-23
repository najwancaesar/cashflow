(function () {
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

    function enhanceDataTableToolbar(table) {
        if (!window.jQuery || !table) {
            return;
        }

        var $ = window.jQuery;
        var $table = $(table);
        var $wrapper = $table.closest('.dataTables_wrapper');
        var $tableArea = $wrapper.closest('.table-responsive');
        var $cardBody = $tableArea.closest('.card-body');

        if (!$wrapper.length || !$tableArea.length || !$cardBody.length) {
            return;
        }

        if ($cardBody.children('.cashflow-table-toolbar').length) {
            return;
        }

        var $controls = $wrapper.children('.d-flex').first();
        if (!$controls.length) {
            return;
        }

        var $action = $cardBody.children('.text-end').filter(function () {
            return $(this).find('button[data-bs-toggle="modal"], a[data-bs-toggle="modal"]').length > 0;
        }).first();

        var $toolbar = $('<div class="cashflow-table-toolbar"></div>');
        $controls.addClass('cashflow-table-controls');

        $tableArea.before($toolbar);
        $toolbar.append($controls);

        if ($action.length) {
            $action.addClass('cashflow-table-action');
            $toolbar.append($action);
        }

        $controls.find('.dataTables_filter input')
            .attr('placeholder', 'Ketik untuk mencari data...');
    }

    function annotateResponsiveTable(table) {
        if (!table || table.dataset.skipResponsive === 'true' || table.classList.contains('cashflow-responsive-table')) {
            return;
        }

        var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (header) {
            if (header.classList.contains('bulk-select-col')) {
                return null;
            }
            return (header.textContent || '').replace(/\s+/g, ' ').trim();
        });

        if (!headers.length) {
            return;
        }

        table.classList.add('cashflow-responsive-table');

        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (row) {
            Array.prototype.forEach.call(row.children, function (cell, index) {
                if (cell.classList.contains('bulk-select-col')) {
                    cell.removeAttribute('data-label');
                    cell.setAttribute('aria-hidden', 'true');
                    return;
                }

                if (cell.classList.contains('action-col')) {
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
            enhanceDataTableToolbar(settings.nTable);
            annotateResponsiveTable(settings.nTable);
        });

        $(function () {
            $('table.dataTable, table[id="datatable"]').each(function () {
                enhanceDataTableToolbar(this);
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
        var sidebarToggle = document.getElementById('iconNavbarSidenav');

        function clearTapState(element) {
            if (!element) {
                return;
            }

            window.setTimeout(function () {
                element.classList.remove('cashflow-tap-active');
            }, 140);
        }

        function syncSidebarState() {
            if (!sidebarToggle) {
                return;
            }

            sidebarToggle.classList.toggle(
                'cashflow-sidebar-open',
                document.body.classList.contains('g-sidenav-pinned')
            );
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

        document.addEventListener('click', function (event) {
            if (event.target.closest('#iconNavbarSidenav, #iconSidenav')) {
                window.setTimeout(syncSidebarState, 40);
            }
        }, true);

        window.addEventListener('resize', syncSidebarState);
        syncSidebarState();
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

    setupDataTableToolbars();
    setupResponsiveTables();
    setupMobileMicroInteractions();
})();
