<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name      = trim(mysqli_real_escape_string($conn, $_POST['full_name'] ?? ''));
    $phone          = trim(mysqli_real_escape_string($conn, $_POST['phone'] ?? ''));
    $specialization = trim(mysqli_real_escape_string($conn, $_POST['specialization'] ?? ''));
    $qualification  = trim(mysqli_real_escape_string($conn, $_POST['qualification'] ?? ''));
    $experience     = (int)($_POST['experience_years'] ?? 0);
    $fee            = (float)($_POST['consultation_fee'] ?? 0);
    $about          = trim(mysqli_real_escape_string($conn, $_POST['about'] ?? ''));

    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=? WHERE user_id=?");
    $stmt->bind_param("ssi", $full_name, $phone, $doctor_id);
    $stmt->execute();

    $stmt2 = $conn->prepare("UPDATE doctor_profiles SET specialization=?, qualification=?, experience_years=?, consultation_fee=?, about=? WHERE doctor_id=?");
    $stmt2->bind_param("ssidsi", $specialization, $qualification, $experience, $fee, $about, $doctor_id);

    if ($stmt2->execute()) {
        $_SESSION['user_name'] = $full_name;
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile.";
    }
}

// Fetch data
$user = $conn->prepare("SELECT full_name, email, phone FROM users WHERE user_id=?");
$user->bind_param("i", $doctor_id);
$user->execute();
$user_data = $user->get_result()->fetch_assoc();

$profile = $conn->prepare("SELECT * FROM doctor_profiles WHERE doctor_id=?");
$profile->bind_param("i", $doctor_id);
$profile->execute();
$profile_data = $profile->get_result()->fetch_assoc();

// Stats
$total_patients     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = $doctor_id"))['count'];
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = $doctor_id"))['count'];
$completed          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = $doctor_id AND status = 'completed'"))['count'];

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $doctor_id AND is_read = 0"))['count'];
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – MediCare Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px; background: #1778F2; min-height: 100vh;
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h2 { color: white; font-size: 1.7rem; font-weight: 800; }
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
            text-transform: uppercase; transition: background .2s;
        }
        .sidebar-footer a:hover { background: rgba(255,255,255,0.28); }

        /* Main */
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

        /* Profile Header - Clean White Box */
        .profile-header {
            background: white;
            border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            padding: 1.5rem 2rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;
        }
        .profile-big-avatar {
            width: 60px; height: 60px; background: #1778F2;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 1.5rem; flex-shrink: 0;
        }
        .profile-header-info h1 { color: #1e3c4f; font-size: 1.2rem; font-weight: 700; }
        .profile-header-info p  { color: #888; font-size: .82rem; margin-top: .2rem; }
        .profile-stats {
            margin-left: auto; display: flex; gap: 0;
            border: 1px solid #e4e6ea; overflow: hidden;
        }
        .profile-stat { text-align: center; padding: .75rem 2rem; border-right: 1px solid #e4e6ea; }
        .profile-stat:last-child { border-right: none; }
        .profile-stat .val { color: #1778F2; font-size: 1.4rem; font-weight: 800; }
        .profile-stat .lbl { color: #888; font-size: .68rem; text-transform: uppercase; letter-spacing: .5px; margin-top: .15rem; }

        /* Tabs */
        .tab-nav { display: flex; margin-bottom: 1.5rem; border-bottom: 2px solid #e4e6ea; }
        .tab-nav button {
            padding: .75rem 1.5rem; background: none; border: none;
            font-size: .88rem; font-weight: 600; color: #888;
            cursor: pointer; border-bottom: 2px solid transparent;
            margin-bottom: -2px; transition: all .2s;
        }
        .tab-nav button:hover { color: #1778F2; }
        .tab-nav button.active { color: #1778F2; border-bottom-color: #1778F2; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Cards */
        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem; }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        /* Alerts */
        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; display: flex; align-items: center; gap: .5rem; }
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem; margin-bottom: 1.25rem;
        }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block; font-size: .78rem; font-weight: 700;
            color: #1e3c4f; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%; padding: .7rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; color: #333;
            transition: border-color .2s; font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: #1778F2; }
        .form-group input[readonly] { background: #f8f9fb; color: #888; cursor: not-allowed; }
        .form-group textarea { resize: vertical; min-height: 100px; }

        .section-title {
            font-size: .78rem; font-weight: 700; color: #888;
            text-transform: uppercase; letter-spacing: .5px;
            margin: 1.5rem 0 1rem; padding-bottom: .5rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .btn-save {
            padding: .75rem 2rem; background: #1778F2; color: white;
            border: none; font-size: .9rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: #1060c9; }

        /* Info display */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .info-item { padding: .75rem 0; border-bottom: 1px solid #f4f4f4; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-size: .72rem; color: #888; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-bottom: .2rem; }
        .info-value { font-size: .92rem; color: #1e3c4f; font-weight: 600; }
        .info-value.empty { color: #ccc; font-style: italic; font-weight: 400; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
    </div>
    <div class="sidebar-doctor">
        <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
        <div>
            <div class="sidebar-doctor-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></div>
            <div class="sidebar-doctor-role"><?= htmlspecialchars($profile_data['specialization'] ?? 'Doctor') ?></div>
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

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">My Profile</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
            <span class="topbar-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-big-avatar">
                <?= strtoupper(substr($user_data['full_name'], 0, 1)) ?>
            </div>
            <div class="profile-header-info">
                <h1>Dr. <?= htmlspecialchars($user_data['full_name']) ?></h1>
                <p><?= htmlspecialchars($profile_data['specialization'] ?? '') ?></p>
                <p><?= htmlspecialchars($profile_data['qualification'] ?? '') ?></p>
            </div>
            <div class="profile-stats">
                <div class="profile-stat">
                    <div class="val"><?= $total_patients ?></div>
                    <div class="lbl">Patients</div>
                </div>
                <div class="profile-stat">
                    <div class="val"><?= $total_appointments ?></div>
                    <div class="lbl">Appointments</div>
                </div>
                <div class="profile-stat">
                    <div class="val"><?= $completed ?></div>
                    <div class="lbl">Completed</div>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tab-nav">
            <button class="active" onclick="switchTab('overview', this)">Overview</button>
            <button onclick="switchTab('edit', this)">Edit Profile</button>
        </div>

        <!-- Overview Tab -->
        <div class="tab-content active" id="tab-overview">
            <div class="card">
                <div class="card-header"><h2>Professional Information</h2></div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value">Dr. <?= htmlspecialchars($user_data['full_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['email']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value <?= empty($user_data['phone']) ? 'empty' : '' ?>">
                                <?= !empty($user_data['phone']) ? htmlspecialchars($user_data['phone']) : 'Not provided' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Specialization</div>
                            <div class="info-value"><?= htmlspecialchars($profile_data['specialization'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Qualification</div>
                            <div class="info-value <?= empty($profile_data['qualification']) ? 'empty' : '' ?>">
                                <?= !empty($profile_data['qualification']) ? htmlspecialchars($profile_data['qualification']) : 'Not provided' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Experience</div>
                            <div class="info-value"><?= (int)($profile_data['experience_years'] ?? 0) ?> Years</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Consultation Fee</div>
                            <div class="info-value">LKR <?= number_format((float)($profile_data['consultation_fee'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <?php if (!empty($profile_data['about'])): ?>
                    <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid #f4f4f4;">
                        <div class="info-label" style="margin-bottom:.5rem;">About</div>
                        <p style="font-size:.9rem; color:#555; line-height:1.7;"><?= nl2br(htmlspecialchars($profile_data['about'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Profile Tab -->
        <div class="tab-content" id="tab-edit">
            <div class="card">
                <div class="card-header"><h2>Edit Profile</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <p class="section-title">Personal Information</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" required
                                       value="<?= htmlspecialchars($user_data['full_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Email (cannot change)</label>
                                <input type="email" value="<?= htmlspecialchars($user_data['email']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone"
                                       value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>">
                            </div>
                        </div>

                        <p class="section-title">Professional Information</p>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Specialization</label>
                                <input type="text" name="specialization"
                                       value="<?= htmlspecialchars($profile_data['specialization'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Qualification</label>
                                <input type="text" name="qualification"
                                       value="<?= htmlspecialchars($profile_data['qualification'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Experience (Years)</label>
                                <input type="number" name="experience_years" min="0"
                                       value="<?= (int)($profile_data['experience_years'] ?? 0) ?>">
                            </div>
                            <div class="form-group">
                                <label>Consultation Fee (LKR)</label>
                                <input type="number" name="consultation_fee" min="0" step="0.01"
                                       value="<?= number_format((float)($profile_data['consultation_fee'] ?? 0), 2, '.', '') ?>">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:1.5rem;">
                            <label>About</label>
                            <textarea name="about"
                                      placeholder="Write a brief description about yourself..."><?= htmlspecialchars($profile_data['about'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-nav button').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
}
<?php if (isset($success) || isset($error)): ?>
switchTab('edit', document.querySelectorAll('.tab-nav button')[1]);
<?php endif; ?>
</script>
</body>
</html>