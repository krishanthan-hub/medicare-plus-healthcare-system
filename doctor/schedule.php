<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// Handle schedule update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $available_days       = isset($_POST['available_days']) ? implode(',', $_POST['available_days']) : '';
    $available_time_start = $_POST['available_time_start'] ?? '';
    $available_time_end   = $_POST['available_time_end'] ?? '';

    $stmt = $conn->prepare("UPDATE doctor_profiles SET available_days=?, available_time_start=?, available_time_end=? WHERE doctor_id=?");
    $stmt->bind_param("sssi", $available_days, $available_time_start, $available_time_end, $doctor_id);
    if ($stmt->execute()) {
        $success = "Schedule updated successfully!";
    } else {
        $error = "Failed to update schedule.";
    }
}

// Fetch current schedule
$schedule = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT available_days, available_time_start, available_time_end, specialization
    FROM doctor_profiles WHERE doctor_id = $doctor_id
"));

$current_days = explode(',', $schedule['available_days'] ?? '');
$all_days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// Today's appointments
$today_apts = mysqli_query($conn, "
    SELECT a.*, u.full_name AS patient_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    WHERE a.doctor_id = $doctor_id AND a.appointment_date = CURDATE()
    AND a.status != 'cancelled'
    ORDER BY a.appointment_time ASC
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
    <title>My Schedule – MediCare Plus</title>
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

        .page-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; }
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        /* Days grid */
        .days-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: .75rem; margin-bottom: 1.5rem;
        }
        .day-checkbox { display: none; }
        .day-label {
            display: block; padding: .65rem .5rem; text-align: center;
            border: 1.5px solid #ddd; font-size: .85rem; font-weight: 600;
            color: #666; cursor: pointer; transition: all .15s;
        }
        .day-label:hover { border-color: #1778F2; color: #1778F2; }
        .day-checkbox:checked + .day-label {
            background: #1778F2; border-color: #1778F2; color: white;
        }

        .time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block; font-size: .78rem; font-weight: 700;
            color: #1e3c4f; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .form-group input, .form-group select {
            width: 100%; padding: .7rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; color: #333; transition: border-color .2s;
        }
        .form-group input:focus { border-color: #1778F2; }

        .btn-save {
            width: 100%; padding: .8rem; background: #1778F2; color: white;
            border: none; font-size: .95rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: #1060c9; }

        /* Today's schedule */
        .today-header {
            background: linear-gradient(135deg, #1778F2, #1e3c4f);
            padding: 1.25rem 1.5rem; margin-bottom: 1rem;
        }
        .today-header h3 { color: white; font-size: .95rem; font-weight: 700; }
        .today-header p  { color: rgba(255,255,255,0.75); font-size: .78rem; margin-top: .2rem; }

        .apt-item {
            display: flex; align-items: center; gap: 1rem;
            padding: .9rem 1.5rem; border-bottom: 1px solid #f4f4f4;
        }
        .apt-item:last-child { border-bottom: none; }
        .apt-time {
            background: #e8f1fd; color: #1778F2;
            font-weight: 700; font-size: .82rem;
            padding: .4rem .75rem; white-space: nowrap;
        }
        .apt-patient { font-size: .9rem; font-weight: 600; color: #1e3c4f; }
        .apt-symptoms { font-size: .78rem; color: #888; margin-top: .15rem; }

        .badge { padding: .3rem .7rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; border-radius: 20px; margin-left: auto; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1e7dd; color: #0a3622; }
        .badge-completed { background: #cfe2ff; color: #084298; }

        .empty-state { text-align: center; padding: 2.5rem; color: #aaa; }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: .5rem; color: #dee2e6; }

        /* Current schedule display */
        .schedule-display {
            display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem;
        }
        .day-badge {
            padding: .3rem .75rem; font-size: .78rem; font-weight: 600;
        }
        .day-badge.active { background: #e8f1fd; color: #1778F2; border: 1px solid #1778F2; }
        .day-badge.inactive { background: #f4f4f4; color: #bbb; border: 1px solid #e4e6ea; }

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
        <div class="topbar-left">Pages / <span class="page">My Schedule</span></div>
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

            <!-- Left: Update Schedule -->
            <div>
                <!-- Current Schedule -->
                <div class="card">
                    <div class="card-header"><h2>Current Schedule</h2></div>
                    <div class="card-body">
                        <p style="font-size:.78rem; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-bottom:.75rem;">Available Days</p>
                        <div class="schedule-display">
                            <?php foreach ($all_days as $day): ?>
                                <span class="day-badge <?= in_array(trim($day), array_map('trim', $current_days)) ? 'active' : 'inactive' ?>">
                                    <?= $day ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <p style="font-size:.78rem; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-bottom:.5rem;">Consultation Hours</p>
                        <p style="font-size:.95rem; font-weight:600; color:#1e3c4f;">
                            <?= date('h:i A', strtotime($schedule['available_time_start'] ?? '09:00:00')) ?>
                            —
                            <?= date('h:i A', strtotime($schedule['available_time_end'] ?? '17:00:00')) ?>
                        </p>
                    </div>
                </div>

                <!-- Update Schedule -->
                <div class="card">
                    <div class="card-header"><h2>Update Schedule</h2></div>
                    <div class="card-body">
                        <form method="POST">
                            <p style="font-size:.78rem; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-bottom:.75rem;">Select Available Days</p>
                            <div class="days-grid">
                                <?php foreach ($all_days as $day): ?>
                                    <div>
                                        <input type="checkbox" name="available_days[]"
                                               id="day_<?= $day ?>" value="<?= $day ?>"
                                               class="day-checkbox"
                                               <?= in_array(trim($day), array_map('trim', $current_days)) ? 'checked' : '' ?>>
                                        <label for="day_<?= $day ?>" class="day-label"><?= $day ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <p style="font-size:.78rem; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-bottom:.75rem;">Consultation Hours</p>
                            <div class="time-grid">
                                <div class="form-group">
                                    <label>Start Time</label>
                                    <input type="time" name="available_time_start"
                                           value="<?= htmlspecialchars($schedule['available_time_start'] ?? '09:00') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>End Time</label>
                                    <input type="time" name="available_time_end"
                                           value="<?= htmlspecialchars($schedule['available_time_end'] ?? '17:00') ?>" required>
                                </div>
                            </div>

                            <button type="submit" name="update_schedule" class="btn-save">
                                <i class="fas fa-save"></i> Save Schedule
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Today's Appointments -->
            <div>
                <div class="card">
                    <div class="today-header">
                        <h3>Today's Appointments</h3>
                        <p><?= date('l, F j, Y') ?></p>
                    </div>
                    <?php
                    $today_count = mysqli_num_rows($today_apts);
                    if ($today_count > 0):
                        while ($apt = mysqli_fetch_assoc($today_apts)):
                    ?>
                    <div class="apt-item">
                        <div class="apt-time"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                        <div>
                            <div class="apt-patient"><?= htmlspecialchars($apt['patient_name']) ?></div>
                            <div class="apt-symptoms"><?= htmlspecialchars(substr($apt['symptoms'] ?? '', 0, 60)) ?>...</div>
                        </div>
                        <span class="badge badge-<?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            No appointments today.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

</body>
</html>