<?php
include './connect-DB.php';

if (isset($_POST['updateStatus'])) {
    $id = htmlspecialchars($_POST['id']);
    $sql = 'UPDATE tblUtilities SET fldStatus = "Unpaid" WHERE pmkBillID = ?';
    $statement = $pdo->prepare($sql);
    $data = array($id);
    $statement->execute($data);

    include "update_ics.php";

    // Redirect back to the main page after updating
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>