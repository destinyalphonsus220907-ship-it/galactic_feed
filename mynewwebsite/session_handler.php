<?php

/**
 * session_handler.php
 * Centralized session management for the website
 */

// Database connection helper
require_once __DIR__ . '/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
session_start([
    'cookie_lifetime' => 0,
    'cookie_secure'  => true,
    'cookie_httponly'=> true,
    'use_strict_mode'=> true
]);}

/**
 * Check if user is logged in
 * @return bool
 */
function isUserLoggedIn() {
    return isset($_SESSION['username']) && !empty($_SESSION['username']);
}

/**
 * Get current logged-in user info
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (isUserLoggedIn()) {
        return [
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'] ?? '',
            'age' => $_SESSION['age'] ?? 0
        ];
    }
    return null;
}

/**
 * Login user - set session variables
 * @param string $username
 * @param string $email
 * @param int $age
 * @param bool $rememberMe
 */
function loginUser($username, $email, $age, $rememberMe = false) {
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['age'] = $age;
    
    if ($rememberMe) {
        setcookie('username', $username, time() + (30 * 24 * 60 * 60), '/');
        setcookie('remember_me', 'true', time() + (30 * 24 * 60 * 60), '/');
    }
}

/**
 * Logout user - clear session and cookies
 */
function logoutUser() {
    // Unset session variables
    unset($_SESSION['username']);
    unset($_SESSION['email']);
    unset($_SESSION['age']);
    
    // Clear remember me cookies
    setcookie('username', '', time() - 3600, '/');
    setcookie('remember_me', '', time() - 3600, '/');
    
    // Destroy session
    session_destroy();
}

/**
 * Require login - redirect to login page if not logged in
 * @param string $redirectUrl URL to redirect to if not logged in (default: index.php)
 */
function requireLogin($redirectUrl = 'index.php') {
    if (!isUserLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Redirect if already logged in
 * @param string $redirectUrl URL to redirect to if logged in (default: none)
 */
function redirectIfLoggedIn($redirectUrl = '') {
    if (isUserLoggedIn() && !empty($redirectUrl)) {
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Get user from users.json by username
 * @param string $username
 * @return array|null User data or null if not found
 */
function getUserByUsername($username) {
    $usersFile = 'users.json';
    
    if (!file_exists($usersFile)) {
        return null;
    }
    
    $users = json_decode(file_get_contents($usersFile), true);
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    
    return null;
}

/**
 * Get user from users.json by email
 * @param string $email
 * @return array|null User data or null if not found
 */
function getUserByEmail($email) {
    $usersFile = 'users.json';
    
    if (!file_exists($usersFile)) {
        return null;
    }
    
    $users = json_decode(file_get_contents($usersFile), true);
    foreach ($users as $user) {
        if (isset($user['email']) && $user['email'] === $email) {
            return $user;
        }
    }
    
    return null;
}

/**
 * Verify user credentials
 * @param string $username
 * @param string $password
 * @return array|false User data if valid, false otherwise
 */
function verifyCredentials($username, $password) {
    $user = getUserByUsername($username);
    
    if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
        return $user;
    }
    
    return false;
}

/**
 * Get all users from users.json
 * @return array Array of users or empty array
 */
function getAllUsers() {
    $usersFile = 'users.json';
    
    if (!file_exists($usersFile)) {
        return [];
    }
    
    return json_decode(file_get_contents($usersFile), true) ?? [];
}

/**
 * Check if username already exists
 * @param string $username
 * @return bool
 */
function usernameExists($username) {
    return getUserByUsername($username) !== null;
}

/**
 * Check if email already exists
 * @param string $email
 * @return bool
 */
function emailExists($email) {
    return getUserByEmail($email) !== null;
}

/**
 * Save user to users.json
 * @param array $user User data to save
 * @return bool Success status
 */
function saveUser($user) {
    $usersFile = 'users.json';
    $users = [];
    
    if (file_exists($usersFile)) {
        $users = json_decode(file_get_contents($usersFile), true) ?? [];
    }
    
    $users[] = $user;
    
    return file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Update user password
 * @param string $username
 * @param string $newPassword
 * @return bool Success status
 */
function updateUserPassword($username, $newPassword) {
    $usersFile = 'users.json';
    
    if (!file_exists($usersFile)) {
        return false;
    }
    
    $users = json_decode(file_get_contents($usersFile), true);
    
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            return true;
        }
    }
    
    return false;
}

/**
 * Generate password reset token
 * @param string $username
 * @return string Reset token
 */
function generateResetToken($username) {
    $token = bin2hex(random_bytes(16));
    $resetsFile = 'password_resets.json';
    $resets = [];
    
    if (file_exists($resetsFile)) {
        $resets = json_decode(file_get_contents($resetsFile), true) ?? [];
    }
    
    $resets[] = [
        'token' => $token,
        'username' => $username,
        'expires' => time() + 3600 // 1 hour expiry
    ];
    
    file_put_contents($resetsFile, json_encode($resets, JSON_PRETTY_PRINT));
    
    return $token;
}

/**
 * Verify reset token
 * @param string $token
 * @return array|false Token data if valid, false otherwise
 */
function verifyResetToken($token) {
    $resetsFile = 'password_resets.json';
    
    if (!file_exists($resetsFile)) {
        return false;
    }
    
    $resets = json_decode(file_get_contents($resetsFile), true);
    
    foreach ($resets as $i => $reset) {
        if ($reset['token'] === $token) {
            if ($reset['expires'] >= time()) {
                return ['entry' => $reset, 'index' => $i];
            } else {
                return false; // Token expired
            }
        }
    }
    
    return false;
}

/**
 * Remove reset token after use
 * @param int $tokenIndex Index of token in array
 * @return bool Success status
 */
function removeResetToken($tokenIndex) {
    $resetsFile = 'password_resets.json';
    
    if (!file_exists($resetsFile)) {
        return false;
    }
    
    $resets = json_decode(file_get_contents($resetsFile), true);
    array_splice($resets, $tokenIndex, 1);
    
    return file_put_contents($resetsFile, json_encode($resets, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Get reset link URL
 * @param string $token
 * @return string Full reset link
 */
function getResetLink($token) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    
    return $protocol . '://' . $host . $path . '/reset.php?token=' . $token;
}
