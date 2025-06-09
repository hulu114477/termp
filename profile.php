<?php
session_start();
require_once 'config.php';

// בדיקת התחברות
if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee = null;
$message = '';
$error = '';

// שליפת פרטי העובד מהמסד
$stmt_get = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
if ($stmt_get) {
    $stmt_get->bind_param("i", $employee_id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    if ($result_get->num_rows === 1) {
        $employee = $result_get->fetch_assoc();
    } else {
        session_destroy();
        header("Location: index.php?error=employee_not_found");
        exit();
    }
    $stmt_get->close();
} else {
    session_destroy();
    header("Location: index.php?error=employee_load_failed");
    exit();
}

// עדכון פרופיל
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $employee_name = trim($_POST['employee_name']);
    $address = trim($_POST['address']);
    $phone_number = trim($_POST['phone_number']);
    $has_car = isset($_POST['has_car']) ? 1 : 0;

    if (empty($employee_name)) {
        $error = "שם עובד הוא שדה חובה.";
    } elseif (strlen($employee_name) > 50) {
        $error = "שם עובד ארוך מדי (עד 50 תווים).";
    } else {
        $stmt_check = $conn->prepare("SELECT employee_id FROM employees WHERE employee_name = ? AND employee_id != ?");
        if ($stmt_check) {
            $stmt_check->bind_param("si", $employee_name, $employee_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $error = "שם העובד '$employee_name' כבר קיים במערכת.";
            }
            $stmt_check->close();
        } else {
            $error = "שגיאה בבדיקת שם עובד.";
        }
    }

    if (empty($error) && empty($address)) {
        $error = "כתובת היא שדה חובה.";
    } elseif (empty($error) && strlen($address) > 100) {
        $error = "כתובת ארוכה מדי (עד 100 תווים).";
    }
    if (empty($error) && empty($phone_number)) {
        $error = "מספר טלפון הוא שדה חובה.";
    } elseif (empty($error) && !preg_match('/^[0-9]{9,10}$/', $phone_number)) {
        $error = "מספר טלפון לא תקין (נדרשות 9 או 10 ספרות).";
    }

    if (empty($error)) {
        $stmt_update = $conn->prepare("UPDATE employees SET employee_name=?, address=?, phone_number=?, has_car=? WHERE employee_id=?");
        if ($stmt_update) {
            $stmt_update->bind_param("sssii", $employee_name, $address, $phone_number, $has_car, $employee_id);
            if ($stmt_update->execute()) {
                $message = "הפרופיל עודכן בהצלחה!";
                // רענון ערכים לטופס
                $employee['employee_name'] = $employee_name;
                $employee['address'] = $address;
                $employee['phone_number'] = $phone_number;
                $employee['has_car'] = $has_car;
            } else {
                $error = "שגיאה בעדכון הפרופיל.";
            }
            $stmt_update->close();
        } else {
            $error = "שגיאה בהכנת שאילתת עדכון הפרופיל.";
        }
    }
}

// שינוי סיסמה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password !== $employee['password']) {
        $error = "הסיסמה הנוכחית שגויה.";
    } elseif (empty($new_password)) {
        $error = "סיסמה חדשה היא שדה חובה.";
    } elseif ($new_password !== $confirm_password) {
        $error = "הסיסמאות אינן תואמות.";
    } elseif ($new_password === $employee['password']) {
        $error = "הסיסמה החדשה חייבת להיות שונה מהקודמת.";
    } else {
        $stmt_pw = $conn->prepare("UPDATE employees SET password=? WHERE employee_id=?");
        if ($stmt_pw) {
            $stmt_pw->bind_param("si", $new_password, $employee_id);
            if ($stmt_pw->execute()) {
                $message = "הסיסמה שונתה בהצלחה!";
                $employee['password'] = $new_password;
            } else {
                $error = "שגיאה בעדכון הסיסמה.";
            }
            $stmt_pw->close();
        } else {
            $error = "שגיאה בהכנת שאילתת שינוי הסיסמה.";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הפרופיל שלי</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h1>הפרופיל שלי</h1>
    <?php if (!empty($message)): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <h2>עדכון פרטי עובד</h2>
    <form method="POST" action="profile.php">
        <div class="form-group">
            <label for="employee_name">שם עובד:</label>
            <input type="text" id="employee_name" name="employee_name" maxlength="50" required value="<?php echo htmlspecialchars($employee['employee_name']); ?>">
        </div>
        <div class="form-group">
            <label for="address">כתובת:</label>
            <input type="text" id="address" name="address" maxlength="100" required value="<?php echo htmlspecialchars($employee['address']); ?>">
        </div>
        <div class="form-group">
            <label for="phone_number">מספר טלפון:</label>
            <input type="text" id="phone_number" name="phone_number" required pattern="[0-9]{9,10}" title="מספר טלפון חייב להכיל 9 או 10 ספרות" value="<?php echo htmlspecialchars($employee['phone_number']); ?>">
        </div>
        <div class="form-group">
            <label for="has_car">יש לי מכונית:</label>
            <input type="checkbox" id="has_car" name="has_car" <?php echo $employee['has_car'] ? 'checked' : ''; ?>>
        </div>
        <button type="submit" name="update_profile" class="btn-primary">שמור שינויים בפרטי עובד</button>
    </form>

    <h2>שינוי סיסמת עובד</h2>
 
    <form method="POST" action="profile.php">
        <div class="form-group">
            <label for="current_password">סיסמה נוכחית:</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="form-group">
            <label for="new_password">סיסמה חדשה:</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">אישור סיסמה חדשה:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" name="change_password" class="btn-primary">שנה סיסמת עובד</button>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
