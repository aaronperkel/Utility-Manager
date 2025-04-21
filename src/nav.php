<!-- src/nav.php -->
<div class="navBox">
    <header>
        <h1>81 Buell Utilities</h1>
    </header>
    <nav>
        <a class="<?php if ($pathParts['filename'] == 'index')
            echo 'activePage'; ?>" href="./">Home</a>
        <a class="<?php if ($pathParts['filename'] == 'trends')
            echo 'activePage'; ?>" href="./trends.php">Trends</a>
        <?php if ($_SERVER['REMOTE_USER'] == 'aperkel'): ?>
            <a class="<?php if ($pathParts['filename'] == 'portal')
                echo 'activePage'; ?>" href="./portal.php">Admin
                Portal</a>
            <a class="<?php if ($pathParts['filename'] == 'send_custom_email')
                echo 'activePage'; ?>"
                href="./send_custom_email.php">Send Email</a>
        <?php endif; ?>
    </nav>
</div>