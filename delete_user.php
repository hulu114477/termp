<?php
// delete_user.php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['is_manager']) || !$_SESSION['is_manager']
    || !isset($_POST['user_id'])
    || $_POST['user_id'] == $_SESSION['employee_id'])
{
    header("Location: manage_users.php?error=invalid_delete_request");
    exit();
}

$user_id_to_delete = (int)$_POST['user_id'];

$conn->begin_transaction();

$stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $user_id_to_delete);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        $stmt->close();
        $conn->close();
        header("Location: manage_users.php?success=deleted");
        exit();
    } else {
        $conn->rollback();
        $conn->close();
        header("Location: manage_users.php?error=delete_failed");
        exit();
    }
}

if ($conn->ping()) { $conn->close(); }
?>