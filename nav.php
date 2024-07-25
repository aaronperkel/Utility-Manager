    <nav>
        <a class="<?php
        if ($pathParts['filename'] == "index") {
            print 'activePage';
        }
        ?>" href="/utilities">Home</a>

        <a class="<?php
        if ($pathParts['filename'] == "portal") {
            print 'activePage';
        }
        ?>" href="/utilities/portal">Admin Portal</a>
    </nav>
</div>