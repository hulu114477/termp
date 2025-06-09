<?php
// dashboard.php
session_start();
require_once 'config.php';

// בודק אם המשתמש לא מחובר, מפנה לעמוד התחברות
if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee = null; 

// שליפת פרטי העובד מהמסד
$stmt = $conn->prepare("SELECT employee_id, employee_name, address, phone_number, has_car, is_manager FROM employees WHERE employee_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $employee = $result->fetch_assoc();
    } else {
        session_destroy();
        header("Location: index.php?error=employee_not_found");
        exit();
    }
    $stmt->close();
} else {
    session_destroy();
    header("Location: index.php?error=employee_load_failed");
    exit();
}
$conn->close();

?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח בקרה</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h1>ברוך הבא, <?php echo htmlspecialchars($employee['employee_name']); ?>!</h1>
    <p>זהו דף הבית שלך במערכת ניהול הטרמפים</p>
    <h2>הפרטים שלך:</h2>
    <ul>
        <li><strong>מזהה עובד:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></li>
        <li><strong>שם:</strong> <?php echo htmlspecialchars($employee['employee_name']); ?></li>
        <li><strong>כתובת:</strong> <?php echo htmlspecialchars($employee['address']); ?></li>
        <li><strong>מספר טלפון:</strong> <?php echo htmlspecialchars($employee['phone_number']); ?></li>
        <li><strong>יש רכב רשום:</strong> <?php echo $employee['has_car'] ? 'כן' : 'לא'; ?></li>
        <li><strong>סטטוס מנהל:</strong> <?php echo $employee['is_manager'] ? 'כן' : 'לא'; ?></li>
    </ul>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
