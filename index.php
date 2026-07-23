<?php
require_once __DIR__ . '/bootstrap.php';

if (is_app_logged_in()) {
    header("Location: tiles.php");
} else {
    header("Location: login.php");
}
exit;
