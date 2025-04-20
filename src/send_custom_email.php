<?php include 'top.php';

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

<main class="form-area">
    <h2 class="section-title">Send Custom Email</h2>
    <div class="form-panel">
        <form method="POST">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" required>

            <label for="body">Message</label>
            <textarea id="body" name="body" rows="6" required></textarea>

            <button type="submit">Send Email</button>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>