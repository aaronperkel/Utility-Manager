<?php 
// send_custom_email.php
// Allows an admin user to send a custom-composed email to all users defined in the email map.
// Note: This script currently lacks CSRF protection for the form submission.

session_start(); // Start session to potentially use for CSRF tokens or admin checks if refactored.
include 'top.php'; // Includes header, nav, and DB connection (which loads .env variables).

// --- Admin Access Check ---
// Note: This is a hardcoded admin check. For consistency, this should use the
// isAdminUser() function and APP_ADMIN_USERS list from .env, similar to portal.php.
$currentUser = $_SERVER['REMOTE_USER'] ?? '';
if ($currentUser !== 'aperkel') { // Simplified for directness, but should be made configurable.
    die("Access denied. User '" . htmlspecialchars($currentUser) . "' is not authorized.");
}

// --- Configuration Loading (from .env, loaded by connect-DB.php in top.php) ---
$appEmailFromAddress = $_ENV['APP_EMAIL_FROM_ADDRESS'] ?? 'utilities@example.com';
$appEmailFromName = $_ENV['APP_EMAIL_FROM_NAME'] ?? '81 Buell Utilities';
$appConfirmationEmailTo = $_ENV['APP_CONFIRMATION_EMAIL_TO'] ?? 'admin@example.com';

$userEmailsJson = $_ENV['APP_USER_EMAILS'] ?? '{}';
$emailMapArray = json_decode($userEmailsJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Failed to parse APP_USER_EMAILS JSON in send_custom_email.php: " . json_last_error_msg());
    $emailMapArray = []; // Default to empty map on error to prevent email sending issues.
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: Implement CSRF token generation and validation here.
    // Example:
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token_custom_email'], $_POST['csrf_token'])) {
    //     die("CSRF token validation failed.");
    // }
    // $_SESSION['csrf_token_custom_email'] = bin2hex(random_bytes(32)); // Regenerate after use or on form display.


    // 1) Sanitize inputs from the form.
    $subject = trim($_POST['subject'] ?? 'No Subject'); // Default subject if empty.
    $bodyRaw = trim($_POST['body'] ?? '');

    // 2) Process raw body text into HTML:
    //    - Split text into paragraphs based on one or more empty lines.
    //    - Convert single newlines within paragraphs to <br> tags.
    //    - HTML escape paragraph content to prevent XSS.
    $paras = preg_split('/\R\R+/', $bodyRaw, -1, PREG_SPLIT_NO_EMPTY);
    $htmlBody = '';
    foreach ($paras as $p) {
        $cleanParagraph = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        $cleanParagraphWithBreaks = nl2br($cleanParagraph); // Convert newlines to <br>
        $htmlBody .= "<p style=\"font: 14pt serif;\">{$cleanParagraphWithBreaks}</p>\n";
    }

    // 3) Append a standard signature (using configured details).
    // Note: The original signature with phone number is removed unless also made configurable.
    $htmlBody .= "<p style=\"font: 14pt serif; margin-top: 20px;\">"
              . "<span style=\"color: green;\">" . htmlspecialchars($appEmailFromName) . "</span><br>"
              . "Contact: " . htmlspecialchars($appEmailFromAddress)
              . "</p>";

    // 4) Prepare email headers using configured "From" address and name.
    $fromHeader = "From: " . htmlspecialchars($appEmailFromName) . " <" . htmlspecialchars($appEmailFromAddress) . ">";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= $fromHeader . "\r\n";

    // 5) Use the configured email map for recipients.
    // $emailMapArray is already loaded from .env.

    $sentToForConfirmation = []; // For admin confirmation.

    // 6) Send email to each recipient in the map.
    if (!empty($emailMapArray)) {
        foreach ($emailMapArray as $name => $toEmailAddress) {
            if (empty($toEmailAddress) || !filter_var($toEmailAddress, FILTER_VALIDATE_EMAIL)) {
                error_log("Invalid email address for {$name}: {$toEmailAddress}. Skipping.");
                continue;
            }
            if (!mail($toEmailAddress, $subject, $htmlBody, $headers)) {
                error_log("Custom email to {$toEmailAddress} (for {$name}) with subject '{$subject}' failed to send.");
            } else {
                $sentToForConfirmation[] = $name . " <" . $toEmailAddress . ">";
            }
        }
    } else {
        error_log("APP_USER_EMAILS is empty or invalid. No custom emails sent.");
        // Consider adding a user-facing error message here or redirecting with an error.
    }


    // 7) Send a confirmation email to the admin, if configured.
    if (!empty($appConfirmationEmailTo) && filter_var($appConfirmationEmailTo, FILTER_VALIDATE_EMAIL)) {
        $sentListStr = empty($sentToForConfirmation) ? 'None (check logs/config)' : implode(', ', $sentToForConfirmation);
        $confirmSubject = 'Admin Confirmation: Custom Email Sent';
        $confirmBody  = "<p style=\"font:12pt monospace;\">A custom email was sent from the portal.</p>"
                     . "<p style=\"font:12pt monospace;\"><b>Subject:</b> " . htmlspecialchars($subject) . "</p>"
                     . "<p style=\"font:12pt monospace;\"><b>Attempted to send to:</b> {$sentListStr}</p>"
                     . "<hr><h3>Original Message Body:</h3>" . $htmlBody; // Include the body for admin reference.

        if (!mail($appConfirmationEmailTo, $confirmSubject, $confirmBody, $headers)) {
            error_log("Admin confirmation for custom email failed to send to {$appConfirmationEmailTo}.");
        }
    }

    // 8) Redirect back to portal.php (or another confirmation page).
    // Consider using session flash messages for success/error feedback on portal.php.
    $_SESSION['success_message'] = "Custom email has been dispatched."; // Example flash message
    header('Location: portal.php');
    exit; // Ensure script termination after redirect.
}

// TODO: Generate and include CSRF token in the form below.
// $csrfTokenCustomEmail = $_SESSION['csrf_token_custom_email'] = bin2hex(random_bytes(32));
?>

<main class="form-area">
  <h2 class="section-title">Send Custom Email to All Users</h2>
  <div class="form-panel">
    <form method="POST" action="send_custom_email.php"> <!-- Action should point to itself -->
      <!-- <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfTokenCustomEmail ?? '') ?>"> -->
      <label for="subject">Subject</label>
      <input type="text" id="subject" name="subject" required>

      <label for="body">Message</label>
      <textarea id="body" name="body" rows="6" required></textarea>

      <button type="submit">Send Email</button>
    </form>
  </div>
</main>

<?php include 'footer.php'; ?>