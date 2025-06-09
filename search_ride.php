<?php
// search_ride.php
session_start();
require_once 'config.php';

// בדיקת התחברות של עובד
if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}
$employee_id = $_SESSION['employee_id'];
$results = [];
$message = ''; // משתנה להודעה המוצגת למשתמש
$message_class = ''; // משתנה לקלאס של ההודעה (success או error)
$direction = '';
$shift_name = '';


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_class = $_SESSION['message_class'];
    unset($_SESSION['message']);
    unset($_SESSION['message_class']);
}

// טיפול בטופס חיפוש
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $direction = $_POST['direction'] ?? '';
    $shift_name = $_POST['shift_name'] ?? '';

    // בדיקת תקינות הקלט
    if (!$direction || !$shift_name) {
        $message = "יש למלא את כל השדות.";
        $message_class = 'error';
    } elseif (!in_array($direction, ['to_work', 'from_work'])) {
        $message = "כיוון לא תקין.";
        $message_class = 'error';
    } elseif (!in_array($shift_name, ['בוקר', 'ערב', 'לילה'])) {
        $message = "משמרת לא תקינה.";
        $message_class = 'error';
    } else {
        // בחירת עמודת מקומות פנויים לפי הכיוון
        $places_column = $direction === 'to_work' ? 'available_places_to_work' : 'available_places_from_work';

        // יצירת שאילתת SQL לחיפוש טרמפים
        $select = "
            SELECT 
                jp.placement_id,
                jp.placement_date,
                s.shift_name,
                jp.$places_column AS available_places,
                e.employee_name,
                e.address,
                e.phone_number
        ";
        $from = "
            FROM job_placement jp
            JOIN employees e ON jp.employee_id = e.employee_id
            JOIN shifts s ON jp.shift_id = s.shift_id
        ";
        $where = "
            WHERE s.shift_name = ?
              AND jp.employee_id != ?
              AND jp.status = 'regular'
              AND jp.placement_date >= CURDATE()
              AND jp.$places_column > 0
              AND NOT EXISTS (
                  SELECT 1 FROM ride_requests rr
                  WHERE rr.placement_id = jp.placement_id
                    AND rr.passenger_id = ?
                    AND rr.direction = ?
              )
        ";

        $sql = $select . $from . $where;

        // הכנת השאילתה וביצוע
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $message = "שגיאה במערכת. נסה שוב.";
            $message_class = 'error';
        } else {
            $stmt->bind_param("siis", $shift_name, $employee_id, $employee_id, $direction);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // בדיקת תוצאות החיפוש
            if (!$results) {
                $message = "לא נמצאו טרמפים מתאימים.";
                $message_class = 'error';
            } else {
                $message = "נמצאו " . count($results) . " טרמפים זמינים.";
                $message_class = 'success';
            }
        }
    }
}

// שליפת כתובת המשתמש עבור המפה
$user_address = '';
if ($results) {
    $stmt_user = $conn->prepare("SELECT address FROM employees WHERE employee_id = ?");
    $stmt_user->bind_param("i", $employee_id);
    $stmt_user->execute();
    $stmt_user->bind_result($user_address);
    $stmt_user->fetch();
    $stmt_user->close();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש טרמפ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h1>חיפוש טרמפ</h1>
    <form method="POST">
        <label>כיוון:</label>
        <select class="select" name="direction" required>
            <option value="">בחר כיוון</option>
            <option value="to_work" <?php echo $direction === 'to_work' ? 'selected' : ''; ?>>לכיוון העבודה</option>
            <option value="from_work" <?php echo $direction === 'from_work' ? 'selected' : ''; ?>>חזרה מהעבודה</option>
        </select>
        <label>משמרת:</label>
        <select class="select" name="shift_name" required>
            <option value="">בחר משמרת</option>
            <option value="בוקר" <?php echo $shift_name === 'בוקר' ? 'selected' : ''; ?>>בוקר</option>
            <option value="ערב" <?php echo $shift_name === 'ערב' ? 'selected' : ''; ?>>ערב</option>
            <option value="לילה" <?php echo $shift_name === 'לילה' ? 'selected' : ''; ?>>לילה</option>
        </select>
        <button type="submit" name="search" class="btn-primary">חפש</button>
    </form>

    <!-- הצגת הודעה אם קיימת, בהתאם לעיצוב מ-style.css -->
    <?php if ($message): ?>
        <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- הצגת תוצאות החיפוש אם קיימות -->
    <?php if ($results): ?>
        <script>
            var currentUserAddress = <?php echo json_encode($user_address); ?>;
        </script>
        <?php include 'map_widget.html'; ?>
        <h2>תוצאות חיפוש</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>מספר טרמפ</th>
                    <th>תאריך</th>
                    <th>משמרת</th>
                    <th>מקומות פנויים</th>
                    <th>שם הנהג</th>
                    <th>כתובת</th>
                    <th>מספר טלפון</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $ride): ?>
                    <tr>
                        <td><?php echo $ride['placement_id']; ?></td>
                        <td><?php echo $ride['placement_date']; ?></td>
                        <td><?php echo $ride['shift_name']; ?></td>
                        <td><?php echo $ride['available_places']; ?></td>
                        <td><?php echo $ride['employee_name']; ?></td>
                        <td><?php echo $ride['address']; ?></td>
                        <td><?php echo $ride['phone_number']; ?></td>
                        <td>
                            <form method="POST" action="request_ride.php">
                                <input type="hidden" name="placement_id" value="<?php echo $ride['placement_id']; ?>">
                                <input type="hidden" name="direction" value="<?php echo $direction; ?>">
                                <input type="hidden" name="shift_name" value="<?php echo $shift_name; ?>">
                                <button type="submit" name="request" class="btn-primary">בקש טרמפ</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
<?php $conn->close(); ?>
</body>
</html>