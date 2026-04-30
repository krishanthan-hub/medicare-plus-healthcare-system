<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// Get all unique patients
$patients = mysqli_query($conn, "
    SELECT DISTINCT u.user_id, u.full_name, u.email, u.phone,
           pp.gender, pp.blood_group, pp.date_of_birth,
           COUNT(a.appointment_id) AS total_appointments,
           MAX(a.appointment_date) AS last_visit,
           (SELECT status FROM appointments WHERE doctor_id = $doctor_id AND patient_id = u.user_id ORDER BY appointment_date DESC LIMIT 1) AS last_status
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    LEFT JOIN patient_profiles pp ON u.user_id = pp.patient_id
    WHERE a.doctor_id = $doctor_id
    GROUP BY u.user_id
    ORDER BY u.full_name ASC
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
    <title>My Patients – MediCare Plus</title>
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

        /* Search */
        .search-bar {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center;
        }
        .search-bar input {
            flex: 1; padding: .7rem 1rem; border: 1.5px solid #ddd;
            outline: none; font-size: .9rem;
        }
        .search-bar input:focus { border-color: #1778F2; }
        .total-count { font-size: .85rem; color: #888; white-space: nowrap; }

        /* Patient Cards Grid */
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .patient-card {
            background: white; border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            transition: box-shadow .2s;
        }
        .patient-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }

        .patient-card-header {
            padding: 1.25rem; display: flex; align-items: center; gap: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .patient-big-avatar {
            width: 50px; height: 50px; background: #e8f1fd;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #1778F2; font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
        }
        .patient-card-name { font-size: .95rem; font-weight: 700; color: #1e3c4f; }
        .patient-card-email { font-size: .75rem; color: #888; margin-top: .15rem; }

        .patient-card-body { padding: 1rem 1.25rem; }
        .patient-info-row {
            display: flex; align-items: center; gap: .5rem;
            font-size: .82rem; color: #555; margin-bottom: .5rem;
        }
        .patient-info-row:last-child { margin-bottom: 0; }
        .patient-info-row i { color: #1778F2; width: 14px; font-size: .8rem; }

        .patient-card-footer {
            padding: .75rem 1.25rem; border-top: 1px solid #f0f0f0;
            display: flex; gap: .5rem;
        }
        .btn-msg {
            flex: 1; padding: .5rem; background: white; color: #1778F2;
            border: 1.5px solid #1778F2; font-size: .8rem; font-weight: 600;
            text-decoration: none; text-align: center; transition: all .2s;
        }
        .btn-msg:hover { background: #e8f1fd; }
        .btn-view {
            flex: 1; padding: .5rem; background: #1778F2; color: white;
            border: 1.5px solid #1778F2; font-size: .8rem; font-weight: 600;
            text-decoration: none; text-align: center; transition: all .2s;
        }
        .btn-view:hover { background: #1060c9; }

        .badge-apt {
            display: inline-block; background: #e8f1fd; color: #1778F2;
            font-size: .72rem; font-weight: 700; padding: .2rem .6rem;
            margin-left: auto;
        }

        .empty-state { text-align: center; padding: 4rem; color: #aaa; grid-column: 1/-1; }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 1rem; color: #dee2e6; }

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
        <div class="topbar-left">Pages / <span class="page">My Patients</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
            <span class="topbar-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <?php
        $all_patients = [];
        while ($row = mysqli_fetch_assoc($patients)) $all_patients[] = $row;
        ?>

        <!-- Search Bar -->
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search patients by name, email or phone..."
                   onkeyup="searchPatients()">
            <span class="total-count"><?= count($all_patients) ?> patient(s)</span>
        </div>

        <!-- Patients Grid -->
        <div class="patients-grid" id="patientsGrid">
            <?php if (count($all_patients) > 0): ?>
                <?php foreach ($all_patients as $pat): ?>
                <div class="patient-card" data-name="<?= strtolower(htmlspecialchars($pat['full_name'])) ?>"
                     data-email="<?= strtolower(htmlspecialchars($pat['email'])) ?>"
                     data-phone="<?= htmlspecialchars($pat['phone']) ?>">

                    <div class="patient-card-header">
                        <div class="patient-big-avatar">
                            <?= strtoupper(substr($pat['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="patient-card-name"><?= htmlspecialchars($pat['full_name']) ?></div>
                            <div class="patient-card-email"><?= htmlspecialchars($pat['email']) ?></div>
                        </div>
                        <span class="badge-apt"><?= $pat['total_appointments'] ?> visits</span>
                    </div>

                    <div class="patient-card-body">
                        <div class="patient-info-row">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($pat['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="patient-info-row">
                            <i class="fas fa-tint"></i>
                            Blood Group: <?= htmlspecialchars($pat['blood_group'] ?? 'N/A') ?>
                        </div>
                        <div class="patient-info-row">
                            <i class="fas fa-venus-mars"></i>
                            Gender: <?= ucfirst(htmlspecialchars($pat['gender'] ?? 'N/A')) ?>
                        </div>
                        <?php if (!empty($pat['date_of_birth'])): ?>
                        <div class="patient-info-row">
                            <i class="fas fa-birthday-cake"></i>
                            DOB: <?= date('M d, Y', strtotime($pat['date_of_birth'])) ?>
                        </div>
                        <?php endif; ?>
                        <div class="patient-info-row">
                            <i class="fas fa-calendar"></i>
                            Last Visit: <?= !empty($pat['last_visit']) ? date('M d, Y', strtotime($pat['last_visit'])) : 'N/A' ?>
                        </div>
                    </div>

                    <div class="patient-card-footer">
                        <a href="messages.php?patient=<?= (int)$pat['user_id'] ?>" class="btn-msg">
                            <i class="fas fa-comments"></i> Message
                        </a>
                        <a href="appointments.php" class="btn-view">
                            <i class="fas fa-calendar"></i> Appointments
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    No patients yet. Patients will appear here after their appointments.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
function searchPatients() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.patient-card').forEach(card => {
        const name  = card.dataset.name  || '';
        const email = card.dataset.email || '';
        const phone = card.dataset.phone || '';
        card.style.display = (name.includes(query) || email.includes(query) || phone.includes(query)) ? '' : 'none';
    });
}
</script>
</body>
</html>