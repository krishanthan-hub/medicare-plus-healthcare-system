<?php
require_once '../includes/auth_check.php';
include '../config/database.php';
$contact_messages = mysqli_query($conn, "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 10");
$total_doctors      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE user_type = 'doctor'"))['count'];
$total_patients     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE user_type = 'patient'"))['count'];
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments"))['count'];
$today_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()"))['count'];

$recent_appointments = mysqli_query($conn, "
    SELECT a.*, u1.full_name AS patient_name, u2.full_name AS doctor_name
    FROM appointments a
    JOIN users u1 ON a.patient_id = u1.user_id
    JOIN users u2 ON a.doctor_id  = u2.user_id
    ORDER BY a.created_at DESC LIMIT 10
");

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – MediCare Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: 260px;
            background: #1778F2;
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .sidebar-brand h2 {
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            line-height: 1.4;
        }
        .sidebar-brand .badge-portal {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: .68rem;
            font-weight: 600;
            padding: .15rem .5rem;
            margin-top: .35rem;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .sidebar-menu {
            flex: 1;
            padding: 1rem 0;
        }
        .menu-label {
            color: rgba(255,255,255,0.5);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            padding: .85rem 1.25rem .3rem;
        }
        .sidebar-menu a {
            display: block;
            padding: .7rem 1.25rem;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: .82rem;
            font-weight: 500;
            margin: .1rem .6rem;
            transition: background .15s;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active { background: white; color: #1778F2; font-weight: 700; }
        .sidebar-footer {
            padding: 1rem .6rem;
            border-top: 1px solid rgba(255,255,255,0.15);
        }
        .sidebar-footer a {
            display: block;
            padding: .75rem;
            background: rgba(255,255,255,0.15);
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: .85rem;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            transition: background .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }

        /* ── Main ── */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Topbar ── */
        .topbar {
            background: white;
            padding: .9rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e4e6ea;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .82rem;
            color: #888;
        }
        .topbar-left span.page { font-size: 1.25rem; font-weight: 700; color: #1e3c4f; margin-left: .35rem; }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .topbar-avatar {
            width: 36px; height: 36px;
            background: #1778F2;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: .85rem;
        }
        .topbar-name { font-size: .875rem; color: #444; font-weight: 500; }
        .topbar-logout {
            font-size: .8rem;
            color: #1778F2;
            text-decoration: none;
            font-weight: 600;
            padding: .35rem .85rem;
            border: 1.5px solid #1778F2;
        }
        .topbar-logout:hover { background: #1778F2; color: white; }

        /* ── Body ── */
        .content { padding: 2rem; flex: 1; }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem 1.25rem;
            border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }
        .stat-card .label {
            font-size: .8rem;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: .5rem;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3c4f;
        }

        /* ── Table Card ── */
        .card {
            background: white;
            border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            margin-bottom: 2rem;
        }
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .table th {
            background: #f8f9fb;
            padding: .75rem 1.5rem;
            text-align: left;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #555;
            border-bottom: 1px solid #e4e6ea;
        }
        .table td {
            padding: .8rem 1.5rem;
            border-bottom: 1px solid #f4f4f4;
            color: #444;
        }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #fafbfc; }

        .badge { padding: .3rem .7rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .badge-pending    { background: #fff3cd; color: #856404; }
        .badge-confirmed  { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled  { background: #f8d7da; color: #58151c; }
        .badge-completed  { background: #cfe2ff; color: #084298; }

        .btn-manage {
            padding: .3rem .8rem;
            background: #1778F2;
            color: white;
            text-decoration: none;
            font-size: .78rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        .btn-manage:hover { background: #1060c9; }

        /* ── Footer ── */
        .admin-footer {
            text-align: center;
            padding: 1.25rem;
            font-size: .8rem;
            color: #aaa;
            border-top: 1px solid #e4e6ea;
            background: white;
        }
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>

        <a href="contact_messages.php" class="<?= $current === 'contact_messages.php' ? 'active' : '' ?>">Contact Messages</a>
        <a href="manage_doctors.php" class="<?= $current === 'manage_doctors.php' ? 'active' : '' ?>">Doctors</a>
        <a href="manage_patients.php" class="<?= $current === 'manage_patients.php' ? 'active' : '' ?>">Patients</a>
        <a href="manage_appointments.php" class="<?= $current === 'manage_appointments.php' ? 'active' : '' ?>">Appointments</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../pages/logout.php">Logout</a>
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
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            <a href="../pages/logout.php" class="topbar-logout">Logout</a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Doctors</div>
                <div class="value"><?= $total_doctors ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Patients</div>
                <div class="value"><?= $total_patients ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Appointments</div>
                <div class="value"><?= $total_appointments ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Today's Appointments</div>
                <div class="value"><?= $today_appointments ?></div>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Appointments</h2>
                <a href="manage_appointments.php" class="btn-manage">View All</a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($apt = mysqli_fetch_assoc($recent_appointments)): ?>
                    <tr>
                        <td>#<?= (int)$apt['appointment_id'] ?></td>
                        <td><?= htmlspecialchars($apt['patient_name']) ?></td>
                        <td>Dr. <?= htmlspecialchars($apt['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($apt['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($apt['appointment_time']) ?></td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($apt['status']) ?>">
                                <?= htmlspecialchars($apt['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="manage_appointments.php" class="btn-manage">Manage</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Contact Messages -->
        <div class="card">
           <div class="card-header">
           <h2>Contact Messages</h2>
       </div>
          <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($msg = mysqli_fetch_assoc($contact_messages)): ?>
            <tr>
                <td><?= htmlspecialchars($msg['name']) ?></td>
                <td><?= htmlspecialchars($msg['email']) ?></td>
                <td><?= htmlspecialchars($msg['subject']) ?></td>
                <td><?= htmlspecialchars(substr($msg['message'], 0, 80)) ?>...</td>
                <td><?= date('M d, Y', strtotime($msg['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
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