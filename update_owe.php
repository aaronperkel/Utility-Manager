<?php
include 'connect-DB.php';

if (isset($_POST['updateNames'])) {
    $id = htmlspecialchars($_POST['id2']);
    $paidPeople = isset($_POST['paidPeople']) ? $_POST['paidPeople'] : [];
    $allPeople = ['Alice', 'Bob', 'Charlie', 'David'];

    // Determine who still owes money
    $unpaidPeople = array_diff($allPeople, $paidPeople);
    $names = implode(', ', $unpaidPeople);

    $sql = 'UPDATE tblCSFair SET fldOwe = ? WHERE pmkBillID = ?';
    $statement = $pdo->prepare($sql);
    $data = array($names, $id);
    $statement->execute($data);

    // Update the status based on whether anyone still owes money
    if (empty($unpaidPeople)) {
        $sql = 'UPDATE tblCSFair SET fldStatus = "Paid" WHERE pmkBillID = ?';
    } else {
        $sql = 'UPDATE tblCSFair SET fldStatus = "Unpaid" WHERE pmkBillID = ?';
    }
    $statement = $pdo->prepare($sql);
    $statement->execute([$id]);

    // Redirect back to the main page after updating
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>