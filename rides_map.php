<?php
// rides_map.php
session_start();
require 'config.php';

$current_date = date('Y-m-d');
$employee_id = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
$direction   = $_GET['direction']   ?? null;
$shift_name  = $_GET['shift_name']  ?? null;
$my_only     = (isset($_GET['my_only']) && $_GET['my_only'] == '1');

// בסיס השאילתה והפרמטרים
$sql = "
    SELECT jp.placement_id, jp.placement_date, s.shift_name,
           jp.Available_places_away, jp.Vacancies_return,
           e.employee_name, e.address, e.phone_number, e.has_car, jp.employee_id
    FROM job_placement jp
    JOIN employees e ON jp.employee_id = e.employee_id
    JOIN shifts s ON jp.shift_id = s.shift_id
    WHERE jp.placement_date >= ? AND jp.status = 'regular'
";
$params = [$current_date];
$types  = "s";

// הוספת מסננים אם נשלחו בפרמטרים
if ($shift_name) {
    $sql .= " AND s.shift_name = ?";
    $params[] = $shift_name;
    $types   .= "s";
}
if ($my_only) {
    $sql .= " AND jp.employee_id = ?";
    $params[] = $employee_id;
    $types   .= "i";
}

$sql .= " ORDER BY jp.placement_date ASC";

// הכנה, בדיקת תקינות וביצוע
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "DB Prepare failed: " . $conn->error], JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rides = [];
while ($row = $result->fetch_assoc()) {
    $row['is_me'] = ($employee_id && $row['employee_id'] == $employee_id);
    $rides[] = $row;
}
$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($rides, JSON_UNESCAPED_UNICODE);
