<!-- nav.php -->
<div class="navBox">
    <header>
        <h1>81 Buell Utilities</h1>
    </header>
    <nav>
        <?php
        if ($_SERVER['REMOTE_USER'] == 'aperkel') { ?>
            <a class="<?php if ($pathParts['filename'] == 'index') {
                print 'activePage';
            } ?>" href="./">Home</a>

            <a class="<?php if ($pathParts['filename'] == 'portal') {
                print 'activePage';
            } ?>" href="./portal.php">Admin Portal</a>

            <a class="<?php if ($pathParts['filename'] == 'send_custom_email') {
                print 'activePage';
            } ?>" href="./send_custom_email.php">Send Email</a>
        <?php } ?>
    </nav>
</div>