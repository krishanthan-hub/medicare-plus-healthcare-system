<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'patient') {
    header("Location: ../pages/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $message     = trim(mysqli_real_escape_string($conn, $_POST['message'] ?? ''));
    if (!empty($message) && $receiver_id > 0) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $patient_id, $receiver_id, $message);
        $stmt->execute();
    }
    header("Location: messages.php?doctor=" . $receiver_id);
    exit();
}

// Mark messages as read
$selected_doctor = (int)($_GET['doctor'] ?? 0);
if ($selected_doctor > 0) {
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $selected_doctor AND receiver_id = $patient_id");
}

// Get all doctors patient has appointments with
$doctors_stmt = $conn->prepare("
    SELECT DISTINCT u.user_id, u.full_name, d.specialization,
           (SELECT COUNT(*) FROM messages 
            WHERE sender_id = u.user_id AND receiver_id = ? AND is_read = 0) AS unread
    FROM appointments a
    JOIN users u ON a.doctor_id = u.user_id
    JOIN doctor_profiles d ON u.user_id = d.doctor_id
    WHERE a.patient_id = ?
    ORDER BY u.full_name ASC
");
$doctors_stmt->bind_param("ii", $patient_id, $patient_id);
$doctors_stmt->execute();
$doctors = $doctors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get conversation with selected doctor
$conversation = [];
if ($selected_doctor > 0) {
    $conv_stmt = $conn->prepare("
        SELECT m.*, u.full_name AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $conv_stmt->bind_param("iiii", $patient_id, $selected_doctor, $selected_doctor, $patient_id);
    $conv_stmt->execute();
    $conversation = $conv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get selected doctor info
    $doc_info = $conn->prepare("SELECT u.full_name, d.specialization FROM users u JOIN doctor_profiles d ON u.user_id = d.doctor_id WHERE u.user_id = ?");
    $doc_info->bind_param("i", $selected_doctor);
    $doc_info->execute();
    $selected_doc = $doc_info->get_result()->fetch_assoc();
}

$unread_messages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM messages WHERE receiver_id = $patient_id AND is_read = 0"))['count'];
$current = basename($_SERVER['PHP_SELF']);
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

        /* ── Messaging Layout ── */
        .messaging-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 160px);
        }

        /* ── Doctors List Panel ── */
        .doctors-panel {
            background: white; border: 1px solid #e4e6ea; border-radius: 0px;
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        .doctors-panel-header {
            padding: 1rem 1.25rem; border-bottom: 1px solid #f0f0f0;
            background: #f8f9fb;
        }
        .doctors-panel-header h3 { font-size: .9rem; font-weight: 700; color: #1e3c4f; }
        .doctors-panel-header p  { font-size: .75rem; color: #888; margin-top: .15rem; }
        .doctors-list { flex: 1; overflow-y: auto; }

        .doctor-item {
            display: flex; align-items: center; gap: .75rem;
            padding: .9rem 1.25rem; border-bottom: 1px solid #f4f4f4;
            text-decoration: none; transition: background .15s;
        }
        .doctor-item:hover { background: #f8f9fb; }
        .doctor-item.active { background: #e8f1fd; border-left: 3px solid #1778F2; }
        .doctor-item-avatar {
            width: 42px; height: 42px; background: #e8f1fd;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: #1778F2; font-weight: 800; font-size: 1rem; flex-shrink: 0;
        }
        .doctor-item-name { font-size: .88rem; font-weight: 700; color: #1e3c4f; }
        .doctor-item-spec { font-size: .75rem; color: #888; }
        .doctor-item-unread {
            margin-left: auto; background: #e74c3c; color: white;
            font-size: .65rem; font-weight: 700; padding: .15rem .45rem;
            border-radius: 10px; flex-shrink: 0;
        }

        .no-doctors {
            text-align: center; padding: 3rem 1.5rem; color: #aaa; font-size: .85rem;
        }
        .no-doctors i { font-size: 2rem; display: block; margin-bottom: .5rem; color: #dee2e6; }

        /* ── Chat Panel ── */
        .chat-panel {
            background: white; border: 1px solid #e4e6ea; border-radius: 0px;
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
        .chat-header-name { font-size: .95rem; font-weight: 700; color: #1e3c4f; }
        .chat-header-spec { font-size: .78rem; color: #888; }

        .chat-messages {
            flex: 1; overflow-y: auto; padding: 1.25rem;
            display: flex; flex-direction: column; gap: .75rem;
            background: #f8f9fb;
        }

        .msg-bubble {
            max-width: 70%; padding: .7rem 1rem;
            font-size: .88rem; line-height: 1.5;
            position: relative;
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
        .msg-time {
            font-size: .68rem; margin-top: .3rem;
            opacity: .7; text-align: right;
        }
        .msg-bubble.received .msg-time { text-align: left; }

        .chat-input-area {
            padding: 1rem 1.25rem; border-top: 1px solid #e4e6ea;
            background: white;
        }
        .chat-input-form { display: flex; gap: .75rem; align-items: flex-end; }
        .chat-input-form textarea {
            flex: 1; padding: .7rem 1rem;
            border: 1.5px solid #ddd; outline: none;
            font-size: .9rem; resize: none; min-height: 44px; max-height: 120px;
            font-family: inherit; transition: border-color .2s;
        }
        .chat-input-form textarea:focus { border-color: #1778F2; }
        .btn-send {
            padding: .7rem 1.25rem; background: #1778F2; color: white; border-radius: 6px;
            border: none; font-size: .88rem; font-weight: 700;
            cursor: pointer; transition: background .2s; white-space: nowrap;
            display: flex; align-items: center; gap: .4rem;
        }
        .btn-send:hover { background: #1060c9; }

        /* No chat selected */
        .no-chat-selected {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            color: #aaa; text-align: center; padding: 2rem;
        }
        .no-chat-selected i { font-size: 3rem; margin-bottom: 1rem; color: #dee2e6; }
        .no-chat-selected h3 { font-size: 1rem; color: #888; margin-bottom: .5rem; }
        .no-chat-selected p  { font-size: .85rem; }

        .admin-footer {
            text-align: center; padding: 1.25rem; font-size: .8rem;
            color: #aaa; border-top: 1px solid #e4e6ea; background: white;
        }

        @media (max-width: 768px) {
            .messaging-layout { grid-template-columns: 1fr; }
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
        <div class="topbar-left">Pages / <span class="page">Messages</span></div>
        <div class="topbar-right">
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)) ?></div>
            <span class="topbar-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
    </div>

    <div class="content">
        <div class="messaging-layout">

            <!-- Doctors List -->
            <div class="doctors-panel">
                <div class="doctors-panel-header">
                    <h3>Your Doctors</h3>
                    <p>Doctors from your appointments</p>
                </div>
                <div class="doctors-list">
                    <?php if (count($doctors) > 0): ?>
                        <?php foreach ($doctors as $doc): ?>
                        <a href="messages.php?doctor=<?= (int)$doc['user_id'] ?>"
                           class="doctor-item <?= $selected_doctor === (int)$doc['user_id'] ? 'active' : '' ?>">
                            <div class="doctor-item-avatar">
                                <?= strtoupper(substr($doc['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="doctor-item-name">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
                                <div class="doctor-item-spec"><?= htmlspecialchars($doc['specialization']) ?></div>
                            </div>
                            <?php if ($doc['unread'] > 0): ?>
                                <span class="doctor-item-unread"><?= $doc['unread'] ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-doctors">
                            <i class="fas fa-user-md"></i>
                            No doctors yet.<br>Book an appointment first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Panel -->
            <div class="chat-panel">
                <?php if ($selected_doctor > 0 && isset($selected_doc)): ?>

                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-header-avatar">
                            <?= strtoupper(substr($selected_doc['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="chat-header-name">Dr. <?= htmlspecialchars($selected_doc['full_name']) ?></div>
                            <div class="chat-header-spec"><?= htmlspecialchars($selected_doc['specialization']) ?></div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (count($conversation) > 0): ?>
                            <?php foreach ($conversation as $msg): ?>
                            <div class="msg-bubble <?= $msg['sender_id'] == $patient_id ? 'sent' : 'received' ?>">
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
                            <input type="hidden" name="receiver_id" value="<?= $selected_doctor ?>">
                            <textarea name="message" placeholder="Type your message..." required
                                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
                            <button type="submit" name="send_message" class="btn-send">
                                 Send
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="no-chat-selected">
                        <i class="fas fa-comments"></i>
                        <h3>Select a Doctor</h3>
                        <p>Choose a doctor from the left to start messaging</p>
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
// Auto scroll to bottom of chat
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
</script>
</body>
</html>