<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

$total_appointments  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE patient_id = $patient_id"))['count'];
$upcoming            = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE patient_id = $patient_id AND appointment_date >= CURDATE() AND status != 'cancelled'"))['count'];
$total_records       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM medical_records WHERE patient_id = $patient_id"))['count'];
$total_prescriptions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM medical_records WHERE patient_id = $patient_id AND record_type = 'prescription'"))['count'];
$unread_messages     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];

$recent_apts = mysqli_query($conn, "
    SELECT a.*, u.full_name AS doctor_name, d.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.user_id
    JOIN doctor_profiles d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = $patient_id
    ORDER BY a.appointment_date DESC LIMIT 5
");

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard – MediCare Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: 260px; background: #1778F2; min-height: 100vh;
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .sidebar-brand h2 { color: white; font-size: 1.7rem; font-weight: 800; line-height: 1.4; }
        .sidebar-brand .badge-portal {
            display: inline-block; background: rgba(255,255,255,0.2); color: white;
            font-size: .80rem; font-weight: 600; padding: .15rem .5rem;
            margin-top: .35rem; letter-spacing: .5px; text-transform: uppercase;
        }

        /* Patient info */
        .sidebar-patient {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; gap: .75rem;
        }
        .sidebar-avatar {
            width: 40px; height: 40px; background: rgba(255,255,255,0.25);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 1rem; flex-shrink: 0;
        }
        .sidebar-patient-info { overflow: hidden; }
        .sidebar-patient-name { color: white; font-weight: 700; font-size: .88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-patient-role { color: rgba(255,255,255,0.6); font-size: .72rem; }

        .sidebar-menu { flex: 1; padding: 1rem 0; overflow-y: auto; }
        .menu-label {
            color: rgba(255,255,255,0.5); font-size: .6rem; font-weight: 700;
            letter-spacing: 1.2px; text-transform: uppercase; padding: .85rem 1.25rem .3rem;
        }
        .sidebar-menu a {
            display: flex; align-items: center; gap: .65rem;
            padding: .7rem 1.25rem; color: rgba(255,255,255,0.85);
            text-decoration: none; font-size: .85rem; font-weight: 500;
            margin: .1rem .6rem; transition: background .15s; position: relative;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active { background: white; color: #1778F2; font-weight: 700; }
        .sidebar-menu a.active i { color: #1778F2; }
        .sidebar-menu a i { width: 16px; font-size: .85rem; color: rgba(255,255,255,0.7); }
        .sidebar-menu a.active i { color: #1778F2; }
        .msg-badge {
            background: #e74c3c; color: white; font-size: .65rem;
            font-weight: 700; padding: .1rem .4rem; border-radius: 0px;
            margin-left: auto;
        }

        .sidebar-footer { padding: 1rem .6rem; border-top: 1px solid rgba(255,255,255,0.15); }
        .sidebar-footer a {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            padding: .75rem; background: rgba(255,255,255,0.15); color: white;
            text-decoration: none; font-size: .85rem; font-weight: 700;
            letter-spacing: .5px; text-transform: uppercase; transition: background .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }

        /* ── Main ── */
        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── Topbar ── */
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

        /* ── Content ── */
        .content { padding: 2rem; flex: 1; }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #1778F2 0%, #1e3c4f 100%);
            padding: 1.75rem 2rem;
            margin-bottom: 1.75rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 1rem;
        }
        .welcome-banner h1 { color: white; font-size: 1.4rem; font-weight: 800; margin-bottom: .2rem; }
        .welcome-banner p  { color: rgba(255,255,255,0.8); font-size: .88rem; }
        .welcome-btn {
            padding: .65rem 1.5rem; background: white; color: #1778F2;
            text-decoration: none; font-size: .88rem; font-weight: 700;
            transition: all .2s; white-space: nowrap;
        }
        .welcome-btn:hover { background: #e8f1fd; }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
            gap: 1.25rem; margin-bottom: 1.75rem;
        }
        .stat-card {
        background: white; padding: 1.25rem;
          border: 1px solid #e4e6ea;
          box-shadow: 0 1px 4px rgba(0,0,0,.05);
          border-radius: 0px; 
        }
        .stat-card .label { font-size: .75rem; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; margin-bottom: .4rem; }
        .stat-card .value { font-size: 1.9rem; font-weight: 800; color: #1e3c4f; line-height: 1; }
        .stat-card .icon {
            width: 38px; height: 38px; background: #e8f1fd; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: .75rem;
        }
        .stat-card .icon i { color: #1778F2; font-size: .95rem; }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem; margin-bottom: 1.75rem;
        }
        .quick-action {
            background: white; border: 1px solid #e4e6ea;
            padding: 1.25rem 1rem; text-align: center;
            text-decoration: none; transition: all .2s;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }
        .quick-action:hover { background: #e8f1fd; border-color: #1778F2; }
        .quick-action i { font-size: 1.5rem; color: #1778F2; display: block; margin-bottom: .5rem; }
        .quick-action span { font-size: .82rem; font-weight: 600; color: #1e3c4f; }

        /* Cards */
          .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem;
          border-radius: 0px;
        }
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-header a { font-size: .8rem; color: #1778F2; text-decoration: none; font-weight: 600; }
        .card-header a:hover { text-decoration: underline; }

        /* Table */
        .table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .table th {
            background: #f8f9fb; padding: .75rem 1.5rem; text-align: left;
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #555; border-bottom: 1px solid #e4e6ea;
        }
        .table td { padding: .8rem 1.5rem; border-bottom: 1px solid #f4f4f4; color: #444; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #fafbfc; }

        .badge { padding: .3rem .7rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; border-radius: 20px; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #58151c; }
        .badge-completed { background: #cfe2ff; color: #084298; }

        .empty-state { text-align: center; padding: 3rem; color: #aaa; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; color: #dee2e6; }

        /* Footer */
        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .3s; }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
    </div>

    <div class="sidebar-patient">
        <div class="sidebar-avatar">
            <?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?>
        </div>
        <div class="sidebar-patient-info">
            <div class="sidebar-patient-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
            <div class="sidebar-patient-role">Patient</div>
        </div>
    </div>

    <nav class="sidebar-menu">
        
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
             Dashboard
        </a>
        <a href="book_appointment.php" class="<?= $current === 'book_appointment.php' ? 'active' : '' ?>">
            Book Appointment
        </a>

        
        <a href="my_appointments.php" class="<?= $current === 'my_appointments.php' ? 'active' : '' ?>">
             My Appointments
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
        <a href="rate_doctor.php" class="<?= $current === 'rate_doctor.php' ? 'active' : '' ?>">
            Rate a Doctor
        </a>

        
        <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>">
           My Profile
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../pages/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<!-- ── Main ── -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            Pages / <span class="page">Dashboard</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?>
            </div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Patient') ?></span>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Welcome Banner -->
        <div class="dashboard-welcome" style="display:flex; align-items:center; justify-content:space-between;">
    <div>
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?> </h1>
        <p>Here's a summary of your health activity today.</p>
    </div>
    <a href="/medicare_plus/patient/book_appointment.php" 
   style="padding:.65rem 1.4rem; background:#1778F2; color:white; border-radius:8px; font-size:.9rem; font-weight:600; text-decoration:none; white-space:nowrap;">
    + Book Appointment
</a>
</div>


        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Appointments</div>
                <div class="value"><?= $total_appointments ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Upcoming</div>
                <div class="value"><?= $upcoming ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Medical Reports</div>
                <div class="value"><?= $total_records ?></div>
            </div>
            
        </div>

        <!-- Recent Appointments -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Appointments</h2>
                <a href="my_appointments.php">View All →</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($recent_apts) > 0): ?>
                        <?php while ($apt = mysqli_fetch_assoc($recent_apts)): ?>
                        <tr>
                            <td>Dr. <?= htmlspecialchars($apt['doctor_name']) ?></td>
                            <td><?= htmlspecialchars($apt['specialization']) ?></td>
                            <td><?= date('M d, Y', strtotime($apt['appointment_date'])) ?></td>
                            <td><?= htmlspecialchars($apt['appointment_time']) ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($apt['status']) ?>">
                                    <?= ucfirst($apt['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    No appointments yet.
                                    <a href="book_appointment.php" style="display:block; margin-top:.5rem; color:#1778F2; font-weight:600;">Book your first appointment →</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- end content -->

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>

</div><!-- end main -->

</body>
</html>