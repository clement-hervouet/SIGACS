<?php
// Standalone endpoint — fetches the menu tree via JS after mutations
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403); exit;
}
require_once __DIR__ . '/../config/config.php';
include __DIR__ . '/content.php';