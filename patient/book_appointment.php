<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id       = (int)$_SESSION['user_id'];
$preselect_doctor = (int)($_GET['doctor'] ?? $_GET['doctor_id'] ?? 0);

$doctors_stmt = $conn->prepare("
    SELECT u.user_id, u.full_name, d.specialization, d.consultation_fee,
           d.available_days, d.available_time_start, d.available_time_end
    FROM users u
    JOIN doctor_profiles d ON u.user_id = d.doctor_id
    WHERE u.user_type = 'doctor'
    ORDER BY u.full_name ASC
");
$doctors_stmt->execute();
$doctors = $doctors_stmt->get_result();
$doctors_list = $doctors->fetch_all(MYSQLI_ASSOC);

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id        = (int)$_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $symptoms         = mysqli_real_escape_string($conn, $_POST['symptoms'] ?? '');

    if (empty($appointment_date) || strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = "Please select a valid future date.";
    } elseif (empty($appointment_time)) {
        $error = "Please select a time slot.";
    } elseif (empty($symptoms)) {
        $error = "Please describe your symptoms.";
    } else {
        $check = $conn->prepare("SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
        $check->bind_param("iss", $doctor_id, $appointment_date, $appointment_time);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This time slot is already booked. Please select another time.";
        } else {
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, symptoms) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $symptoms);
            if ($stmt->execute()) {
                $success = "Appointment booked successfully!";
            } else {
                $error = "Booking failed. Please try again.";
            }
        }
    }
}

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment – MediCare Plus</title>
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
        .sidebar-patient {
            padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15);
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

        /* ── Booking Layout ── */
        .booking-wrapper {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 1.5rem;
            align-items: start;
        }

        /* ── Cards ── */
        .card {
                background: white; border: 1px solid #e4e6ea; border-radius: 0px;
                box-shadow: 0 1px 4px rgba(0,0,0,.05);
                margin-bottom: 0;
        }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        /* ── Doctor Info Card ── */
        .doctor-info-card {
            background: white; border: 1px solid #e4e6ea; border-radius: 0px;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            position: sticky; top: 80px;
        }
        .doctor-info-header { background: #1778F2; padding: 1.25rem 1.5rem; }
        .doctor-info-header h3 { color: white; font-size: .95rem; font-weight: 700; }
        .doctor-info-body { padding: 1.25rem 1.5rem; }
        .doc-info-row { margin-bottom: 1rem; }
        .doc-info-row:last-child { margin-bottom: 0; }
        .doc-info-label {
            font-size: .78rem; font-weight: 700; color: #1e3c4f;
            display: flex; align-items: center; gap: .4rem; margin-bottom: .2rem;
        }
        .doc-info-label i { color: #1778F2; width: 14px; }
        .doc-info-value { font-size: .88rem; color: #555; padding-left: 1.2rem; }
        .doc-info-value.fee { color: #27ae60; font-weight: 700; font-size: .95rem; }
        .doc-placeholder {
            text-align: center; color: #aaa; padding: 2rem 1rem; font-size: .85rem;
        }
        .doc-placeholder i { font-size: 2rem; display: block; margin-bottom: .5rem; color: #dee2e6; }

        /* ── Alerts ── */
        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; border-radius: 0px;}
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

        /* ── Form ── */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: .8rem; font-weight: 700;
            color: #1e3c4f; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%; padding: .7rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; color: #333;
            transition: border-color .2s; font-family: inherit;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus { border-color: #1778F2; }
        .form-group textarea { resize: vertical; min-height: 110px; }

        /* ── Time Slots ── */
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: .5rem; margin-top: .5rem;
        }
        .time-slot {
            padding: .5rem; text-align: center;
            border: 1.5px solid #dee2e6; background: white;
            font-size: .8rem; font-weight: 600; color: #444;
            cursor: pointer; transition: all .15s;
        }
        .time-slot:hover { border-color: #1778F2; color: #1778F2; background: #e8f1fd; }
        .time-slot.selected { background: #1778F2; border-color: #1778F2; color: white; border-radius: 6px;}
        .time-slot.booked { background: #f8f9fa; border-color: #dee2e6; color: #bbb; cursor: not-allowed; text-decoration: line-through; }

        /* ── Submit ── */
        .btn-submit {
            width: 100%; padding: .85rem; background: #1778F2; color: white; border-radius: 6px;
            border: none; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-submit:hover { background: #1060c9; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }

        @media (max-width: 900px) {
            .booking-wrapper { grid-template-columns: 1fr; }
        }
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
        <a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- ── Main ── -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">Book Appointment</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?></div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="booking-wrapper">

            <!-- Left: Form -->
            <div>
        <form method="POST" id="bookingForm">

            <div class="card">
                <div class="card-body">
                    <h2 style="font-size:1.2rem; font-weight:800; color:#1e3c4f; margin-bottom:1.5rem;">Book an Appointment</h2>

                    <div class="form-group">
                        <label>Doctor</label>
                        <select name="doctor_id" id="doctorSelect" required
                                onchange="updateDoctorInfo(); getAvailableSlots();">
                            <option value="">-- Choose a Doctor --</option>
                            <?php foreach ($doctors_list as $doctor): ?>
                                <option value="<?= (int)$doctor['user_id'] ?>"
                                    data-spec="<?= htmlspecialchars($doctor['specialization']) ?>"
                                    data-fee="<?= number_format((float)$doctor['consultation_fee']) ?>"
                                    data-days="<?= htmlspecialchars($doctor['available_days']) ?>"
                                    data-start="<?= htmlspecialchars($doctor['available_time_start']) ?>"
                                    data-end="<?= htmlspecialchars($doctor['available_time_end']) ?>"
                                    <?= $preselect_doctor === (int)$doctor['user_id'] ? 'selected' : '' ?>>
                                    Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                                    — <?= htmlspecialchars($doctor['specialization']) ?>
                                    (LKR <?= number_format((float)$doctor['consultation_fee']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="appointment_date" id="appointmentDate"
                               min="<?= date('Y-m-d') ?>" required onchange="getAvailableSlots()">
                    </div>

                    <div class="form-group">
                        <label>Time</label>
                        <div id="timeSlots">
                        </div>
                        <input type="hidden" name="appointment_time" id="selected_time" required>
                        <p style="font-size:.75rem; color:#aaa; margin-top:.4rem;">
                            <i class="fas fa-info-circle"></i> Greyed slots are already booked.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>Symptoms / Reason for Visit</label>
                        <textarea name="symptoms" required
                                  placeholder="Please describe your symptoms or reason for this visit..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                    Confirm Booking
                    </button>

                </div>
            </div>

        </form>
    </div>


            <!-- Right: Doctor Info -->
            <div>
                <div class="doctor-info-card">
                    <div class="doctor-info-header">
                        <h3>Doctor Information</h3>
                    </div>
                    <div class="doctor-info-body" id="doctorInfoBody">
                        <div class="doc-placeholder">
                            <i class="fas fa-user-md"></i>
                            Select a doctor to see their details
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
function updateDoctorInfo() {
    const select = document.getElementById('doctorSelect');
    const opt    = select.options[select.selectedIndex];
    const body   = document.getElementById('doctorInfoBody');

    if (!select.value) {
        body.innerHTML = '<div class="doc-placeholder"><i class="fas fa-user-md"></i>Select a doctor to see their details</div>';
        return;
    }

    const fmtTime = t => {
        if (!t) return '';
        const [h, m] = t.split(':');
        const hr = parseInt(h);
        return `${hr > 12 ? hr-12 : (hr || 12)}:${m} ${hr >= 12 ? 'PM' : 'AM'}`;
    };

    body.innerHTML = `
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-stethoscope"></i> Specialization:</div>
            <div class="doc-info-value">${opt.dataset.spec}</div>
        </div>
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-money-bill-wave"></i> Consultation Fee:</div>
            <div class="doc-info-value fee">LKR ${opt.dataset.fee}</div>
        </div>
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-calendar-alt"></i> Available Days:</div>
            <div class="doc-info-value">${opt.dataset.days}</div>
        </div>
        <div class="doc-info-row">
            <div class="doc-info-label"><i class="fas fa-clock"></i> Consultation Hours:</div>
            <div class="doc-info-value">${fmtTime(opt.dataset.start)} – ${fmtTime(opt.dataset.end)}</div>
        </div>
    `;
}

function getAvailableSlots() {
    const doctorId  = document.getElementById('doctorSelect').value;
    const date      = document.getElementById('appointmentDate').value;
    const container = document.getElementById('timeSlots');

    if (!doctorId || !date) {
        container.innerHTML = '<p style="color:#aaa; font-size:.85rem;">Please select a doctor and date first.</p>';
        return;
    }

    container.innerHTML = '<p style="color:#888; font-size:.85rem;"><i class="fas fa-spinner fa-spin"></i> Loading slots...</p>';

    fetch(`get_slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`)
        .then(r => r.json())
        .then(slots => {
            if (!slots.length) {
                container.innerHTML = '<p style="color:#aaa; font-size:.85rem;">No slots available for this date.</p>';
                return;
            }
            const grid = document.createElement('div');
            grid.className = 'time-slots-grid';
            slots.forEach(slot => {
                const div = document.createElement('div');
                div.className = 'time-slot' + (slot.available ? '' : ' booked');
                div.textContent = slot.time;
                if (slot.available) {
                    div.onclick = function () {
                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('selected_time').value = slot.time;
                    };
                }
                grid.appendChild(div);
            });
            container.innerHTML = '';
            container.appendChild(grid);
        })
        .catch(() => {
            container.innerHTML = '<p style="color:#e74c3c; font-size:.85rem;">Failed to load slots. Please try again.</p>';
        });
}

window.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('doctorSelect').value) {
        updateDoctorInfo();
        if (document.getElementById('appointmentDate').value) {
            getAvailableSlots();
        }
    }
});
</script>
</body>
</html>