<?php
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $age = isset($_POST['age']) ? intval($_POST['age']) : 0;

    // Validation
    if (empty($username)) {
        $error = 'Username is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($password)) {
        $error = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif ($age < 13) {
        $error = 'You must be at least 13 years old to create an account.';
    } else {
        // Check if user already exists
        $usersFile = 'users.json';
        $users = [];

        if (file_exists($usersFile)) {
            $users = json_decode(file_get_contents($usersFile), true);

            // Check if username or email already exists
            foreach ($users as $user) {
                if ($user['username'] === $username) {
                    $error = 'Username already taken.';
                    break;
                }
                if ($user['email'] === $email) {
                    $error = 'Email already registered.';
                    break;
                }
            }
        }

        if (empty($error)) {
            // Hash password and save user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $users[] = [
                'username' => htmlspecialchars($username),
                'email' => htmlspecialchars($email),
                'password' => $hashedPassword,
                'age' => $age,
                'created_at' => date("Y-m-d H:i:s")
            ];

            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            $success = 'Account created successfully! <a href="index.php" style="color: #155724; text-decoration: underline;">Log in here</a>.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
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
            display: none;
        }
        .signup-container {
            background: rgba(26, 11, 46, 0.85);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(124, 77, 255, 0.3);
            border: 1px solid #4a2c60;
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 400px;
        }
        .signup-container h2 {
            color: #fff;
            margin-top: 0;
            text-align: center;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #b388ff; font-weight: bold; }
        .form-group input { 
            width: 100%; 
            padding: 10px; 
            background: rgba(0,0,0,0.3);
            border: 1px solid #4a2c60;
            border-radius: 4px; 
            color: white;
            font-family: inherit;
        }
        .form-group input:focus { outline: none; border-color: #7c4dff; }
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
        button:hover { background-color: #651fff; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 12px; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 15px; }
        .success-message { background-color: #d4edda; color: #155724; padding: 12px; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 15px; }
        .login-link { margin-top: 15px; text-align: center; }
        .login-link a { color: #b388ff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="signup-container">
        <div style="text-align: center; font-size: 3em; margin-bottom: 10px;">🪐</div>
        <h2>Create an Account</h2>

        <?php
            if (!empty($error)) {
                echo '<div class="error-message">' . $error . '</div>';
            }
            if (!empty($success)) {
                echo '<div class="success-message">' . $success . '</div>';
            }
        ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Choose a username" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="your@email.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
            </div>

            <div class="form-group">
                <label for="age">Age:</label>
                <input type="number" id="age" name="age" placeholder="Your age" min="1" max="120" required>
            </div>

            <button type="submit">Sign Up</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="index.php">Log in here</a>
        </div>
    </div>
</body>
</html>