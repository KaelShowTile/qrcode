<?php
require_once __DIR__ . '/bootstrap.php';

$error = $_GET['error'] ?? '';
$success = '';

$current_path = '';
if (file_exists(__DIR__ . '/config.json')) {
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    $current_path = $config['wp_path'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $path = $_POST['wp_path'] ?? '';
    if (file_exists(rtrim($path, '/\\') . '/wp-load.php')) {
        file_put_contents(__DIR__ . '/config.json', json_encode(['wp_path' => rtrim($path, '/\\')]));
        header("Location: login.php");
        exit;
    } else {
        $error = "wp-load.php not found in the specified directory: " . htmlspecialchars($path);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup - QR Code Generator</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { margin-top: 0; color: #1a1a1a; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: .5rem; color: #4a4a4a; }
        input[type="text"] { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; padding: .5rem 1rem; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1rem; }
        button:hover { background: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; padding: .75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>System Setup</h2>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 1.5rem;">Please provide the absolute path to your WordPress installation directory.</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="wp_path">WordPress Absolute Path</label>
                <input type="text" id="wp_path" name="wp_path" value="<?= htmlspecialchars($current_path ?: '/var/www/html/wordpress') ?>" required>
            </div>
            <button type="submit">Save Configuration</button>
        </form>
    </div>
</body>
</html>
