    <nav>
        <a class="<?php
        if ($pathParts['filename'] == "index") {
            print 'activePage';
        }
        ?>" href="/cs-fair">Home</a>

        <a class="<?php
        if ($pathParts['filename'] == "portal") {
            print 'activePage';
        }
        ?>" href="/cs-fair/portal.php">Admin Portal</a>
    </nav>
</div>