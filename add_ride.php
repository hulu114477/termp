<?php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';
$has_car = 0;

// בודקים אם לעובד יש רכב במערכת (שולפים מה-DB)
$stmt = $conn->prepare("SELECT has_car FROM employees WHERE employee_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->bind_result($has_car);
    $stmt->fetch();
    $stmt->close();
} else {
    // במקרה של תקלה בשאילתה
    $message = "שגיאה בבדיקת רכב העובד.";
    $message_class = "error";
}

// אם אין רכב – המשתמש לא יכול להוסיף טרמפ
if ($has_car != 1 && $message == '') {
    $message = "אינך יכול להוסיף נסיעה - אין לך רכב רשום במערכת.";
    $message_class = "error";
}

// אם נשלח הטופס ע"י המשתמש ויש לו רכב
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ride']) && $has_car == 1) {
    // מקבלים את הנתונים מהטופס
    $placement_date = $_POST['placement_date'];
    $shift_type = $_POST['shift_type'];
    $available_places_to_work = $_POST['available_places_to_work'];
    $available_places_from_work = $_POST['available_places_from_work'];

    // בדיקה שכל השדות מולאו (אסור להשאיר ריק)
    if (
        $placement_date == "" ||
        $shift_type == "" ||
        $available_places_to_work == "" ||
        $available_places_from_work == ""
    ) {
        $message = "יש למלא את כל השדות!";
        $message_class = "error";
    }
    // בדיקה שסוג המשמרת תקין (רק מה שיש ב-DB)
    else if ($shift_type != 'בוקר' && $shift_type != 'ערב' && $shift_type != 'לילה') {
        $message = "סוג משמרת לא תקין.";
        $message_class = "error";
    } else {
        // קביעת קוד משמרת לפי שם
        $shift_id = 1;
        if ($shift_type == 'ערב') $shift_id = 2;
        if ($shift_type == 'לילה') $shift_id = 3;

        // מוסיפים את הטרמפ לטבלת job_placement עם prepared statement
        $stmt = $conn->prepare("INSERT INTO job_placement (placement_date, employee_id, shift_id, available_places_to_work, available_places_from_work)
            VALUES (?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("siiii", $placement_date, $employee_id, $shift_id, $available_places_to_work, $available_places_from_work);
            if ($stmt->execute()) {
                $message = "טרמפ נוסף בהצלחה!";
                $message_class = "success";
            } else {
                $message = "שגיאה בהוספת הטרמפ.";
                $message_class = "error";
            }
            $stmt->close();
        } else {
            $message = "שגיאה בהכנת השאילתה.";
            $message_class = "error";
        }
    }
}

// סוגרים את החיבור למסד הנתונים
$conn->close();
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <title>הוסף טרמפ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>הוסף טרמפ</h1>
        <!-- הודעה למשתמש (שגיאה או הצלחה) -->
        <?php if ($message != ""): ?>
            <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($has_car == 1): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="placement_date">תאריך:</label>
                    <input type="date" id="placement_date" name="placement_date" required
                        value="<?php echo isset($_POST['placement_date']) ? $_POST['placement_date'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="shift_type">סוג משמרת:</label>
                    <select class="select" id="shift_type" name="shift_type" required>
                        <option value="">-- בחר סוג משמרת --</option>
                        <option value="בוקר" <?php if(isset($_POST['shift_type']) && $_POST['shift_type']=='בוקר') echo "selected"; ?>>בוקר</option>
                        <option value="ערב" <?php if(isset($_POST['shift_type']) && $_POST['shift_type']=='ערב') echo "selected"; ?>>ערב</option>
                        <option value="לילה" <?php if(isset($_POST['shift_type']) && $_POST['shift_type']=='לילה') echo "selected"; ?>>לילה</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="available_places_to_work">מקומות פנויים לעבודה:</label>
                    <input type="number" id="available_places_to_work" name="available_places_to_work" min="0" max="60" required
                        value="<?php echo isset($_POST['available_places_to_work']) ? $_POST['available_places_to_work'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="available_places_from_work">מקומות פנויים מהעבודה:</label>
                    <input type="number" id="available_places_from_work" name="available_places_from_work" min="0" max="60" required
                        value="<?php echo isset($_POST['available_places_from_work']) ? $_POST['available_places_from_work'] : ''; ?>">
                </div>
                <button type="submit" name="add_ride" class="btn-primary">הוסף</button>
            </form>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
