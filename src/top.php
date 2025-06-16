<!-- top.php -->
<?php
// This script starts output buffering, sets up global variables for page identification,
// and includes the database connection script. It forms the top part of every HTML page.

ob_start(); // Start output buffering. Useful for redirecting with header() calls even after some output.

// Get the current script's filename without extension to identify the active page for navigation styling.
$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']); // Sanitize PHP_SELF.
$pathParts = pathinfo($phpSelf); // Get path info, $pathParts['filename'] will be used in nav.php.

// Establish database connection and load environment variables.
// $pdo object becomes available globally in the scope of including scripts.
// All .env variables are loaded into $_ENV.
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