        <?php
        require_once 'session_handler.php';
        // Do not auto-redirect logged-in users to homepage.php
        redirectIfLoggedIn();    

            // Handle password reset request (sent from the login page)
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request'])) {
                $resetEmail = isset($_POST['reset_email']) ? trim($_POST['reset_email']) : '';
                $usersFile = 'users.json';
                $foundUser = null;

                if (file_exists($usersFile)) {
                    $users = json_decode(file_get_contents($usersFile), true);
                    foreach ($users as $u) {
                        if (isset($u['email']) && $u['email'] === $resetEmail) {
                            $foundUser = $u;
                            break;
                        }
                    }
                }

                // Always show a generic message to avoid user enumeration
                if ($foundUser) {
                    // create/reset token
                    $token = bin2hex(random_bytes(16));
                    $resetsFile = 'password_resets.json';
                    $resets = [];
                    if (file_exists($resetsFile)) {
                        $resets = json_decode(file_get_contents($resetsFile), true);
                    }

                    $resets[] = [
                        'token' => $token,
                        'username' => $foundUser['username'],
                        'expires' => time() + 3600
                    ];

                    file_put_contents($resetsFile, json_encode($resets, JSON_PRETTY_PRINT));

                    $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '\/') . '/reset.php?token=' . $token;

                    $success = 'If the email exists, a reset link was generated. For testing use: <a href="' . $resetLink . '">Reset link</a>';
                } else {
                    $success = 'If the email exists, a reset link was sent.';
                }
            }

            // Check if user is already logged in (no automatic redirect)
            if (isset($_SESSION['username'])) {
                // User is logged in; do not redirect to homepage.php per request
            }

            // Handle login submission
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['reset_request'])) {
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $password = isset($_POST['password']) ? $_POST['password'] : '';
                $rememberMe = isset($_POST['remember_me']);

                // Validation
                if (empty($username)) {
                    $error = 'Username is required.';
                } elseif (empty($password)) {
                    $error = 'Password is required.';
                } else {
                    // Check credentials against users.json
                    $usersFile = 'users.json';

                    if (file_exists($usersFile)) {
                        $users = json_decode(file_get_contents($usersFile), true);
                        $userFound = false;

                        foreach ($users as $user) {
                            if ($user['username'] === $username) {
                                $userFound = true;

                                // Verify password
                                if (password_verify($password, $user['password'])) {
                                    // Login successful
                                    $_SESSION['username'] = $user['username'];
                                    $_SESSION['email'] = $user['email'];
                                    $_SESSION['age'] = $user['age'];

                                    // Remember me functionality
                                    if ($rememberMe) {
                                        setcookie('username', $user['username'], time() + (30 * 24 * 60 * 60), '/');
                                        setcookie('remember_me', 'true', time() + (30 * 24 * 60 * 60), '/');
                                    }

                                    // Login successful — redirect immediately to homepage.php
                                    header('Location: comments.php');
                                    exit;
                                } else {
                                    $error = 'Invalid password.';
                                }
                                break;
                            }
                        }

                        if (!$userFound) {
                            $error = 'Username not found.';
                        }
                    } else {
                        $error = 'No users registered yet. <a href="signup.php" style="color: #721c24; text-decoration: underline;">Sign up here</a>.';
                    }
                }
            }

            if (!empty($error)) {
                echo '<div class="error-message">' . $error . '</div>';
            }

            if (!empty($success)) {
                echo '<div class="success-message">' . $success . '</div>';
            }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
        .navbar {
            display: none; /* Hide navbar on login page for cleaner look, or style it if needed */
        }
        .login-container {
            background: rgba(26, 11, 46, 0.85);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 400px;
        }
        .login-container h2 {
            color: #fff;
            margin-top: 0;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #b388ff;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            border: 1px solid #4a2c60;
            border-radius: 4px;
            color: white;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #7c4dff;
            box-shadow: 0 0 5px rgba(124, 77, 255, 0.3);
        }
        button {
            background-color: #7c4dff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
            transition: background 0.3s;
        }
        button:hover {
            background-color: #651fff;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .signup-link {
            margin-top: 15px;
            text-align: center;
        }
        .signup-link a {
            color: #b388ff;
            text-decoration: none;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .remember-me input {
            width: auto;
            margin-right: 8px;
            cursor: pointer;
        }
        .remember-me label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }
        #reset-box {
            background: rgba(0,0,0,0.3) !important;
            border: 1px solid #4a2c60 !important;
            color: #e0e0e0;
        }
    </style>
</head>
<body>
    <!-- Centered Login -->
    <div class="login-container">
        <div style="text-align: center; font-size: 3em; margin-bottom: 10px;">🪐</div>
        <h2>Log In</h2>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required <?php echo isset($_POST['username']) ? 'value="' . htmlspecialchars($_POST['username']) . '"' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Remember me</label>
            </div>
            
            <button type="submit">Log In</button>
        </form>
        
        <div style="margin-top:12px; text-align:center;">
            <a href="#" id="forgot-link" style="color:#007bff; text-decoration:none;">Forgot password?</a>
        </div>

        <div id="reset-box" style="display:none; margin-top:15px; background:#fff; padding:12px; border-radius:6px; border:1px solid #eee;">
            <form method="POST">
                <input type="hidden" name="reset_request" value="1">
                <div class="form-group">
                    <label for="reset_email">Enter your email to reset password:</label>
                    <input type="email" id="reset_email" name="reset_email" placeholder="you@example.com" required>
                </div>
                <button type="submit">Request Reset</button>
            </form>
        </div>

        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up here</a>
        </div>

        <script>
            document.getElementById('forgot-link').addEventListener('click', function(e){
                e.preventDefault();
                var box = document.getElementById('reset-box');
                box.style.display = box.style.display === 'none' ? 'block' : 'none';
            });
        </script>
    </div>
</body>
</html>