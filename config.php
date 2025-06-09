<?php
// config.php
$host = "localhost";
$user = "root";
$password = "";
$database = "tremp";

// יצירת חיבור
$conn = new mysqli($host, $user, $password, $database);

// בדיקת חיבור
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// הגדרת קידוד ל־UTF-8
$conn->set_charset("utf8");
?>
