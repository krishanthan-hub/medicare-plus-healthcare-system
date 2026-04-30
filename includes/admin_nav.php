<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

.admin-wrapper {
    display: flex;
    min-height: 100vh;
    font-family: 'Segoe UI', sans-serif;
}

/* ── Sidebar ── */
.sidebar {
    width: 260px;
    min-height: 100vh;
    background: #1778F2;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    z-index: 1000;
}

.sidebar-brand {
    padding: 1.75rem 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}

.sidebar-brand h2 {
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.4;
}

.sidebar-brand span {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    color: white;
    font-size: .7rem;
    font-weight: 600;
    padding: .2rem .6rem;
    margin-top: .4rem;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.sidebar-menu {
    flex: 1;
    padding: 1.25rem 0;
    overflow-y: auto;
}

.menu-section-label {
    color: rgba(255,255,255,0.55);
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: .75rem 1.5rem .35rem;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: .75rem 1.5rem;
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    font-size: .9rem;
    font-weight: 500;
    transition: background .2s;
    margin: .1rem .75rem;
    border-radius: 0;
}

.sidebar-menu a:hover {
    background: rgba(255,255,255,0.15);
    color: white;
}

.sidebar-menu a.active {
    background: white;
    color: #1778F2;
    font-weight: 700;
}

.sidebar-menu a.active i {
    color: #1778F2;
}

.sidebar-menu a i {
    width: 18px;
    font-size: 1rem;
    color: rgba(255,255,255,0.75);
}

.sidebar-menu a.active i {
    color: #1778F2;
}

/* Logout button */
.sidebar-logout {
    padding: 1.25rem .75rem;
    border-top: 1px solid rgba(255,255,255,0.15);
}

.sidebar-logout a {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .6rem;
    padding: .75rem;
    background: rgba(255,255,255,0.15);
    color: white;
    text-decoration: none;
    font-size: .9rem;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
    transition: background .2s;
}

.sidebar-logout a:hover {
    background: rgba(255,255,255,0.25);
}

/* ── Main Content ── */
.main-content {
    margin-left: 260px;
    flex: 1;
    background: #f4f6f9;
    min-height: 100vh;
}

/* ── Top Bar ── */
.topbar {
    background: white;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e0e0e0;
    position: sticky;
    top: 0;
    z-index: 100;
}

.topbar-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1e3c4f;
}

.topbar-admin {
    display: flex;
    align-items: center;
    gap: .75rem;
    font-size: .875rem;
    color: #555;
}

.topbar-avatar {
    width: 36px; height: 36px;
    background: #1778F2;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-weight: 700;
    font-size: .85rem;
}

.page-body {
    padding: 2rem;
}

/* ── Mobile toggle ── */
.mobile-toggle {
    display: none;
    background: #1778F2;
    color: white;
    border: none;
    padding: .5rem .75rem;
    font-size: 1.2rem;
    cursor: pointer;
}

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); transition: transform .3s; }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; }
    .mobile-toggle { display: block; }
}
</style>

<!-- Sidebar -->
<div class="admin-wrapper">
<div class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <h2>MediCare Plus</h2>
        <span>🔒 Admin Portal</span>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-section-label">Main</div>
        <a href="/medicare_plus/admin/dashboard.php"
           class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>

        <div class="menu-section-label">Manage</div>
        <a href="/medicare_plus/admin/manage_doctors.php"
           class="<?= $current === 'manage_doctors.php' ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i> Doctors
        </a>
        <a href="/medicare_plus/admin/manage_patients.php"
           class="<?= $current === 'manage_patients.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Patients
        </a>
        <a href="/medicare_plus/admin/manage_appointments.php"
           class="<?= $current === 'manage_appointments.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Appointments
        </a>

        <div class="menu-section-label">Analytics</div>
        <a href="/medicare_plus/admin/reports.php"
           class="<?= $current === 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
    </nav>

    <div class="sidebar-logout">
        <a href="/medicare_plus/pages/logout.php">
            <i class="fas fa-sign-out-alt"></i> LOGOUT
        </a>
    </div>
</div>

<!-- Main content wrapper starts -->
<div class="main-content">
    <div class="topbar">
        <div style="display:flex; align-items:center; gap:1rem;">
            <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
            <span class="topbar-title">
                <?php
                $titles = [
                    'dashboard.php'             => 'Dashboard',
                    'manage_doctors.php'        => 'Manage Doctors',
                    'manage_patients.php'       => 'Manage Patients',
                    'manage_appointments.php'   => 'Manage Appointments',
                    'reports.php'               => 'Reports',
                ];
                echo $titles[$current] ?? 'Admin Panel';
                ?>
            </span>
        </div>
        <div class="topbar-admin">
            <div class="topbar-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
        </div>
    </div>
    <div class="page-body"></div>