<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../admin/login.php");
    exit();
}

// ADD DOCTOR
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['add_doctor'])) {
        $name           = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $email          = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $password       = $_POST['password'] ?? '';
        $phone          = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $specialization = mysqli_real_escape_string($conn, $_POST['specialization'] ?? '');
        $qualification  = mysqli_real_escape_string($conn, $_POST['qualification'] ?? '');
        $experience     = (int)($_POST['experience'] ?? 0);
        $fee            = (float)($_POST['consultation_fee'] ?? 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters.";
        } else {
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Email already exists!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, user_type) VALUES (?, ?, ?, ?, 'doctor')");
                    $stmt->bind_param("ssss", $name, $email, $hashed, $phone);
                    $stmt->execute();
                    $doctor_id = $conn->insert_id;

                    $stmt2 = $conn->prepare("INSERT INTO doctor_profiles (doctor_id, specialization, qualification, experience_years, consultation_fee) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->bind_param("issid", $doctor_id, $specialization, $qualification, $experience, $fee);
                    $stmt2->execute();

                    $conn->commit();
                    $success = "Doctor added successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error adding doctor: " . $e->getMessage();
                }
            }
        }
    }

    if (isset($_POST['delete_doctor'])) {
        $del_id = (int)$_POST['doctor_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND user_type = 'doctor'");
        $stmt->bind_param("i", $del_id);
        $stmt->execute() ? $success = "Doctor removed." : $error = "Failed to remove doctor.";
    }

    if (isset($_POST['edit_doctor'])) {
        $edit_id        = (int)$_POST['doctor_id'];
        $name           = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $phone          = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $specialization = mysqli_real_escape_string($conn, $_POST['specialization'] ?? '');
        $qualification  = mysqli_real_escape_string($conn, $_POST['qualification'] ?? '');
        $experience     = (int)($_POST['experience'] ?? 0);
        $fee            = (float)($_POST['consultation_fee'] ?? 0);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=? WHERE user_id=?");
            $stmt->bind_param("ssi", $name, $phone, $edit_id);
            $stmt->execute();

            $stmt2 = $conn->prepare("UPDATE doctor_profiles SET specialization=?, qualification=?, experience_years=?, consultation_fee=? WHERE doctor_id=?");
            $stmt2->bind_param("ssidi", $specialization, $qualification, $experience, $fee, $edit_id);
            $stmt2->execute();

            $conn->commit();
            $success = "Doctor updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$doctors = mysqli_query($conn, "
    SELECT u.user_id, u.full_name, u.email, u.phone,
           d.specialization, d.qualification, d.experience_years, d.consultation_fee
    FROM users u
    JOIN doctor_profiles d ON u.user_id = d.doctor_id
    WHERE u.user_type = 'doctor'
    ORDER BY u.full_name ASC
");

$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors – MediCare Plus</title>
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
        .sidebar-brand h2 { color: white; font-size: 1.4rem; font-weight: 800; line-height: 1.4; }
        .sidebar-brand .badge-portal {
            display: inline-block; background: rgba(255,255,255,0.2);
            color: white; font-size: .68rem; font-weight: 600;
            padding: .15rem .5rem; margin-top: .35rem;
            letter-spacing: .5px; text-transform: uppercase;
        }
        .sidebar-menu { flex: 1; padding: 1rem 0; }
        .menu-label {
            color: rgba(255,255,255,0.5); font-size: .65rem; font-weight: 700;
            letter-spacing: 1.2px; text-transform: uppercase;
            padding: .85rem 1.25rem .3rem;
        }
        .sidebar-menu a {
            display: block; padding: .7rem 1.25rem;
            color: rgba(255,255,255,0.85); text-decoration: none;
            font-size: .82rem; font-weight: 500;
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

        /* ── Main ── */
        .main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

        /* ── Topbar ── */
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

        /* ── Content ── */
        .content { padding: 2rem; flex: 1; }

        /* ── Alerts ── */
        .alert {
            padding: .85rem 1.25rem; margin-bottom: 1.5rem;
            font-size: .9rem; font-weight: 500; border-left: 4px solid;
        }
        .alert-success { background: #d1e7dd; color: #0a3622; border-color: #0a3622; }
        .alert-error   { background: #f8d7da; color: #58151c; border-color: #58151c; }

        /* ── Form Card ── */
        .card {
            background: white; border: 1px solid #e4e6ea;
            box-shadow: 0 1px 4px rgba(0,0,0,.05); margin-bottom: 2rem;
        }
        .card-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .card-header h2 { font-size: 1rem; font-weight: 700; color: #1e3c4f; }
        .card-body { padding: 1.5rem; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block; font-size: .8rem; font-weight: 700;
            color: #1e3c4f; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .form-group input {
            width: 100%; padding: .65rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; color: #333;
            transition: border-color .2s;
        }
        .form-group input:focus { border-color: #1778F2; }

        .btn-add {
            padding: .7rem 2rem; background: #1778F2; color: white;
            border: none; font-size: .9rem; font-weight: 700;
            cursor: pointer; letter-spacing: .3px; transition: background .2s;
        }
        .btn-add:hover { background: #1060c9; }

        /* ── Table ── */
        .table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .table th {
            background: #f8f9fb; padding: .75rem 1.5rem; text-align: left;
            font-size: .75rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #555; border-bottom: 1px solid #e4e6ea;
        }
        .table td {
            padding: .8rem 1.5rem; border-bottom: 1px solid #f4f4f4; color: #444;
        }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #fafbfc; }

        .btn-delete {
            padding: .3rem .8rem; background: #e74c3c; color: white;
            border: none; font-size: .78rem; font-weight: 600; cursor: pointer;
            transition: background .2s;
        }
        .btn-delete:hover { background: #c0392b; }

        /* ── Footer ── */
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

<!-- ── Main ── -->
<div class="main">

    <div class="topbar">
        <div class="topbar-left">
            Pages / <span class="page">Manage Doctors</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            <a href="../pages/logout.php" class="topbar-logout">Logout</a>
        </div>
    </div>

    <div class="content">

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Add Doctor Form -->
        <div class="card">
            <div class="card-header">
                <h2>Add New Doctor</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password (min. 8 chars)</label>
                            <input type="password" name="password" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label>Specialization</label>
                            <input type="text" name="specialization" required>
                        </div>
                        <div class="form-group">
                            <label>Qualification</label>
                            <input type="text" name="qualification" required>
                        </div>
                        <div class="form-group">
                            <label>Experience (years)</label>
                            <input type="number" name="experience" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Consultation Fee (LKR)</label>
                            <input type="number" name="consultation_fee" min="0" step="0.01" required>
                        </div>
                    </div>
                    <button type="submit" name="add_doctor" class="btn-add">+ Add Doctor</button>
                </form>
            </div>
        </div>

        <!-- Doctors Table -->
        <div class="card">
            <div class="card-header">
                <h2>All Doctors</h2>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialization</th>
                        <th>Experience</th>
                        <th>Fee (LKR)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($doc = mysqli_fetch_assoc($doctors)): ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['full_name']) ?></td>
                        <td><?= htmlspecialchars($doc['email']) ?></td>
                        <td><?= htmlspecialchars($doc['phone']) ?></td>
                        <td><?= htmlspecialchars($doc['specialization']) ?></td>
                        <td><?= (int)$doc['experience_years'] ?> yrs</td>
                        <td><?= number_format((float)$doc['consultation_fee'], 2) ?></td>
                        <td>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Delete this doctor?');">
                                <input type="hidden" name="doctor_id" value="<?= (int)$doc['user_id'] ?>">
                                <button type="submit" name="delete_doctor" class="btn-delete">Delete</button>
                            </form>
                        </td>
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