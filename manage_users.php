<?php
// manage_users.php
session_start();
require 'config.php';

if (!isset($_SESSION['is_manager']) || !$_SESSION['is_manager']) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_class = '';
$errors = [];

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'added') $message = "המשתמש נוסף בהצלחה.";
    if ($_GET['success'] == 'deleted') $message = "המשתמש נמחק בהצלחה.";
    if ($_GET['success'] == 'password_changed') $message = "הסיסמה אופסה בהצלחה.";
    if ($_GET['success'] == 'updated') $message = "פרטי המשתמש עודכנו בהצלחה!";
    $message_class = 'success';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'delete_failed') $message = "שגיאה במחיקת המשתמש.";
    if ($_GET['error'] == 'user_not_found') $message = "משתמש לא נמצא.";
    if ($_GET['error'] == 'invalid_request') $message = "שגיאה בבקשת איפוס סיסמה.";
    $message_class = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $address = $_POST['address'];
    $phone_number = $_POST['phone_number'];
    $has_car = isset($_POST['has_car']) ? 1 : 0;
    $is_manager = isset($_POST['is_manager']) ? 1 : 0;

    if (empty($username)) { $errors[] = "שם משתמש הוא שדה חובה."; }
    elseif (strlen($username) > 50) { $errors[] = "שם משתמש ארוך מדי (עד 50 תווים)."; }
    else {
        $stmt_check = $conn->prepare("SELECT employee_id FROM employees WHERE employee_name = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $errors[] = "שם המשתמש '$username' כבר קיים במערכת.";
        }
        $stmt_check->close();
    }

    if (empty($password)) { $errors[] = "סיסמה היא שדה חובה."; }
    if (empty($address)) { $errors[] = "כתובת היא שדה חובה."; }
    elseif (strlen($address) > 100) { $errors[] = "כתובת ארוכה מדי (עד 100 תווים)."; }
    if (empty($phone_number)) { $errors[] = "מספר טלפון הוא שדה חובה."; }
    elseif (!preg_match('/^[0-9]{9,10}$/', $phone_number)) {
        $errors[] = "מספר טלפון לא תקין (נדרשות 9 או 10 ספרות).";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO employees (employee_name, password, address, phone_number, has_car, is_manager) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $username, $password, $address, $phone_number, $has_car, $is_manager);
        if ($stmt->execute()) {
            header("Location: manage_users.php?success=added");
            exit();
        } else {
            $errors[] = "שגיאה בהוספת המשתמש.";
        }
        $stmt->close();
    }
}

$users_result = $conn->query("SELECT * FROM employees ORDER BY employee_id DESC");
if (!$users_result) {
    die("שגיאה בשליפת רשימת המשתמשים.");
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        
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

        <h1>ניהול משתמשים</h1>

        <form method="POST" action="manage_users.php">
            <h2>הוסף משתמש חדש</h2>
            <div class="form-group">
                <label>שם משתמש:</label>
                <input type="text" name="username" maxlength="50" required value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>סיסמה:</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>כתובת:</label>
                <input type="text" name="address" maxlength="100" required value="<?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>מספר טלפון:</label>
                <input type="text" name="phone_number" required pattern="[0-9]{9,10}" title="מספר טלפון חייב להכיל 9 או 10 ספרות" value="<?php echo isset($_POST['phone_number']) ? $_POST['phone_number'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>יש מכונית:</label>
                <input type="checkbox" name="has_car" <?php echo (isset($_POST['has_car'])) ? 'checked' : ''; ?>">
            </div>
            <div class="form-group">
                <label>מנהל:</label>
                <input type="checkbox" name="is_manager" <?php echo (isset($_POST['is_manager'])) ? 'checked' : ''; ?>">
            </div>
            <button type="submit" name="add_user" class="btn-primary">הוסף משתמש</button>
        </form>

        <hr style="margin: 30px 0;">

        <h2>רשימת משתמשים</h2>
        <?php if ($users_result->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>שם משתמש</th>
                        <th>כתובת</th>
                        <th>טלפון</th>
                        <th>רכב</th>
                        <th>מנהל</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($user = $users_result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $user['employee_id']; ?></td>
                        <td><?php echo $user['employee_name']; ?></td>
                        <td><?php echo $user['address']; ?></td>
                        <td><?php echo $user['phone_number']; ?></td>
                        <td><?php echo $user['has_car'] ? 'כן' : 'לא'; ?></td>
                        <td><?php echo $user['is_manager'] ? 'כן' : 'לא'; ?></td>
                        <td>
                            <form method="POST" action="edit_user.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $user['employee_id']; ?>">
                                <button type="submit" class="btn-primary">ערוך</button>
                            </form>

                            <form method="POST" action="reset_password.php" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['employee_id']; ?>">
                                <button type="submit" class="btn-reset-pw" onclick="return confirm('האם לאפס סיסמה למשתמש <?php echo addslashes($user['employee_name']); ?>? הסיסמה החדשה תצטרך להיקבע במסך הבא.');">אפס סיסמה</button>
                            </form>

                            <?php if ($_SESSION['employee_id'] != $user['employee_id']): ?>
                                <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm('האם אתה בטוח שברצונך למחוק את המשתמש <?php echo addslashes($user['employee_name']); ?>? פעולה זו תשפיע גם על נתונים קשורים ולא ניתנת לביטול!');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['employee_id']; ?>">
                                    <button type="submit" class="btn-danger">מחק</button>
                                </form>
                            <?php else: ?>
                                <button class="btn-danger" disabled title="לא ניתן למחוק את עצמך">מחק</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>לא נמצאו משתמשים במערכת.</p>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>