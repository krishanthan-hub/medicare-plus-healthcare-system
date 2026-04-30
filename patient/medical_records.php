<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT mr.*, u.full_name AS doctor_name, a.appointment_date
    FROM medical_records mr
    LEFT JOIN users u ON mr.doctor_id = u.user_id
    LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records – MediCare Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1778F2; min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h2 { color: white; font-size: 1.4rem; font-weight: 800; }
        .sidebar-brand .badge-portal { display: inline-block; background: rgba(255,255,255,0.2); color: white; font-size: .68rem; font-weight: 600; padding: .15rem .5rem; margin-top: .35rem; letter-spacing: .5px; text-transform: uppercase; }
        .sidebar-patient { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; gap: .75rem; }
        .sidebar-avatar { width: 40px; height: 40px; background: rgba(255,255,255,0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1rem; flex-shrink: 0; }
        .sidebar-patient-name { color: white; font-weight: 700; font-size: .88rem; }
        .sidebar-patient-role { color: rgba(255,255,255,0.6); font-size: .72rem; }
        .sidebar-menu { flex: 1; padding: 1rem 0; overflow-y: auto; }
        .menu-label { color: rgba(255,255,255,0.5); font-size: .6rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: .85rem 1.25rem .3rem; }
        .sidebar-menu a { display: flex; align-items: center; gap: .65rem; padding: .7rem 1.25rem; color: rgba(255,255,255,0.85); text-decoration: none; font-size: .85rem; font-weight: 500; margin: .1rem .6rem; transition: background .15s; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active { background: white; color: #1778F2; font-weight: 700; }
        .sidebar-menu a.active i { color: #1778F2; }
        .sidebar-menu a i { width: 16px; font-size: .85rem; color: rgba(255,255,255,0.7); }
        .msg-badge { background: #e74c3c; color: white; font-size: .65rem; font-weight: 700; padding: .1rem .4rem; border-radius: 10px; margin-left: auto; }
        .sidebar-footer { padding: 1rem .6rem; border-top: 1px solid rgba(255,255,255,0.15); }
        .sidebar-footer a { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: .75rem; background: rgba(255,255,255,0.15); color: white; text-decoration: none; font-size: .85rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; transition: background .2s; }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }
        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { background: white; padding: .9rem 2rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e4e6ea; position: sticky; top: 0; z-index: 50; }
        .topbar-left { font-size: .82rem; color: #888; }
        .topbar-left span.page { font-size: 1.25rem; font-weight: 700; color: #1e3c4f; margin-left: .35rem; }
        .topbar-right { display: flex; align-items: center; gap: .75rem; }
        .topbar-avatar { width: 36px; height: 36px; background: #1778F2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: .85rem; }
        .topbar-name { font-size: .875rem; color: #444; font-weight: 500; }
        .content { padding: 2rem; flex: 1; }
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; }
        .filter-bar select { padding: .6rem 1rem; border: 1.5px solid #ddd; outline: none; font-size: .88rem; color: #333; background: white; min-width: 200px; }
        .filter-bar select:focus { border-color: #1778F2; }
        .record-count { font-size: .85rem; color: #888; }
        .records-list { display: flex; flex-direction: column; gap: 1rem; }
        .record-card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); display: flex; align-items: flex-start; gap: 1.25rem; padding: 1.25rem; border-radius: 10px;}
        .record-icon { width: 46px; height: 46px; background: #e8f1fd; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .record-icon i { color: #1778F2; font-size: 1.1rem; }
        .record-content { flex: 1; }
        .record-title { font-size: .95rem; font-weight: 700; color: #1e3c4f; margin-bottom: .35rem; }
        .record-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .8rem; color: #888; margin-bottom: .5rem; }
        .record-meta span { display: flex; align-items: center; gap: .3rem; }
        .record-meta i { color: #1778F2; }
        .record-desc { font-size: .85rem; color: #555; line-height: 1.5; }
        .record-type-badge { display: inline-block; font-size: .7rem; font-weight: 700; padding: .2rem .6rem; text-transform: uppercase; letter-spacing: .3px; margin-bottom: .4rem; }
        .badge-prescription  { background: #cfe2ff; color: #084298; }
        .badge-lab_report    { background: #d1e7dd; color: #0a3622; }
        .badge-diagnosis     { background: #fff3cd; color: #856404; }
        .badge-visit_summary { background: #f8d7da; color: #58151c; }
        .record-actions { display: flex; flex-direction: column; gap: .4rem; flex-shrink: 0; }
        .btn-view { padding: .35rem .85rem; background: white; color: #1778F2; border: 1.5px solid #1778F2; font-size: .78rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: .3rem; transition: all .2s; border-radius: 6px;}
        .btn-view:hover { background: #e8f1fd; }
        .empty-state { text-align: center; padding: 4rem; color: #aaa; background: white; border: 1px solid #e4e6ea; border-radius: 10px; }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 1rem; color: #dee2e6; }
        .admin-footer { text-align: center; padding: 1.25rem; font-size: .8rem; color: #aaa; border-top: 1px solid #e4e6ea; background: white; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; width: 100%; max-width: 560px; margin: 1rem; max-height: 90vh; overflow-y: auto; border-radius: 10px;}
        .modal-header { background: #1778F2; padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h3 { color: white; font-size: .95rem; font-weight: 700; }
        .modal-close { background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer; }
        .modal-body { padding: 1.5rem; }
        .modal-info-row { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .modal-info-label { font-size: .72rem; color: #888; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; margin-bottom: .2rem; }
        .modal-info-value { font-size: .9rem; font-weight: 600; color: #1e3c4f; }
        .modal-section-title { font-size: .78rem; color: #888; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: .75rem; }
        .modal-desc { font-size: .9rem; color: #444; line-height: 1.8; white-space: pre-line; background: #f8f9fb; padding: 1rem; border-left: 3px solid #1778F2; }
        .modal-footer { padding: .85rem 1.5rem; border-top: 1px solid #f0f0f0; text-align: right; }
        .modal-btn-close { padding: .5rem 1.25rem; background: #1778F2; color: white; border: none; font-size: .88rem; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

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
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="book_appointment.php" class="<?= $current === 'book_appointment.php' ? 'active' : '' ?>"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
        <a href="my_appointments.php" class="<?= $current === 'my_appointments.php' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i> My Appointments</a>
        <a href="medical_records.php" class="<?= $current === 'medical_records.php' ? 'active' : '' ?>"><i class="fas fa-file-medical"></i> Medical Records</a>
        <a href="messages.php" class="<?= $current === 'messages.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i> Messages
            <?php if ($unread_messages > 0): ?><span class="msg-badge"><?= $unread_messages ?></span><?php endif; ?>
        </a>
        <a href="rate_doctor.php" class="<?= $current === 'rate_doctor.php' ? 'active' : '' ?>"><i class="fas fa-star"></i> Rate a Doctor</a>
        <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user"></i> My Profile</a>
    </nav>
    <div class="sidebar-footer">
        <a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">Medical Records</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?></div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">
        <div class="filter-bar">
            <select id="recordType" onchange="filterRecords()">
                <option value="all">All Records</option>
                <option value="prescription">Prescriptions</option>
                <option value="lab_report">Lab Reports</option>
                <option value="diagnosis">Diagnosis</option>
                <option value="visit_summary">Visit Summaries</option>
            </select>
            <span class="record-count"><?= count($records) ?> record(s)</span>
        </div>

        <div class="records-list" id="recordsList">
            <?php if (count($records) > 0): ?>
                <?php
                $icons = ['prescription'=>'prescription-bottle','lab_report'=>'flask','diagnosis'=>'stethoscope','visit_summary'=>'file-medical'];
                foreach ($records as $record):
                    $icon = $icons[$record['record_type']] ?? 'file-medical';
                    $date = !empty($record['created_at']) ? $record['created_at'] : null;
                ?>
                <div class="record-card" data-type="<?= htmlspecialchars($record['record_type']) ?>">
                    <div class="record-icon"><i class="fas fa-<?= $icon ?>"></i></div>
                    <div class="record-content">
                        <span class="record-type-badge badge-<?= htmlspecialchars($record['record_type']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $record['record_type'])) ?>
                        </span>
                        <div class="record-title"><?= htmlspecialchars($record['title']) ?></div>
                        <div class="record-meta">
                            <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($record['doctor_name'] ?? 'N/A') ?></span>
                            <?php if ($date): ?><span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($date)) ?></span><?php endif; ?>
                            <?php if (!empty($record['appointment_date'])): ?><span><i class="fas fa-calendar-check"></i> Visit: <?= date('M d, Y', strtotime($record['appointment_date'])) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($record['description'])): ?>
                        <p class="record-desc"><?= htmlspecialchars(substr($record['description'], 0, 120)) ?>...</p>
                        <?php endif; ?>
                    </div>
                    <div class="record-actions">
                        <button class="btn-view" onclick="viewRecord(
                            '<?= htmlspecialchars(addslashes($record['title'])) ?>',
                            '<?= htmlspecialchars(addslashes($record['doctor_name'] ?? 'N/A')) ?>',
                            '<?= !empty($record['appointment_date']) ? date('M d, Y', strtotime($record['appointment_date'])) : 'N/A' ?>',
                            '<?= !empty($date) ? date('M d, Y', strtotime($date)) : 'N/A' ?>',
                            '<?= htmlspecialchars(addslashes($record['description'] ?? '')) ?>',
                            '<?= ucfirst(str_replace('_', ' ', $record['record_type'])) ?>'
                        )">
                             View
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    No medical records found.<br>
                    <small>Records will appear here after your doctor visits.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-footer">Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.</div>
</div>

<!-- View Record Modal -->
<div class="modal-overlay" id="recordModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle"></h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="modal-info-row">
                <div class="modal-info-item">
                    <div class="modal-info-label">Doctor</div>
                    <div class="modal-info-value" id="modalDoctor"></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Visit Date</div>
                    <div class="modal-info-value" id="modalVisitDate"></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Issued On</div>
                    <div class="modal-info-value" id="modalIssuedDate"></div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label">Type</div>
                    <div class="modal-info-value" id="modalType" style="color:#1778F2;"></div>
                </div>
            </div>
            <div class="modal-section-title">Details</div>
            <div class="modal-desc" id="modalDesc"></div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn-close" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
function viewRecord(title, doctor, visitDate, issuedDate, desc, type) {
    document.getElementById('modalTitle').textContent     = title;
    document.getElementById('modalDoctor').textContent    = 'Dr. ' + doctor;
    document.getElementById('modalVisitDate').textContent = visitDate;
    document.getElementById('modalIssuedDate').textContent= issuedDate;
    document.getElementById('modalType').textContent      = type;
    document.getElementById('modalDesc').textContent      = desc;
    document.getElementById('recordModal').classList.add('open');
}
function closeModal() {
    document.getElementById('recordModal').classList.remove('open');
}
document.getElementById('recordModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
function filterRecords() {
    const type = document.getElementById('recordType').value;
    document.querySelectorAll('.record-card').forEach(card => {
        card.style.display = (type === 'all' || card.dataset.type === type) ? 'flex' : 'none';
    });
}
</script>
</body>
</html>