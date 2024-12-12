<?php
include '../app/connect-DB.php';

if (isset($_POST['updateNames'])) {
    $id = htmlspecialchars($_POST['id2']);
    $paidPeople = isset($_POST['paidPeople']) ? $_POST['paidPeople'] : [];
    $allPeople = ['Aaron', 'Owen', 'Ben']; // Replace with actual names

    // Determine who still owes money
    $unpaidPeople = array_diff($allPeople, $paidPeople);
    $names = implode(', ', $unpaidPeople);

    // Update the fldOwe field
    $sql = 'UPDATE tblUtilities SET fldOwe = ? WHERE pmkBillID = ?';
    $statement = $pdo->prepare($sql);
    $data = array($names, $id);
    $statement->execute($data);

    // Update the status based on whether anyone still owes money
    if (empty($unpaidPeople)) {
        $sql = 'UPDATE tblUtilities SET fldStatus = "Paid" WHERE pmkBillID = ?';
    } else {
        $sql = 'UPDATE tblUtilities SET fldStatus = "Unpaid" WHERE pmkBillID = ?';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$id]);

    include 'update_ics.php';

    // Redirect back to the portal page
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>