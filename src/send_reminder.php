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
        $due   = $bill['fldDue'];
        $item  = $bill['fldItem'];
        $total = $bill['fldTotal'];
        $cost  = $bill['fldCost'];

        // Map names to email addresses
        $emailMap = [
            'Aaron' => 'aperkel@uvm.edu',
            'Owen'  => 'oacook@uvm.edu',
            'Ben'   => 'bquacken@uvm.edu',
        ];

        // Determine subject urgency based on due date
        $dueDate = new DateTime($due);
        $today   = new DateTime();
        $interval = $today->diff($dueDate)->days;
        $subject  = ($interval <= 3)
            ? 'URGENT: Utility Bill Reminder'
            : 'Utility Bill Reminder';

        // Prepare email headers
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: 81 Buell Utilities <me@aaronperkel.com>" . "\r\n";

        // Send to each person who owes
        $owedList = array_map('trim', explode(',', $bill['fldOwe']));
        foreach ($owedList as $name) {
            if (empty($name) || !isset($emailMap[$name])) {
                continue;
            }
            $to = $emailMap[$name];

            // Build HTML message
            $message  = "<html><body>";
            $message .= "<p>Hello $name,</p>";
            $message .= "<p>This is a reminder that your <strong>$item</strong> bill — total <strong>$total</strong>, due <strong>$due</strong> — is coming up.</p>";
            $message .= "<ul>";
            $message .= "<li>Total cost: $total</li>";
            $message .= "<li>Cost per person: $cost</li>";
            $message .= "</ul>";
            $message .= "<p>Please log in to <a href=\"https://utilities.aperkel.w3.uvm.edu\">81 Buell Utilities</a> for details.</p>";
            $message .= "<p>81 Buell Utilities<br>P: (478) 262‑8935 | E: me@aaronperkel.com</p>";
            $message .= "</body></html>";

            // Send mail
            mail($to, $subject, $message, $headers);
        }
    }

    // Redirect back
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
