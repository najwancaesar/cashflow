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
        if (!table || table.classList.contains('cashflow-responsive-table')) {
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
})();
