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

// SSL Configuration
$dbUseSsl = strtolower($_ENV['DB_USE_SSL'] ?? 'false') === 'true' || ($_ENV['DB_USE_SSL'] ?? '0') === '1';
$dbSslCaPath = $_ENV['DB_SSL_CA_PATH'] ?? null; // Path to SSL CA certificate.

// Validate that essential database credentials are provided.
if (!$databaseName || !$username || !$password) {
    // Terminate script if critical connection details are missing.
    die("Error: Database credentials (DB_NAME, DB_USER, DB_PASS) not found in .env file. Please check configuration.");
}

// Construct the Data Source Name (DSN) for PDO.
$dsn = 'mysql:host=' . $dbHost . ';dbname=' . $databaseName;
$options = []; // Array to hold PDO connection options.
$sslWarning = null; // To store any SSL related warnings.

// Configure PDO SSL options if DB_USE_SSL is true.
if ($dbUseSsl) {
    if (!empty($dbSslCaPath)) {
        // Check if the CA path is readable.
        // Note: For relative paths, PHP checks based on the entry point script's CWD or include paths.
        // It's often more reliable to use an absolute path for DB_SSL_CA_PATH in .env
        // or ensure it's relative to a known location (e.g., project root).
        // Here, we construct path relative to project root if DB_SSL_CA_PATH is not absolute.
        $caPathToCheck = $dbSslCaPath;
        if (!preg_match('/^([\/]|[a-zA-Z]:)/', $dbSslCaPath)) { // Simple check if not absolute (Unix/Windows)
            $caPathToCheck = __DIR__ . '/../' . $dbSslCaPath; // Assume relative to project root
        }

        if (is_readable($caPathToCheck)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPathToCheck;
            // For stricter SSL, you might also want to set:
            // $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true; // (or false if using self-signed cert not in CA)
            // However, MYSQL_ATTR_SSL_VERIFY_SERVER_CERT is true by default if MYSQL_ATTR_SSL_CA is set.
            // Setting it to false explicitly is usually only for specific self-signed certificate scenarios.
        } else {
            $sslWarning = "DB_USE_SSL is true, but DB_SSL_CA_PATH ('" . htmlspecialchars($dbSslCaPath) . "', resolved to '" . htmlspecialchars($caPathToCheck) . "') is not readable. Attempting connection without SSL CA verification.";
            error_log($sslWarning); // Log this critical warning.
            // Proceeding without setting MYSQL_ATTR_SSL_CA means it might still try SSL if server enforces,
            // but without client-side CA verification, or might connect unencrypted if server allows.
            // Depending on MySQL server config (e.g. require_secure_transport), connection might fail if SSL is mandatory.
        }
    } else {
        // DB_USE_SSL is true, but no CA path provided.
        // This might work if the server's CA is trusted by the system's default CA store,
        // or if the server doesn't strictly require client-verified CA.
        // It's generally better to provide a CA if SSL is explicitly enabled.
        $sslWarning = "DB_USE_SSL is true, but DB_SSL_CA_PATH is not set. SSL connection will rely on system CAs or server configuration.";
        error_log($sslWarning);
    }
} else {
    // SSL is explicitly disabled.
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false; // Explicitly disable SSL cert verification if SSL not used
    // This might not be strictly necessary if not connecting with SSL, but shows intent.
    // Some drivers might still attempt SSL if server requests it, this aims to prevent that or related warnings.
    // The most robust way to disable SSL is specific to the driver, e.g. for some it's using 'sslmode=disable' in DSN.
    // For PDO MySQL, not setting SSL options usually means it's opportunistic.
}


try {
    // Create a new PDO instance to connect to the database.
    $pdo = new PDO($dsn, $username, $password, $options);
    // Set PDO error mode to throw exceptions, allowing for robust error handling.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $connectionStatusComment = '<!-- Database connection complete';
    if ($dbUseSsl && isset($options[PDO::MYSQL_ATTR_SSL_CA])) {
        $connectionStatusComment .= ' (SSL WITH CA VERIFIED)';
    } elseif ($dbUseSsl) {
        $connectionStatusComment .= ' (SSL ENABLED, CA NOT VERIFIED OR SYSTEM CA)';
    } else {
        $connectionStatusComment .= ' (SSL DISABLED)';
    }
    if ($sslWarning) {
         $connectionStatusComment .= "\n     WARNING: " . htmlspecialchars(str_replace("'", "", $sslWarning)) . " -->"; // Basic sanitization for HTML comment
    } else {
        $connectionStatusComment .= ' -->';
    }
    if ($pdo) print $connectionStatusComment;

} catch (PDOException $e) {
    // Catch any exceptions during connection attempt.
    error_log("Database Connection Error: " . $e->getMessage() . ($sslWarning ? " SSL Info: " . $sslWarning : "")); // Log the detailed error.
    // Display a user-friendly error message and terminate script.
    die("Database connection failed. Please check server logs. Error detail: " . htmlspecialchars($e->getMessage()) . ($sslWarning ? "<br>SSL Info: " . htmlspecialchars($sslWarning) : ""));
}

// Note: All other environment variables loaded by phpdotenv (e.g., APP_BASE_URL, APP_USERS_OWING)
// are available globally via $_ENV or getenv() in any script that includes this file.

// --- Dry Run Mode Function ---
/**
 * Checks if Testing/Dry-Run mode is active based on environment variables.
 *
 * Dry-run mode, when active, typically prevents actions that make persistent changes
 * (e.g., database writes, sending real emails) and instead simulates them.
 *
 * @return bool True if Dry-Run mode is active, false otherwise.
 */
function isDryRunActive(): bool {
    // Check if dry-run mode is globally enabled via .env.
    $dryRunEnabled = filter_var($_ENV['APP_DRY_RUN_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    if (!$dryRunEnabled) {
        return false; // Dry-run is not enabled at all.
    }

    // Check if dry-run mode is restricted to admin users only.
    $adminOnly = filter_var($_ENV['APP_DRY_RUN_ADMIN_ONLY'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    if (!$adminOnly) {
        return true; // Dry-run is enabled for everyone.
    }

    // If dry-run is enabled and admin-only, check if the current user is an admin.
    $currentUser = $_SERVER['REMOTE_USER'] ?? '';
    $adminUsersStr = $_ENV['APP_ADMIN_USERS'] ?? '';
    $adminUsersArray = !empty($adminUsersStr) ? array_map('trim', explode(',', $adminUsersStr)) : [];

    // Return true if current user is in the admin list, false otherwise.
    return in_array($currentUser, $adminUsersArray, true);
}
?>
