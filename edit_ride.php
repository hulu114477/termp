<?php
// edit_ride.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';

// --- עריכת טרמפ ---
if (isset($_POST['edit_ride'])) {
    $placement_id = $_POST['placement_id'];
    $placement_date = $_POST['placement_date'];
    $shift_type = $_POST['shift_type'];
    $available_places_to_work = $_POST['available_places_away'];
    $available_places_from_work = $_POST['vacancies_return'];

    if (empty($placement_date) || !in_array($shift_type, ['בוקר', 'ערב', 'לילה']) || $available_places_to_work < 0 || $available_places_from_work < 0) {
        $message = "נתוני קלט לא תקינים";
        $message_class = "error";
    } else {
        $check_stmt = $conn->prepare("SELECT employee_id FROM job_placement WHERE placement_id = ?");
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
                $message = "אין לך הרשאה לערוך טרמפ זה";
                $message_class = "error";
            } else {
                $shift_id_map = ['בוקר' => 1, 'ערב' => 2, 'לילה' => 3];
                $shift_id = $shift_id_map[$shift_type];

                $count_passengers_stmt = $conn->prepare("
                    SELECT direction, COUNT(*) as count
                    FROM ride_requests
                    WHERE placement_id = ? AND status = 'approved'
                    GROUP BY direction
                ");
                if (!$count_passengers_stmt) {
                    $message = "שגיאה בהכנת שאילתת ספירת נוסעים";
                    $message_class = "error";
                } else {
                    $count_passengers_stmt->bind_param("i", $placement_id);
                    $count_passengers_stmt->execute();
                    $passenger_counts = $count_passengers_stmt->get_result();
                    $count_passengers_stmt->close();

                    $to_work_count = 0;
                    $from_work_count = 0;
                    while ($row = $passenger_counts->fetch_assoc()) {
                        if ($row['direction'] == 'to_work') $to_work_count = $row['count'];
                        elseif ($row['direction'] == 'from_work') $from_work_count = $row['count'];
                    }
                    if ($available_places_to_work < $to_work_count) {
                        $message = "מספר המקומות הפנויים לכיוון העבודה לא יכול להיות נמוך ממספר הנוסעים המאושרים ($to_work_count)";
                        $message_class = "error";
                    } elseif ($available_places_from_work < $from_work_count) {
                        $message = "מספר המקומות הפנויים בחזרה לא יכול להיות נמוך ממספר הנוסעים המאושרים ($from_work_count)";
                        $message_class = "error";
                    } else {
                        $update_stmt = $conn->prepare("
                            UPDATE job_placement 
                            SET placement_date = ?, shift_id = ?, available_places_to_work = ?, available_places_from_work = ? 
                            WHERE placement_id = ?
                        ");
                        if (!$update_stmt) {
                            $message = "שגיאה בהכנת עדכון טרמפ";
                            $message_class = "error";
                        } else {
                            $update_stmt->bind_param("siiii", $placement_date, $shift_id, $available_places_to_work, $available_places_from_work, $placement_id);
                            if (!$update_stmt->execute()) {
                                $message = "שגיאה בעדכון הטרמפ";
                                $message_class = "error";
                            } else {
                                $find_passengers_stmt = $conn->prepare("
                                    SELECT passenger_id, direction 
                                    FROM ride_requests 
                                    WHERE placement_id = ? AND status IN ('pending', 'approved')
                                ");
                                if (!$find_passengers_stmt) {
                                    $message = "שגיאה בהכנת שאילתת נוסעים לעדכון";
                                    $message_class = "error";
                                } else {
                                    $find_passengers_stmt->bind_param("i", $placement_id);
                                    $find_passengers_stmt->execute();
                                    $passengers_result = $find_passengers_stmt->get_result();

                                    $update_date = date('d/m/Y');
                                    $ride_date = date('d/m/Y', strtotime($placement_date));
                                    while ($passenger = $passengers_result->fetch_assoc()) {
                                        $passenger_id = $passenger['passenger_id'];
                                        $direction = $passenger['direction'];
                                        $update_message = ($direction == 'to_work')
                                            ? "שלום פרטי הטרמפ לעבודה בתאריך $ride_date במשמרת $shift_type עודכנו נשלח בתאריך $update_date"
                                            : "שלום פרטי הטרמפ מהעבודה הביתה בתאריך $ride_date במשמרת $shift_type עודכנו נשלח בתאריך $update_date";
                                        $insert_message_stmt = $conn->prepare("
                                            INSERT INTO messages (placement_id, sender_id, recipient_id, messages_text, message_time, direction)
                                            VALUES (?, ?, ?, ?, NOW(), ?)
                                        ");
                                        if (!$insert_message_stmt) {
                                            $message = "שגיאה בהכנת שאילתת הודעת עדכון";
                                            $message_class = "error";
                                        } else {
                                            $insert_message_stmt->bind_param("iiiss", $placement_id, $employee_id, $passenger_id, $update_message, $direction);
                                            if (!$insert_message_stmt->execute()) {
                                                $message = "שגיאה בשליחת הודעת עדכון לנוסע ID $passenger_id";
                                                $message_class = "error";
                                            } else {
                                                $message = "טרמפ עודכן בהצלחה הודעות עדכון נשלחו לכל הנוסעים הרשומים";
                                                $message_class = "success";
                                            }
                                            $insert_message_stmt->close();
                                        }
                                    }
                                    $find_passengers_stmt->close();
                                }
                            }
                            $update_stmt->close();
                        }
                    }
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