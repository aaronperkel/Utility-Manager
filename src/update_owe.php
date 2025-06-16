<?php
session_start(); // Start session for CSRF token access
include './connect-DB.php';

if (isset($_POST['updateNames'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_list_forms']) || !hash_equals($_SESSION['csrf_token_list_forms'], $_POST['csrf_token'])) {
        die("CSRF token validation failed for update owe.");
    }
    // Same reasoning as send_reminder.php for not regenerating token here.
    // (Token is general for list actions, portal.php will regen if it becomes empty).

    $billId = htmlspecialchars($_POST['id2']); // Get the bill ID from POST data and sanitize.
    $paidPeopleInput = isset($_POST['paidPeople']) ? (array)$_POST['paidPeople'] : []; // Ensure it's an array.

    // Load the configured list of all people who could owe from .env
    // This ensures consistency with portal.php and other parts of the application.
    $allAppUsersStr = $_ENV['APP_USERS_OWING'] ?? 'Aaron,Owen,Ben'; // Fallback if not set.
    $allAppUsersArray = array_map('trim', explode(',', $allAppUsersStr));

    // Determine who still owes money by comparing the submitted paid people against all possible users.
    // Only names present in $allAppUsersArray and not in $paidPeopleInput are considered unpaid.
    $unpaidPeople = array_diff($allAppUsersArray, $paidPeopleInput);
    $unpaidPeople = array_diff($allPeople, $paidPeople);
    $fldOweValue = implode(', ', $unpaidPeople); // Create comma-separated string for fldOwe.

    // Update the fldOwe field in the database for the specific bill.
    $sqlSetOwe = 'UPDATE tblUtilities SET fldOwe = :owe WHERE pmkBillID = :id';
    $stmtSetOwe = $pdo->prepare($sqlSetOwe);
    $stmtSetOwe->execute([':owe' => $fldOweValue, ':id' => $billId]);

    // Update the fldStatus field based on whether anyone still owes money.
    $newStatus = empty($unpaidPeople) ? 'Paid' : 'Unpaid';
    $sqlSetStatus = 'UPDATE tblUtilities SET fldStatus = :status WHERE pmkBillID = :id';
    $stmtSetStatus = $pdo->prepare($sqlSetStatus);
    $stmtSetStatus->execute([':status' => $newStatus, ':id' => $billId]);

    // After updating the bill, regenerate the iCalendar file.
    include 'update_ics.php';

    // Redirect the user back to the page they came from (likely portal.php).
    // This prevents form resubmission issues if the user refreshes the page.
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'portal.php')); // Fallback to portal.php if HTTP_REFERER is not set.
    exit; // Ensure script termination after redirect.
}
// If the script is accessed without the required POST data, it will do nothing and exit.
?>