<?php
// reset_password.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$is_manager = isset($_SESSION['is_manager']) && $_SESSION['is_manager'];

$errors = [];
$message = '';
$message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['user_id']) && $_POST['user_id']) && ($_POST['user_id'] != $_SESSION['employee_id'])) {
        if (!$is_manager) {
            header("Location: index.php?error=not_allowed");
            exit();
        }
        $reset_for_id = $_POST['user_id'];
    } else {
        $reset_for_id = $_SESSION['employee_id'];
    }

    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $reset_for_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        if ($is_manager) {
            header("Location: manage_users.php?error=user_not_found");
        } else {
            header("Location: profile.php?error=user_not_found");
        }
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $current_password = $_POST['current_password'] ?? '';

        $require_current = ($reset_for_id == $_SESSION['employee_id'] || !$is_manager);

        if ($require_current) {
            if ($current_password !== $user['password']) {
                $errors[] = "הסיסמה הנוכחית שגויה.";
            }
        }
        if (empty($new_password)) $errors[] = "סיסמה חדשה היא שדה חובה.";
        if ($new_password !== $confirm_password) $errors[] = "הסיסמאות אינן תואמות.";
        if ($new_password === $user['password']) $errors[] = "הסיסמה החדשה חייבת להיות שונה מהקודמת.";

        if (empty($errors)) {
            $stmt2 = $conn->prepare("UPDATE employees SET password=? WHERE employee_id=?");
            $stmt2->bind_param("si", $new_password, $reset_for_id);
            if ($stmt2->execute()) {
                $stmt2->close();
                if ($reset_for_id == $_SESSION['employee_id']) {
                    header("Location: profile.php?password_changed=1");
                } else {
                    header("Location: manage_users.php?success=password_changed");
                }
                exit();
            } else {
                $errors[] = "שגיאה בשינוי הסיסמה.";
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="he">
    <head>
        <meta charset="UTF-8">
        <title>איפוס סיסמה</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2>איפוס סיסמה עבור: <?php echo $user['employee_name']; ?></h2>
        <?php if (!empty($message)): ?>
            <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="reset_password.php">
            <input type="hidden" name="user_id" value="<?php echo $reset_for_id; ?>">
            <?php if ($reset_for_id == $_SESSION['employee_id'] || !$is_manager): ?>
                <div class="form-group">
                    <label for="current_password">סיסמה נוכחית:</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="new_password">סיסמה חדשה:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">אישור סיסמה חדשה:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn-primary">שנה סיסמה</button>
        </form>
    </div>
    <?php include 'footer.php'; ?>
    </body>
    </html>
    <?php
    $conn->close();
    exit();
}
header("Location: index.php");
exit();
?>