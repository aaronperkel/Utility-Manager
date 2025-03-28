<!-- top.php -->
<?php
ob_start();
$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']);
$pathParts = pathinfo($phpSelf);

include 'connect-DB.php';
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>81 Buell Utilites</title>
    <link rel="icon" type="image/x" href="./public/favicon.ico">
    <meta name="author" content="Aaron Perkel">
    <meta name="description" content="A dashboard to keep
        track of the monthly utilities of our apartment">
    <meta name="viewport" content="width=device-width,
        initial-scale=1.0">

    <link href="css/custom.css?version=<?php print time(); ?>" rel="stylesheet" type="text/css">

    <link href="css/layout-phone.css?version=<?php print time(); ?>" media="(max-width: 920px)" rel="stylesheet"
        type="text/css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

    <script src="js/dropdown.js"></script>

</head>
<?php
print '<body class="' . $pathParts['filename'] . '">';
print '<!-- #################   Body element    ################# -->';
include 'nav.php';
?>