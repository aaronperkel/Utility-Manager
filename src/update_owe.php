<?php
// update_owe.php
// Handles updates to who owes for a specific bill based on checkbox submissions from portal.php.
// Works with the normalized schema: tblPeople, tblBillOwes, tblUtilities.

session_start(); // Start session for CSRF token access and potential flash messages.
include './connect-DB.php'; // Includes $pdo, and helper functions like isDryRunActive().
// top.php is not strictly needed as this is an action script, but connect-DB.php is essential.

$is_dry_run_active = isDryRunActive(); // Check dry-run status.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_list_forms']) || !hash_equals($_SESSION['csrf_token_list_forms'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "CSRF token validation failed. Please try again.";
        header('Location: portal.php');
        exit;
    }

    // 2. Get Inputs
    $billID = filter_input(INPUT_POST, 'billID', FILTER_VALIDATE_INT);
    // $paidPersonIDs will contain an array of personIDs for whom the checkbox was checked (meaning they "paid").
    $paidPersonIDs = $_POST['paidPersonIDs'] ?? [];
    // Ensure $paidPersonIDs is an array, even if only one or no boxes are checked.
    if (!is_array($paidPersonIDs)) {
        $paidPersonIDs = [];
    }
    // Sanitize each ID in the array
    $paidPersonIDs = array_map('intval', $paidPersonIDs);


    if (!$billID) {
        $_SESSION['error_message'] = "No bill ID provided for updating payment status.";
        header('Location: portal.php');
        exit;
    }

    // 3. Fetch All People from tblPeople (to know the complete set of users)
    try {
        $peopleStmt = $pdo->query("SELECT personID, personName FROM tblPeople ORDER BY personName ASC");
        $allPeople = $peopleStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching people in update_owe.php: " . $e->getMessage());
        $_SESSION['error_message'] = "Could not load user data. Payment status update failed.";
        header('Location: portal.php?bill_error_id=' . $billID);
        exit;
    }

    if (empty($allPeople)) {
        $_SESSION['error_message'] = "No users found in the system. Cannot update payment status.";
        header('Location: portal.php?bill_error_id=' . $billID);
        exit;
    }

    $dryRunMessages = [];
    $peopleStillOwingCount = 0;

    // 4. Logic to update tblBillOwes and tblUtilities.fldStatus
    try {
        if (!$is_dry_run_active) {
            $pdo->beginTransaction();
        }

        // First, get the current global status of the bill.
        // We don't want to add people to tblBillOwes if the bill is already globally 'Paid'.
        $utilStatusStmt = $pdo->prepare("SELECT fldStatus FROM tblUtilities WHERE pmkBillID = :billID");
        $utilStatusStmt->execute([':billID' => $billID]);
        $currentBillGlobalStatus = $utilStatusStmt->fetchColumn();

        if ($currentBillGlobalStatus === 'Paid' && !$is_dry_run_active) {
            // If the bill is already globally paid, we typically shouldn't be making people owe again,
            // unless this action is specifically to "unpay" it.
            // For now, if globally paid, we'll mostly just ensure tblBillOwes is clear for this bill.
            // And if someone is marked as "unpaid" (checkbox unchecked), that's an issue.
            // This logic might need refinement based on desired behavior for "unpaying" a globally paid bill.
            // For simplicity here: if bill is globally 'Paid', then effectively everyone has paid.
            // We will primarily clear any stragglers from tblBillOwes.
        }

        $currentPeopleOwingForThisBill = 0;

        foreach ($allPeople as $person) {
            $personID = $person['personID'];
            $personName = $person['personName'];

            if (in_array($personID, $paidPersonIDs, true)) {
                // This person is marked as "paid" (checkbox was checked).
                // So, REMOVE their record from tblBillOwes for this billID.
                if ($is_dry_run_active) {
                    $dryRunMessages[] = "DRY RUN: Would REMOVE " . htmlspecialchars($personName) . " (ID: {$personID}) from owing for bill ID {$billID}.";
                } else {
                    $stmtDelete = $pdo->prepare("DELETE FROM tblBillOwes WHERE billID = :billID AND personID = :personID");
                    $stmtDelete->execute([':billID' => $billID, ':personID' => $personID]);
                }
            } else {
                // This person is marked as "owing" (checkbox was NOT checked).
                // So, ENSURE their record IS in tblBillOwes for this billID.
                // The INSERT IGNORE handles cases where they might already be there.
                // This action can potentially revert a globally 'Paid' bill to 'Unpaid'.
                if ($is_dry_run_active) {
                    $dryRunMessages[] = "DRY RUN: Would ADD/KEEP " . htmlspecialchars($personName) . " (ID: {$personID}) as owing for bill ID {$billID}. (Current global status: {$currentBillGlobalStatus})";
                } else {
                    $stmtInsert = $pdo->prepare("INSERT IGNORE INTO tblBillOwes (billID, personID) VALUES (:billID, :personID)");
                    $stmtInsert->execute([':billID' => $billID, ':personID' => $personID]);
                }
                $currentPeopleOwingForThisBill++; // This person will contribute to the count of those owing.
            }
        }

        // Determine the new overall status based on whether anyone is left in tblBillOwes for this bill.
        // This check is done AFTER all individual add/remove operations.
        if (!$is_dry_run_active) { // For live mode, re-query tblBillOwes to get the true count after modifications.
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tblBillOwes WHERE billID = :billID");
            $countStmt->execute([':billID' => $billID]);
            $finalOwingCount = (int)$countStmt->fetchColumn();
        } else { // For dry run, use the simulated count.
            $finalOwingCount = $currentPeopleOwingForThisBill;
        }

        $newOverallStatus = ($finalOwingCount === 0) ? 'Paid' : 'Unpaid';

        if ($is_dry_run_active) {
            $dryRunMessages[] = "DRY RUN: Based on selections, {$finalOwingCount} people would owe. Overall status for bill ID {$billID} would be set to '{$newOverallStatus}'.";
            if ($currentBillGlobalStatus !== $newOverallStatus) {
                 $dryRunMessages[] = "DRY RUN: Calendar file (update_ics.php) would have been updated due to status change.";
            }
        } else { // Live mode
            $statusActuallyChanged = false;
            if ($currentBillGlobalStatus !== $newOverallStatus) {
                $stmtUpdateStatus = $pdo->prepare("UPDATE tblUtilities SET fldStatus = :status WHERE pmkBillID = :id");
                $stmtUpdateStatus->execute([':status' => $newOverallStatus, ':id' => $billID]);
                $statusActuallyChanged = true;
            }

            $pdo->commit(); // Commit transaction

            if ($statusActuallyChanged) {
                include 'update_ics.php'; // Update calendar file only if global status actually changed.
            }
            $_SESSION['success_message'] = "Payment statuses for bill ID {$billID} updated successfully. Overall status: {$newOverallStatus}.";
        }

        if ($is_dry_run_active && !empty($dryRunMessages)) {
            $_SESSION['dry_run_action_message'] = implode("<br>", $dryRunMessages);
        }

    } catch (PDOException $e) {
        if (!$is_dry_run_active && isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating payment status for bill ID {$billID}: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error updating payment status. Details: " . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        if (!$is_dry_run_active && isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error in update_owe.php for bill ID {$billID}: " . $e->getMessage());
        $_SESSION['error_message'] = "An unexpected error occurred. Details: " . htmlspecialchars($e->getMessage());
    }

    // 5. Redirect back
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'portal.php'));
    exit;

} else {
    // If not a POST request, redirect to portal or show an error.
    $_SESSION['error_message'] = "Invalid request method for updating payment status.";
    header('Location: portal.php');
    exit;
}
?>