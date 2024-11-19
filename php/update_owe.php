<?php
include 'connect-DB.php';

if (isset($_POST['updateNames'])) {
    $id = htmlspecialchars($_POST['id2']);
    $names = htmlspecialchars($_POST['updateNames']);
    $sql = 'UPDATE tblUtilities SET fldOwe = ? WHERE pmkBillID = ?';
    $statement = $pdo->prepare($sql);
    $data = array($names, $id);
    $statement->execute($data);

    // Redirect back to the main page after updating
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>

