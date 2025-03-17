<!-- nav.php -->
<nav>
    <?php
    if ($_SERVER['REMOTE_USER'] == 'aperkel') {
        echo '<a href="index.php">Home</a>';
        echo '<a href="portal.php">Admin Portal</a>';
        echo '<a href="send_custom_email.php">Send Email</a>';
    }
    ?>
</nav>
</div>