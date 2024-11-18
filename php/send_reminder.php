<?php
include 'connect-DB.php';

if (isset($_POST['sendReminder'])) {
    $id = htmlspecialchars($_POST['pmk']);
    $sql = 'SELECT fldDue, fldOwe FROM tblCSFair WHERE pmkBillID = ?';
    $statement = $pdo->prepare($sql);
    $data = array($id);
    $statement->execute($data);

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    // Update the $owedString to properly format the list of people
    $owedArray = array_map('trim', explode(',', $result['fldOwe']));
    $owedString = implode(', ', array_map(function($value) {
        return "'" . $value . "'";
    }, $owedArray));

    $command = escapeshellcmd('python3 send_reminder.py "' . $result['fldDue'] . '" "Alice, Bob, Charlie, David"');
    $output = shell_exec($command);

    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>
