<?php
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    redirect('../' . $_SESSION['user_type'] . '/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validateCSRF();
    $email    = validate($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';
    $role     = validate($_POST['role']     ?? 'patient');
    $conn = getDB();
    $stmt = $conn->prepare("SELECT user_id, full_name, user_type, password FROM users WHERE email = ? AND user_type = ?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($password, $row['password'])) {
    $_SESSION['user_id']   = $row['user_id'];
    $_SESSION['user_name'] = $row['full_name'];
    $_SESSION['user_type'] = $row['user_type'];
    $upd = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $upd->bind_param("i", $row['user_id']);
    $upd->execute();
    $success = "Login Successful!";
    $redirect_url = '../' . $row['user_type'] . '/dashboard.php';
    } else {
        $error = "Invalid email or password.";
        $selected_role = $role;
    }
}

$selected_role = $selected_role ?? 'patient';

include '../includes/header.php';
include '../includes/navigation.php';
?>

<style>
    .login-section {
        background: #f4f6f9;
        min-height: calc(100vh - 140px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem;
    }

    .login-box {
        background: white;
        border-radius: 16px;
        padding: 2.5rem 2rem;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.10);
    }

    .login-box h1 {
        font-size: 1.6rem;
        font-weight: 800;
        color: #1e3c4f;
        margin-bottom: 1.75rem;
        text-align: center;
    }

    .form-group { margin-bottom: 1.25rem; }

    .form-group label {
        display: block;
        font-size: .88rem;
        font-weight: 600;
        color: #333;
        margin-bottom: .5rem;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: .75rem 1rem;
        border: 1.5px solid #e0e0e0;
        border-radius: 8px;
        font-size: .95rem;
        color: #333;
        background: white;
        transition: border-color .3s;
        font-family: inherit;
        appearance: none;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #dee2e6;
        box-shadow: none;
    }

    .btn-login {
        width: 100%;
        padding: .85rem;
        background: #1778F2;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: background .3s, transform .2s;
        margin-top: .5rem;
        margin-bottom: 1.25rem;
    }

    .btn-login:hover { background: #1060c9; transform: translateY(-1px); }

    .login-links {
        text-align: center;
        font-size: .88rem;
        color: #6c757d;
    }

    .login-links a {
        color: #1778F2;
        text-decoration: none;
        display: block;
        margin-top: .5rem;
        font-weight: 500;
    }

    .login-links a:hover { text-decoration: underline; }

    .alert {
        padding: .75rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: .88rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .alert-error   { background: #fde8e8; color: #c0392b; border: 1px solid #f5c6cb; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
</style>

<section class="login-section">
    <div class="login-box">
        <h1>Login to MediCare Plus</h1>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php flash(); ?>
<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "<?php echo $redirect_url; ?>";
        }, 1500);
    </script>
<?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">

            <div class="form-group">
                <label>Login As</label>
                <select name="role">
                    <option value="patient" <?php echo $selected_role == 'patient' ? 'selected' : ''; ?>>Patient</option>
                    <option value="doctor"  <?php echo $selected_role == 'doctor'  ? 'selected' : ''; ?>>Doctor</option>
                </select>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="login-links">
            Don't have an account?
            <a href="register.php">Register here</a>
            <a href="#">Forgot your password?</a>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
