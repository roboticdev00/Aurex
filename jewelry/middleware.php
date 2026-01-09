<?php
function requireLogin() {
    if (empty($_SESSION['User_ID'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (empty($_SESSION['Role']) || $_SESSION['Role'] !== 'admin') {
        header('Location: index_user.php');
        exit;
    }
}

function requireVerified() {
    requireLogin();
    if (empty($_SESSION['verified'])) {
        header('Location: verify_2fa.php');
        exit;
    }
}
?>