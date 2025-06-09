<?php
// requests_from_me.php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = $_SESSION['message'] ?? '';
$message_class = $_SESSION['message_class'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['message_class']);

// --- שליפת בקשות ---
$stmt_requests = $conn->prepare("
    SELECT
        pa.request_id,
        p.placement_id,
        pa.passenger_id,
        p.placement_date,
        s.shift_name,
        e.employee_name AS passenger_name,
        e.phone_number AS passenger_phone,
        pa.direction,
        pa.status
    FROM ride_requests pa
    JOIN job_placement p ON pa.placement_id = p.placement_id
    JOIN employees e ON pa.passenger_id = e.employee_id
    JOIN shifts s ON p.shift_id = s.shift_id
    WHERE pa.driver_id = ?
    ORDER BY p.placement_date DESC, pa.request_id DESC
");
if (!$stmt_requests) {
    $message = "שגיאה בהכנת שאילתת הבקשות";
    $message_class = "error";
    $_SESSION['message'] = $message;
    $_SESSION['message_class'] = $message_class;
    header("Location: requests_from_me.php");
    exit();
}
$stmt_requests->bind_param("i", $employee_id);
$stmt_requests->execute();
$request_results = $stmt_requests->get_result();

?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>בקשות טרמפ ממני</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h1>מבקשים טרמפ ממני</h1>
    <?php if ($message): ?>
        <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($request_results->num_rows > 0): ?>
        <div>
        <table class="table">
            <thead>
                <tr>
                    <th>ID בקשה</th>
                    <th>ID טרמפ</th>
                    <th>תאריך</th>
                    <th>משמרת</th>
                    <th>נוסע</th>
                    <th>טלפון נוסע</th>
                    <th>כיוון</th>
                    <th>סטטוס</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($request = $request_results->fetch_assoc()):
                     $status = $request['status']; 
                     $status_class = 'status-' . ($status ? strtolower($status) : 'unknown');
                ?>
                    <tr>
                        <td><?php echo $request['request_id']; ?></td>
                        <td><?php echo $request['placement_id']; ?></td>
                        <td><?php echo $request['placement_date']; ?></td>
                        <td><?php echo $request['shift_name']; ?></td>
                        <td><?php echo $request['passenger_name']; ?></td>
                        <td><?php echo $request['passenger_phone']; ?></td>
                        <td><?php echo ($request['direction'] == 'to_work') ? 'לעבודה' : (($request['direction'] == 'from_work') ? 'מהעבודה' : 'לא ידוע'); ?></td>
                        <td class="<?php echo $status_class; ?>">
                            <?php
                             if ($status === null) {
                                 echo 'לא ידוע';
                             } else {
                                switch ($status) {
                                    case 'approved': echo 'מאושר'; break;
                                    case 'not_approved': echo 'נדחה'; break;
                                    case 'pending': echo 'ממתין'; break;
                                    default: echo 'לא ידוע';
                                }
                             }
                            ?>
                        </td>
                        <td>
                            <?php if ($status == 'pending'): ?>
                                <form method="POST" action="approve_request.php">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="placement_id" value="<?php echo $request['placement_id']; ?>">
                                    <input type="hidden" name="passenger_id" value="<?php echo $request['passenger_id']; ?>">
                                    <button type="submit" name="approve_request" class="btn-primary">אשר</button>
                                </form>
                                <form method="POST" action="cancel_request.php">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="placement_id" value="<?php echo $request['placement_id']; ?>">
                                    <input type="hidden" name="passenger_id" value="<?php echo $request['passenger_id']; ?>">
                                    <input type="text" name="cancel_reason" placeholder="סיבת דחייה" required class="cancel-reason-input">
                                    <button type="submit" name="cancel_request" class="btn-danger">דחה</button>
                                </form>
                             <?php elseif ($status == 'approved'): ?>
                                 <form method="POST" action="cancel_request.php">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="placement_id" value="<?php echo $request['placement_id']; ?>">
                                    <input type="hidden" name="passenger_id" value="<?php echo $request['passenger_id']; ?>">
                                    <input type="text" name="cancel_reason" placeholder="סיבת ביטול אישור" required class="cancel-reason-input">
                                    <button type="submit" name="cancel_request" class="btn-danger" onclick="return confirm('האם לבטל את האישור לבקשה זו');">בטל אישור</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <p>אין בקשות טרמפ פעילות ממך</p>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
<?php
$stmt_requests->close();
$conn->close();
?>
</body>
</html>