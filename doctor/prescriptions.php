<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// Handle add prescription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_prescription'])) {
    $patient_id     = (int)$_POST['patient_id'];
    $appointment_id = (int)$_POST['appointment_id'];
    $medication     = trim(mysqli_real_escape_string($conn, $_POST['medication'] ?? ''));
    $dosage         = trim(mysqli_real_escape_string($conn, $_POST['dosage'] ?? ''));
    $frequency      = trim(mysqli_real_escape_string($conn, $_POST['frequency'] ?? ''));
    $duration       = trim(mysqli_real_escape_string($conn, $_POST['duration'] ?? ''));
    $notes          = trim(mysqli_real_escape_string($conn, $_POST['notes'] ?? ''));

    if (!empty($medication) && !empty($dosage)) {
        $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_type, title, description, created_at) VALUES (?, ?, ?, 'prescription', ?, ?, NOW())");
        $title = "$medication - $dosage";
        $desc  = "Frequency: $frequency | Duration: $duration | Notes: $notes";
        $stmt->bind_param("iiiss", $patient_id, $doctor_id, $appointment_id, $title, $desc);
        if ($stmt->execute()) {
            $success = "Prescription added successfully!";
        } else {
            $error = "Failed to add prescription.";
        }
    } else {
        $error = "Medication and dosage are required.";
    }
}

// Get all patients for dropdown
$patients_list = mysqli_query($conn, "
    SELECT DISTINCT u.user_id, u.full_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    WHERE a.doctor_id = $doctor_id
    ORDER BY u.full_name ASC
");

// Get all prescriptions by this doctor
$prescriptions = mysqli_query($conn, "
    SELECT mr.*, u.full_name AS patient_name,
           a.appointment_date
    FROM medical_records mr
    JOIN users u ON mr.patient_id = u.user_id
    LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
    WHERE mr.doctor_id = $doctor_id AND mr.record_type = 'prescription'
    ORDER BY mr.created_at DESC
");

$doc_info        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT specialization FROM doctor_profiles WHERE doctor_id = $doctor_id"));
$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $doctor_id AND is_read = 0"))['count'];
$current         = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions – MediCare Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px; background: #1778F2; min-height: 100vh;
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h2 { color: white; font-size: 1.7rem; font-weight: 800; }
        .sidebar-brand .badge-portal {
            display: inline-block; background: rgba(255,255,255,0.2); color: white;
            font-size: .68rem; font-weight: 600; padding: .15rem .5rem;
            margin-top: .35rem; letter-spacing: .5px; text-transform: uppercase;
        }
        .sidebar-doctor {
            padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; gap: .75rem;
        }
        .sidebar-avatar {
            width: 40px; height: 40px; background: rgba(255,255,255,0.25);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 1rem; flex-shrink: 0;
        }
        .sidebar-doctor-name { color: white; font-weight: 700; font-size: .88rem; }
        .sidebar-doctor-role { color: rgba(255,255,255,0.6); font-size: .72rem; }
        .sidebar-menu { flex: 1; padding: 1rem 0; overflow-y: auto; }
        .menu-label {
            color: rgba(255,255,255,0.5); font-size: .6rem; font-weight: 700;
            letter-spacing: 1.2px; text-transform: uppercase; padding: .85rem 1.25rem .3rem;
        }
        .sidebar-menu a {
            display: flex; align-items: center; gap: .65rem;
            padding: .7rem 1.25rem; color: rgba(255,255,255,0.85);
            text-decoration: none; font-size: .85rem; font-weight: 500;
            margin: .1rem .6rem; transition: background .15s;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active { background: white; color: #1778F2; font-weight: 700; }
        .sidebar-menu a.active i { color: #1778F2; }
        .sidebar-menu a i { width: 16px; font-size: .85rem; color: rgba(255,255,255,0.7); }
        .msg-badge {
            background: #e74c3c; color: white; font-size: .65rem;
            font-weight: 700; padding: .1rem .4rem; border-radius: 10px; margin-left: auto;
        }
        .sidebar-footer { padding: 1rem .6rem; border-top: 1px solid rgba(255,255,255,0.15); }
        .sidebar-footer a {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .75rem; background: rgba(255,255,255,0.15); color: white;
            text-decoration: none; font-size: .85rem; font-weight: 700;
            letter-spacing: .5px; text-transform: uppercase; transition: background .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }

        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            background: white; padding: .9rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #e4e6ea; position: sticky; top: 0; z-index: 50;
        }
        .topbar-left { font-size: .82rem; color: #888; }
        .topbar-left span.page { font-size: 1.25rem; font-weight: 700; color: #1e3c4f; margin-left: .35rem; }
        .topbar-right { display: flex; align-items: center; gap: .75rem; }
        .topbar-avatar {
            width: 36px; height: 36px; background: #1778F2; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: .85rem;
        }
        .topbar-name { font-size: .875rem; color: #444; font-weight: 500; }

        .content { padding: 2rem; flex: 1; }

        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; }
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

        .page-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem; }
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.1rem; }
        .form-group label {
            display: block; font-size: .78rem; font-weight: 700;
            color: #1e3c4f; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%; padding: .7rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; color: #333;
            transition: border-color .2s; font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: #1778F2; }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .btn-add {
            width: 100%; padding: .8rem; background: #1778F2; color: white;
            border: none; font-size: .95rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-add:hover { background: #1060c9; }

        /* Prescription cards */
        .rx-card {
            background: white; border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            margin-bottom: 1rem; overflow: hidden;
        }
        .rx-card-header {
            padding: .85rem 1.25rem; background: #f8f9fb;
            border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .rx-patient { font-size: .9rem; font-weight: 700; color: #1e3c4f; }
        .rx-date { font-size: .75rem; color: #888; }
        .rx-card-body { padding: 1rem 1.25rem; }
        .rx-medication { font-size: 1rem; font-weight: 700; color: #1778F2; margin-bottom: .35rem; }
        .rx-detail {
            font-size: .82rem; color: #555; margin-bottom: .25rem;
            display: flex; align-items: center; gap: .4rem;
        }
        .rx-detail i { color: #1778F2; width: 14px; font-size: .8rem; }
        .rx-notes {
            margin-top: .5rem; padding: .5rem .75rem;
            background: #f8f9fb; border-left: 3px solid #1778F2;
            font-size: .82rem; color: #555;
        }

        .rx-icon {
            width: 36px; height: 36px; background: #e8f1fd;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .rx-icon i { color: #1778F2; font-size: .9rem; }

        .empty-state { text-align: center; padding: 3rem; color: #aaa; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; color: #dee2e6; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }

        @media (max-width: 900px) { .page-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
    </div>
    <div class="sidebar-doctor">
        <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
        <div>
            <div class="sidebar-doctor-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></div>
            <div class="sidebar-doctor-role"><?= htmlspecialchars($doc_info['specialization'] ?? 'Doctor') ?></div>
        </div>
    </div>
    <nav class="sidebar-menu">
       
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
           Dashboard
        </a>
        
        <a href="appointments.php" class="<?= $current === 'appointments.php' ? 'active' : '' ?>">
           Appointments
        </a>
        <a href="schedule.php" class="<?= $current === 'schedule.php' ? 'active' : '' ?>">
          My Schedule
        </a>
        
        <a href="my_patients.php" class="<?= $current === 'my_patients.php' ? 'active' : '' ?>">
           My Patients
        </a>
        <a href="medical_records.php" class="<?= $current === 'medical_records.php' ? 'active' : '' ?>">
            Medical Reports
        </a>
        <a href="prescriptions.php" class="<?= $current === 'prescriptions.php' ? 'active' : '' ?>">
           Prescriptions
        </a>
        
        <a href="messages.php" class="<?= $current === 'messages.php' ? 'active' : '' ?>">
             Messages
            <?php if ($unread_messages > 0): ?>
                <span class="msg-badge"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        
        <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>">
           My Profile
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">Prescriptions</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
            <span class="topbar-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="page-grid">

            <!-- Left: Add Prescription Form -->
            <div>
                <div class="card">
                    <div class="card-header"><h2>Add Prescription</h2></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>Patient</label>
                                <select name="patient_id" required onchange="loadAppointments(this.value)">
                                    <option value="">-- Select Patient --</option>
                                    <?php while ($pat = mysqli_fetch_assoc($patients_list)): ?>
                                        <option value="<?= (int)$pat['user_id'] ?>">
                                            <?= htmlspecialchars($pat['full_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Appointment</label>
                                <select name="appointment_id" id="appointmentSelect">
                                    <option value="">-- Select patient first --</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Medication Name</label>
                                <input type="text" name="medication" required
                                       placeholder="e.g. Amoxicillin 500mg">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Dosage</label>
                                    <input type="text" name="dosage" required
                                           placeholder="e.g. 1 tablet">
                                </div>
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <input type="text" name="frequency"
                                           placeholder="e.g. 3x daily">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Duration</label>
                                <input type="text" name="duration"
                                       placeholder="e.g. 7 days">
                            </div>

                            <div class="form-group">
                                <label>Notes (optional)</label>
                                <textarea name="notes"
                                          placeholder="Additional instructions..."></textarea>
                            </div>

                            <button type="submit" name="add_prescription" class="btn-add">
                                <i class="fas fa-plus"></i> Add Prescription
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Prescriptions List -->
            <div>
                <div class="card-header" style="background:white; border:1px solid #e4e6ea; padding:1rem 1.5rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between;">
                    <h2 style="font-size:1rem; font-weight:700; color:#1e3c4f;">All Prescriptions</h2>
                    <span style="font-size:.8rem; color:#888;"><?= mysqli_num_rows($prescriptions) ?> total</span>
                </div>

                <?php if (mysqli_num_rows($prescriptions) > 0): ?>
                    <?php while ($rx = mysqli_fetch_assoc($prescriptions)):
                        // Parse title and description
                        preg_match('/^(.+?) - (.+)$/', $rx['title'], $title_parts);
                        $medication = $title_parts[1] ?? $rx['title'];
                        $dosage     = $title_parts[2] ?? '';

                        preg_match('/Frequency: (.+?) \|/', $rx['description'], $freq_match);
                        preg_match('/Duration: (.+?) \|/', $rx['description'], $dur_match);
                        preg_match('/Notes: (.+)$/', $rx['description'], $notes_match);
                        $frequency = $freq_match[1] ?? '';
                        $duration  = $dur_match[1]  ?? '';
                        $notes     = $notes_match[1] ?? '';
                    ?>
                    <div class="rx-card">
                        <div class="rx-card-header">
                            <div style="display:flex; align-items:center; gap:.75rem;">
                                <div class="rx-icon"><i class="fas fa-prescription-bottle"></i></div>
                                <div>
                                    <div class="rx-patient"><?= htmlspecialchars($rx['patient_name']) ?></div>
                                    <div class="rx-date">
                                        <?= !empty($rx['appointment_date']) ? date('M d, Y', strtotime($rx['appointment_date'])) : date('M d, Y', strtotime($rx['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <span style="font-size:.75rem; color:#888;">
                                <?= date('M d, Y', strtotime($rx['created_at'])) ?>
                            </span>
                        </div>
                        <div class="rx-card-body">
                            <div class="rx-medication">
                                <i class="fas fa-pills" style="margin-right:.4rem;"></i>
                                <?= htmlspecialchars($medication) ?>
                                <?php if ($dosage): ?> — <span style="font-weight:500; color:#555;"><?= htmlspecialchars($dosage) ?></span><?php endif; ?>
                            </div>
                            <?php if ($frequency): ?>
                            <div class="rx-detail">
                                <i class="fas fa-clock"></i>
                                Frequency: <?= htmlspecialchars($frequency) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($duration): ?>
                            <div class="rx-detail">
                                <i class="fas fa-calendar-alt"></i>
                                Duration: <?= htmlspecialchars($duration) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($notes && $notes !== ''): ?>
                            <div class="rx-notes">
                                <i class="fas fa-sticky-note" style="margin-right:.4rem; color:#1778F2;"></i>
                                <?= htmlspecialchars($notes) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription-bottle"></i>
                        No prescriptions added yet.
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script src="js/prescriptions.js"></script>

</body>
</html>