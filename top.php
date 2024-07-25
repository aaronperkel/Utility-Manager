<?php
$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']);
$pathParts = pathinfo($phpSelf);
?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>81 Buell Utilites</title>
        <meta name="author" content="Aaron Perkel">
        <meta name="description" content="A dashboard to keep
        track of the monthly utilities of our apartment">

        <meta name="viewport" content="width=device-width,
        initial-scale=1.0">

        <link href="css/custom.css?version=<?php print time(); ?>" 
            rel="stylesheet" 
            type="text/css">

        <link href="css/layout-phone.css?version=<?php print time(); ?>" 
            media="(max-width: 920px)"
            rel="stylesheet" 
            type="text/css">

        <link rel="apple-touch-icon" sizes="16x16" href="images/apple-touch-icon.png">
        <link rel="icon" href="images/favicon.ico">

        <script>
            function updateField() {
                var number = document.getElementById('total').value;
                var result = number / 3;
                result = Math.round(result * 100) / 100;
                document.getElementById('cost').value = result;
            }   
        </script>
    </head>
    <?php
    print '<body class="' . $pathParts['filename'] . '">';
    print '<!-- #################   Body element    ################# -->';
    include 'connect-DB.php';
    include 'header.php';
    include 'nav.php';
    ?>