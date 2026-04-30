<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id     = (int)$_SESSION['user_id'];
$appointment_id = (int)($_GET['id'] ?? 0);

if ($appointment_id) {
    // Ensure the appointment belongs to this patient and is cancellable
    $stmt = $conn->prepare("
        UPDATE appointments SET status = 'cancelled'
        WHERE appointment_id = ? AND patient_id = ? AND status IN ('pending','confirmed')
    ");
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();
}

header("Location: my_appointments.php");
exit();