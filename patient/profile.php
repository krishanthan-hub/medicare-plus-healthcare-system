<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name   = trim(mysqli_real_escape_string($conn, $_POST['full_name'] ?? ''));
    $phone       = trim(mysqli_real_escape_string($conn, $_POST['phone'] ?? ''));
    $gender      = trim(mysqli_real_escape_string($conn, $_POST['gender'] ?? ''));
    $blood_group = trim(mysqli_real_escape_string($conn, $_POST['blood_group'] ?? ''));
    $dob         = trim($_POST['dob'] ?? '');
    $address     = trim(mysqli_real_escape_string($conn, $_POST['address'] ?? ''));

    // Update users table
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $full_name, $phone, $patient_id);
    $stmt->execute();

    // Update or insert patient_profiles
    $check = $conn->prepare("SELECT patient_id FROM patient_profiles WHERE patient_id = ?");
    $check->bind_param("i", $patient_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmt2 = $conn->prepare("UPDATE patient_profiles SET gender=?, blood_group=?, date_of_birth=?, address=? WHERE patient_id=?");
        $stmt2->bind_param("ssssi", $gender, $blood_group, $dob, $address, $patient_id);
    } else {
        $stmt2 = $conn->prepare("INSERT INTO patient_profiles (patient_id, gender, blood_group, date_of_birth, address) VALUES (?,?,?,?,?)");
        $stmt2->bind_param("issss", $patient_id, $gender, $blood_group, $dob, $address);
    }

    if ($stmt2->execute()) {
        $_SESSION['user_name'] = $full_name;
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile. " . $conn->error;
    }
}

// Fetch current data
$user = $conn->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
$user->bind_param("i", $patient_id);
$user->execute();
$user_data = $user->get_result()->fetch_assoc();

$profile = $conn->prepare("SELECT * FROM patient_profiles WHERE patient_id = ?");
$profile->bind_param("i", $patient_id);
$profile->execute();
$profile_data = $profile->get_result()->fetch_assoc();

// Stats
$total_appointments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE patient_id = $patient_id"))['count'];
$completed          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM appointments WHERE patient_id = $patient_id AND status = 'completed'"))['count'];
$reviews_given      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM reviews WHERE patient_id = $patient_id"))['count'];

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];
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

        .sidebar {
            width: 260px; background: #1778F2; min-height: 100vh;
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .sidebar-brand { padding: 1.5rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .sidebar-brand h2 { color: white; font-size: 1.7rem; font-weight: 800; }
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

        /* Profile header – outlined white box, fully aligned */
        .profile-header {
            background: white;
            border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            padding: 1.5rem 2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            border-radius: 0px; 
        }
        .profile-big-avatar {
            width: 60px; height: 60px;
            background: #1778F2;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 1.5rem;
            flex-shrink: 0;
        }
        .profile-header-info h1 { color: #1e3c4f; font-size: 1.2rem; font-weight: 700; }
        .profile-header-info p  { color: #888; font-size: .82rem; margin-top: .2rem; }
        .profile-stats {
            display: flex;
            gap: 0;
            margin-left: auto;
            border: 1px solid #e4e6ea;
            border-radius: 0px; 
        }
        .profile-stat {
            text-align: center;
            padding: .75rem 2rem;
            border-right: 1px solid #e4e6ea;
        }
        .profile-stat:last-child { border-right: none; }
        .profile-stat .val { color: #1778F2; font-size: 1.4rem; font-weight: 800; }
        .profile-stat .lbl { color: #888; font-size: .68rem; text-transform: uppercase; letter-spacing: .5px; margin-top: .15rem; }

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

        .card { background: white; border: 1px solid #e4e6ea; box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 1.5rem; border-radius: 0px;}
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        .alert { padding: .85rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; font-weight: 500; border-left: 4px solid; border-radius: 8px;}
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

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
        .form-group select {
            width: 100%; padding: .7rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; color: #333;
            transition: border-color .2s; font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus { border-color: #1778F2; }
        .form-group input[readonly] { background: #f8f9fb; color: #888; cursor: not-allowed; }

        .section-title {
            font-size: .78rem; font-weight: 700; color: #888;
            text-transform: uppercase; letter-spacing: .5px;
            margin: 1.5rem 0 1rem; padding-bottom: .5rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .btn-save {
            padding: .75rem 2rem; background: #1778F2; color: white; border-radius: 6px;
            border: none; font-size: .9rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
        }
        .btn-save:hover { background: #1060c9; }

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

<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">My Profile</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?></div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">

        <!-- Profile Header – outlined white box -->
        <div class="profile-header">
            <div class="profile-big-avatar">
                <?= strtoupper(substr($user_data['full_name'], 0, 1)) ?>
            </div>
            <div class="profile-header-info">
                <h1><?= htmlspecialchars($user_data['full_name']) ?></h1>
                <p><?= htmlspecialchars($user_data['email']) ?></p>
                <p><?= htmlspecialchars($user_data['phone'] ?? 'No phone added') ?></p>
            </div>
            <div class="profile-stats">
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
                <div class="card-header"><h2>Personal Information</h2></div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['full_name']) ?></div>
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
                            <div class="info-label">Gender</div>
                            <div class="info-value <?= empty($profile_data['gender']) ? 'empty' : '' ?>">
                                <?= !empty($profile_data['gender']) ? ucfirst(htmlspecialchars($profile_data['gender'])) : 'Not provided' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Blood Group</div>
                            <div class="info-value <?= empty($profile_data['blood_group']) ? 'empty' : '' ?>">
                                <?= !empty($profile_data['blood_group']) ? htmlspecialchars($profile_data['blood_group']) : 'Not provided' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value <?= empty($profile_data['date_of_birth']) ? 'empty' : '' ?>">
                                <?= !empty($profile_data['date_of_birth']) ? date('F j, Y', strtotime($profile_data['date_of_birth'])) : 'Not provided' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Address</div>
                            <div class="info-value <?= empty($profile_data['address']) ? 'empty' : '' ?>">
                                <?= !empty($profile_data['address']) ? htmlspecialchars($profile_data['address']) : 'Not provided' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Tab -->
        <div class="tab-content" id="tab-edit">
            <div class="card">
                <div class="card-header"><h2>Edit Profile</h2></div>
                <div class="card-body">
                    <form method="POST">
                        <p class="section-title">Basic Information</p>
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
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="dob"
                                       value="<?= htmlspecialchars($profile_data['date_of_birth'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="">-- Select --</option>
                                    <option value="male"   <?= ($profile_data['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($profile_data['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other"  <?= ($profile_data['gender'] ?? '') === 'other'  ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Blood Group</label>
                                <select name="blood_group">
                                    <option value="">-- Select --</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= ($profile_data['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:1.25rem;">
                            <label>Address</label>
                            <input type="text" name="address"
                                   value="<?= htmlspecialchars($profile_data['address'] ?? '') ?>"
                                   placeholder="Your home address">
                        </div>

                        <button type="submit" name="update_profile" class="btn-save">
                             Save Changes
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