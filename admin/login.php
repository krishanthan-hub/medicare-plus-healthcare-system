<?php
session_start();
include '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT user_id, full_name, password, user_type FROM users WHERE email = ? AND user_type = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && password_verify($password, $result['password'])) {
        $_SESSION['user_id']   = $result['user_id'];
        $_SESSION['user_name'] = $result['full_name'];
        $_SESSION['user_type'] = $result['user_type'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login – MediCare Plus</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-box {
            background: white;
            padding: 2.5rem 2rem;
            width: 100%; max-width: 420px;
            border: 1px solid #dde1e7;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            text-align: center;
            border-top: none;
        }
        .login-box h2 { color: #1e3c4f; margin-bottom: .25rem; font-size: 1.5rem; }
        .login-box p  { color: #6c757d; font-size: .875rem; margin-bottom: 1.75rem; }
        label  { font-size: .85rem; font-weight: 600; color: #1e3c4f; display: block; margin-bottom: .35rem; }
        input  {
            width: 100%; padding: .65rem .9rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .95rem; margin-bottom: 1.2rem;
        }
        input:focus { border-color: #1778F2; }
        .btn {
            width: 100%; padding: .75rem;
            background: #1778F2; color: white;
            border: none; font-size: 1rem;
            font-weight: 600; cursor: pointer;
            letter-spacing: .3px;
        }
        .btn:hover { background: #1060c9; }
        .error {
            background: #fff0f0; border-left: 4px solid #e74c3c;
            padding: .65rem .9rem; font-size: .85rem;
            color: #c0392b; margin-bottom: 1.2rem;
            text-align: left;
        }
        .badge {
            display: inline-block; background: #e8f1fd;
            color: #1778F2; font-size: .75rem; font-weight: 700;
            padding: .2rem .6rem; margin-bottom: 1rem;
            letter-spacing: .5px; text-transform: uppercase;
        }

        /* ← Only this was added */
        .login-box form {
            text-align: left;
        }
    </style>
</head>
<body>
<div class="login-box">
    <span class="badge">Admin Portal</span>
    <h2>Administrator Login</h2>
    <p>Restricted access MediCare Plus staff only.</p>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Email Address</label>
        <input type="email" name="email">

        <label>Password</label>
        <input type="password" name="password">

        <button type="submit" class="btn">Sign In to Dashboard</button>
    </form>
</div>
</body>
</html>