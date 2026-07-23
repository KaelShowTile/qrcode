<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

$config_file = __DIR__ . '/config.json';
if (!file_exists($config_file)) {
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        header("Location: setup.php");
        exit;
    }
} else {
    $config = json_decode(file_get_contents($config_file), true);
    $wp_path = rtrim($config['wp_path'] ?? '', '/\\');
    $wp_load = $wp_path . '/wp-load.php';
    if (!file_exists($wp_load)) {
        if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
            header("Location: setup.php?error=wp-load.php not found at configured path");
            exit;
        }
    } else {
        if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
            // Include WordPress core
            if (!defined('ABSPATH')) {
                require_once $wp_load;
            }
        }
    }
}

// Function to check if user is logged in (WP admin)
function is_app_logged_in() {
    return isset($_SESSION['wp_user_id']) && $_SESSION['wp_user_id'] > 0;
}
