<!-- nav.php -->
<nav>
    <?php
    session_start();
    if (isset($_SESSION['username'])) {
        echo '<a href="index.php">Home</a>';
        echo '<a href="portal.php">Admin Portal</a>';
        echo '<a href="logout.php">Logout (' . htmlspecialchars($_SESSION['username']) . ')</a>';
    } else {
        echo '<a href="login.php">Login</a>';
        echo '<a href="register.php">Register</a>';
    }
    ?>
</nav>
</div>