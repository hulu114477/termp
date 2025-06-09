<?php
// cancel_request.php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];
    $placement_id = $_POST['placement_id'];
    $passenger_id = $_POST['passenger_id'];
    $cancel_reason = $_POST['cancel_reason'];

    if (empty($cancel_reason)) {
        $message = "יש לציין סיבת ביטול";
        $message_class = "error";
    } elseif (!$request_id || !$placement_id || !$passenger_id) {
        $message = "נתונים חסרים לדחיית הבקשה";
        $message_class = "error";
    } else {
        $stmt_info = $conn->prepare("
            SELECT direction, status
            FROM ride_requests
            WHERE request_id = ? AND driver_id = ? AND placement_id = ? AND passenger_id = ?
            FOR UPDATE
        ");
        if (!$stmt_info) {
            $message = "שגיאה בהכנת שאילתת המידע";
            $message_class = "error";
        } else {
            $stmt_info->bind_param("iiii", $request_id, $employee_id, $placement_id, $passenger_id);
            $stmt_info->execute();
            $request_info = $stmt_info->get_result()->fetch_assoc();
            $stmt_info->close();

            if (!$request_info) {
                $message = "הבקשה לא נמצאה או אינה שייכת לך";
                $message_class = "error";
            } else {
                $current_status = $request_info['status'];
                $direction = $request_info['direction'];

                if ($current_status == 'not_approved') {
                    $message = "לא ניתן לדחות בקשה שכבר נדחתה";
                    $message_class = "error";
                } else {
                    $stmt_cancel = $conn->prepare("UPDATE ride_requests SET status = 'not_approved' WHERE request_id = ?");
                    if (!$stmt_cancel) {
                        $message = "שגיאה בהכנת עדכון סטטוס";
                        $message_class = "error";
                    } else {
                        $stmt_cancel->bind_param("i", $request_id);
                        if (!$stmt_cancel->execute()) {
                            $message = "שגיאה בעדכון סטטוס";
                            $message_class = "error";
                        } else {
                            if ($current_status == 'approved') {
                                $update_field = ($direction == 'to_work') ?
                                    'available_places_to_work = available_places_to_work + 1' :
                                    'available_places_from_work = available_places_from_work + 1';
                                $stmt_update_seats = $conn->prepare("UPDATE job_placement SET $update_field WHERE placement_id = ?");
                                if (!$stmt_update_seats) {
                                    $message = "שגיאה בהכנת עדכון מקומות";
                                    $message_class = "error";
                                } else {
                                    $stmt_update_seats->bind_param("i", $placement_id);
                                    if (!$stmt_update_seats->execute()) {
                                        $message = "שגיאה בעדכון מקומות";
                                        $message_class = "error";
                                    }
                                    $stmt_update_seats->close();
                                }
                            }

                            // שליחת הודעה ישירות כאן
                            $message_text_for_db = "לצערנו בקשת הטרמפ שלך לנסיעה מספר $placement_id נדחתה סיבה: $cancel_reason";
                            $direction_for_message = ($direction == 'to_work') ? 'to_work' : 'from_work';
                            $stmt_msg = $conn->prepare("
                                INSERT INTO messages (placement_id, sender_id, recipient_id, message_text, message_time, direction)
                                VALUES (?, ?, ?, ?, NOW(), ?)
                            ");
                            if ($stmt_msg) {
                                $stmt_msg->bind_param("iiiss", $placement_id, $employee_id, $passenger_id, $message_text_for_db, $direction_for_message);
                                $stmt_msg->execute();
                                $stmt_msg->close();
                            } else {
                                $message = "שגיאה בשליחת הודעה לנוסע";
                                $message_class = "error";
                            }
                            $message = "הבקשה נדחתה בהצלחה והודעה נשלחה לנוסע";
                            $message_class = "success";
                        }
                        $stmt_cancel->close();
                    }
                }
            }
        }
    }
}

$_SESSION['message'] = $message;
$_SESSION['message_class'] = $message_class;
header("Location: requests_from_me.php");
exit();

$conn->close();
?>