<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT a.*, u.full_name AS doctor_name, d.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.user_id
    JOIN doctor_profiles d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$appointments = $stmt->get_result();

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];
$current = basename($_SERVER['PHP_SELF']);
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

        /* ── Sidebar ── */
        .sidebar {
            width: 260px; background: #1778F2; min-height: 100vh;
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h2 { color: white; font-size: 1.7rem; font-weight: 800; line-height: 1.4; }
        .sidebar-brand .badge-portal {
            display: inline-block; background: rgba(255,255,255,0.2); color: white;
            font-size: .68rem; font-weight: 600; padding: .15rem .5rem;
            margin-top: .35rem; letter-spacing: .5px; text-transform: uppercase;
        }
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
        .sidebar-patient-name { color: white; font-weight: 700; font-size: .88rem; }
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

        /* ── Main ── */
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

        /* ── Filter tabs ── */
        .filter-tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .tab-btn {
            padding: .4rem 1.1rem; border: 1.5px solid #ddd;
            background: white; color: #6c757d;
            font-size: .82rem; font-weight: 600; cursor: pointer; transition: all .15s;
        }
        .tab-btn:hover { border-color: #1778F2; color: #1778F2; }
        .tab-btn.active { background: #1778F2; border-color: #1778F2; color: white; }

        /* ── Appointment Cards ── */
        .appointments-list { display: flex; flex-direction: column; gap: 1rem; }

        .apt-card {
            background: white; border: 1px solid #e4e6ea; border-radius: 0px;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            padding: 1.5rem;
        }
        .apt-card-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 1rem;
            flex-wrap: wrap; gap: .5rem;
        }
        .apt-card-header h3 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }

        .apt-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: .6rem; margin-bottom: 1.25rem;
        }
        .apt-detail {
            display: flex; align-items: center; gap: .5rem;
            font-size: .85rem; color: #555;
        }
        .apt-detail i { color: #1778F2; width: 15px; font-size: .82rem; }

        .apt-actions { display: flex; gap: .6rem; flex-wrap: wrap; }

        .btn-cancel {
            padding: .45rem 1.1rem; background: white; color: #e74c3c; border-radius: 6px;
            border: 1.5px solid #e74c3c; font-size: .82rem; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: all .2s;
        }
        .btn-cancel:hover { background: #e74c3c; color: white; }

        .btn-review {
            padding: .45rem 1.1rem; background: #1778F2; color: white; border-radius: 6px;
            border: 1.5px solid #1778F2; font-size: .82rem; font-weight: 600;
            text-decoration: none; transition: all .2s;
        }
        .btn-review:hover { background: #1060c9; }

        .btn-book {
            padding: .45rem 1.1rem; background: white; color: #1778F2; border-radius: 6px;
            border: 1.5px solid #1778F2; font-size: .82rem; font-weight: 600;
            text-decoration: none; transition: all .2s;
        }
        .btn-book:hover { background: #e8f1fd; }

        /* Badges */
        .badge { padding: .3rem .75rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; border-radius: 20px; }
        .badge-pending   { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1e7dd; color: #0a3622; }
        .badge-cancelled { background: #f8d7da; color: #58151c; }
        .badge-completed { background: #cfe2ff; color: #084298; }

        /* Empty */
        .empty-state { text-align: center; padding: 4rem; color: #aaa; background: white; border: 1px solid #e4e6ea; border-radius: 0px;}
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; color: #dee2e6; }
        .empty-state a { color: #1778F2; font-weight: 600; text-decoration: none; display: inline-block; margin-top: .5rem; }

        /* Summary bar */
        .summary-bar {
            display: flex; gap: 1rem; flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .summary-item {
            background: white; border: 1px solid #e4e6ea; border-radius: 0px;
            padding: .75rem 1.25rem; font-size: .82rem;
            display: flex; align-items: center; gap: .5rem;
        }
        .summary-item strong { color: #1e3c4f; font-size: 1.1rem; }
        .summary-item span { color: #888; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }

        .alert { display:none; } /* hide any stray flash alerts on this page */
    </style>
</head>
<body>

<!-- ── Sidebar ── -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
    </div>
    <div class="sidebar-patient">
        <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?></div>
        <div>
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
             Medical Records
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
        <a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- ── Main ── -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">My Appointments</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?></div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <?php
        // Count by status for summary
        $all_apts = [];
        while ($row = $appointments->fetch_assoc()) $all_apts[] = $row;
        $counts = ['all' => count($all_apts), 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($all_apts as $a) $counts[$a['status']] = ($counts[$a['status']] ?? 0) + 1;
        ?>


        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterApts('all', this)">All (<?= $counts['all'] ?>)</button>
            <button class="tab-btn" onclick="filterApts('pending', this)">Pending (<?= $counts['pending'] ?>)</button>
            <button class="tab-btn" onclick="filterApts('confirmed', this)">Confirmed (<?= $counts['confirmed'] ?>)</button>
            <button class="tab-btn" onclick="filterApts('completed', this)">Completed (<?= $counts['completed'] ?>)</button>
            <button class="tab-btn" onclick="filterApts('cancelled', this)">Cancelled (<?= $counts['cancelled'] ?>)</button>
        </div>

        <!-- Appointments List -->
        <div class="appointments-list">
            <?php if (count($all_apts) > 0): ?>
                <?php foreach ($all_apts as $apt): ?>
                <div class="apt-card" data-status="<?= htmlspecialchars($apt['status']) ?>">
                    <div class="apt-card-header">
                        <h3>Dr. <?= htmlspecialchars($apt['doctor_name']) ?></h3>
                        <span class="badge badge-<?= htmlspecialchars($apt['status']) ?>">
                            <?= ucfirst($apt['status']) ?>
                        </span>
                    </div>
                    <div class="apt-details">
                        <div class="apt-detail">
                            <i class="fas fa-stethoscope"></i>
                            <?= htmlspecialchars($apt['specialization']) ?>
                        </div>
                        <div class="apt-detail">
                            <i class="fas fa-calendar"></i>
                            <?= date('F j, Y', strtotime($apt['appointment_date'])) ?>
                        </div>
                        <div class="apt-detail">
                            <i class="fas fa-clock"></i>
                            <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                        </div>
                        <?php if (!empty($apt['symptoms'])): ?>
                        <div class="apt-detail">
                            <i class="fas fa-notes-medical"></i>
                            <?= htmlspecialchars(substr($apt['symptoms'], 0, 60)) ?><?= strlen($apt['symptoms']) > 60 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="apt-actions">
                        <?php if (in_array($apt['status'], ['pending', 'confirmed'])): ?>
                            <a href="cancel_appointment.php?id=<?= (int)$apt['appointment_id'] ?>"
                               class="btn-cancel"
                               onclick="return confirm('Cancel this appointment?')">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                        <?php if ($apt['status'] === 'completed'): ?>
                            <a href="rate_doctor.php?doctor=<?= (int)$apt['doctor_id'] ?>"
                               class="btn-review">
                                <i class="fas fa-star"></i> Rate Doctor
                            </a>
                        <?php endif; ?>
                        <?php if ($apt['status'] === 'cancelled'): ?>
                            <a href="book_appointment.php?doctor=<?= (int)$apt['doctor_id'] ?>"
                               class="btn-book">
                                <i class="fas fa-redo"></i> Rebook
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    No appointments found.
                    <a href="book_appointment.php">Book your first appointment →</a>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- end content -->

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
function filterApts(status, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.apt-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? 'block' : 'none';
    });
}
</script>
</body>
</html>