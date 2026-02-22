<?php
// db.php - MySQL PDO connection helper

function getPDO(): PDO {
    $host = 'localhost';
    $db   = 'social_app';
    $user = 'root';
    $pass = ''; // change if your MySQL has a password

    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return new PDO($dsn, $user, $pass, $opts);
}
