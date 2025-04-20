<?php
// send_reminder.php - send email reminders via PHP mail()

include './connect-DB.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['sendReminder'])) {
    $id = htmlspecialchars($_POST['pmk']);

    // Fetch bill details
    $stmt = $pdo->prepare(
        'SELECT fldDue, fldOwe, fldItem, fldTotal, fldCost
         FROM tblUtilities WHERE pmkBillID = ?'
    );
    $stmt->execute([$id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bill) {
        $due = $bill['fldDue'];
        $item = $bill['fldItem'];
        $total = $bill['fldTotal'];
        $cost = $bill['fldCost'];

        $owedList = array_map('trim', explode(',', $bill['fldOwe']));

        // Map names to email addresses
        $emailMap = [
            'Aaron' => 'aperkel@uvm.edu',
            'Owen' => 'oacook@uvm.edu',
            'Ben' => 'bquacken@uvm.edu',
        ];

        // Determine subject urgency based on due date
        $dueDate = new DateTime($due);
        $today = new DateTime();
        $interval = $today->diff($dueDate)->days;
        $subject = ($interval <= 3)
            ? 'URGENT: Utility Bill Reminder'
            : 'Utility Bill Reminder';

        // Prepare email headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: 81 Buell Utilities <me@aaronperkel.com>\r\n";

        $sentRecipients = [];
        foreach ($owedList as $name) {
            if (empty($name) || !isset($emailMap[$name])) {
                continue;
            }
            $to = $emailMap[$name];
            $sentRecipients[] = $to;

            // Build the new “db.py–style” body
            $body = <<<HTML
                <p style="font: 14pt serif;">Hello {$name},</p>
                <p style="font: 14pt serif;">This is a reminder that your <strong>{$item}</strong> bill is due soon.</p>
                <ul>
                <li style="font: 14pt serif;">Bill total: \${$total}</li>
                <li style="font: 14pt serif;">Cost per person: \${$cost}</li>
                </ul>
                <p style="font: 14pt serif;">
                    Please login to
                    <a href="https://utilities.aperkel.w3.uvm.edu">81 Buell Utilities</a>
                    for more info.
                </p>
                <p style="font: 14pt serif;">
                    <span style="color: green;">81 Buell Utilities</span><br>
                    P: (478)262-8935 | E: me@aaronperkel.com
                </p>
                HTML;

            mail($to, $subject, $body, $headers);
        }

        // ——— now send yourself a confirmation ———
        $confirmTo = 'aperkel@uvm.edu';
        $confirmSubj = 'Mail Sent';
        $sentList = implode(', ', $sentRecipients);
        $confirmBody = <<<HTML
            <p style="font: 12pt monospace;">An email was just sent via utilities.aperkel.w3.uvm.edu.</p>
            <hr>
            <p style="font: 12pt monospace;">To: {$sentList}<br>
            <p style="font: 12pt monospace;">Subject: {$subject}</p>
            HTML;

        mail($confirmTo, $confirmSubj, $confirmBody, $headers);
    }

    // Redirect back
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>