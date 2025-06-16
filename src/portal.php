<?php
// portal.php
// Admin portal for managing utility bills: adding new bills, viewing existing ones, and managing payment statuses.

session_start(); // Start session for CSRF token management and flash messages.

include 'top.php'; // Includes header, navigation, and database connection (connect-DB.php).

// --- Configuration Loading ---
// Load application configuration from environment variables (loaded by connect-DB.php via Dotenv).
$appBaseUrl = rtrim($_ENV['APP_BASE_URL'] ?? 'https://utilities.example.com', '/'); // Base URL for the application, ensure no trailing slash.
$appAdminUsersStr = $_ENV['APP_ADMIN_USERS'] ?? ''; // Comma-separated string of admin usernames.
$appAdminUsersList = !empty($appAdminUsersStr) ? array_map('trim', explode(',', $appAdminUsersStr)) : []; // Convert to array of admin users.

$defaultOweListStr = $_ENV['APP_USERS_OWING'] ?? 'Aaron,Owen,Ben'; // Default list of users who owe, as a string.
$defaultOweListArray = array_map('trim', explode(',', $defaultOweListStr)); // Convert to array.

$userEmailsJson = $_ENV['APP_USER_EMAILS'] ?? '{}'; // JSON string mapping user names to emails.
$emailMapArray = json_decode($userEmailsJson, true); // Decode JSON into an associative array.
if (json_last_error() !== JSON_ERROR_NONE) { // Check for JSON decoding errors.
    error_log("Failed to parse APP_USER_EMAILS JSON: " . json_last_error_msg());
    $emailMapArray = []; // Default to empty map on error.
}

$appEmailFromAddress = $_ENV['APP_EMAIL_FROM_ADDRESS'] ?? 'utilities@example.com'; // Email address for sending notifications.
$appEmailFromName = $_ENV['APP_EMAIL_FROM_NAME'] ?? '81 Buell Utilities'; // Sender name for emails.
$appConfirmationEmailTo = $_ENV['APP_CONFIRMATION_EMAIL_TO'] ?? 'admin@example.com'; // Admin email for confirmation messages.
$uploadBaseDir = __DIR__ . '/public/'; // Base directory for file uploads.

// Allowed items for the bill item dropdown.
$allowedBillItems = ['Gas', 'Electric', 'Internet'];


// --- Function Definitions ---

/**
 * Checks if the currently logged-in user is an authorized admin.
 * @param string $currentUserLogin The username of the current user (e.g., from $_SERVER['REMOTE_USER']).
 * @param array $allowedAdminLogins An array of authorized admin usernames.
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdminUser(string $currentUserLogin, array $allowedAdminLogins): bool
{
    return !empty($currentUserLogin) && in_array($currentUserLogin, $allowedAdminLogins, true);
}

/**
 * Sanitizes a string by trimming whitespace and converting special characters to HTML entities.
 * @param string $s The input string.
 * @return string The sanitized string.
 */
function sanitize(string $s): string
{
    return htmlspecialchars(trim($s), ENT_QUOTES);
}

/**
 * Validates the data submitted for a new bill.
 * @param array $postData Data from the $_POST superglobal.
 * @param array $filesData Data from the $_FILES superglobal.
 * @param array $allowedItemsList List of valid items for the 'item' field.
 * @return array An associative array containing 'data' (validated and processed values) and 'errors' (an array of error messages).
 */
function validateBillSubmissionData(array $postData, array $filesData, array $allowedItemsList): array
{
    $errors = [];
    $validatedData = [];

    // Extract and perform initial presence check for required fields.
    $validatedData['billDateStr'] = $postData['date'] ?? '';
    $validatedData['item'] = $postData['item'] ?? '';
    $validatedData['totalStr'] = $postData['total'] ?? '';
    $validatedData['costStr'] = $postData['cost'] ?? '';
    $validatedData['dueDateStr'] = $postData['due'] ?? '';

    if (empty($validatedData['billDateStr']) || empty($validatedData['item']) || empty($validatedData['totalStr']) ||
        empty($validatedData['costStr']) || empty($validatedData['dueDateStr']) || !isset($filesData['view']) || $filesData['view']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Missing one of: date, item, total, cost, due, or PDF.";
    }

    if (!in_array($validatedData['item'], $allowedItemsList, true)) {
        $errors[] = "Invalid item selected.";
    }

    if (!is_numeric($validatedData['totalStr']) || !is_numeric($validatedData['costStr'])) {
        $errors[] = "Total and Cost must be numeric.";
    } else {
        $validatedData['total'] = (float)$validatedData['totalStr'];
        $validatedData['cost'] = (float)$validatedData['costStr'];
        if ($validatedData['total'] <= 0 || $validatedData['cost'] <= 0) {
            $errors[] = "Total and Cost must be positive values.";
        }
    }

    $billDateTs = strtotime($validatedData['billDateStr']);
    $dueDateTs = strtotime($validatedData['dueDateStr']);
    if ($billDateTs === false) {
        $errors[] = "Invalid bill date format. Please use YYYY-MM-DD.";
    } else {
        $validatedData['billDate'] = date('Y-m-d', $billDateTs);
        $validatedData['year'] = date('Y', $billDateTs);
    }
    if ($dueDateTs === false) {
        $errors[] = "Invalid due date format. Please use YYYY-MM-DD.";
    } else {
        $validatedData['dueDate'] = date('Y-m-d', $dueDateTs);
    }


    // Validate presence of uploaded file. More detailed file validation (size, type) is in handleBillFileUpload.
    if (isset($filesData['view']) && $filesData['view']['error'] !== UPLOAD_ERR_OK && $filesData['view']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "File upload error code: " . $filesData['view']['error']; // Report existing upload errors.
    } elseif (!isset($filesData['view']) || $filesData['view']['error'] === UPLOAD_ERR_NO_FILE) {
        // This case is already covered by the initial presence check, but good for clarity.
        // $errors[] = "PDF file is required.";
    }

    return ['data' => $validatedData, 'errors' => $errors];
}

/**
 * Handles the file upload process for a bill's PDF.
 * Validates file size, type, sanitizes filename, creates upload directory, and moves the file.
 * @param array $fileInfo Entry from $_FILES superglobal (e.g., $_FILES['view']).
 * @param string $year Year derived from the bill date, used for structuring upload path.
 * @param string $itemValue Item name, used for structuring upload path.
 * @param string $baseUploadPath Base path for uploads (e.g., '/var/www/html/public/').
 * @return string The relative path to the uploaded file for database storage (e.g., 'public/2024/Gas/bill.pdf').
 * @throws RuntimeException If any validation or file operation fails.
 */
function handleBillFileUpload(array $fileInfo, string $year, string $itemValue, string $baseUploadPath): string
{
    // Check for upload errors reported by PHP.
    if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload error code: " . $fileInfo['error']);
    }

    // Validate file size (e.g., 5MB limit).
    $maxFileSize = 5 * 1024 * 1024;
    if ($fileInfo['size'] > $maxFileSize) {
        throw new RuntimeException("File is too large. Maximum size is 5MB.");
    }
    if ($fileInfo['size'] === 0) {
        throw new RuntimeException("File is empty. Please upload a valid PDF.");
    }

    // Validate MIME type (server-side check).
    $allowedMimeTypes = ['application/pdf', 'application/x-pdf'];
    $fileMimeType = mime_content_type($fileInfo['tmp_name']); // More reliable than $_FILES['view']['type'].
    if (!in_array($fileMimeType, $allowedMimeTypes, true)) {
        throw new RuntimeException("Invalid file type. Only PDF files are allowed. Detected type: " . htmlspecialchars($fileMimeType));
    }

    // Sanitize filename.
    $origName = basename($fileInfo['name']); // Get filename component.
    $origName = preg_replace('/[^A-Za-z0-9.\-_]/', '', $origName); // Remove potentially harmful characters.
    if (empty($origName) || $origName === '.' || $origName === '..') {
        throw new RuntimeException("Invalid filename after sanitization. Please use standard characters.");
    }
    // Ensure filename ends with .pdf (case-insensitive).
    if (strtolower(substr($origName, -4)) !== '.pdf') {
        throw new RuntimeException("Filename must end with .pdf.");
    }

    // Construct and create item-specific upload directory if it doesn't exist.
    $uploadDir = $baseUploadPath . "{$year}/{$itemValue}/"; // e.g., '/var/www/html/public/2024/Gas/'
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) { // Create recursively with appropriate permissions.
        throw new RuntimeException("Failed to create upload directory: " . htmlspecialchars($uploadDir));
    }

    // Move the uploaded file to the destination.
    $destinationPath = $uploadDir . $origName;
    if (!move_uploaded_file($fileInfo['tmp_name'], $destinationPath)) {
        throw new RuntimeException("Failed to move uploaded file to " . htmlspecialchars($destinationPath));
    }
    // Return the relative path for database storage and linking.
    return "public/{$year}/{$itemValue}/{$origName}";
}

/**
 * Inserts a new bill record into the database.
 * @param PDO $dbConnection The PDO database connection object.
 * @param array $billDetails Associative array of validated bill data (item, total, cost, billDate, dueDate, year).
 * @param string $filePath Relative path to the uploaded PDF file.
 * @param string $owingUsersList Comma-separated string of users who owe for this bill.
 * @return bool True on successful insertion, false otherwise.
 */
function insertBillRecord(PDO $dbConnection, array $billDetails, string $filePath, string $owingUsersList): bool
{
    $sql = "
        INSERT INTO tblUtilities
          (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
        VALUES
          (:date, :item, :total, :cost, :due, 'Unpaid', :view, :owe) -- Default status is 'Unpaid'.
    ";
    $stmt = $dbConnection->prepare($sql);
    // Bind parameters and execute.
    return $stmt->execute([
        ':date' => $billDetails['billDate'],
        ':item' => $billDetails['item'],
        ':total' => $billDetails['total'],
        ':cost' => $billDetails['cost'],
        ':due' => $billDetails['dueDate'],
        ':view' => sanitize($filePath), // Sanitize the file path before database insertion.
        ':owe' => $owingUsersList,
    ]);
}

/**
 * Sends email notifications for a newly posted bill to relevant users and an admin confirmation.
 * @param array $billDetails Associative array of validated bill data.
 * @param string $dbPath Relative path to the uploaded PDF file, used for constructing links.
 * @param array $config Associative array of email configuration (emailMap, from details, admin email, base URL).
 */
function sendBillNotifications(array $billDetails, string $dbPath, array $config): void
{
    $subject = 'New Bill Posted: ' . htmlspecialchars($billDetails['item']); // Add item to subject for clarity.
    $fromHeader = "From: " . htmlspecialchars($config['emailFromName']) . " <" . htmlspecialchars($config['emailFromAddress']) . ">"; // Construct From header.
    $headers = implode("\r\n", [ // Standard email headers for HTML email.
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        $fromHeader,
    ]) . "\r\n";

    // Construct absolute link to the bill PDF.
    $billViewLink = $config['baseUrl'] . "/" . htmlspecialchars($dbPath); // Prepend base URL.

    // Compose email body (HTML).
    $body = "<p style=\"font:14pt serif;\">Hello,</p>"
        . "<p style=\"font:14pt serif;\">"
        . "A new <strong>" . htmlspecialchars($billDetails['item']) . "</strong> bill was posted (billed on " . htmlspecialchars($billDetails['billDate']) . "), due " . htmlspecialchars($billDetails['dueDate']) . "."
        . "</p><ul style=\"font:14pt serif;\">"
        . "<li>Total: $" . number_format($billDetails['total'], 2) . "</li>" // Format currency.
        . "<li>Per person: $" . number_format($billDetails['cost'], 2) . "</li>"
        . "</ul>"
        . "<p style=\"font:14pt serif;\">"
        . "View/download it at <a href=\"" . $billViewLink . "\">the portal</a>." // Use absolute link.
        . "</p>"
        . "<p style=\"font:14pt serif;color:green;\">" . htmlspecialchars($config['emailFromName']) . "<br>" // Email signature.
        . "Contact: " . htmlspecialchars($config['emailFromAddress']) . "</p>";

    $emailedRecipientsForConfirmation = []; // Track who was emailed for admin confirmation.
    // Send email to each user in the default "owe" list who has a mapped email address.
    if (!empty($config['emailMap'])) { // Check if email map is available.
        foreach ($config['defaultOweArray'] as $personName) { // Iterate through users expected to owe.
            if (isset($config['emailMap'][$personName])) { // Check if user has an email address.
                $toEmail = $config['emailMap'][$personName];
                if (!mail($toEmail, $subject, $body, $headers)) { // Send email.
                    error_log("Mail to $toEmail failed for person $personName regarding bill item " . $billDetails['item']);
                } else {
                    $emailedRecipientsForConfirmation[$personName] = $toEmail; // Record successful send.
                }
            } else {
                error_log("No email address found in APP_USER_EMAILS for person: $personName");
            }
        }
    } else {
        error_log("Email map (APP_USER_EMAILS) is empty or invalid. No bill notifications sent.");
    }

    // Send a confirmation email to the admin.
    if (!empty($config['confirmationEmailTo'])) {
        $confSubject = 'Admin Confirmation: New Bill Posted - ' . htmlspecialchars($billDetails['item']);
        $confBody = "<p style=\"font:12pt monospace;\">A new bill was posted using the portal.</p>"
            . "<p style=\"font:12pt monospace;\">Item: " . htmlspecialchars($billDetails['item']) . "</p>"
            . "<p style=\"font:12pt monospace;\">Total: $" . number_format($billDetails['total'], 2) . "</p>"
            . "<p style=\"font:12pt monospace;\">Emailed to: " . htmlspecialchars(implode(', ', array_values($emailedRecipientsForConfirmation))) . "</p>"
            . "<hr><p style=\"font:12pt monospace;\">Original Subject: {$subject}</p>";
        if (!mail($config['confirmationEmailTo'], $confSubject, $confBody, $headers)) { // Send using same headers.
            error_log("Admin confirmation mail to {$config['confirmationEmailTo']} failed for bill item " . $billDetails['item']);
        }
    }
}

/**
 * Fetches a specific page of utility bills for the admin view.
 * @param PDO $dbConnection The PDO database connection object.
 * @param int $limit Number of bills per page.
 * @param int $offset Number of bills to offset.
 * @return array An array of bills for the current page.
 */
function getBillsForAdminPage(PDO $dbConnection, int $limit, int $offset): array
{
    $sql = "
      SELECT pmkBillID,fldDate,fldItem,fldTotal,fldCost,fldDue,fldStatus,fldView,fldOwe
        FROM tblUtilities
       ORDER BY fldDate DESC -- Show most recent bills first.
       LIMIT :limit OFFSET :offset
    ";
    $stmt = $dbConnection->prepare($sql);
    // Bind parameters for LIMIT and OFFSET to prevent SQL injection and ensure correct type.
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows for the current page.
}

/**
 * Gets the total count of all utility bills for admin pagination.
 * @param PDO $dbConnection The PDO database connection object.
 * @return int Total number of bills.
 */
function getTotalBillCountForAdmin(PDO $dbConnection): int
{
    $sql = 'SELECT COUNT(*) FROM tblUtilities'; // Simple count of all rows.
    $stmt = $dbConnection->prepare($sql);
    $stmt->execute();
    return (int)$stmt->fetchColumn(); // Returns the count as an integer.
}


// --- Admin Access Check ---
// Verify if the current user (from web server authentication) is in the list of authorized admins.
$currentRemoteUser = $_SERVER['REMOTE_USER'] ?? ''; // Get current authenticated user.
if (!isAdminUser($currentRemoteUser, $appAdminUsersList)) {
    // If not an admin, terminate script with an access denied message.
    // This is a hard exit as non-admins should not see any part of this page.
    die("Access denied. User '" . htmlspecialchars($currentRemoteUser) . "' is not authorized for admin portal.");
}

// Initialize arrays for holding error or success messages to be displayed to the user.
$error_messages = [];
$success_messages = [];
$dry_run_messages = []; // For messages specific to dry-run mode actions.

$is_dry_run_active = isDryRunActive(); // Check if dry-run mode is active.

// --- POST Request Handling (Adding a new bill) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token.
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_main_form'], $_POST['csrf_token'])) {
        $error_messages[] = "CSRF token validation failed. Please try submitting the form again.";
    } else {
        // Regenerate CSRF token after successful validation.
        $_SESSION['csrf_token_main_form'] = bin2hex(random_bytes(32));

        // Validate submitted form data.
        $validationResult = validateBillSubmissionData($_POST, $_FILES, $allowedBillItems);

        if (!empty($validationResult['errors'])) {
            $error_messages = array_merge($error_messages, $validationResult['errors']);
        } else {
            $validatedPostData = $validationResult['data'];

            if ($is_dry_run_active) {
                // --- DRY-RUN MODE ACTIVE ---
                $dry_run_messages[] = "DRY RUN: Form data validated successfully.";

                // Simulate file handling: Check if file appears valid based on initial checks.
                if (isset($_FILES['view']) && $_FILES['view']['error'] === UPLOAD_ERR_OK) {
                    // Further checks from handleBillFileUpload could be simulated here if needed,
                    // e.g., file size, type based on $_FILES data directly.
                    // For this exercise, we'll assume basic presence and no UPLOAD_ERR_* is sufficient for dry run.
                    $dry_run_messages[] = "DRY RUN: File '" . htmlspecialchars($_FILES['view']['name']) . "' appears valid and would have been processed.";
                } else {
                    // This case should ideally be caught by validateBillSubmissionData, but as a fallback:
                    $error_messages[] = "DRY RUN: File is missing or has an upload error.";
                }

                if(empty($error_messages)) { // Only add these if no file errors from above
                    $dry_run_messages[] = "DRY RUN: Bill data would have been saved to the database.";
                    $dry_run_messages[] = "DRY RUN: Calendar file (update_ics.php) would have been updated.";
                    $dry_run_messages[] = "DRY RUN: Notifications would have been sent.";
                }
                // Do NOT redirect in dry-run mode; stay on page to show messages.
            } else {
                // --- LIVE MODE ---
                $dbPath = null;
                try {
                    $dbPath = handleBillFileUpload(
                        $_FILES['view'],
                        $validatedPostData['year'],
                        $validatedPostData['item'],
                        $uploadBaseDir
                    );

                    if (!insertBillRecord($pdo, $validatedPostData, $dbPath, $defaultOweListStr)) {
                        $error_messages[] = "Failed to insert bill into database. Please check logs or contact support.";
                    } else {
                        include 'update_ics.php'; // Rebuild calendar.
                        $notificationConfig = [
                            'emailMap' => $emailMapArray,
                            'defaultOweArray' => $defaultOweListArray,
                            'emailFromName' => $appEmailFromName,
                            'emailFromAddress' => $appEmailFromAddress,
                            'confirmationEmailTo' => $appConfirmationEmailTo,
                            'baseUrl' => $appBaseUrl
                        ];
                        sendBillNotifications($validatedPostData, $dbPath, $notificationConfig);

                        $_SESSION['success_message'] = "New bill successfully added!";
                        header('Location: portal.php');
                        exit;
                    }
                } catch (RuntimeException $e) {
                    $error_messages[] = "File handling error: " . htmlspecialchars($e->getMessage());
                } catch (Exception $e) {
                    error_log("General error during POST processing: " . $e->getMessage());
                    $error_messages[] = "An unexpected error occurred. Please try again or contact support if the issue persists.";
                }
            }
        }
    }
}

// After POST processing or on a GET request, check for flash success messages from session.
if (isset($_SESSION['success_message'])) {
    $success_messages[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
// Check for dry run action messages from other scripts (like send_reminder.php)
if (isset($_SESSION['dry_run_action_message'])) {
    // Prepend to any dry_run_messages from this page's POST, or initialize if empty
    $dry_run_messages = array_merge([$_SESSION['dry_run_action_message']], $dry_run_messages);
    unset($_SESSION['dry_run_action_message']);
}


// --- GET Request Handling (Displaying bills and forms) ---

// Pagination setup for admin view
$billsPerPage = (int)($_ENV['APP_BILLS_PER_PAGE'] ?? 10); // Number of bills per page from .env or default.
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Get current page from URL, default to 1.
if ($currentPage < 1) { // Ensure current page is at least 1.
    $currentPage = 1;
}
$offset = ($currentPage - 1) * $billsPerPage; // Calculate database offset.

$totalBills = getTotalBillCountForAdmin($pdo); // Get total number of bills.
$totalPages = $totalBills > 0 ? ceil($totalBills / $billsPerPage) : 1; // Calculate total pages, ensure at least 1.

// If current page is beyond the total number of pages (and there are bills), redirect to the last valid page.
if ($currentPage > $totalPages && $totalBills > 0) {
    header('Location: portal.php?page=' . $totalPages);
    exit;
}

// Fetch bills for the current page.
$cells = getBillsForAdminPage($pdo, $billsPerPage, $offset);


// Generate a CSRF token for forms within the bills list (e.g., send reminder, update owe).
if (empty($_SESSION['csrf_token_list_forms'])) {
    $_SESSION['csrf_token_list_forms'] = bin2hex(random_bytes(32));
}
$csrfTokenListForms = $_SESSION['csrf_token_list_forms'];
?>
<main class="admin-area">

    <?php if ($is_dry_run_active): ?>
        <div class="messages dry-run-banner">
            <strong>TESTING/DRY-RUN MODE IS CURRENTLY ACTIVE.</strong> No actual data changes will be made or emails sent from the 'Add New Bill' form.
        </div>
    <?php endif; ?>

    <h2 class="section-title">Admin Portal</h2>

    <?php if (!empty($error_messages)): ?>
        <div class="messages error-messages">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($error_messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_messages)): ?>
        <div class="messages success-messages">
            <ul>
                <?php foreach ($success_messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($dry_run_messages)): ?>
        <div class="messages dry-run-info">
            <strong>Dry Run Information (No changes were made):</strong>
            <ul>
                <?php foreach ($dry_run_messages as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date Billed</th>
                    <th>Item</th>
                    <th>Total</th>
                    <th>Per Person</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cells)): ?>
                    <tr><td colspan="7">No bills found for this page.</td></tr>
                <?php else: ?>
                    <?php foreach ($cells as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['fldDate']) ?></td>
                            <td><?= htmlspecialchars($c['fldItem']) ?></td>
                            <td>$<?= htmlspecialchars(number_format((float)$c['fldTotal'], 2)) ?></td>
                            <td>$<?= htmlspecialchars(number_format((float)$c['fldCost'], 2)) ?></td>
                            <td>
                                <?php if ($c['fldStatus'] !== 'Paid'): ?>
                                    <form method="POST" action="send_reminder.php" style="margin:0">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfTokenListForms ?>">
                                        <input type="hidden" name="sendReminder" value="1">
                                        <input type="hidden" name="pmk" value="<?= htmlspecialchars((string)$c['pmkBillID']) ?>">
                                        <button class="badge badge-unpaid">
                                            <?= htmlspecialchars($c['fldDue']) ?>
                                        </button>
                                    </form
                            <?php else: ?>
                                <span class="badge badge-paid"><?= htmlspecialchars($c['fldDue']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="payment-cell">
                            <form method="POST" action="update_owe.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrfTokenListForms ?>">
                                <input type="hidden" name="updateNames" value="1">
                                <input type="hidden" name="id2" value="<?= htmlspecialchars((string)$c['pmkBillID']) ?>">
                                <?php
                                $currentOwedNames = array_map('trim', explode(',', $c['fldOwe']));
                                // $defaultOweListArray now serves as the list of all possible users from config
                                $paidThisBill = array_diff($defaultOweListArray, $currentOwedNames);
                                foreach ($defaultOweListArray as $personName) {
                                    $isChecked = in_array($personName, $paidThisBill) ? 'checked' : '';
                                    // Sanitize personName for display and value attribute, though it comes from config
                                    $safePersonName = htmlspecialchars($personName);
                                    echo "<label><input type=\"checkbox\" name=\"paidPeople[]\" value=\"$safePersonName\" $isChecked> $safePersonName</label> ";
                                }
                                ?>
                                <button>Update</button>
                            </form>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars("{$c['fldView']}") ?>" target="_blank"
                                class="icon-link">View</a>
                            |
                            <a href="<?= htmlspecialchars("{$c['fldView']}") ?>" download
                                class="icon-link">Download</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            Page <?= $currentPage ?> of <?= $totalPages ?>
            <ul class="pagination-links">
                <?php if ($currentPage > 1): ?>
                    <li><a href="?page=<?= $currentPage - 1 ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li>
                        <a href="?page=<?= $i ?>" class="<?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <li><a href="?page=<?= $currentPage + 1 ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <h2 class="section-title">Add New Bill</h2>
    <div class="form-panel admin-area">
        <form method="POST" action="portal.php" enctype="multipart/form-data">
            <?php
            // Generate and store CSRF token for the main form
            if (empty($_SESSION['csrf_token_main_form'])) { // Use a unique name for this form's token
                $_SESSION['csrf_token_main_form'] = bin2hex(random_bytes(32));
            }
            $csrfTokenMainForm = $_SESSION['csrf_token_main_form'];
            ?>
            <input type="hidden" name="csrf_token" value="<?= $csrfTokenMainForm ?>">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" required>

            <label for="item">Item</label>
            <select id="item" name="item" required>
                <option>Gas</option>
                <option>Electric</option>
                <option>Internet</option>
            </select>

            <label for="total">Total</label>
            <input type="number" id="total" name="total" oninput="updateField()" step="0.01" required>

            <label for="cost">Per Person</label>
            <input type="number" id="cost" name="cost" readonly step="0.01">

            <label for="due">Due Date</label>
            <input type="date" id="due" name="due" required>

            <label for="view">PDF Upload</label>
            <input type="file" id="view" name="view" accept="application/pdf" required>

            <button type="submit">Submit New Bill</button>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>