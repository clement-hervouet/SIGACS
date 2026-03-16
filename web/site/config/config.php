<?php
/* Database credentials. Assuming you are running MySQL
server with user.sql setting (user 'your-user' with 'your-passwd' password) */
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your-user');
define('DB_PASSWORD', 'your-passwd');
define('DB_NAME', 'users_base');

/* Attempt to connect to MySQL database using PDO */
try {
    $dsn = 'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO(
        $dsn,
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('ERROR: Could not connect. ' . $e->getMessage());
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
