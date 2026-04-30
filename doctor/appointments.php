<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// Handle status update
if (isset($_POST['update_status'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $status = $_POST['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND doctor_id = ?");
        $stmt->bind_param("sii", $status, $appointment_id, $doctor_id);
        $stmt->execute();
        $success = "Appointment status updated!";
    }
}

$stmt = $conn->prepare("
    SELECT a.*, u.full_name AS patient_name, u.phone, u.email, p.blood_group
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    LEFT JOIN patient_profiles p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count by status
$counts = ['all' => count($appointments), 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($appointments as $a) $counts[$a['status']] = ($counts[$a['status']] ?? 0) + 1;

$doc_info        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT specialization FROM doctor_profiles WHERE doctor_id = $doctor_id"));
$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $doctor_id AND is_read = 0"))['count'];
$current         = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments – MediCare Plus</title>
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

        /* Summary bar */
        .summary-bar { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .summary-item {
            background: white; border: 1px solid #e4e6ea;
            padding: .75rem 1.25rem; font-size: .82rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .summary-item strong { color: #1e3c4f; font-size: 1.1rem; }
        .summary-item span { color: #888; }

        /* Filter tabs */
        .filter-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .tab-btn {
            padding: .4rem 1.1rem; border: 1.5px solid #ddd;
            background: white; color: #6c757d;
            font-size: .82rem; font-weight: 600; cursor: pointer; transition: all .15s;
        }
        .tab-btn:hover { border-color: #1778F2; color: #1778F2; }
        .tab-btn.active { background: #1778F2; border-color: #1778F2; color: white; }

        /* Card */
        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }

        /* Table */
        .table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .table th {
            background: #f8f9fb; padding: .75rem 1rem; text-align: left;
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #555; border-bottom: 1px solid #e4e6ea;
        }
        .table td { padding: .8rem 1rem; border-bottom: 1px solid #f4f4f4; color: #444; vertical-align: middle; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #fafbfc; }

        .badge { padding: .3rem .7rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; border-radius: 20px; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #58151c; }
        .badge-completed { background: #cfe2ff; color: #084298; }

        .status-select {
            padding: .3rem .5rem; border: 1.5px solid #ddd;
            font-size: .8rem; color: #444; cursor: pointer; outline: none;
        }
        .status-select:focus { border-color: #1778F2; }

        .btn-view {
            padding: .3rem .75rem; background: white; color: #1778F2;
            border: 1.5px solid #1778F2; font-size: .78rem; font-weight: 600;
            text-decoration: none; transition: all .2s; display: inline-block; margin-left: .3rem;
        }
        .btn-view:hover { background: #e8f1fd; }

        .btn-rx {
            padding: .3rem .75rem; background: #1778F2; color: white;
            border: 1.5px solid #1778F2; font-size: .78rem; font-weight: 600;
            text-decoration: none; transition: all .2s; display: inline-block; margin-left: .3rem;
        }
        .btn-rx:hover { background: #1060c9; }

        .empty-state { text-align: center; padding: 3rem; color: #aaa; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; color: #dee2e6; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
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

<!-- ── Main ── -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">My Appointments</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
            <span class="topbar-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Summary Bar -->
        <div class="summary-bar">
            <div class="summary-item"><strong><?= $counts['all'] ?></strong><span>Total</span></div>
            <div class="summary-item"><strong><?= $counts['pending'] ?></strong><span>Pending</span></div>
            <div class="summary-item"><strong><?= $counts['confirmed'] ?></strong><span>Confirmed</span></div>
            <div class="summary-item"><strong><?= $counts['completed'] ?></strong><span>Completed</span></div>
            <div class="summary-item"><strong><?= $counts['cancelled'] ?></strong><span>Cancelled</span></div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterRows('all', this)">All (<?= $counts['all'] ?>)</button>
            <button class="tab-btn" onclick="filterRows('pending', this)">Pending (<?= $counts['pending'] ?>)</button>
            <button class="tab-btn" onclick="filterRows('confirmed', this)">Confirmed (<?= $counts['confirmed'] ?>)</button>
            <button class="tab-btn" onclick="filterRows('completed', this)">Completed (<?= $counts['completed'] ?>)</button>
            <button class="tab-btn" onclick="filterRows('cancelled', this)">Cancelled (<?= $counts['cancelled'] ?>)</button>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="card-header">
                <h2>All Appointments</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Blood Group</th>
                        <th>Symptoms</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appointments) > 0): ?>
                        <?php foreach ($appointments as $apt): ?>
                        <tr class="apt-row" data-status="<?= htmlspecialchars($apt['status']) ?>">
                            <td><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></td>
                            <td><?= date('h:i A', strtotime($apt['appointment_time'])) ?></td>
                            <td><?= htmlspecialchars($apt['patient_name']) ?></td>
                            <td><?= htmlspecialchars($apt['phone']) ?></td>
                            <td><?= htmlspecialchars($apt['blood_group'] ?? 'N/A') ?></td>
                            <td style="max-width:150px;">
                                <?= htmlspecialchars(substr($apt['symptoms'] ?? '', 0, 50)) ?>
                                <?= strlen($apt['symptoms'] ?? '') > 50 ? '...' : '' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($apt['status']) ?>">
                                    <?= ucfirst($apt['status']) ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?= (int)$apt['appointment_id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" onchange="this.form.submit()" class="status-select">
                                        <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $apt['status'] === $s ? 'selected' : '' ?>>
                                                <?= ucfirst($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <a href="patient_details.php?id=<?= (int)$apt['patient_id'] ?>&appointment=<?= (int)$apt['appointment_id'] ?>"
                                   class="btn-view">View</a>
                                <a href="add_prescriptions.php?appointment=<?= (int)$apt['appointment_id'] ?>&patient=<?= (int)$apt['patient_id'] ?>"
                                   class="btn-rx">Add Rx</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    No appointments found.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
function filterRows(status, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.apt-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>