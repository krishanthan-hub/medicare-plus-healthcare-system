<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'doctor') {
    header("Location: ../pages/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $message     = trim(mysqli_real_escape_string($conn, $_POST['message'] ?? ''));
    if (!empty($message) && $receiver_id > 0) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $doctor_id, $receiver_id, $message);
        $stmt->execute();
    }
    header("Location: messages.php?patient=" . $receiver_id);
    exit();
}

// Mark messages as read
$selected_patient = (int)($_GET['patient'] ?? 0);
if ($selected_patient > 0) {
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $selected_patient AND receiver_id = $doctor_id");
}

// Get all patients doctor has appointments with
$patients_stmt = $conn->prepare("
    SELECT DISTINCT u.user_id, u.full_name, u.phone,
           (SELECT COUNT(*) FROM messages
            WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = 0) AS unread
    FROM appointments a
    JOIN users u ON a.patient_id = u.user_id
    WHERE a.doctor_id = ?
    ORDER BY u.full_name ASC
");
$patients_stmt->bind_param("ii", $doctor_id, $doctor_id);
$patients_stmt->execute();
$patients = $patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get conversation
$conversation = [];
if ($selected_patient > 0) {
    $conv_stmt = $conn->prepare("
        SELECT m.*, u.full_name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $conv_stmt->bind_param("iiii", $doctor_id, $selected_patient, $selected_patient, $doctor_id);
    $conv_stmt->execute();
    $conversation = $conv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $pat_info = $conn->prepare("SELECT full_name, phone FROM users WHERE user_id = ?");
    $pat_info->bind_param("i", $selected_patient);
    $pat_info->execute();
    $selected_pat = $pat_info->get_result()->fetch_assoc();
}

$doc_info        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT specialization FROM doctor_profiles WHERE doctor_id = $doctor_id"));
$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $doctor_id AND is_read = 0"))['count'];
$current         = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages – MediCare Plus</title>
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

        /* Messaging Layout */
        .messaging-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 160px);
        }

        /* Patients Panel */
        .patients-panel {
            background: white; border: 1px solid #e4e6ea;
            display: flex; flex-direction: column; overflow: hidden;
        }
        .patients-panel-header {
            padding: 1rem 1.25rem; border-bottom: 1px solid #f0f0f0; background: #f8f9fb;
        }
        .patients-panel-header h3 { font-size: .9rem; font-weight: 700; color: #1e3c4f; }
        .patients-panel-header p  { font-size: .75rem; color: #888; margin-top: .15rem; }
        .patients-list { flex: 1; overflow-y: auto; }

        .patient-item {
            display: flex; align-items: center; gap: .75rem;
            padding: .9rem 1.25rem; border-bottom: 1px solid #f4f4f4;
            text-decoration: none; transition: background .15s;
        }
        .patient-item:hover { background: #f8f9fb; }
        .patient-item.active { background: #e8f1fd; border-left: 3px solid #1778F2; }
        .patient-avatar {
            width: 42px; height: 42px; background: #e8f1fd;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #1778F2; font-weight: 800; font-size: 1rem; flex-shrink: 0;
        }
        .patient-name  { font-size: .88rem; font-weight: 700; color: #1e3c4f; }
        .patient-phone { font-size: .75rem; color: #888; }
        .patient-unread {
            margin-left: auto; background: #e74c3c; color: white;
            font-size: .65rem; font-weight: 700; padding: .15rem .45rem;
            border-radius: 10px; flex-shrink: 0;
        }
        .no-patients { text-align: center; padding: 3rem 1.5rem; color: #aaa; font-size: .85rem; }
        .no-patients i { font-size: 2rem; display: block; margin-bottom: .5rem; color: #dee2e6; }

        /* Chat Panel */
        .chat-panel {
            background: white; border: 1px solid #e4e6ea;
            display: flex; flex-direction: column; overflow: hidden;
        }
        .chat-header {
            padding: 1rem 1.5rem; border-bottom: 1px solid #f0f0f0;
            background: #f8f9fb; display: flex; align-items: center; gap: .75rem;
        }
        .chat-header-avatar {
            width: 40px; height: 40px; background: #1778F2;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: .95rem; flex-shrink: 0;
        }
        .chat-header-name  { font-size: .95rem; font-weight: 700; color: #1e3c4f; }
        .chat-header-phone { font-size: .78rem; color: #888; }

        .chat-messages {
            flex: 1; overflow-y: auto; padding: 1.25rem;
            display: flex; flex-direction: column; gap: .75rem;
            background: #f8f9fb;
        }
        .msg-bubble {
            max-width: 70%; padding: .7rem 1rem;
            font-size: .88rem; line-height: 1.5;
        }
        .msg-bubble.sent {
            background: #1778F2; color: white;
            align-self: flex-end;
            border-radius: 16px 16px 4px 16px;
        }
        .msg-bubble.received {
            background: white; color: #333;
            align-self: flex-start;
            border-radius: 16px 16px 16px 4px;
            border: 1px solid #e4e6ea;
        }
        .msg-time { font-size: .68rem; margin-top: .3rem; opacity: .7; text-align: right; }
        .msg-bubble.received .msg-time { text-align: left; }

        .chat-input-area { padding: 1rem 1.25rem; border-top: 1px solid #e4e6ea; background: white; }
        .chat-input-form { display: flex; gap: .75rem; align-items: flex-end; }
        .chat-input-form textarea {
            flex: 1; padding: .7rem 1rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; resize: none; min-height: 44px; max-height: 120px;
            font-family: inherit; transition: border-color .2s;
        }
        .chat-input-form textarea:focus { border-color: #1778F2; }
        .btn-send {
            padding: .7rem 1.25rem; background: #1778F2; color: white;
            border: none; font-size: .88rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
            display: flex; align-items: center; gap: .4rem;
        }
        .btn-send:hover { background: #1060c9; }

        .no-chat-selected {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: #aaa; text-align: center; padding: 2rem;
        }
        .no-chat-selected i { font-size: 3rem; margin-bottom: 1rem; color: #dee2e6; }
        .no-chat-selected h3 { font-size: 1rem; color: #888; margin-bottom: .5rem; }

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
            <div class="sidebar-doctor-role"><?= htmlspecialchars($doc_info['specialization'] ?? 'Doctor') ?></div>
        </div>
    </div>
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="appointments.php" class="<?= $current === 'appointments.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Appointments
        </a>
        <a href="schedule.php" class="<?= $current === 'schedule.php' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> My Schedule
        </a>
        <a href="my_patients.php" class="<?= $current === 'my_patients.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> My Patients
        </a>
        <a href="medical_records.php" class="<?= $current === 'medical_records.php' ? 'active' : '' ?>">
             Medical Reports
        </a>
        <a href="messages.php" class="<?= $current === 'messages.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($unread_messages > 0): ?>
                <span class="msg-badge"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i> My Profile
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="../pages/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">Pages / <span class="page">Messages</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'D', 0, 1)) ?></div>
            <span class="topbar-name">Dr. <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">
        <div class="messaging-layout">

            <!-- Patients List -->
            <div class="patients-panel">
                <div class="patients-panel-header">
                    <h3>My Patients</h3>
                    <p>Patients from your appointments</p>
                </div>
                <div class="patients-list">
                    <?php if (count($patients) > 0): ?>
                        <?php foreach ($patients as $pat): ?>
                        <a href="messages.php?patient=<?= (int)$pat['user_id'] ?>"
                           class="patient-item <?= $selected_patient === (int)$pat['user_id'] ? 'active' : '' ?>">
                            <div class="patient-avatar">
                                <?= strtoupper(substr($pat['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name"><?= htmlspecialchars($pat['full_name']) ?></div>
                                <div class="patient-phone"><?= htmlspecialchars($pat['phone'] ?? '') ?></div>
                            </div>
                            <?php if ($pat['unread'] > 0): ?>
                                <span class="patient-unread"><?= $pat['unread'] ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-patients">
                            <i class="fas fa-users"></i>
                            No patients yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="chat-panel">
                <?php if ($selected_patient > 0 && isset($selected_pat)): ?>

                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-header-avatar">
                            <?= strtoupper(substr($selected_pat['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="chat-header-name"><?= htmlspecialchars($selected_pat['full_name']) ?></div>
                            <div class="chat-header-phone"><?= htmlspecialchars($selected_pat['phone'] ?? '') ?></div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($conversation) > 0): ?>
                            <?php foreach ($conversation as $msg): ?>
                            <div class="msg-bubble <?= $msg['sender_id'] == $doctor_id ? 'sent' : 'received' ?>">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                <div class="msg-time">
                                    <?= date('M d, h:i A', strtotime($msg['created_at'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; color:#aaa; margin:auto; font-size:.85rem;">
                                <i class="fas fa-comments" style="font-size:2rem; display:block; margin-bottom:.5rem; color:#dee2e6;"></i>
                                No messages yet. Start the conversation!
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Input -->
                    <div class="chat-input-area">
                        <form method="POST" class="chat-input-form">
                            <input type="hidden" name="receiver_id" value="<?= $selected_patient ?>">
                            <textarea name="message" placeholder="Type your message..."
                                      required onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                            <button type="submit" name="send_message" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="no-chat-selected">
                        <i class="fas fa-comments"></i>
                        <h3>Select a Patient</h3>
                        <p>Choose a patient from the left to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <div class="admin-footer">
        Copyright &copy; <?= date('Y') ?> MediCare Plus. All rights reserved.
    </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
</script>
</body>
</html>