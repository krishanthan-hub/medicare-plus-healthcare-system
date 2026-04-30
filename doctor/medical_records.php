<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];
$doc_info  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT specialization FROM doctor_profiles WHERE doctor_id = $doctor_id"));
$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $doctor_id AND is_read = 0"))['count'];
$current = basename($_SERVER['PHP_SELF']);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id  = (int)($_POST['patient_id'] ?? 0);
    $title       = mysqli_real_escape_string($conn, trim($_POST['title'] ?? ''));
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $record_date = mysqli_real_escape_string($conn, $_POST['record_date'] ?? date('Y-m-d'));
    $record_type = mysqli_real_escape_string($conn, $_POST['record_type'] ?? 'medical_report');

    if (!$patient_id || !$title || !$description) {
        $error = "Please fill in all required fields.";
    } else {
        $sql = "INSERT INTO medical_records (patient_id, doctor_id, record_type, title, description, record_date, created_at)
                VALUES ($patient_id, $doctor_id, '$record_type', '$title', '$description', '$record_date', NOW())";
        if (mysqli_query($conn, $sql)) {
            $success = $record_type === 'prescription' ? "Prescription added successfully!" : "Medical report added successfully!";
        } else {
            $error = "Failed: " . mysqli_error($conn);
        }
    }
}

// Get patients of this doctor
$patients = mysqli_query($conn, "
    SELECT DISTINCT u.user_id, u.full_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    WHERE a.doctor_id = $doctor_id
    ORDER BY u.full_name ASC
");

// Get all records by this doctor
$records = mysqli_query($conn, "
    SELECT mr.*, u.full_name AS patient_name
    FROM medical_records mr
    JOIN users u ON mr.patient_id = u.user_id
    WHERE mr.doctor_id = $doctor_id
    ORDER BY mr.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Reports – MediCare Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px; background: #1778F2; min-height: 100vh;
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h2 { color: white; font-size: 1.7rem; font-weight: 800; }
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
            text-transform: uppercase; transition: background .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }

        /* Main */
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

        /* Tabs */
        .tabs { display: flex; gap: 0; margin-bottom: 1.75rem; border-bottom: 2px solid #e4e6ea; }
        .tab-btn {
            padding: .75rem 1.75rem; background: none; border: none;
            font-size: .9rem; font-weight: 600; color: #888; cursor: pointer;
            border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all .2s;
        }
        .tab-btn.active { color: #1778F2; border-bottom-color: #1778F2; }
        .tab-btn:hover { color: #1778F2; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Card */
        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem; }
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        /* Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: .4rem; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: .82rem; font-weight: 600; color: #444; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: .7rem 1rem; border: 1.5px solid #e0e0e0; border-radius: 8px;
            font-size: .9rem; font-family: inherit; color: #333;
            background: white; transition: border-color .2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: #1778F2; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .btn-submit {
            padding: .75rem 2rem; background: #1778F2; color: white;
            border: none; border-radius: 8px; font-size: .9rem; font-weight: 700;
            cursor: pointer; margin-top: .5rem; transition: background .2s;
        }
        .btn-submit:hover { background: #1060c9; }

        /* Alert */
        .alert { padding: .85rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: .88rem; display: flex; align-items: center; gap: .5rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #fde8e8; color: #c0392b; border: 1px solid #f5c6cb; }

        /* Table */
        .table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .table th {
            background: #f8f9fb; padding: .75rem 1.25rem; text-align: left;
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #555; border-bottom: 1px solid #e4e6ea;
        }
        .table td { padding: .8rem 1.25rem; border-bottom: 1px solid #f4f4f4; color: #444; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #fafbfc; }
        .badge { padding: .3rem .7rem; font-size: .72rem; font-weight: 700; border-radius: 20px; }
        .badge-prescription   { background: #e8f1fd; color: #1778F2; }
        .badge-medical_report { background: #d1e7dd; color: #0a3622; }

        .empty-state { text-align: center; padding: 3rem; color: #aaa; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; color: #dee2e6; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
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

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">Medical Reports</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
            <span class="topbar-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab(event, 'prescription')">
                 Add Prescription
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'medical_report')">
                Add Medical Report
            </button>
            <button class="tab-btn" onclick="switchTab(event, 'history')">
                 View All Records
            </button>
        </div>

        <!-- Tab: Add Prescription -->
        <div class="tab-pane active" id="tab-prescription">
            <div class="card">
                <div class="card-header"><h2>New Prescription</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="record_type" value="prescription">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label>Patient </label>
                                <select name="patient_id" required>
                                    <option value="">-- Select Patient --</option>
                                    <?php while ($p = mysqli_fetch_assoc($patients)): ?>
                                        <option value="<?= $p['user_id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                                    <?php endwhile; mysqli_data_seek($patients, 0); ?>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Prescription Medication</label>
                                <input type="text" name="title" placeholder="e.g. Amoxicillin 500mg - 3x daily for 7 days" required>
                            </div>
                            <div class="form-group full">
                                <label>Instructions</label>
                                <textarea name="description" placeholder="Dosage instructions, warnings, refill info..." required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit"> Add Prescription</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: Add Medical Report -->
        <div class="tab-pane" id="tab-medical_report">
            <div class="card">
                <div class="card-header"><h2>New Medical Report</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="record_type" value="medical_report">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label>Patient </label>
                                <select name="patient_id" required>
                                    <option value="">-- Select Patient --</option>
                                    <?php while ($p = mysqli_fetch_assoc($patients)): ?>
                                        <option value="<?= $p['user_id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Report Title </label>
                                <input type="text" name="title" placeholder="e.g. Blood Test Results, X-Ray Report..." required>
                            </div>
                            <div class="form-group full">
                                <label>Report Details </label>
                                <textarea name="description" placeholder="Diagnosis, findings, recommendations..." required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit">Add Medical Report</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: View All Records -->
        <div class="tab-pane" id="tab-history">
            <div class="card">
                <div class="card-header"><h2><i class="fas fa-history"></i> &nbsp;All Records</h2></div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($records) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($records)): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['patient_name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $r['record_type'] ?>">
                                        <?= $r['record_type'] === 'prescription' ? 'Prescription' : 'Medical Report' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($r['title']) ?></td>
                                <td><?= htmlspecialchars(substr($r['description'], 0, 60)) ?>...</td>
                                <td><?= date('M d, Y', strtotime($r['record_date'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    No records found yet.
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
function switchTab(e, name) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    e.target.closest('.tab-btn').classList.add('active');
}
</script>

</body>
</html>