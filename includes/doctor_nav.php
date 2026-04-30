<nav class="navbar">
    <div class="container">
        <div class="logo">
            <h1>MediCare Plus - Doctor</h1>
        </div>
        <ul class="nav-menu">
            <li><a href="../doctor/dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
            <li><a href="../doctor/appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
            <li><a href="../doctor/patients.php"><i class="fas fa-users"></i> My Patients</a></li>
            <li><a href="../doctor/messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="../doctor/prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a></li>
            <li><a href="../doctor/schedule.php"><i class="fas fa-clock"></i> Schedule</a></li>
            <li class="dropdown">
                <a href="#"><i class="fas fa-user-circle"></i> Dr. <?php echo $_SESSION['user_name']; ?></a>
                <div class="dropdown-menu">
                    <a href="../doctor/profile.php">Profile</a>
                    <a href="../pages/logout.php">Logout</a>
                </div>
            </li>
        </ul>
        <div class="menu-toggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>