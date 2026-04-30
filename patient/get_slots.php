<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

header('Content-Type: application/json');

$doctor_id = (int)($_GET['doctor_id'] ?? 0);
$date      = validate($_GET['date'] ?? '');

if (!$doctor_id || !$date || strtotime($date) < strtotime(date('Y-m-d'))) {
    echo json_encode([]);
    exit();
}

// Get doctor schedule for the given day
$day_name = date('l', strtotime($date)); // e.g. "Monday"

$sched = $conn->prepare("
    SELECT start_time, end_time, slot_duration
    FROM doctor_schedules
    WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1
");
$sched->bind_param("is", $doctor_id, $day_name);
$sched->execute();
$schedule = $sched->get_result()->fetch_assoc();

if (!$schedule) {
    // Fallback: default 9am-5pm, 60-min slots
    $schedule = ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_duration' => 60];
}

// Get already booked slots for this doctor/date
$booked_stmt = $conn->prepare("
    SELECT appointment_time FROM appointments
    WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'
");
$booked_stmt->bind_param("is", $doctor_id, $date);
$booked_stmt->execute();
$booked_result = $booked_stmt->get_result();
$booked = [];
while ($row = $booked_result->fetch_assoc()) {
    $booked[] = $row['appointment_time'];
}

// Generate time slots
$slots    = [];
$current  = strtotime($date . ' ' . $schedule['start_time']);
$end      = strtotime($date . ' ' . $schedule['end_time']);
$duration = (int)$schedule['slot_duration'] * 60;

while ($current < $end) {
    $time_str = date('H:i:s', $current);
    $slots[] = [
        'time'      => date('H:i', $current),
        'available' => !in_array($time_str, $booked)
    ];
    $current += $duration;
}

echo json_encode($slots);