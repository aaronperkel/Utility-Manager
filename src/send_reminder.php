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

    // Fetch bill details from the database (fldOwe is no longer in tblUtilities).
    $billDetailsStmt = $pdo->prepare(
        'SELECT pmkBillID, fldDue, fldItem, fldTotal, fldCost
         FROM tblUtilities WHERE pmkBillID = :id'
    );
    $billDetailsStmt->execute([':id' => $billId]);
    $bill = $billDetailsStmt->fetch(PDO::FETCH_ASSOC);

    $dryRunActionMessage = ""; // To accumulate messages for dry run

    if ($bill) {
        $billDueDate = $bill['fldDue'];
        $billItem = $bill['fldItem'];
        $billTotal = (float)$bill['fldTotal'];
        $billCostPerPerson = (float)$bill['fldCost'];

        // Fetch people who owe for this specific bill from tblBillOwes and tblPeople.
        $peopleOwingStmt = $pdo->prepare("
            SELECT p.personName
            FROM tblPeople p
            JOIN tblBillOwes bo ON p.personID = bo.personID
            WHERE bo.billID = :billID
        ");
        $peopleOwingStmt->execute([':billID' => $billId]);
        // Get a list of names of people who owe for this bill.
        $owedPeopleNameList = $peopleOwingStmt->fetchAll(PDO::FETCH_COLUMN);

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
        $sentToForConfirmation = []; // Stores "Name <Email>" for admin confirmation

        if (empty($owedPeopleNameList) && !$is_dry_run_active) {
            // If no one owes (e.g., bill already marked paid or tblBillOwes is empty for this bill),
            // no individual reminders to send. Maybe send a different admin note or log.
            error_log("No one currently owes for bill ID {$billId} (Item: {$billItem}). No reminders sent.");
        } elseif ($is_dry_run_active && empty($owedPeopleNameList)) {
            $dryRunActionMessage .= "DRY RUN: No one currently listed as owing for bill '" . htmlspecialchars($billItem) . "' (ID: {$billId}). No reminder simulations for individuals.<br>";
        }


        foreach ($owedPeopleNameList as $personName) {
            if (empty($personName) || !isset($emailMapArray[$personName])) {
                error_log("Skipping reminder for '{$personName}' (Bill ID: {$billId}): name is empty or not in email map.");
                continue;
            }
            $toEmailAddress = $emailMapArray[$personName];

            $formattedDueDate = "";
            try {
                // Format due date for email body
                $dateObjForBody = new DateTime($billDueDate);
                $formattedDueDate = $dateObjForBody->format("F j, Y"); // e.g., "July 15, 2024"
            } catch(Exception $e){
                $formattedDueDate = $billDueDate; // Fallback to YYYY-MM-DD string
            }

            // Construct email body for each person.
            $body = "<p style=\"font:14pt serif;\">Hello " . htmlspecialchars($personName) . ",</p>"
                . "<p style=\"font:14pt serif;\">This is a reminder that your <strong>" . htmlspecialchars($billItem) . "</strong> bill (total: $" . number_format($billTotal, 2) . ") is due on " . htmlspecialchars($formattedDueDate) . ".</p>"
                . "<p style=\"font:14pt serif;\">Your share is: $" . number_format($billCostPerPerson, 2) . ".</p>"
                . "<p style=\"font:14pt serif;\">Please login to <a href=\"" . htmlspecialchars($portalLink) . "\">" . htmlspecialchars($appEmailFromName) . " Portal</a> for more info.</p>"
                . "<p style=\"font:14pt serif;color:green;\">" . htmlspecialchars($appEmailFromName) . "<br>"
                . "Contact: " . htmlspecialchars($appEmailFromAddress) . "</p>";

            if ($is_dry_run_active) {
                $dryRunActionMessage .= "DRY RUN: Reminder for bill '" . htmlspecialchars($billItem) . "' (Due: " . htmlspecialchars($billDueDate) . ") would have been sent to " . htmlspecialchars($personName) . " (" . htmlspecialchars($toEmailAddress) . "). Subject: " . $subject . "<br>";
            } else {
                if (!mail($toEmailAddress, $subject, $body, $headers)) {
                    error_log("Mail to {$toEmailAddress} failed for bill ID {$billId}, item {$billItem}.");
                } else {
                    $sentToForConfirmation[] = htmlspecialchars($personName) . " &lt;" . htmlspecialchars($toEmailAddress) . "&gt;";
                }
            }
        }

        // Admin confirmation email
        // Send confirmation if reminders were processed (even in dry run for simulation details)
        // or if the list of people owing was initially not empty.
        if (!empty($owedPeopleNameList) && !empty($appConfirmationEmailTo)) {
            $confirmSubject = ($is_dry_run_active ? "[DRY RUN] " : "") . "Reminder Batch Processed: " . htmlspecialchars($billItem) . " due " . htmlspecialchars($billDueDate);

            $processedListStr = "";
            if ($is_dry_run_active) {
                // In dry run, list all people who would have been processed.
                $tempDryRunRecipients = [];
                foreach($owedPeopleNameList as $pName) {
                    $tempDryRunRecipients[] = htmlspecialchars($pName) . (isset($emailMapArray[$pName]) ? " (&lt;" . htmlspecialchars($emailMapArray[$pName]) . "&gt;)" : " (No email in map)");
                }
                $processedListStr = empty($tempDryRunRecipients) ? 'None (no one found in tblBillOwes or email map issues)' : implode(', ', $tempDryRunRecipients);
            } else {
                // In live mode, list who emails were actually sent to.
                $processedListStr = empty($sentToForConfirmation) ? 'None (or all failed, check logs)' : implode(', ', $sentToForConfirmation);
            }

            $confirmBody = "<p style=\"font:12pt monospace;\">Reminder emails were " . ($is_dry_run_active ? "simulated" : "processed") . " for the " . htmlspecialchars($billItem) . " bill (due " . htmlspecialchars($billDueDate) . ").</p>"
                . "<hr>"
                . "<p style=\"font:12pt monospace;\">Processed for: {$processedListStr}</p>"
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