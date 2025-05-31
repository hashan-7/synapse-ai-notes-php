<?php
require_once 'config.php'; 

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
} catch (PDOException $e) {
    // Production වලදී, මෙවැනි සවිස්තරාත්මක error පණිවිඩ පෙන්වීම සුදුසු නැහැ.
    // Log to a file or show a generic error message.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please try again later or contact support.");
}


?>