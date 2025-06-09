<?php
// edit_user.php
session_start();
require 'config.php';

if (!isset($_SESSION['is_manager']) || !$_SESSION['is_manager']) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_class = '';
$errors = [];
$user_id_to_edit = null;
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $user_id_to_edit = $_POST['id'];
    $stmt_get = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt_get->bind_param("i", $user_id_to_edit);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    if ($result_get->num_rows === 1) {
        $user = $result_get->fetch_assoc();
    } else {
        $errors[] = "משתמש עם ID זה לא נמצא.";
        $user_id_to_edit = null;
    }
    $stmt_get->close();
} else {
    $errors[] = "ID משתמש לא תקין או חסר.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user']) && isset($_POST['id']) && $user_id_to_edit !== null && $user !== null) {
    $user_id_to_edit = $_POST['id'];
    $username = $_POST['username'];
    $address = $_POST['address'];
    $phone_number = $_POST['phone_number'];
    $has_car = isset($_POST['has_car']) ? 1 : 0;
    $is_manager = $user['is_manager'];

    if ($user_id_to_edit != $_SESSION['employee_id']) {
        $is_manager = isset($_POST['is_manager']) ? 1 : 0;
    } elseif (isset($_POST['is_manager']) && $_POST['is_manager'] == 0 && $user['is_manager'] == 1) {
        $errors[] = "לא ניתן להסיר הרשאות מנהל מעצמך.";
        $is_manager = 1;
    }

    if (empty($username)) { $errors[] = "שם משתמש הוא שדה חובה."; }
    elseif (strlen($username) > 50) { $errors[] = "שם משתמש ארוך מדי (עד 50 תווים)."; }
    else {
        $stmt_check = $conn->prepare("SELECT employee_id FROM employees WHERE employee_name = ? AND employee_id != ?");
        $stmt_check->bind_param("si", $username, $user_id_to_edit);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $errors[] = "שם המשתמש '$username' כבר קיים במערכת.";
        }
        $stmt_check->close();
    }

    if (empty($address)) { $errors[] = "כתובת היא שדה חובה."; }
    elseif (strlen($address) > 100) { $errors[] = "כתובת ארוכה מדי (עד 100 תווים)."; }

    if (empty($phone_number)) { $errors[] = "מספר טלפון הוא שדה חובה."; }
    elseif (!preg_match('/^[0-9]{9,10}$/', $phone_number)) {
        $errors[] = "מספר טלפון לא תקין (נדרשות 9 או 10 ספרות).";
    }

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE employees SET employee_name=?, address=?, phone_number=?, has_car=?, is_manager=? WHERE employee_id=?");
        $stmt_update->bind_param("sssiii", $username, $address, $phone_number, $has_car, $is_manager, $user_id_to_edit);
        if ($stmt_update->execute()) {
            header("Location: manage_users.php?success=updated");
            exit();
        } else {
            $message = "שגיאה בעדכון פרטי המשתמש.";
            $message_class = "error";
        }
        $stmt_update->close();
    } else {
        $message = "נא לתקן את השגיאות בטופס.";
        $message_class = "error";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>עריכת משתמש</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h1>עריכת משתמש<?php echo $user ? ': ' . $user['employee_name'] : ''; ?></h1>

        <?php if (!empty($message)): ?>
            <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>נא לתקן את השגיאות הבאות:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="POST" action="edit_user.php">
                <input type="hidden" name="id" value="<?php echo $user_id_to_edit; ?>">
                <input type="hidden" name="update_user" value="1">
                <div class="form-group">
                    <label>שם משתמש:</label>
                    <input type="text" name="username" maxlength="50" required value="<?php echo $user['employee_name']; ?>">
                </div>
                <div class="form-group">
                    <label>כתובת:</label>
                    <input type="text" name="address" maxlength="100" required value="<?php echo $user['address']; ?>">
                </div>
                <div class="form-group">
                    <label>מספר טלפון:</label>
                    <input type="text" name="phone_number" required pattern="[0-9]{9,10}" title="מספר טלפון חייב להכיל 9 או 10 ספרות" value="<?php echo $user['phone_number']; ?>">
                </div>
                <div class="form-group">
                    <label>יש מכונית:</label>
                    <input type="checkbox" name="has_car" <?php echo $user['has_car'] ? 'checked' : ''; ?>">
                </div>
                <div class="form-group">
                    <label>מנהל:</label>
                    <input type="checkbox" name="is_manager"
                           <?php echo $user['is_manager'] ? 'checked' : ''; ?>
                           <?php echo ($user_id_to_edit == $_SESSION['employee_id']) ? 'disabled' : ''; ?>>
                    <?php if ($user_id_to_edit == $_SESSION['employee_id'] && $user['is_manager']): ?>
                        <small>(לא ניתן להסיר הרשאות מנהל מעצמך)</small>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">עדכן פרטים</button>
            </form>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>