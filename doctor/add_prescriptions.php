<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id      = (int)$_SESSION['user_id'];
$appointment_id = (int)($_GET['appointment'] ?? 0);
$patient_id     = (int)($_GET['patient'] ?? 0);

if (!$appointment_id || !$patient_id) {
    header("Location: appointments.php");
    exit();
}

// Verify the appointment belongs to this doctor
$verify = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND doctor_id = ?");
$verify->bind_param("ii", $appointment_id, $doctor_id);
$verify->execute();
if ($verify->get_result()->num_rows === 0) {
    header("Location: appointments.php");
    exit();
}

// Get patient details
$stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    header("Location: appointments.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validateCSRF();

    $title       = validate($_POST['title'] ?? '');
    $description = validate($_POST['description'] ?? '');
    $record_date = date('Y-m-d');
    $file_path   = null;

    if (isset($_FILES['prescription_file']) && $_FILES['prescription_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['prescription_file']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_types)) {
            $error = "Only PDF, JPG, and PNG files are allowed.";
        } elseif ($_FILES['prescription_file']['size'] > 5 * 1024 * 1024) {
            $error = "File size must be under 5 MB.";
        } else {
            $target_dir = "../uploads/prescriptions/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $ext       = pathinfo($_FILES['prescription_file']['name'], PATHINFO_EXTENSION);
            $filename  = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $file_path = "prescriptions/" . $filename;
            if (!move_uploaded_file($_FILES['prescription_file']['tmp_name'], "../uploads/" . $file_path)) {
                $error = "File upload failed.";
                $file_path = null;
            }
        }
    }

    if (!isset($error)) {
        $ins = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_type, title, description, file_path, record_date) VALUES (?, ?, ?, 'prescription', ?, ?, ?, ?)");
        $ins->bind_param("iiissss", $patient_id, $doctor_id, $appointment_id, $title, $description, $file_path, $record_date);

        if ($ins->execute()) {
            $upd = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?");
            $upd->bind_param("i", $appointment_id);
            $upd->execute();
            header("Location: appointments.php?success=1");
            exit();
        } else {
            $error = "Failed to save prescription.";
        }
    }
}

include '../includes/header.php';
include '../includes/doctor_nav.php';
?>

<div class="container">
    <h1>Add Prescription</h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="prescription-form-container">
        <div class="patient-info">
            <h3>Patient: <?php echo htmlspecialchars($patient['full_name']); ?></h3>
            <p>Date: <?php echo date('F j, Y'); ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="prescription-form">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">

            <div class="form-group">
                <label>Prescription Title</label>
                <input type="text" name="title" class="form-control"
                       value="Prescription - <?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label>Medications / Instructions</label>
                <textarea name="description" rows="10" class="form-control"
                          placeholder="Enter medications and instructions here..." required></textarea>
                <small class="form-text">Format: Medicine name - Dosage - Frequency - Duration (one per line)</small>
            </div>

            <div class="form-group">
                <label>Upload Prescription (PDF/Image, max 5 MB)</label>
                <input type="file" name="prescription_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Prescription</button>
                <a href="appointments.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>