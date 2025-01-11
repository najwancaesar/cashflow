<?php
session_start();
unset($_SESSION['nama']);
unset($_SESSION['role']);
unset($_SESSION['id_user']);
unset($_SESSION['foto']);

echo "<script>window.alert('Terimakasih atas kunjunganya');
            window.location=(href='./')</script>";
?>