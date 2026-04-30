<?php
$current = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar">
    <div class="container">

        <!-- Logo -->
        <a href="/medicare_plus/index.php" class="navbar-brand">
    <img src="/medicare_plus/assets/images/logo.png" alt="MediCare Plus" style="height:50px; width: 50px; object-fit:contain;">
    <span style="font-size:1.5rem; font-weight:800; color:#ffffff; white-space:nowrap; line-height:1;">MediCare Plus</span>
</a>

        <!-- Hamburger (mobile) -->
        <button class="navbar-toggler" id="navToggler" onclick="toggleNav()">
            <i class="fas fa-bars" style="color:#ffffff;"></i>
        </button>

        <!-- Nav Links -->
        <div class="navbar-collapse" id="navCollapse">
            <ul class="navbar-nav">
                <li>
                    <a href="/medicare_plus/index.php"
                       class="nav-link <?php echo ($current == 'index.php') ? 'active' : ''; ?>">
                        Home
                    </a>
                </li>
                <li>
                    <a href="/medicare_plus/pages/about.php"
                       class="nav-link <?php echo ($current == 'about.php') ? 'active' : ''; ?>">
                        About
                    </a>
                </li>
                <li>
                    <a href="/medicare_plus/pages/services.php"
                       class="nav-link <?php echo ($current == 'services.php') ? 'active' : ''; ?>">
                        Services
                    </a>
                </li>
                <li>
                    <a href="/medicare_plus/pages/doctors.php"
                       class="nav-link <?php echo ($current == 'doctors.php') ? 'active' : ''; ?>">
                        Doctors
                    </a>
                </li>
                <li>
                    <a href="/medicare_plus/pages/contact.php"
                       class="nav-link <?php echo ($current == 'contact.php') ? 'active' : ''; ?>">
                        Contact
                    </a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li>
                        <a href="/medicare_plus/<?php echo $_SESSION['user_type']; ?>/dashboard.php"
                           class="nav-link">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="/medicare_plus/pages/logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="/medicare_plus/pages/login.php" class="nav-link">
                            Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

    </div>
</nav>

<script>
function toggleNav() {
    const collapse = document.getElementById('navCollapse');
    const toggler  = document.getElementById('navToggler');
    collapse.classList.toggle('open');
    const icon = toggler.querySelector('i');
    icon.className = collapse.classList.contains('open') ? 'fas fa-times' : 'fas fa-bars';
    icon.style.color = '#ffffff';
}

document.addEventListener('click', function(e) {
    const nav     = document.getElementById('navCollapse');
    const toggler = document.getElementById('navToggler');
    if (!nav.contains(e.target) && !toggler.contains(e.target)) {
        nav.classList.remove('open');
        const icon = toggler.querySelector('i');
        icon.className = 'fas fa-bars';
        icon.style.color = '#ffffff';
    }
});
</script>