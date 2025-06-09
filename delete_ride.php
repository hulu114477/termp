<?php
// delete_ride.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';

// --- ביטול טרמפ ---
if (isset($_POST['delete_ride'])) {
    $placement_id = $_POST['placement_id'];
    
    $check_stmt = $conn->prepare("
        SELECT jp.employee_id, jp.placement_date, s.shift_name 
        FROM job_placement jp
        JOIN shifts s ON jp.shift_id = s.shift_id
        WHERE jp.placement_id = ?
    ");
    if (!$check_stmt) {
        $message = "שגיאה בהכנת שאילתת הבדיקה";
        $message_class = "error";
    } else {
        $check_stmt->bind_param("i", $placement_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $ride = $result->fetch_assoc();
        $check_stmt->close();

        if (!$ride) {
            $message = "הטרמפ לא נמצא";
            $message_class = "error";
        } elseif ($ride['employee_id'] != $employee_id) {
            $message = "אין לך הרשאה למחוק טרמפ זה";
            $message_class = "error";
        } else {
            $find_passengers_stmt = $conn->prepare("
                SELECT passenger_id, direction
                FROM ride_requests
                WHERE placement_id = ? AND status IN ('pending', 'approved')
            ");
            if (!$find_passengers_stmt) {
                $message = "שגיאה בהכנת שאילתת הנוסעים";
                $message_class = "error";
            } else {
                $find_passengers_stmt->bind_param("i", $placement_id);
                $find_passengers_stmt->execute();
                $passengers_result = $find_passengers_stmt->get_result();

                $cancellation_date = date('d/m/Y');
                $ride_date = date('d/m/Y', strtotime($ride['placement_date']));
                $shift_name = $ride['shift_name'];

                while ($passenger = $passengers_result->fetch_assoc()) {
                    $passenger_id = $passenger['passenger_id'];
                    $direction = $passenger['direction'];
                    $msg_txt = ($direction == 'to_work')
                        ? "שלום הטרמפ לעבודה בתאריך $ride_date במשמרת $shift_name בוטל על ידי הנהג נשלח בתאריך $cancellation_date"
                        : "שלום הטרמפ מהעבודה הביתה בתאריך $ride_date במשמרת $shift_name בוטל על ידי הנהג נשלח בתאריך $cancellation_date";

                    $insert_message_stmt = $conn->prepare("
                        INSERT INTO messages (placement_id, sender_id, recipient_id, message_text, message_time, direction)
                        VALUES (?, ?, ?, ?, NOW(), ?)
                    ");
                    if (!$insert_message_stmt) {
                        $message = "שגיאה בהכנת שאילתת ההודעה";
                        $message_class = "error";
                    } else {
                        $insert_message_stmt->bind_param("iiiss", $placement_id, $employee_id, $passenger_id, $msg_txt, $direction);
                        if (!$insert_message_stmt->execute()) {
                            $message = "שגיאה בשליחת הודעה לנוסע ID $passenger_id";
                            $message_class = "error";
                        }
                        $insert_message_stmt->close();
                    }
                }
                $find_passengers_stmt->close();

                $update_status_stmt = $conn->prepare("UPDATE job_placement SET status = 'cancelled' WHERE placement_id = ?");
                if (!$update_status_stmt) {
                    $message = "שגיאה בהכנת עדכון סטטוס הטרמפ";
                    $message_class = "error";
                } else {
                    $update_status_stmt->bind_param("i", $placement_id);
                    if (!$update_status_stmt->execute()) {
                        $message = "שגיאה בעדכון סטטוס הטרמפ";
                        $message_class = "error";
                    } else {
                        $update_requests_stmt = $conn->prepare("
                            UPDATE ride_requests SET status = 'cancelled_by_driver'
                            WHERE placement_id = ? AND status IN ('pending', 'approved')
                        ");
                        if (!$update_requests_stmt) {
                            $message = "שגיאה בהכנת עדכון סטטוס בקשות";
                            $message_class = "error";
                        } else {
                            $update_requests_stmt->bind_param("i", $placement_id);
                            if (!$update_requests_stmt->execute()) {
                                $message = "שגיאה בעדכון סטטוס בקשות הטרמפ";
                                $message_class = "error";
                            } else {
                                $message = "טרמפ בוטל בהצלחה הודעות נשלחו לכל הנוסעים הרשומים";
                                $message_class = "success";
                            }
                            $update_requests_stmt->close();
                        }
                    }
                    $update_status_stmt->close();
                }
            }
        }
    }
}

// החזרה לדף הטרמפים עם הודעה
$_SESSION['message'] = $message;
$_SESSION['message_class'] = $message_class;
header("Location: rides.php");
exit();

$conn->close();
?>
