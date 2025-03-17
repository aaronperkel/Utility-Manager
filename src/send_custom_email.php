<?php
include 'top.php';

if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject = escapeshellarg($_POST['subject']);
    $body = escapeshellarg($_POST['body']);
    $command = "python3 scripts/send_custom_email.py $subject $body";
    shell_exec($command);
    header('Location: portal.php');
    exit;
}
?>
<form method="POST">
    <label>Subject:</label><br>
    <input type="text" name="subject" required><br><br>
    <label>Message:</label><br>
    <textarea name="body" required></textarea><br><br>
    <button type="submit">Send Email</button>
</form>

<?php
include 'footer.php';
?>