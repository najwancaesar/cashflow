<?php
// LEGACY NON-ACTIVE ENDPOINT:
// File ini dipertahankan agar link lama tidak fatal, tetapi semua alur aktif
// sudah memakai aksi_hutang.php.
include "includes/sweetalert_helper.php";

show_sweetalert_and_redirect('Endpoint lama dinonaktifkan', 'Gunakan modul hutang aktif yang terbaru.', 'info', 'main.php?module=hutang');
