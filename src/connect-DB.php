<!-- Connecting -->
<?php
require __DIR__ . '/./vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$databaseName = $_ENV['DBNAME'];
$dsn = 'mysql:host=webdb.uvm.edu;dbname=' . $databaseName;
$username = $_ENV['DBUSER'];
$password = $_ENV['DBPASS'];

$pdo = new PDO($dsn, $username, $password);
if ($pdo) print '<!-- Connection complete -->';
?>
