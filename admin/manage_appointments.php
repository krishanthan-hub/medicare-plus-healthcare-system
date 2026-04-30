<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $apt_id  = (int)$_POST['appointment_id'];
    $status  = $_POST['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
        $stmt->bind_param("si", $status, $apt_id);
        $stmt->execute();
        $success = "Appointment status updated.";
    }
}

$appointments = mysqli_query($conn, "
    SELECT a.*, u1.full_name AS patient_name, u2.full_name AS doctor_name
    FROM appointments a
    JOIN users u1 ON a.patient_id = u1.user_id
    JOIN users u2 ON a.doctor_id  = u2.user_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments – MediCare Plus</title>
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
        .sidebar-brand h2 { color: white; font-size: 1.4rem; font-weight: 800; line-height: 1.4; }
        .sidebar-brand .badge-portal {
            display: inline-block; background: rgba(255,255,255,0.2); color: white;
            font-size: .68rem; font-weight: 600; padding: .15rem .5rem;
            margin-top: .35rem; letter-spacing: .5px; text-transform: uppercase;
        }
        .sidebar-menu { flex: 1; padding: 1rem 0; }
        .menu-label {
            color: rgba(255,255,255,0.5); font-size: .65rem; font-weight: 700;
            letter-spacing: 1.2px; text-transform: uppercase; padding: .85rem 1.25rem .3rem;
        }
        .sidebar-menu a {
            display: block; padding: .7rem 1.25rem; color: rgba(255,255,255,0.85);
            text-decoration: none; font-size: .82rem; font-weight: 500;
            margin: .1rem .6rem; transition: background .15s;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active { background: white; color: #1778F2; font-weight: 700; }
        .sidebar-footer { padding: 1rem .6rem; border-top: 1px solid rgba(255,255,255,0.15); }
        .sidebar-footer a {
            display: block; padding: .75rem; background: rgba(255,255,255,0.15);
            color: white; text-align: center; text-decoration: none;
            font-size: .85rem; font-weight: 700; letter-spacing: .5px;
            text-transform: uppercase; transition: background .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }

        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        .topbar {
            background: white; padding: .9rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #e4e6ea; position: sticky; top: 0; z-index: 50;
        }
        .topbar-left { display: flex; align-items: center; gap: .5rem; font-size: .82rem; color: #888; }
        .topbar-left span.page { font-size: 1.25rem; font-weight: 700; color: #1e3c4f; margin-left: .35rem; }
        .topbar-right { display: flex; align-items: center; gap: .75rem; }
        .topbar-avatar {
            width: 36px; height: 36px; background: #1778F2; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: .85rem;
        }
        .topbar-name { font-size: .875rem; color: #444; font-weight: 500; }
        .topbar-logout {
            font-size: .8rem; color: #1778F2; text-decoration: none;
            font-weight: 600; padding: .35rem .85rem; border: 1.5px solid #1778F2;
        }
        .topbar-logout:hover { background: #1778F2; color: white; }

        .content { padding: 2rem; flex: 1; }

        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; }
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }

        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 2rem; }
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }

        .filter-btns { display: flex; gap: .5rem; flex-wrap: wrap; }
        .filter-btn {
            padding: .35rem .9rem; font-size: .8rem; font-weight: 600;
            border: 1.5px solid #ddd; background: white; color: #555;
            cursor: pointer; transition: all .15s;
        }
        .filter-btn:hover { border-color: #1778F2; color: #1778F2; }
        .filter-btn.active { background: #1778F2; color: white; border-color: #1778F2; }

        .table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .table th {
            background: #f8f9fb; padding: .75rem 1.25rem; text-align: left;
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #555; border-bottom: 1px solid #e4e6ea;
        }
        .table td { padding: .8rem 1.25rem; border-bottom: 1px solid #f4f4f4; color: #444; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #fafbfc; }

        .badge { padding: .3rem .7rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #58151c; }
        .badge-completed { background: #cfe2ff; color: #084298; }

        .status-select {
            padding: .3rem .5rem; border: 1.5px solid #ddd;
            font-size: .8rem; color: #444; cursor: pointer; outline: none;
        }
        .status-select:focus { border-color: #1778F2; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="manage_doctors.php" class="<?= $current === 'manage_doctors.php' ? 'active' : '' ?>">Doctors</a>
        <a href="manage_patients.php" class="<?= $current === 'manage_patients.php' ? 'active' : '' ?>">Patients</a>
        <a href="manage_appointments.php" class="<?= $current === 'manage_appointments.php' ? 'active' : '' ?>">Appointments</a>

    </nav>
    <div class="sidebar-footer">
        <a href="../pages/logout.php">Logout</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            Pages / <span class="page">Manage Appointments</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            <a href="../pages/logout.php" class="topbar-logout">Logout</a>
        </div>
    </div>

    <div class="content">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>All Appointments</h2>
                <div class="filter-btns">
                    <button class="filter-btn active" onclick="filterRows('all', this)">All</button>
                    <button class="filter-btn" onclick="filterRows('pending', this)">Pending</button>
                    <button class="filter-btn" onclick="filterRows('confirmed', this)">Confirmed</button>
                    <button class="filter-btn" onclick="filterRows('completed', this)">Completed</button>
                    <button class="filter-btn" onclick="filterRows('cancelled', this)">Cancelled</button>
                </div>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th><th>Patient</th><th>Doctor</th>
                        <th>Date</th><th>Time</th><th>Status</th><th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($apt = mysqli_fetch_assoc($appointments)): ?>
                    <tr class="apt-row" data-status="<?= htmlspecialchars($apt['status']) ?>">
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
                        </td>
                    </tr>
                    <?php endwhile; ?>
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
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.apt-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>