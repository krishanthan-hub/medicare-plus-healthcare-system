<?php
session_start();
include '../config/database.php';
include '../includes/header.php';
include '../includes/navigation.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validateCSRF();
    $name    = validate($_POST['name']    ?? '');
    $email   = validate($_POST['email']   ?? '');
    $phone   = validate($_POST['phone']   ?? '');
    $subject = validate($_POST['subject'] ?? '');
    $message = validate($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        if ($stmt->execute()) {
            $success = "Your message has been sent! We'll get back to you shortly.";
        } else {
            $error = "Failed to send message. Please try again.";
        }
    }
}
?>

<style>
    .contact-hero {
        background-image:
            linear-gradient(rgba(30,60,79,0.65), rgba(30,60,79,0.65)),
            url('/medicare_plus/assets/images/Contact_BG.png');
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
        text-align: center;
        padding: 6rem 1rem 5rem;
    }
    .contact-hero h1 {
        font-size: 2.8rem;
        font-weight: 800;
        color: white;
        margin-bottom: 1rem;
    }
    .contact-hero p {
        color: rgba(255,255,255,0.85);
        font-size: 1rem;
        max-width: 620px;
        margin: 0 auto;
        line-height: 1.75;
    }

    .contact-page {
        background: #f0f7f8;
        padding: 3rem 0;
        min-height: 80vh;
    }

    .contact-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2.5rem;
    }

    .contact-info-card {
        background: white;
        border-radius: 0px;
        padding: 2rem 1.5rem;
        border: 1.5px solid #e0e0e0;
        text-align: center;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }

    .info-icon-wrap {
        width: 60px; height: 60px;
        background: #d0e8ff; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.25rem;
    }
    .info-icon-wrap i { font-size: 1.4rem; color: #1778F2; }

    .contact-info-card h3 { font-size: 1rem; font-weight: 700; color: #1e3c4f; margin-bottom: 0.75rem; }
    .contact-info-card p  { color: #555; font-size: 0.9rem; line-height: 1.7; margin: 0; }

    .message-form-card {
        background: white; border-radius: 0px;
        border: 1.5px solid #e0e0e0; padding: 2rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        max-width: 680px; margin: 0 auto;
    }

    .form-card-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.75rem; }
    .form-card-header h2 { font-size: 1.3rem; font-weight: 700; color: #1e3c4f; margin-bottom: 0.2rem; }
    .form-card-header p  { color: #6c757d; font-size: 0.88rem; margin: 0; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 576px) { .form-row { grid-template-columns: 1fr; } }

    .contact-form .form-group { margin-bottom: 1rem; }
    .contact-form label { display: block; font-weight: 600; color: #1e3c4f; font-size: 0.88rem; margin-bottom: 0.4rem; }

    .contact-form input,
    .contact-form select,
    .contact-form textarea {
        width: 100%; padding: 0.75rem 1rem;
        border: 1.5px solid #e0e0e0; border-radius: 10px;
        font-size: 0.9rem; color: #333; background: white;
        transition: border-color 0.3s, box-shadow 0.3s; font-family: inherit;
    }
    .contact-form input::placeholder,
    .contact-form textarea::placeholder { color: #adb5bd; }
    .contact-form input:focus,
    .contact-form select:focus,
    .contact-form textarea:focus {
        outline: none; border-color: #1778F2;
        box-shadow: 0 0 0 3px rgba(23,120,242,0.1);
    }
    .contact-form textarea { resize: vertical; min-height: 130px; }

    .btn-send {
        width: 100%; padding: 0.9rem;
        background: #1778F2; color: white; border: none;
        border-radius: 10px; font-size: 1rem; font-weight: 600;
        cursor: pointer; display: flex; align-items: center;
        justify-content: center; gap: 0.6rem;
        transition: background 0.3s, transform 0.2s; margin-top: 0.5rem;
    }
    .btn-send:hover { background: #1060c9; transform: translateY(-1px); }

    .alert { padding: 0.85rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; }
    .alert-success { background: #d4f4f5; color: #0e7d85; border: 1px solid #b2e8eb; }
    .alert-error   { background: #fde8e8; color: #c0392b; border: 1px solid #f5c6cb; }
</style>

<!-- Hero -->
<section class="contact-hero">
    <h1>Contact Us</h1>
    <p>We're here to help. Reach out to us anytime.</p>
</section>

<!-- Content -->
<div class="contact-page">
    <div class="container">

        <!-- Info Cards -->
        <div class="contact-info-grid">
            <div class="contact-info-card">
                <div class="info-icon-wrap"><i class="fas fa-phone"></i></div>
                <h3>Phone</h3>
                <p>+94 70 272 4794<br>+94 76 821 1177</p>
            </div>
            <div class="contact-info-card">
                <div class="info-icon-wrap"><i class="fas fa-envelope"></i></div>
                <h3>Email</h3>
                <p>info@medicareplus.com<br>support@medicareplus.com</p>
            </div>
            <div class="contact-info-card">
                <div class="info-icon-wrap"><i class="fas fa-map-marker-alt"></i></div>
                <h3>Address</h3>
                <p>No. 123, Galle Road<br>Colombo 03, Sri Lanka</p>
            </div>
            <div class="contact-info-card">
                <div class="info-icon-wrap"><i class="fas fa-clock"></i></div>
                <h3>Working Hours</h3>
                <p>Mon - Fri: 8:00 AM - 8:00 PM<br>Sat: 9:00 AM - 5:00 PM</p>
            </div>
        </div>

        <!-- Message Form -->
        <div class="message-form-card">
            <div class="form-card-header">
                <div>
                    <h2>Send Us a Message</h2>
                    <p>Fill out the form and we'll get back to you shortly.</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="contact-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" required
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone"
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject">
                            <option value="" disabled <?php echo !isset($_POST['subject']) ? 'selected' : ''; ?>>Select a subject</option>
                            <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                            <option value="Appointment"     <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Appointment')     ? 'selected' : ''; ?>>Appointment</option>
                            <option value="Medical Records" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Medical Records') ? 'selected' : ''; ?>>Medical Records</option>
                            <option value="Billing"         <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Billing')         ? 'selected' : ''; ?>>Billing</option>
                            <option value="Feedback"        <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Feedback')        ? 'selected' : ''; ?>>Feedback</option>
                            <option value="Other"           <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Other')           ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" placeholder="How can we help you?" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>

                <button type="submit" class="btn-send">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>