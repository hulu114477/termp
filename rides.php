<?php
// rides.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$current_date = date('Y-m-d');
$employee_id = $_SESSION['employee_id'];

$message = '';
$message_class = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_class = $_SESSION['message_class'];
    unset($_SESSION['message']);
    unset($_SESSION['message_class']);
}

$user_address = '';
$stmt_user = $conn->prepare("SELECT address FROM employees WHERE employee_id = ?");
$stmt_user->bind_param("i", $employee_id);
$stmt_user->execute();
$stmt_user->bind_result($user_address);
$stmt_user->fetch();
$stmt_user->close();

// --- שליפת כל הטרמפים ---
$stmt = $conn->prepare("
    SELECT jp.placement_id, jp.placement_date, s.shift_name, 
           jp.available_places_to_work, jp.available_places_from_work, 
           e.employee_name, e.address, e.phone_number, e.has_car, jp.employee_id, jp.status
    FROM job_placement jp
    JOIN shifts s ON jp.shift_id = s.shift_id
    JOIN employees e ON jp.employee_id = e.employee_id
    WHERE jp.placement_date >= ? AND jp.status = 'regular'
    ORDER BY jp.placement_date ASC
");
if (!$stmt) {
    $_SESSION['message'] = "שגיאה בהכנת שאילתת הטרמפים";
    $_SESSION['message_class'] = "error";
    header("Location: rides.php");
    exit();
}
$stmt->bind_param("s", $current_date);
$stmt->execute();
$rides = $stmt->get_result();
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כל הטרמפים</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <script>
            var currentUserAddress = <?php echo json_encode($user_address); ?>;
        </script>
        <?php include 'map_widget.html'; ?>
        <h1>כל הטרמפים</h1>
        <?php if (!empty($message)): ?>
            <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($rides->num_rows === 0): ?>
            <p>אין טרמפים עתידיים להצגה</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>מספר טרמפ</th>
                    <th>תאריך</th>
                    <th>משמרת</th>
                    <th>מקומות פנויים לכיוון העבודה</th>
                    <th>מקומות פנויים בחזרה</th>
                    <th>שם הנהג</th>
                    <th>כתובת</th>
                    <th>מספר טלפון</th>
                    <th>יש מכונית</th>
                    <th>פעולות</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($ride = $rides->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $ride['placement_id']; ?></td>
                        <td><?php echo $ride['placement_date']; ?></td>
                        <td><?php echo $ride['shift_name']; ?></td>
                        <td><?php echo $ride['available_places_to_work']; ?></td>
                        <td><?php echo $ride['available_places_from_work']; ?></td>
                        <td><?php echo $ride['employee_name']; ?></td>
                        <td><?php echo $ride['address']; ?></td>
                        <td><?php echo $ride['phone_number']; ?></td>
                        <td><?php echo $ride['has_car'] ? 'כן' : 'לא'; ?></td>
                        <td class="actions">
                            <?php if ($ride['employee_id'] == $employee_id): ?>
                                <form method="POST" action="edit_ride.php" class="edit-form">
                                    <input type="hidden" name="placement_id" value="<?php echo $ride['placement_id']; ?>">
                                    <input type="date" name="placement_date" value="<?php echo $ride['placement_date']; ?>" required>
                                    <select name="shift_type" required>
                                        <option value="בוקר" <?php echo $ride['shift_name'] === 'בוקר' ? 'selected' : ''; ?>>בוקר</option>
                                        <option value="ערב" <?php echo $ride['shift_name'] === 'ערב' ? 'selected' : ''; ?>>ערב</option>
                                        <option value="לילה" <?php echo $ride['shift_name'] === 'לילה' ? 'selected' : ''; ?>>לילה</option>
                                    </select>
                                    <input type="number" name="available_places_away" value="<?php echo $ride['available_places_to_work']; ?>" min="0" max="60" required>
                                    <input type="number" name="vacancies_return" value="<?php echo $ride['available_places_from_work']; ?>" min="0" max="60" required>
                                    <button type="submit" name="edit_ride" class="btn-primary">עדכן</button>
                                </form>
                                <form method="POST" action="delete_ride.php" class="delete-form" onsubmit="return confirm('האם אתה בטוח שברצונך לבטל את הטרמפ הודעת ביטול תישלח לכל הנוסעים הרשומים');">
                                    <input type="hidden" name="placement_id" value="<?php echo $ride['placement_id']; ?>">
                                    <button type="submit" name="delete_ride" class="btn-danger">בטל טרמפ</button>
                                </form>
                            <?php else: ?>
                                <span>אין הרשאה</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
<?php
$stmt->close();
$conn->close();
?>
</body>
</html>