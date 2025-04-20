<?php include 'top.php'; ?>
<?php
if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}
$peopleList = ['Aaron', 'Owen', 'Ben'];

$dataIsGood = false;

$date = '';
$item = '';
$total = '';
$cost = '';
$due = '';
$status = 'Unpaid';
$uploadDir = 'public/';

function getData($field)
{
    if (!isset($_POST[$field])) {
        $data = "";
    } else {
        $data = trim($_POST[$field]);
        $data = htmlspecialchars($data);
    }
    return $data;
}

$date = getData('date');
$item = getData('item');
$total = getData('total');
$cost = getData('cost');
$due = getData('due');

$total = (string) $total;
$cost = (string) $cost;

if ($item == "Gas") {
    $uploadDir = $uploadDir . 'Gas/';
} elseif ($item == "Electric") {
    $uploadDir = $uploadDir . 'Electric/';
} elseif ($item == "Internet") {
    $uploadDir = $uploadDir . 'Internet/';
}

$filePath = $uploadDir . getData('view');

$dataIsGood = true;
if ($date == '') {
    $dataIsGood = false;
}
if ($item == '') {
    $dataIsGood = false;
}
if ($total == '') {
    $dataIsGood = false;
}
if ($cost == '') {
    $dataIsGood = false;
}
if ($due == '') {
    $dataIsGood = false;
}
if ($status == '') {
    $dataIsGood = false;
}
if ($filePath == null) {
    $dataIsGood = false;
}

print '<!-- Starting Saving -->';

$sql = 'INSERT INTO tblUtilities (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
$statement = $pdo->prepare($sql);
$data = array($date, $item, $total, $cost, $due, $status, $filePath, 'Aaron, Owen, Ben');

if ($dataIsGood) {
    $statement->execute($data);
    // stash
    $tmp = [
        'item'  => $item,
        'due'   => $due,
        'total' => $total,
        'cost'  => $cost,
    ];
  
    include "update_ics.php";
    
    // restore
    $item  = $tmp['item'];
    $due   = $tmp['due'];
    $total = $tmp['total'];
    $cost  = $tmp['cost'];

    // ── send “New Bill Posted” emails in the PHP style ──

    // 1) map roommate names to addresses
    $emailMap = [
        'Aaron' => 'aperkel@uvm.edu',
        'Owen' => 'oacook@uvm.edu',
        'Ben' => 'bquacken@uvm.edu',
    ];

    // 2) build subject & headers
    $subject = 'New Bill Posted';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: 81 Buell Utilities <me@aaronperkel.com>\r\n";

    // 3) build the HTML body (same pattern as send_reminder.php)
    $body = <<<HTML
        <p style="font: 14pt serif;">Hello,</p>
        <p style="font: 14pt serif;">You have a new <strong>{$item}</strong> bill ready to view. It’s due on {$due}.</p>
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

    // 4) loop and send to each roommate
    foreach ($emailMap as $name => $to) {
        mail($to, $subject, $body, $headers);
    }

    // 5) send yourself a confirmation
    $confirmTo = 'aperkel@uvm.edu';
    $confirmSubj = 'Mail Sent';
    $sentList = implode(', ', array_values($emailMap));
    $confirmBody = <<<HTML
        <p style="font: 12pt monospace;">An email was just sent via utilities.aperkel.w3.uvm.edu.</p>
        <hr>
        <p style="font: 12pt monospace;">To: {$sentList}<br>
        <p style="font: 12pt monospace;">Subject: {$subject}</p>
        HTML;
    mail($confirmTo, $confirmSubj, $confirmBody, $headers);
}

$sql = 'SELECT pmkBillID,fldDate,fldItem,fldTotal,fldCost,fldDue,fldStatus,fldView,fldOwe
          FROM tblUtilities ORDER BY fldDate DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute();
$cells = $stmt->fetchAll();
?>
<main class="admin-area">
    <h2 class="section-title">Admin Portal</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date Billed</th>
                    <th>Item</th>
                    <th>Total</th>
                    <th>Per Person</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cells as $c): ?>
                    <tr>
                        <td><?= $c['fldDate'] ?></td>
                        <td><?= $c['fldItem'] ?></td>
                        <td>$<?= $c['fldTotal'] ?></td>
                        <td>$<?= $c['fldCost'] ?></td>
                        <td>
                            <?php if ($c['fldStatus'] !== "Paid"): ?>
                                <form method="POST" action="send_reminder.php" style="margin:0">
                                    <input type="hidden" name="sendReminder" value="1">
                                    <input type="hidden" name="pmk" value="<?= $c['pmkBillID'] ?>">
                                    <button type="submit" class="badge badge-unpaid">
                                        <?= htmlspecialchars($c['fldDue']) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- If already paid, just show the date -->
                                <span class="badge badge-paid"><?= htmlspecialchars($c['fldDue']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="payment-cell">
                            <form method="POST" action="update_owe.php" style="display:inline">
                                <input type="hidden" name="updateNames" value="1">
                                <input type="hidden" name="id2" value="<?= $c['pmkBillID'] ?>">
                                <?php
                                // build paid‐list = those NOT in fldOwe
                                $owed = array_map('trim', explode(',', $c['fldOwe']));
                                $all = ['Aaron', 'Owen', 'Ben'];
                                $paid = array_diff($all, $owed);
                                foreach ($all as $p) {
                                    $isChecked = in_array($p, $paid) ? 'checked' : '';
                                    echo "<label style=\"margin-right:.5em\">
                <input type=\"checkbox\" name=\"paidPeople[]\" value=\"$p\" $isChecked>
                $p
              </label>";
                                }
                                ?>
                                <button type="submit">Update</button>
                            </form>
                        </td>
                        <td>
                            <a href="<?= $c['fldView'] ?>" class="icon-link" target="_blank">View</a>
                            |
                            <a href="<?= $c['fldView'] ?>" download class="icon-link">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="section-title">Add New Bill</h2>
    <div class="form-panel admin-area">
        <form method="POST" action="portal.php" enctype="multipart/form-data">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" required>

            <label for="item">Item</label>
            <select id="item" name="item" required>
                <option>Gas</option>
                <option>Electric</option>
                <option>Internet</option>
            </select>

            <label for="total">Total</label>
            <input type="number" id="total" name="total" oninput="updateField()" step="0.01" required>

            <label for="cost">Per Person</label>
            <input type="number" id="cost" name="cost" readonly step="0.01">

            <label for="due">Due Date</label>
            <input type="date" id="due" name="due" required>

            <label for="view">PDF URL</label>
            <input type="text" id="view" name="view" required>

            <button type="submit">Submit New Bill</button>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>