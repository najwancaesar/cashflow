<?php

function get_default_category_map()
{
    return [
        'pemasukan' => [
            'Gaji',
            'Bonus',
            'Freelance',
            'Investasi',
            'Hadiah',
            'Piutang',
            'Lain-lain',
        ],
        'pengeluaran' => [
            'Kebutuhan Hidup',
            'Makan & Minum',
            'Transportasi',
            'Tagihan',
            'Hiburan',
            'Investasi',
            'Kesehatan',
            'Pendidikan',
            'Utang',
            'Lain-lain',
        ],
    ];
}

function seed_default_categories_for_user($con, $userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return;
    }

    $categories = get_default_category_map();

    $checkStmt = $con->prepare("SELECT id_kategori FROM kategori WHERE user_id = ? AND nama_kategori = ? AND tipe_kategori = ? LIMIT 1");
    $insertStmt = $con->prepare("INSERT INTO kategori (user_id, nama_kategori, tipe_kategori) VALUES (?, ?, ?)");

    foreach ($categories as $type => $names) {
        foreach ($names as $name) {
            $checkStmt->bind_param("iss", $userId, $name, $type);
            $checkStmt->execute();
            $existing = $checkStmt->get_result();

            if ($existing && $existing->num_rows > 0) {
                continue;
            }

            $insertStmt->bind_param("iss", $userId, $name, $type);
            $insertStmt->execute();
        }
    }

    $checkStmt->close();
    $insertStmt->close();
}

/**
 * Shared helper: Find a category by name and type, or create it if missing.
 */
function cashflow_find_or_create_category($con, $userId, $namaKategori, $tipeKategori)
{
    // Try to find the category first
    $stmt = $con->prepare("SELECT id_kategori FROM kategori WHERE user_id = ? AND nama_kategori = ? AND tipe_kategori = ? LIMIT 1");
    $stmt->bind_param("iss", $userId, $namaKategori, $tipeKategori);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['id_kategori'];
    }
    $stmt->close();

    // Not found, so create it
    $stmtInsert = $con->prepare("INSERT INTO kategori (user_id, nama_kategori, tipe_kategori) VALUES (?, ?, ?)");
    $stmtInsert->bind_param("iss", $userId, $namaKategori, $tipeKategori);
    
    if ($stmtInsert->execute()) {
        $newId = $stmtInsert->insert_id;
        $stmtInsert->close();
        return $newId;
    }

    $stmtInsert->close();
    return null; // fallback in case of failure
}
