<?php
// messages.php
session_start();
require 'config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$message = '';
$message_class = '';

// שליחת הודעה חדשה
if (isset($_POST['send_message'])) {
    $placement_id    = $_POST['placement_id'];
    $message_content = $_POST['message_content'];

    if ($message_content === '') {
        $message       = "נא למלא את תוכן ההודעה.";
        $message_class = "error";
    } elseif ($placement_id === '' || $placement_id === null) {
        $message       = "נא לבחור טרמפ תקין.";
        $message_class = "error";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO messages
              (placement_id, sender_id, message_text, message_time, direction)
            VALUES (?, ?, ?, NOW(), 'other')
        ");
        $stmt->bind_param("iis", $placement_id, $employee_id, $message_content);
        if ($stmt->execute()) {
            $message       = "הודעה נשלחה בהצלחה!";
            $message_class = "success";
        } else {
            $message       = "שגיאה בשליחת ההודעה.";
            $message_class = "error";
        }
        $stmt->close();
    }
}

// שליפת טרמפים רלוונטיים למשתמש
$stmt = $conn->prepare("
    SELECT DISTINCT
      jp.placement_id,
      jp.placement_date,
      s.shift_name,
      jp.employee_id AS driver_id,
      e.employee_name AS driver_name
    FROM job_placement jp
    JOIN shifts s ON jp.shift_id = s.shift_id
    JOIN employees e ON jp.employee_id = e.employee_id
    LEFT JOIN ride_requests pa
      ON jp.placement_id = pa.placement_id
      AND pa.passenger_id = ?
    WHERE jp.employee_id = ? OR pa.passenger_id = ?
    ORDER BY jp.placement_date DESC
");
$stmt->bind_param("iii", $employee_id, $employee_id, $employee_id);
$stmt->execute();
$relevant_placements = $stmt->get_result();
$stmt->close();

// שליפת כל ההודעות לכל הטרמפים
$sql_all = "
    SELECT
      m.message_id,
      m.placement_id,
      m.sender_id,
      m.message_text,
      m.message_time,
      s.employee_name AS sender_name,
      jp.placement_date,
      sh.shift_name
    FROM messages m
    JOIN employees s ON m.sender_id = s.employee_id
    JOIN job_placement jp ON m.placement_id = jp.placement_id
    JOIN shifts sh ON jp.shift_id = sh.shift_id
    WHERE m.placement_id IN (
        SELECT DISTINCT jp.placement_id
        FROM job_placement jp
        LEFT JOIN ride_requests pa
          ON jp.placement_id = pa.placement_id
        WHERE jp.employee_id = ? OR pa.passenger_id = ?
    )
    ORDER BY m.placement_id, m.message_time ASC
";

$stmt_all = $conn->prepare($sql_all);
if ($stmt_all) {
    $stmt_all->bind_param("ii", $employee_id, $employee_id);
    $stmt_all->execute();
    $all_messages = $stmt_all->get_result();
    $stmt_all->close();
} else {
    $all_messages     = false;
    $message          = "שגיאה בטעינת ההודעות.";
    $message_class    = "error";
}

// סידור הודעות לפי טרמפ
$messages_by_placement = [];
if ($all_messages) {
    while ($msg = $all_messages->fetch_assoc()) {
        $pid = $msg['placement_id'];
        if (!isset($messages_by_placement[$pid])) {
            $messages_by_placement[$pid] = [];
        }
        $messages_by_placement[$pid][] = $msg;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הודעות</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container">
    <h1>הודעות</h1>

    <!-- טופס לשליחת הודעה חדשה -->
    <div class="message-form">
        <h2>שלח הודעה חדשה</h2>
        <?php if ($message !== ''): ?>
            <div class="<?php echo $message_class; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="POST" class="message-form">
            <label>בחר טרמפ:</label>
            <select name="placement_id" required>
                <option value="">-- בחר טרמפ --</option>
                <?php
                $relevant_placements->data_seek(0);
                while ($placement = $relevant_placements->fetch_assoc()): ?>
                    <option value="<?php echo $placement['placement_id']; ?>">
                        מספר <?php echo $placement['placement_id']; ?> |
                        תאריך: <?php echo $placement['placement_date']; ?> |
                        משמרת: <?php echo $placement['shift_name']; ?> |
                        נהג: <?php echo $placement['driver_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <label>תוכן ההודעה:</label>
            <textarea name="message_content" required></textarea>
            <button type="submit" name="send_message" class="btn-primary">שלח</button>
        </form>
    </div>

    <!-- רשימת ההודעות -->
    <h2>רשימת הודעות</h2>
    <?php if (!empty($messages_by_placement)): ?>
        <?php foreach ($messages_by_placement as $placement_id => $messages): ?>
            <div class="chat-room">
                <h3>טרמפ מספר <?php echo $placement_id; ?></h3>
                <ul class="message-list">
                    <?php foreach ($messages as $msg): ?>
                        <li class="message-item">
                            <div class="message-header">
                                <strong><?php echo $msg['sender_name']; ?>:</strong>
                                <span><?php echo $msg['message_time']; ?></span>
                            </div>
                            <p><?php echo $msg['message_text']; ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>אין הודעות להצגה.</p>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
