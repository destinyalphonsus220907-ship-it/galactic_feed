<?php
require_once 'session_handler.php';

if (function_exists('logoutUser')) {
    logoutUser();
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Clear session
    $_SESSION = [];

    // Clear session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();

    // Clear remember cookies
    setcookie('username', '', time() - 3600, '/');
    setcookie('remember_me', '', time() - 3600, '/');
}

header('Location: index.php');
exit;
