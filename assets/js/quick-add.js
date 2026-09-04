(function () {
    'use strict';

    if (window.__cashflowQuickAddInitialized) {
        return;
    }
    window.__cashflowQuickAddInitialized = true;

    function localToday() {
        var now = new Date();
        var offset = now.getTimezoneOffset() * 60000;
        return new Date(now.getTime() - offset).toISOString().slice(0, 10);
    }

    function clearQuickAddParameter(url) {
        url.searchParams.delete('quick_add');
        window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
    }

    function prepareExistingModal(modal) {
        var form = modal ? modal.querySelector('form') : null;
        if (form) {
            form.reset();
            ['id_pemasukan', 'id_pengeluaran', 'id_transfer', 'id_hutang', 'id_piutang'].forEach(function (name) {
                var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
                if (input) {
                    input.value = '';
                }
            });
        }

        var dateInput = modal ? modal.querySelector('input[type="date"][name="tanggal"]') : null;
        if (dateInput) {
            dateInput.value = localToday();
        }
    }

    function openExistingModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return false;
        }

        prepareExistingModal(modal);
        bootstrap.Modal.getOrCreateInstance(modal).show();
        return true;
    }

    function chooseSavingGoal(action) {
        var selector = action === 'tarik' ? '.btntariksavinggoal' : '.btnsetorsavinggoal';
        var buttons = Array.prototype.slice.call(document.querySelectorAll(selector));

        if (buttons.length === 1) {
            buttons[0].click();
            return;
        }

        if (buttons.length > 1 && typeof Swal !== 'undefined') {
            var options = {};
            buttons.forEach(function (button) {
                options[button.getAttribute('data-id')] = button.getAttribute('data-nama') || 'Celengan';
            });

            Swal.fire({
                title: action === 'tarik' ? 'Pilih celengan untuk ditarik' : 'Pilih celengan untuk disetor',
                input: 'select',
                inputOptions: options,
                inputPlaceholder: 'Pilih celengan',
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal',
                inputValidator: function (value) {
                    return value ? null : 'Pilih salah satu celengan.';
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                var selected = buttons.find(function (button) {
                    return button.getAttribute('data-id') === String(result.value);
                });
                if (selected) {
                    selected.click();
                }
            });
            return;
        }

        var list = document.querySelector('.saving-goal-list, #datatableSavingGoal');
        if (list) {
            list.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var url = new URL(window.location.href);
        var quickAdd = url.searchParams.get('quick_add');
        var module = url.searchParams.get('module');

        if (!quickAdd || !module) {
            return;
        }

        window.setTimeout(function () {
            var modalByModule = {
                pemasukan: 'modalTambah',
                pengeluaran: 'modalTambah',
                transfer_wallet: 'modalTransferWallet',
                hutang: 'modalTambah',
                piutang: 'modalTambah'
            };

            if (module === 'saving_goal') {
                chooseSavingGoal(quickAdd === 'tarik' ? 'tarik' : 'setor');
            } else if (modalByModule[module]) {
                openExistingModal(modalByModule[module]);
            }

            clearQuickAddParameter(url);
        }, 100);
    });
})();
