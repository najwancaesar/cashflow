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
