<?php
include "includes/koneksi.php";
include_once "includes/csrf_helper.php";

if (!isset($_SESSION['id_user'])) {
    echo "<script>window.location.href='./';</script>";
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) === 'admin') {
    echo "<script>window.location.href='main.php?module=home';</script>";
    exit;
}

$userYangSedangLogin = (int) $_SESSION['id_user'];
$budgetBulan = (int) date('n');
$budgetTahun = (int) date('Y');
$periodeBudgetLabel = date('M Y');

function format_kategori_rupiah($value)
{
    return 'Rp. ' . number_format((float) $value);
}

function get_budget_status($budgetNominal, $totalTerpakai)
{
    if ($budgetNominal <= 0) {
        return [
            'label' => 'Belum diatur',
            'badge_class' => 'bg-gradient-secondary',
            'progress_class' => 'bg-gradient-secondary',
            'percentage' => 0,
            'width' => 0,
        ];
    }

    $percentage = ($totalTerpakai / $budgetNominal) * 100;

    if ($percentage >= 100) {
        $label = 'Over Budget';
        $badgeClass = 'bg-gradient-danger';
        $progressClass = 'bg-gradient-danger';
    } elseif ($percentage >= 80) {
        $label = 'Warning';
        $badgeClass = 'bg-gradient-warning';
        $progressClass = 'bg-gradient-warning';
    } else {
        $label = 'Aman';
        $badgeClass = 'bg-gradient-success';
        $progressClass = 'bg-gradient-success';
    }

    return [
        'label' => $label,
        'badge_class' => $badgeClass,
        'progress_class' => $progressClass,
        'percentage' => $percentage,
        'width' => min(100, $percentage),
    ];
}

$kategoriQuery = "SELECT
                      kategori.id_kategori,
                      kategori.nama_kategori,
                      kategori.tipe_kategori,
                      kategori.created_at,
                      COALESCE(budget_kategori.nominal_budget, 0) AS nominal_budget,
                      COALESCE(SUM(pengeluaran.jumlah), 0) AS total_pengeluaran_bulan
                  FROM kategori
                  LEFT JOIN budget_kategori
                    ON budget_kategori.user_id = kategori.user_id
                   AND budget_kategori.id_kategori = kategori.id_kategori
                   AND budget_kategori.bulan = ?
                   AND budget_kategori.tahun = ?
                  LEFT JOIN pengeluaran
                    ON pengeluaran.user = kategori.user_id
                   AND pengeluaran.id_kategori = kategori.id_kategori
                   AND pengeluaran.status = 'selesai'
                   AND MONTH(pengeluaran.tanggal) = ?
                   AND YEAR(pengeluaran.tanggal) = ?
                  WHERE kategori.user_id = ?
                  GROUP BY
                      kategori.id_kategori,
                      kategori.nama_kategori,
                      kategori.tipe_kategori,
                      kategori.created_at,
                      budget_kategori.nominal_budget
                  ORDER BY kategori.tipe_kategori ASC, kategori.nama_kategori ASC";
$kategoriStmt = mysqli_prepare($con, $kategoriQuery);
mysqli_stmt_bind_param($kategoriStmt, "iiiii", $budgetBulan, $budgetTahun, $budgetBulan, $budgetTahun, $userYangSedangLogin);
mysqli_stmt_execute($kategoriStmt);
$kategoriResult = mysqli_stmt_get_result($kategoriStmt);
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
                        <h6 class="text-white text-capitalize ps-3">Kategori Transaksi</h6>
                    </div>
                </div>
                <div class="card-body px-0 pb-2">
                    <div class="px-4 pt-2">
                        <p class="text-sm text-secondary mb-0">
                            Kelola kategori pemasukan dan pengeluaran milik akun Anda untuk dipakai di form transaksi.
                        </p>
                        <p class="text-sm text-secondary mb-0">
                            Kategori umum disiapkan otomatis saat akun dibuat, dan Anda tetap bisa menambah kategori sendiri kapan saja.
                        </p>
                    </div>
                    <div class="text-end me-3 mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#modalTambah">
                            <i class="fa fa-plus-circle" aria-hidden="true"></i> Tambah Kategori
                        </button>
                    </div>
                    <div class="table-responsive p-4 mx-2">
                        <table class="table align-items-center mb-0" id="datatable">
                            <thead>
                                <tr>
                                    <th>Nama Kategori</th>
                                    <th>Tipe</th>
                                    <th>Budget Bulan Ini</th>
                                    <th>Dibuat</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($kategoriResult)) { ?>
                                    <?php
                                    $isKategoriPengeluaran = $row['tipe_kategori'] === 'pengeluaran';
                                    $budgetNominal = (float) ($row['nominal_budget'] ?? 0);
                                    $totalTerpakai = (float) ($row['total_pengeluaran_bulan'] ?? 0);
                                    $sisaBudget = max(0, $budgetNominal - $totalTerpakai);
                                    $budgetStatus = get_budget_status($budgetNominal, $totalTerpakai);
                                    ?>
                                    <tr>
                                        <td>
                                            <p class="text-xs font-weight-bold mb-0">
                                                <?= htmlspecialchars($row['nama_kategori']) ?>
                                            </p>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm <?= $row['tipe_kategori'] === 'pemasukan' ? 'bg-gradient-success' : 'bg-gradient-info' ?>">
                                                <?= htmlspecialchars(ucfirst($row['tipe_kategori'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isKategoriPengeluaran) { ?>
                                                <div class="budget-category-box">
                                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                        <span class="badge badge-sm <?= htmlspecialchars($budgetStatus['badge_class'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($budgetStatus['label']) ?>
                                                        </span>
                                                        <span class="text-xs text-secondary"><?= htmlspecialchars($periodeBudgetLabel) ?></span>
                                                    </div>
                                                    <p class="text-xs text-secondary mb-1">
                                                        Terpakai
                                                        <strong class="text-dark"><?= format_kategori_rupiah($totalTerpakai) ?></strong>
                                                        dari
                                                        <strong class="text-dark"><?= format_kategori_rupiah($budgetNominal) ?></strong>
                                                    </p>
                                                    <div class="progress budget-category-progress mb-1">
                                                        <div class="progress-bar <?= htmlspecialchars($budgetStatus['progress_class'], ENT_QUOTES, 'UTF-8') ?>"
                                                            role="progressbar"
                                                            style="width: <?= htmlspecialchars((string) $budgetStatus['width'], ENT_QUOTES, 'UTF-8') ?>%;"
                                                            aria-valuenow="<?= htmlspecialchars((string) round($budgetStatus['width'], 2), ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100"></div>
                                                    </div>
                                                    <p class="text-xs text-secondary mb-2">
                                                        <?= number_format((float) $budgetStatus['percentage'], 1) ?>% terpakai
                                                        <span class="mx-1">|</span>
                                                        Sisa <?= format_kategori_rupiah($sisaBudget) ?>
                                                    </p>
                                                    <form action="aksi_budget.php" method="post" class="budget-category-form">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="id_kategori" value="<?= (int) $row['id_kategori'] ?>">
                                                        <input type="hidden" name="bulan" value="<?= (int) $budgetBulan ?>">
                                                        <input type="hidden" name="tahun" value="<?= (int) $budgetTahun ?>">
                                                        <div class="input-group input-group-outline budget-category-input">
                                                            <input type="text"
                                                                name="nominal_budget"
                                                                class="form-control js-budget-nominal"
                                                                inputmode="numeric"
                                                                placeholder="0"
                                                                value="<?= $budgetNominal > 0 ? htmlspecialchars(number_format($budgetNominal, 0, ',', '.'), ENT_QUOTES, 'UTF-8') : '' ?>">
                                                            <button type="submit" class="btn btn-sm btn-info mb-0">Simpan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php } else { ?>
                                                <p class="text-xs text-secondary mb-0">Tidak berlaku untuk pemasukan</p>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <p class="text-xs text-secondary mb-0">
                                                <?= htmlspecialchars(date('d M Y H:i', strtotime($row['created_at']))) ?>
                                            </p>
                                        </td>
                                        <td class="align-middle">
                                            <form action="aksi_kategori.php?act=h" method="post" class="d-inline">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="id_kategori" value="<?= (int) $row['id_kategori'] ?>">
                                                <button type="submit"
                                                    data-confirm="true"
                                                    data-confirm-title="Hapus kategori ini?"
                                                    data-confirm-text="Kategori yang dihapus tidak akan otomatis menghapus transaksi lama."
                                                    data-confirm-confirm-text="Ya, hapus"
                                                    data-confirm-cancel-text="Batal"
                                                    class="text-secondary text-danger font-weight-bold text-xs border-0 bg-transparent p-0">
                                                    <i class="fa fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>

                                            <a type="button"
                                                data-id="<?= (int) $row['id_kategori'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_kategori'], ENT_QUOTES) ?>"
                                                data-tipe="<?= htmlspecialchars($row['tipe_kategori'], ENT_QUOTES) ?>"
                                                class="text-secondary text-warning font-weight-bold text-xs btneditkategori">
                                                <i class="fa fa-pencil" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php mysqli_stmt_close($kategoriStmt); ?>

<div class="modal fade" id="modalTambah" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="aksi_kategori.php?act=t" method="post">
                <?= csrf_input() ?>
                <div class="modal-header p-0 position-relative mt-n4 mx-3 z-index-2">
                    <div
                        class="w-100 bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 d-flex justify-content-between">
                        <h6 class="modal-title text-white text-capitalize ps-3">Kategori</h6>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_kategori" id="id_kategori" class="form-control">
                    <div class="row">
                        <label class="form-label">Nama Kategori</label>
                        <div class="input-group input-group-outline">
                            <input type="text" name="nama_kategori" id="nama_kategori" class="form-control" maxlength="100" required>
                        </div>
                    </div>
                    <div class="row my-3">
                        <label class="form-label">Tipe Kategori</label>
                        <div class="input-group input-group-outline">
                            <select class="form-control" name="tipe_kategori" id="tipe_kategori" required>
                                <option value="">Pilih Tipe</option>
                                <option value="pemasukan">Pemasukan</option>
                                <option value="pengeluaran">Pengeluaran</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="simpan" class="btn btn-info">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        function getBudgetNominalDigits(value) {
            return String(value || '').replace(/\D/g, '');
        }

        function formatBudgetNominal(value) {
            var digits = getBudgetNominalDigits(value);

            if (!digits) {
                return '';
            }

            return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function applyBudgetNominalFormatting(input) {
            if (!input) {
                return;
            }

            var rawValue = input.value || '';
            var selectionStart = typeof input.selectionStart === 'number' ? input.selectionStart : rawValue.length;
            var digitsBeforeCursor = getBudgetNominalDigits(rawValue.slice(0, selectionStart)).length;
            var formattedValue = formatBudgetNominal(rawValue);

            input.value = formattedValue;

            if (typeof input.setSelectionRange === 'function') {
                var cursorPosition = formattedValue.length;

                if (digitsBeforeCursor === 0) {
                    cursorPosition = 0;
                } else {
                    var countedDigits = 0;
                    for (var i = 0; i < formattedValue.length; i++) {
                        if (/\d/.test(formattedValue.charAt(i))) {
                            countedDigits++;
                        }

                        if (countedDigits >= digitsBeforeCursor) {
                            cursorPosition = i + 1;
                            break;
                        }
                    }
                }

                input.setSelectionRange(cursorPosition, cursorPosition);
            }
        }

        $('.budget-category-form .js-budget-nominal').each(function() {
            this.value = formatBudgetNominal(this.value);
        });

        $(document).on('input', '.budget-category-form .js-budget-nominal', function() {
            applyBudgetNominalFormatting(this);
        });

        $(document).on('submit', '.budget-category-form', function() {
            $(this).find('.js-budget-nominal').each(function() {
                this.value = getBudgetNominalDigits(this.value);
            });
        });

        $('#datatable').DataTable({
            language: {
                "paginate": {
                    "first": "&laquo",
                    "last": "&raquo",
                    "next": "&gt",
                    "previous": "&lt"
                },
            },
            dom: ' <"d-flex"l<"input-group input-group-outline justify-content-end me-4"f>>rt<"d-flex justify-content-between"ip><"clear">'
        });
    });
</script>
