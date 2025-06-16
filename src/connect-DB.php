<!-- Connecting -->
<?php
// connect-DB.php
// Establishes a database connection using PDO and loads environment variables.

// Require the Composer autoloader to load vendor libraries (e.g., phpdotenv).
require __DIR__ . '/./vendor/autoload.php';

// Initialize phpdotenv to load variables from the .env file located in the project root.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load(); // All .env variables are now available via $_ENV or getenv().

// Retrieve database connection details from environment variables.
// Uses null coalescing operator (??) for defaults, though critical ones are checked below.
$databaseName = $_ENV['DB_NAME'] ?? null;
$username = $_ENV['DB_USER'] ?? null;
$password = $_ENV['DB_PASS'] ?? null;
$dbHost = $_ENV['DB_HOST'] ?? 'webdb.uvm.edu'; // Default host if not specified in .env
$sslCaPath = $_ENV['DB_SSL_CA_PATH'] ?? null; // Path to SSL CA certificate, if any.

// Validate that essential database credentials are provided.
if (!$databaseName || !$username || !$password) {
    // Terminate script if critical connection details are missing.
    die("Error: Database credentials (DB_NAME, DB_USER, DB_PASS) not found in .env file. Please check configuration.");
}

// Construct the Data Source Name (DSN) for PDO.
$dsn = 'mysql:host=' . $dbHost . ';dbname=' . $databaseName;
$options = []; // Array to hold PDO connection options.

// If an SSL CA path is provided, configure PDO to use it for a secure connection.
if (!empty($sslCaPath)) {
    // Note: Path should be absolute or correctly relative from the entry point script's perspective.
    // Using absolute paths in .env for DB_SSL_CA_PATH is generally more reliable.
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCaPath;
    // Example for self-signed certs (use with caution):
    // $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    // Create a new PDO instance to connect to the database.
    $pdo = new PDO($dsn, $username, $password, $options);
    // Set PDO error mode to throw exceptions, allowing for robust error handling.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optional: Print a comment to HTML output indicating successful connection (useful for debugging).
    if ($pdo) print '<!-- Database connection complete -->';
} catch (PDOException $e) {
    // Catch any exceptions during connection attempt.
    error_log("Database Connection Error: " . $e->getMessage()); // Log the detailed error.
    // Display a user-friendly error message and terminate script.
    die("Database connection failed. Please check server logs. Error detail: " . htmlspecialchars($e->getMessage()));
}

// Note: All other environment variables loaded by phpdotenv (e.g., APP_BASE_URL, APP_USERS_OWING)
// are available globally via $_ENV or getenv() in any script that includes this file.
?>
