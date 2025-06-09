<?php
// request_ride.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['employee_id'])) {
    $conn->close();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request'])) {
    $passenger_id = $_SESSION['employee_id'];
    $placement_id = $_POST['placement_id'] ?? null;
    $direction = $_POST['direction'] ?? '';
    $shift_name = $_POST['shift_name'] ?? '';

    // אימות קלט
    if (!$placement_id || !$direction || !$shift_name) {
        $_SESSION['message'] = "פרטים חסרים.";
        $_SESSION['message_class'] = "error";
        $conn->close();
        header("Location: search_ride.php");
        exit();
    }

    // בדיקה אם כבר קיימת בקשה לטרמפ זה
    $check_stmt = $conn->prepare("SELECT * FROM ride_requests WHERE placement_id = ? AND passenger_id = ?");
    $check_stmt->bind_param("ii", $placement_id, $passenger_id);
    $check_stmt->execute();
    $existing_request = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing_request) {
        $_SESSION['message'] = "כבר שלחת בקשה לטרמפ זה.";
        $_SESSION['message_class'] = "error";
        $conn->close();
        header("Location: search_ride.php");
        exit();
    } else {
        // שליפת driver_id מטבלת job_placement
        $driver_stmt = $conn->prepare("SELECT employee_id FROM job_placement WHERE placement_id = ?");
        $driver_stmt->bind_param("i", $placement_id);
        $driver_stmt->execute();
        $driver_result = $driver_stmt->get_result()->fetch_assoc();
        $driver_stmt->close();

        if (!$driver_result) {
            $_SESSION['message'] = "טרמפ לא נמצא.";
            $_SESSION['message_class'] = "error";
            $conn->close();
            header("Location: search_ride.php");
            exit();
        }
        $driver_id = $driver_result['employee_id'];

        $insert_stmt = $conn->prepare("INSERT INTO ride_requests (placement_id, passenger_id, direction, driver_id, status) VALUES (?, ?, ?, ?, 'pending')");
        $insert_stmt->bind_param("iisi", $placement_id, $passenger_id, $direction, $driver_id);
        if ($insert_stmt->execute()) {
            $_SESSION['message'] = "הבקשה נשלחה בהצלחה!";
            $_SESSION['message_class'] = "success";
            $insert_stmt->close();
            $conn->close();
            header("Location: my_requests.php");
            exit();
        } else {
            $_SESSION['message'] = "אירעה שגיאה בעת שליחת הבקשה.";
            $_SESSION['message_class'] = "error";
            $insert_stmt->close();
            $conn->close();
            header("Location: search_ride.php");
            exit();
        }
    }
} else {
    $conn->close();
    header("Location: search_ride.php");
    exit();
}