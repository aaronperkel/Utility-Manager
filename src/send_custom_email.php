<?php 
// send_custom_email.php
include 'top.php';

if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) sanitize inputs
    $subject = trim($_POST['subject']);
    $bodyRaw = trim($_POST['body']);

    // 2) split into paragraphs on double new‑lines
    $paras = preg_split('/\R\R+/', $bodyRaw, -1, PREG_SPLIT_NO_EMPTY);
    $htmlBody = '';
    foreach ($paras as $p) {
        // convert any single newlines to <br>
        $clean = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        $clean = nl2br($clean);
        $htmlBody .= "<p style=\"font: 14pt serif;\">{$clean}</p>\n";
    }

    // 3) append signature
    $htmlBody .= <<<HTML
<p style="font: 14pt serif;">
    <span style="color: green;">81 Buell Utilities</span><br>
    P: (478)262-8935 | E: me@aaronperkel.com
</p>
HTML;

    // 4) prepare headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: 81 Buell Utilities <me@aaronperkel.com>\r\n";

    // 5) recipients
    $emailMap = [
        'Aaron' => 'aperkel@uvm.edu',
        'Owen'  => 'oacook@uvm.edu',
        'Ben'   => 'bquacken@uvm.edu',
    ];

    // 6) send to each
    foreach ($emailMap as $name => $to) {
        mail($to, $subject, $htmlBody, $headers);
    }

    // 7) optional: send yourself a confirmation
    $confirmTo   = 'aperkel@uvm.edu';
    $sentList    = implode(', ', array_values($emailMap));
    $confirmSubj = 'Custom Email Sent';
    $confirmBody  = "<p style=\"font:12pt monospace;\">Sent to: {$sentList}</p>\n";
    $confirmBody .= "<p style=\"font:12pt monospace;\">Subject: " . htmlspecialchars($subject) . "</p>\n";
    mail($confirmTo, $confirmSubj, $confirmBody, $headers);

    // 8) redirect back
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