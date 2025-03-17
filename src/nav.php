<!-- nav.php -->
<div class="navBox">
    <header>
        <h1>81 Buell Utilities</h1>
    </header>
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