<?php
// index.php
session_start();
require_once 'config.php';

// משתנה מרכזי להודעת שגיאה
$errorMsg = '';

// טיפול בשגיאות מהדשבורד 
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'employee_not_found') {
        $errorMsg = "שגיאה: המידע שלך לא תואם את המערכת נא להתחבר מחדש";
    } elseif ($_GET['error'] === 'employee_load_failed') {
        $errorMsg = "שגיאה: לא ניתן לטעון את הפרטים שלך נא להתחבר מחדש";
    } else {
        $errorMsg = "אירעה שגיאה לא צפויה נא לנסות שוב מאוחר יותר";
    }
}

// אם העובד כבר מחובר, הפנה לדשבורד
if (isset($_SESSION['employee_id'])) {
    header("Location: dashboard.php");
    exit();
}

//  טיפול בהתחברות 
if (isset($_POST['login'])) {
    $employee_name = trim($_POST['employee_name']);
    $password = $_POST['password'];

    if (empty($employee_name) || empty($password)) {
        $errorMsg = "יש למלא את כל השדות";
    } else {
        $stmt = $conn->prepare("SELECT employee_id, employee_name, password, is_manager FROM employees WHERE employee_name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $employee_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $employee = $result->fetch_assoc();
                if ($password === $employee['password']) {
                    // שמירת פרטי העובד ב-session
                    $_SESSION['employee_id'] = $employee['employee_id'];
                    $_SESSION['employee_name'] = $employee['employee_name'];
                    $_SESSION['is_manager'] = $employee['is_manager'];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $errorMsg = "שם עובד או סיסמה שגויים";
                }
            } else {
                $errorMsg = "שם עובד או סיסמה שגויים";
            }
            $stmt->close();
        } else {
            $errorMsg = "אירעה שגיאה במערכת נסו שוב מאוחר יותר";
        }
    }
}

$conn->close(); // סגירת החיבור למסד הנתונים

// פונקציה להצגת הודעת שגיאה
function printError($msg) {
    echo '<div class="error">' . htmlspecialchars($msg) . '</div>';
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>התחברות עובד למערכת</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="container">
            <form method="POST" class="login-form">
                <h1> התחברות למערכת  </h1>
                <?php
                // הצגת הודעת שגיאה אם קיימת
                if (!empty($errorMsg)) printError($errorMsg);
                ?>
                <div class="form-group">
                    <label for="employee_name">שם עובד:</label>
                    <input type="text"  name="employee_name" required 
                           value="<?php echo isset($employee_name) ? htmlspecialchars($employee_name) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">סיסמת עובד:</label>
                    <input type="password" name="password" required 
                </div>
                <button type="submit" name="login" class="btn-primary">התחבר כעובד</button>
            </form>
        </div>
    </div>
</body>
</html>
