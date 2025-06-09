<?php
// approve_request.php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $request_id = $_POST['request_id'];
    $placement_id = $_POST['placement_id'];
    $passenger_id = $_POST['passenger_id'];

    if (!$request_id || !$placement_id || !$passenger_id) {
        $message = "נתונים חסרים לאישור הבקשה";
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
            } elseif ($request_info['status'] !== 'pending') {
                $message = "ניתן לאשר רק בקשות במצב ממתין";
                $message_class = "error";
            } else {
                $direction = $request_info['direction'];
                $check_field = ($direction == 'to_work') ? 'available_places_to_work' : 'available_places_from_work';
                $stmt_check_seats = $conn->prepare("SELECT $check_field FROM job_placement WHERE placement_id = ? FOR UPDATE");
                if (!$stmt_check_seats) {
                    $message = "שגיאה בהכנת שאילתת המושבים";
                    $message_class = "error";
                } else {
                    $stmt_check_seats->bind_param("i", $placement_id);
                    $stmt_check_seats->execute();
                    $placement_info = $stmt_check_seats->get_result()->fetch_assoc();
                    $stmt_check_seats->close();

                    if (!$placement_info) {
                        $message = "הטרמפ המשויך לבקשה לא נמצא";
                        $message_class = "error";
                    } else {
                        $available_places = $placement_info[$check_field];
                        if ($available_places <= 0) {
                            $message = "אין מקומות פנויים לכיוון זה";
                            $message_class = "error";
                        } else {
                            $stmt_approve = $conn->prepare("UPDATE ride_requests SET status = 'approved' WHERE request_id = ?");
                            if (!$stmt_approve) {
                                $message = "שגיאה בהכנת עדכון סטטוס";
                                $message_class = "error";
                            } else {
                                $stmt_approve->bind_param("i", $request_id);
                                if (!$stmt_approve->execute()) {
                                    $message = "שגיאה בעדכון סטטוס";
                                    $message_class = "error";
                                } else {
                                    $update_field = ($direction == 'to_work') ?
                                        'available_places_to_work = available_places_to_work - 1' :
                                        'available_places_from_work = available_places_from_work - 1';
                                    $stmt_update_seats = $conn->prepare("UPDATE job_placement SET $update_field WHERE placement_id = ?");
                                    if (!$stmt_update_seats) {
                                        $message = "שגיאה בהכנת עדכון מקומות";
                                        $message_class = "error";
                                    } else {
                                        $stmt_update_seats->bind_param("i", $placement_id);
                                        if (!$stmt_update_seats->execute()) {
                                            $message = "שגיאה בעדכון מקומות";
                                            $message_class = "error";
                                        } else {
                                            // שליחת הודעה ישירות כאן
                                            $message_text_for_db = "בקשת הטרמפ שלך לנסיעה מספר $placement_id אושרה";
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
                                                // ניתן להוסיף לוגיקה לטיפול בשגיאה בשליחת הודעה, אך כרגע נתעלם מאבטחה
                                            }
                                            $message = "הבקשה אושרה בהצלחה והודעה נשלחה לנוסע";
                                            $message_class = "success";
                                        }
                                        $stmt_update_seats->close();
                                    }
                                }
                                $stmt_approve->close();
                            }
                        }
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