<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $doctor_id      = (int)$_POST['doctor_id'];
    $appointment_id = (int)$_POST['appointment_id'];
    $rating         = (int)$_POST['rating'];
    $review_text    = trim(mysqli_real_escape_string($conn, $_POST['review_text'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5.";
    } elseif (empty($review_text)) {
        $error = "Please write a review.";
    } else {
        // Check if already reviewed
        $check = $conn->prepare("SELECT review_id FROM reviews WHERE patient_id = ? AND appointment_id = ?");
        $check->bind_param("ii", $patient_id, $appointment_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "You have already reviewed this appointment.";
        } else {
            $stmt = $conn->prepare("INSERT INTO reviews (doctor_id, patient_id, appointment_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $doctor_id, $patient_id, $appointment_id, $rating, $review_text);
            if ($stmt->execute()) {
                $success = "Your review has been submitted successfully!";
            } else {
                $error = "Failed to submit review. Please try again.";
            }
        }
    }
}

// Get completed appointments that haven't been reviewed yet
$completed_apts = $conn->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time,
           u.full_name AS doctor_name, u.user_id AS doctor_id,
           d.specialization, d.profile_image,
           (SELECT review_id FROM reviews WHERE patient_id = ? AND appointment_id = a.appointment_id) AS reviewed
    FROM appointments a
    JOIN users u ON a.doctor_id = u.user_id
    JOIN doctor_profiles d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
");
$completed_apts->bind_param("ii", $patient_id, $patient_id);
$completed_apts->execute();
$appointments = $completed_apts->get_result()->fetch_all(MYSQLI_ASSOC);

// Get patient's existing reviews
$my_reviews = $conn->prepare("
    SELECT r.*, u.full_name AS doctor_name, d.specialization,
           a.appointment_date
    FROM reviews r
    JOIN users u ON r.doctor_id = u.user_id
    JOIN doctor_profiles d ON r.doctor_id = d.doctor_id
    LEFT JOIN appointments a ON r.appointment_id = a.appointment_id
    WHERE r.patient_id = ?
    ORDER BY r.created_at DESC
");
$my_reviews->bind_param("i", $patient_id);
$my_reviews->execute();
$reviews = $my_reviews->get_result()->fetch_all(MYSQLI_ASSOC);

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate a Doctor – MediCare Plus</title>
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

        /* ── Alerts ── */
        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; }
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

        /* ── Cards ── */
        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem; border-radius: 0px;}
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        /* ── Appointment cards to review ── */
        .apt-review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }
        .apt-review-card {
            border: 1px solid #e4e6ea; padding: 1.25rem;
            background: white; position: relative; border-radius: 0px;
        }
        .apt-review-card.reviewed { opacity: .7; }
        .apt-review-card-header { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
        .apt-doc-avatar {
            width: 46px; height: 46px; background: #e8f1fd;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #1778F2; font-weight: 800; font-size: 1.1rem; flex-shrink: 0;
        }
        .apt-doc-name { font-size: .92rem; font-weight: 700; color: #1e3c4f; }
        .apt-doc-spec { font-size: .78rem; color: #888; }
        .apt-date { font-size: .78rem; color: #aaa; margin-bottom: 1rem; }
        .apt-date i { color: #1778F2; margin-right: .3rem; }

        .reviewed-badge {
            display: inline-flex; align-items: center; gap: .3rem;
            background: #d1e7dd; color: #0a3622; font-size: .75rem;
            font-weight: 700; padding: .3rem .75rem;
        }

        .btn-rate {
            width: 100%; padding: .65rem; background: #1778F2; color: white; border-radius: 6px;
            border: none; font-size: .85rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-rate:hover { background: #1060c9; }

        /* ── Star Rating Input ── */
        .star-rating { display: flex; flex-direction: row-reverse; gap: .25rem; margin-bottom: 1rem; }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 2rem; color: #dee2e6; cursor: pointer;
            transition: color .15s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #f59e0b; }

        /* ── Modal ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1000;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white; width: 100%; max-width: 500px; border-radius: 0px;
            margin: 1rem; max-height: 90vh; overflow-y: auto;
        }
        .modal-header {
            padding: 1.25rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h3 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .modal-close {
            background: none; border: none; font-size: 1.2rem;
            color: #aaa; cursor: pointer; padding: .25rem;
        }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 1.5rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: .8rem; font-weight: 700;
            color: #1e3c4f; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .form-group textarea {
            width: 100%; padding: .7rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; resize: vertical; min-height: 100px;
            font-family: inherit; transition: border-color .2s;
        }
        .form-group textarea:focus { border-color: #1778F2; }
        .btn-submit {
            width: 100%; padding: .8rem; background: #1778F2; color: white; border-radius: 6px;
            border: none; font-size: .95rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-submit:hover { background: #1060c9; }

        /* ── My Reviews ── */
        .review-item {
            padding: 1.25rem; border: 1px solid #f0f0f0; margin-bottom: 1rem;
            background: #fafbfc; border-radius: 8px;
        }
        .review-item-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .5rem; }
        .review-doc-name { font-size: .92rem; font-weight: 700; color: #1e3c4f; }
        .review-date { font-size: .75rem; color: #aaa; }
        .review-spec { font-size: .78rem; color: #1778F2; margin-bottom: .5rem; }
        .review-stars i { font-size: .85rem; color: #f59e0b; }
        .review-stars i.empty { color: #dee2e6; }
        .review-text { font-size: .88rem; color: #555; margin-top: .5rem; line-height: 1.6; }

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
        <div class="topbar-left">Pages / <span class="page">Rate a Doctor</span></div>
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

        <!-- Completed Appointments to Review -->
        <div class="card">
            <div class="card-header">
                <h2>Rate Your Visits</h2>
                <span style="font-size:.8rem; color:#888;"><?= count($appointments) ?> completed visit(s)</span>
            </div>
            <div class="card-body">
                <?php if (count($appointments) > 0): ?>
                <div class="apt-review-grid">
                    <?php foreach ($appointments as $apt): ?>
                    <div class="apt-review-card <?= $apt['reviewed'] ? 'reviewed' : '' ?>">
                        <div class="apt-review-card-header">
                            <div class="apt-doc-avatar">
                                <?= strtoupper(substr($apt['doctor_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="apt-doc-name">Dr. <?= htmlspecialchars($apt['doctor_name']) ?></div>
                                <div class="apt-doc-spec"><?= htmlspecialchars($apt['specialization']) ?></div>
                            </div>
                        </div>
                        <p class="apt-date">
                            <i class="fas fa-calendar"></i>
                            <?= date('F j, Y', strtotime($apt['appointment_date'])) ?>
                            at <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                        </p>
                        <?php if ($apt['reviewed']): ?>
                            <span class="reviewed-badge">
                                <i class="fas fa-check"></i> Already Reviewed
                            </span>
                        <?php else: ?>
                            <button class="btn-rate"
                                onclick="openModal(
                                    <?= (int)$apt['doctor_id'] ?>,
                                    <?= (int)$apt['appointment_id'] ?>,
                                    'Dr. <?= addslashes(htmlspecialchars($apt['doctor_name'])) ?>',
                                    '<?= addslashes(htmlspecialchars($apt['specialization'])) ?>'
                                )">
                                <i class="fas fa-star"></i> Write a Review
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        No completed appointments yet.<br>
                        <small>You can rate a doctor after your appointment is completed.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Reviews -->
        <div class="card">
            <div class="card-header">
                <h2>My Reviews</h2>
                <span style="font-size:.8rem; color:#888;"><?= count($reviews) ?> review(s)</span>
            </div>
            <div class="card-body">
                <?php if (count($reviews) > 0): ?>
                    <?php foreach ($reviews as $rev): ?>
                    <div class="review-item">
                        <div class="review-item-header">
                            <div>
                                <div class="review-doc-name">Dr. <?= htmlspecialchars($rev['doctor_name']) ?></div>
                                <div class="review-spec"><?= htmlspecialchars($rev['specialization']) ?></div>
                            </div>
                            <span class="review-date"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                        </div>
                        <div class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i > $rev['rating'] ? 'empty' : '' ?>"></i>
                            <?php endfor; ?>
                            <span style="font-size:.8rem; color:#888; margin-left:.3rem;">(<?= $rev['rating'] ?>/5)</span>
                        </div>
                        <p class="review-text"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        No reviews yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<!-- ── Review Modal ── -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Write a Review</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:1rem; padding:.75rem; background:#f8f9fb; border:1px solid #e4e6ea;">
                <div style="font-weight:700; color:#1e3c4f;" id="modalDoctorName"></div>
                <div style="font-size:.8rem; color:#888;" id="modalDoctorSpec"></div>
            </div>
            <form method="POST">
                <input type="hidden" name="doctor_id" id="modalDoctorId">
                <input type="hidden" name="appointment_id" id="modalAppointmentId">

                <div class="form-group">
                    <label>Your Rating</label>
                    <div class="star-rating">
                        <input type="radio" name="rating" id="star5" value="5">
                        <label for="star5"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" id="star4" value="4">
                        <label for="star4"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" id="star3" value="3">
                        <label for="star3"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" id="star2" value="2">
                        <label for="star2"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" id="star1" value="1">
                        <label for="star1"><i class="fas fa-star"></i></label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Your Review</label>
                    <textarea name="review_text" required
                              placeholder="Share your experience with this doctor..."></textarea>
                </div>

                <button type="submit" name="submit_review" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Review
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(doctorId, appointmentId, doctorName, spec) {
    document.getElementById('modalDoctorId').value      = doctorId;
    document.getElementById('modalAppointmentId').value = appointmentId;
    document.getElementById('modalDoctorName').textContent = doctorName;
    document.getElementById('modalDoctorSpec').textContent = spec;
    document.getElementById('reviewModal').classList.add('open');
}
function closeModal() {
    document.getElementById('reviewModal').classList.remove('open');
}
// Close on overlay click
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>