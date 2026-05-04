<?php
define('DB_SERVER', 'caddy-db');
define('DB_NAME', 'parc');

function get_pdo(string $account): PDO {
    if ($account === 'login') {
        $username = 'login.sigacs';
        $password = 'stjolorient';
    } else {
        $username = 'app.sigacs';
        $password = 'stjolorient';
    }

    try {
        $dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die('ERROR: Could not connect. ' . $e->getMessage());
    }
}

/*
 * Basic input safety helpers
 * - sanitize_input: trims input
 * - is_safe_input: rejects obvious SQL injection tokens/patterns
 * Note: PDO prepared statements already protect against SQL injection;
 * these helpers are an additional layer to detect suspicious input.
 */
function sanitize_input($data)
{
    return trim($data);
}

function is_safe_input($input)
{
    if ($input === null) {
        return false;
    }

    $s = strtolower($input);

    // Reject inputs that contain SQL control characters or keywords
    $blacklist = ["--", ";", "/*", "*/", "select ", "insert ", "update ", "delete ", "drop ", "truncate ", "union ", "xp_"];

    foreach ($blacklist as $token) {
        if (strpos($s, $token) !== false) {
            return false;
        }
    }

    return true;
}
