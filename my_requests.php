<?php
// my_requests.php 
session_start();
require_once 'config.php';

// בדיקת התחברות של עובד
if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$passenger_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_class = $_SESSION['message_class'];
    unset($_SESSION['message']);
    unset($_SESSION['message_class']);
}

// טיפול בביטול בקשה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'] ?? null;
    $placement_id = $_POST['placement_id'] ?? null;

    if ($request_id && $placement_id) {
        // ביטול הבקשה
        $stmt = $conn->prepare("UPDATE ride_requests SET status = 'cancelled_by_passenger' WHERE request_id = ? AND passenger_id = ?");
        $stmt->bind_param("ii", $request_id, $passenger_id);
        
        if ($stmt->execute()) {
            $message = "הבקשה בוטלה בהצלחה";
            $message_class = "success";
        } else {
            $message = "שגיאה בביטול הבקשה";
            $message_class = "error";
        }
        $stmt->close();
    } else {
        $message = "נתונים חסרים";
        $message_class = "error";
    }
}

// פונקציה לתרגום סטטוס לעברית
function getStatusText($status) {
    switch($status) {
        case 'approved': return 'מאושר';
        case 'not_approved': return 'נדחה';
        case 'pending': return 'ממתין לאישור';
        case 'cancelled_by_passenger': return 'בוטל על ידך';
        case 'cancelled_by_driver': return 'בוטל על ידי הנהג';
        default: return 'לא ידוע';
    }
}

// שליפת הבקשות של המשתמש
$query = "SELECT
    rr.request_id,
    jp.placement_id,
    jp.placement_date,
    s.shift_name,
    e.employee_name AS driver_name,
    e.phone_number AS driver_phone,
    rr.status,
    rr.direction
FROM ride_requests rr
JOIN job_placement jp ON rr.placement_id = jp.placement_id
JOIN employees e ON rr.driver_id = e.employee_id
JOIN shifts s ON jp.shift_id = s.shift_id
WHERE rr.passenger_id = ?
ORDER BY jp.placement_date DESC, rr.request_id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $passenger_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בקשות שלי לטרמפ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h1>בקשות שלי לטרמפ</h1> 
    <?php if ($message): ?>
        <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <!-- הצגת טבלת הבקשות אם קיימות -->
    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>מספר בקשה</th>
                    <th>מספר טרמפ</th>
                    <th>תאריך</th>
                    <th>משמרת</th>
                    <th>כיוון</th>
                    <th>נהג</th>
                    <th>טלפון נהג</th>
                    <th>סטטוס</th>
                    <th>פעולה</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['request_id']; ?></td>
                        <td><?php echo $row['placement_id']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($row['placement_date'])); ?></td>
                        <td><?php echo $row['shift_name']; ?></td>
                        <td><?php echo ($row['direction'] == 'to_work') ? 'לעבודה' : 'מהעבודה'; ?></td>
                        <td><?php echo $row['driver_name']; ?></td>
                        <td><?php echo $row['driver_phone']; ?></td>
                        <td><?php echo getStatusText($row['status']); ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending' || $row['status'] == 'approved'): ?>
                                <form method="POST" onsubmit="return confirm('האם לבטל את הבקשה?');">
                                    <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                    <input type="hidden" name="placement_id" value="<?php echo $row['placement_id']; ?>">
                                    <button type="submit" name="cancel_request" class="btn-danger">בטל</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>אין בקשות לטרמפים.</p>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<?php
$stmt->close();
$conn->close();
?>
</body>
</html>