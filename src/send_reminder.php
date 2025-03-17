<?php
include './connect-DB.php';

if (isset($_POST['sendReminder'])) {
    $id = htmlspecialchars($_POST['pmk']);
    $sql = 'SELECT fldDue, fldOwe FROM tblUtilities WHERE pmkBillID = ?';
    $statement = $pdo->prepare($sql);
    $data = array($id);
    $statement->execute($data);

    $result = $statement->fetch(PDO::FETCH_ASSOC);

    $owedArray = explode(',', $result['fldOwe']);

    $owed = array_map(function ($value) {
        return '"' . trim($value) . '"';
    }, $owedArray);

    $owedString = implode(',', $owed);

    $command = escapeshellcmd('python3 ./scripts/send_reminder.py "' . $result['fldDue'] . '" [' . $owedString . ']');
    $output = shell_exec($command);

    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
?>