<?php
// reset.php - handle password reset via token

$error = '';
$success = '';

if (!isset($_GET['token']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    $error = 'Invalid reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($newPassword) || strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPassword !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $resetsFile = 'password_resets.json';
        if (!file_exists($resetsFile)) {
            $error = 'Invalid or expired token.';
        } else {
            $resets = json_decode(file_get_contents($resetsFile), true);
            $found = null;
            foreach ($resets as $i => $r) {
                if ($r['token'] === $token) {
                    if ($r['expires'] < time()) {
                        $error = 'Token expired.';
                        break;
                    }
                    $found = ['entry' => $r, 'index' => $i];
                    break;
                }
            }

            if (!$found) {
                if (empty($error)) $error = 'Invalid or expired token.';
            } else {
                // update user's password
                $usersFile = 'users.json';
                if (!file_exists($usersFile)) {
                    $error = 'User data not available.';
                } else {
                    $users = json_decode(file_get_contents($usersFile), true);
                    $updated = false;
                    foreach ($users as $ui => $u) {
                        if ($u['username'] === $found['entry']['username']) {
                            $users[$ui]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                            $updated = true;
                            break;
                        }
                    }

                    if ($updated) {
                        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                        // remove the used reset token
                        array_splice($resets, $found['index'], 1);
                        file_put_contents($resetsFile, json_encode($resets, JSON_PRETTY_PRINT));
                        // Password updated successfully (no automatic redirect)
                        $success = 'Password updated successfully.';
                    } else {
                        $error = 'User not found.';
                    }
                }
            }
        }
    }
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; 
            padding: 0; 
            min-height: 100vh;
            background-color: #000;
            background-image: url('https://images.unsplash.com/photo-1534796636912-3b95b3ab5986?ixlib=rb-4.0.3&auto=format&fit=crop&w=2342&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 400px; 
            width: 100%;
            margin: 0 auto; 
            background: rgba(26, 11, 46, 0.85);
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
        }
        .form-group { margin-bottom: 12px; }
        label { display:block; margin-bottom:6px; color: #b388ff; font-weight: bold; }
        input { width:100%; padding:10px; background: rgba(0,0,0,0.3); border:1px solid #4a2c60; border-radius:4px; color: white; }
        button { background:#7c4dff; color:#fff; padding:10px 14px; border:none; border-radius:4px; cursor:pointer; width:100%; transition: background 0.3s; }
        button:hover { background: #651fff; }
        .error { background:#f8d7da; color:#721c24; padding:10px; border-radius:4px; margin-bottom:10px; }
        .success { background:#d4edda; color:#155724; padding:10px; border-radius:4px; margin-bottom:10px; }
        h2 { color: #fff; text-align: center; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
