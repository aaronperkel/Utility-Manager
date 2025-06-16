<!-- src/nav.php -->
<!-- Navigation bar for the site. Uses $pathParts from top.php to highlight the active page. -->
<div class="navBox">
    <header>
        <h1>81 Buell Utilities</h1>
    </header>
    <nav>
        <a class="<?php if ($pathParts['filename'] == 'index')
            echo 'activePage'; ?>" href="./">Home</a>
        <a class="<?php if ($pathParts['filename'] == 'trends')
            echo 'activePage'; ?>" href="./trends.php">Trends</a>
        <?php
        // Conditionally display admin links.
        // Note: This uses a hardcoded username check. For better maintainability and consistency,
        // this should ideally use the isAdminUser() function and APP_ADMIN_USERS list from .env,
        // similar to how portal.php handles admin access. However, that would require
        // $appAdminUsersList to be available globally here (e.g., loaded in top.php after .env).
        if (($_SERVER['REMOTE_USER'] ?? '') === 'aperkel'):
        ?>
            <a class="<?php if ($pathParts['filename'] == 'portal')
                echo 'activePage'; ?>" href="./portal.php">Admin
                Portal</a>
            <a class="<?php if ($pathParts['filename'] == 'send_custom_email')
                echo 'activePage'; ?>"
                href="./send_custom_email.php">Send Email</a>
        <?php endif; ?>
    </nav>
</div>