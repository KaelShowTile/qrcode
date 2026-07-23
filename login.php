<?php
require_once __DIR__ . '/bootstrap.php';

if (is_app_logged_in()) {
    header("Location: tiles.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Use WP core authentication
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        $error = "Invalid username or password.";
    } else {
        // Check if user is administrator
        if (in_array('administrator', (array) $user->roles)) {
            $_SESSION['wp_user_id'] = $user->ID;
            header("Location: tiles.php");
            exit;
        } else {
            $error = "You must be an administrator to access this app.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - QR Code Generator</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { margin-top: 0; color: #1a1a1a; text-align: center; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: .5rem; color: #4a4a4a; }
        input[type="text"], input[type="password"] { width: 100%; padding: .5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; padding: .5rem 1rem; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1rem; margin-top: 10px; }
        button:hover { background: #0056b3; }
        .error { color: #dc3545; background: #f8d7da; padding: .75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Admin Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Log In</button>
        </form>
    </div>
</body>
</html>
