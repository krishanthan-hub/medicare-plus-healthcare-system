<?php
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    redirect('../' . $_SESSION['user_type'] . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validateCSRF();
    $full_name = validate($_POST['full_name'] ?? '');
    $email     = validate($_POST['email']     ?? '');
    $phone     = validate($_POST['phone']     ?? '');
    $password  = $_POST['password']           ?? '';
    $confirm   = $_POST['confirm_password']   ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        $conn = getDB();
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, user_type) VALUES (?, ?, ?, ?, 'patient')");
                $stmt->bind_param("ssss", $full_name, $email, $hashed, $phone);
                $stmt->execute();
                $patient_id = $conn->insert_id;
                $profile = $conn->prepare("INSERT INTO patient_profiles (patient_id) VALUES (?)");
                $profile->bind_param("i", $patient_id);
                $profile->execute();
                $conn->commit();
                // Set session directly and redirect
                $_SESSION['flash_message'] = 'Registration Successful! Now you can Log in.';
                $_SESSION['flash_type']    = 'success';
                header("Location: login.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
include '../includes/navigation.php';
?>

<style>
    .register-section {
        background: #f4f6f9;
        min-height: calc(100vh - 140px);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem;
    }

    .auth-card {
        background: white;
        border-radius: 16px;
        padding: 2.5rem 2rem;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .auth-card h1 {
        font-size: 1.6rem;
        font-weight: 800;
        color: #1e3c4f;
        text-align: center;
        margin-bottom: 1.75rem;
    }

    .form-group { margin-bottom: 1.25rem; }
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #1e3c4f;
        font-size: 0.9rem;
    }

    .form-group input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1.5px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.95rem;
        background: white;
        transition: border-color 0.3s;
        color: #333;
        font-family: inherit;
    }
    .form-group input:focus {
        outline: none;
        border-color: #dee2e6;
        box-shadow: none;
    }

    .btn-register {
        width: 100%;
        padding: 0.9rem;
        background: #1778F2;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s;
        margin-top: 0.5rem;
    }
    .btn-register:hover { background: #1060c9; transform: translateY(-1px); }

    .login-link {
        text-align: center;
        margin-top: 1.25rem;
        font-size: .88rem;
        color: #6c757d;
    }
    .login-link a {
        color: #1778F2;
        font-weight: 600;
        text-decoration: none;
    }
    .login-link a:hover { text-decoration: underline; }

    .alert { padding: 0.85rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
    .alert-error   { background: #fde8e8; color: #c0392b; border: 1px solid #f5c6cb; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .password-hint { font-size: 0.8rem; color: #adb5bd; margin-top: 0.3rem; display: block; }
</style>

<section class="register-section">
    <div class="auth-card">

        <h1>Welcome to MediCare Plus</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" required
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" required
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" required minlength="8">
                <small class="password-hint">Must be at least 8 characters</small>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit" class="btn-register">
                Create Account <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>

    </div>
</section>

<?php include '../includes/footer.php'; ?>