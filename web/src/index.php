<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    header('Location: pos.php');
} else {
    header('Location: login.php');
}
exit;
