<?php
// send_reminder.php - send email reminders via PHP mail()
session_start(); // Start session for CSRF token access

include './connect-DB.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['sendReminder'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_list_forms']) || !hash_equals($_SESSION['csrf_token_list_forms'], $_POST['csrf_token'])) {
        die("CSRF token validation failed for send reminder.");
    }
    // It's good practice to use the token once, but for list forms that might refresh
    // or allow multiple actions, regeneration might be too aggressive or needs careful handling.
    // For now, we won't regenerate it here to allow multiple reminders from the same page load.
    // (Token is general for list actions, portal.php will regen if it becomes empty).

    $billId = htmlspecialchars($_POST['pmk']); // Get Bill ID from POST and sanitize.
    $is_dry_run_active = isDryRunActive(); // Check dry run status from connect-DB.php

    // Load configurations from .env variables.
    $appBaseUrl = rtrim($_ENV['APP_BASE_URL'] ?? 'https://utilities.example.com', '/');
    $userEmailsJson = $_ENV['APP_USER_EMAILS'] ?? '{}';
    $emailMapArray = json_decode($userEmailsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse APP_USER_EMAILS JSON in send_reminder.php: " . json_last_error_msg());
        $emailMapArray = [];
    }
    $appEmailFromAddress = $_ENV['APP_EMAIL_FROM_ADDRESS'] ?? 'utilities@example.com';
    $appEmailFromName = $_ENV['APP_EMAIL_FROM_NAME'] ?? '81 Buell Utilities';
    $appConfirmationEmailTo = $_ENV['APP_CONFIRMATION_EMAIL_TO'] ?? 'admin@example.com';

    // Fetch bill details from the database.
    $stmt = $pdo->prepare(
        'SELECT fldDue, fldOwe, fldItem, fldTotal, fldCost
         FROM tblUtilities WHERE pmkBillID = :id'
    );
    $stmt->execute([':id' => $billId]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    $dryRunActionMessage = ""; // To accumulate messages for dry run

    if ($bill) {
        $billDueDate = $bill['fldDue']; // Date string e.g. YYYY-MM-DD
        $billItem = $bill['fldItem'];
        $billTotal = (float)$bill['fldTotal'];
        $billCostPerPerson = (float)$bill['fldCost'];

        $owedPeopleList = array_map('trim', explode(',', $bill['fldOwe']));

        // Determine subject urgency based on due date.
        try {
            $dueDateObj = new DateTime($billDueDate);
            $todayObj = new DateTime();
            $intervalDays = $todayObj > $dueDateObj ? 0 : $todayObj->diff($dueDateObj)->days;
        } catch (Exception $e) {
            error_log("Error parsing due date '{$billDueDate}' for bill ID {$billId}: " . $e->getMessage());
            $intervalDays = 0;
        }

        $subject = ($intervalDays <= 3)
            ? "URGENT: Reminder - " . htmlspecialchars($billItem) . " Bill Due Soon"
            : "Reminder: " . htmlspecialchars($billItem) . " Bill Due";

        $fromHeader = "From: " . htmlspecialchars($appEmailFromName) . " <" . htmlspecialchars($appEmailFromAddress) . ">";
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            $fromHeader,
        ]) . "\r\n";

        $portalLink = $appBaseUrl . '/index.php';
        $sentToForConfirmation = [];

        foreach ($owedPeopleList as $name) {
            if (empty($name) || !isset($emailMapArray[$name])) {
                error_log("Skipping reminder for '{$name}' (Bill ID: {$billId}): name is empty or not in email map.");
                continue;
            }
            $toEmailAddress = $emailMapArray[$name];

            $formattedDueDate = "";
            try {
                $dateObjForBody = new DateTime($billDueDate);
                $formattedDueDate = $dateObjForBody->format("F j, Y");
            } catch(Exception $e){
                $formattedDueDate = $billDueDate; // Fallback to original string
            }

            $body = "<p style=\"font:14pt serif;\">Hello " . htmlspecialchars($name) . ",</p>"
                . "<p style=\"font:14pt serif;\">This is a reminder that your <strong>" . htmlspecialchars($billItem) . "</strong> bill (total: $" . number_format($billTotal, 2) . ") is due on " . htmlspecialchars($formattedDueDate) . ".</p>"
                . "<p style=\"font:14pt serif;\">Your share is: $" . number_format($billCostPerPerson, 2) . ".</p>"
                . "<p style=\"font:14pt serif;\">Please login to <a href=\"" . htmlspecialchars($portalLink) . "\">" . htmlspecialchars($appEmailFromName) . " Portal</a> for more info.</p>"
                . "<p style=\"font:14pt serif;color:green;\">" . htmlspecialchars($appEmailFromName) . "<br>"
                . "Contact: " . htmlspecialchars($appEmailFromAddress) . "</p>";

            if ($is_dry_run_active) {
                $dryRunActionMessage .= "DRY RUN: Reminder for bill '" . htmlspecialchars($billItem) . "' (Due: " . htmlspecialchars($billDueDate) . ") would have been sent to " . htmlspecialchars($name) . " (" . htmlspecialchars($toEmailAddress) . "). Subject: " . $subject . "<br>";
            } else {
                if (!mail($toEmailAddress, $subject, $body, $headers)) {
                    error_log("Mail to {$toEmailAddress} failed for bill ID {$billId}, item {$billItem}.");
                } else {
                    $sentToForConfirmation[] = $name . " <" . $toEmailAddress . ">";
                }
            }
        }

        // Admin confirmation email
        if (count($owedPeopleList) > 0 && !empty($appConfirmationEmailTo)) { // Only send if reminders were attempted for non-empty owed list
            $confirmSubject = ($is_dry_run_active ? "[DRY RUN] " : "") . "Reminder Batch Sent: " . htmlspecialchars($billItem) . " due " . htmlspecialchars($billDueDate);
            $sentListStr = empty($sentToForConfirmation) && !$is_dry_run_active ? 'None (or all failed, check logs)' : htmlspecialchars(implode(', ', $is_dry_run_active ? $owedPeopleList : $sentToForConfirmation));

            $confirmBody = "<p style=\"font:12pt monospace;\">Reminder emails were " . ($is_dry_run_active ? "simulated" : "sent") . " for the " . htmlspecialchars($billItem) . " bill (due " . htmlspecialchars($billDueDate) . ").</p>"
                . "<hr>"
                . "<p style=\"font:12pt monospace;\">Attempted to process for: {$sentListStr}</p>"
                . "<p style=\"font:12pt monospace;\">Original Subject: {$subject}</p>";

            if ($is_dry_run_active) {
                 $dryRunActionMessage .= "DRY RUN: Admin confirmation email (Subject: " . $confirmSubject . ") would have been sent to " . htmlspecialchars($appConfirmationEmailTo) . ".<br>";
            } else {
                if (!mail($appConfirmationEmailTo, $confirmSubject, $confirmBody, $headers)) {
                     error_log("Admin confirmation mail for reminders (bill ID {$billId}) failed to send to {$appConfirmationEmailTo}.");
                }
            }
        }
    } else {
        error_log("Bill with ID {$billId} not found for sending reminder.");
        if ($is_dry_run_active) {
            $dryRunActionMessage .= "DRY RUN: Bill with ID {$billId} not found. No reminders processed.<br>";
        }
    }

    if ($is_dry_run_active && !empty($dryRunActionMessage)) {
        $_SESSION['dry_run_action_message'] = $dryRunActionMessage;
    } elseif (!$is_dry_run_active) {
        $_SESSION['success_message'] = "Reminders for bill '" . htmlspecialchars($billItem ?? 'Unknown') . "' processed.";
    }


    // Redirect back to the referring page (likely portal.php).
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'portal.php'));
    exit; // Ensure script termination.
}
// If script is accessed without POST 'sendReminder', it does nothing and exits.
?>