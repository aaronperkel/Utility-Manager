<?php
// portal.php

include 'top.php';

// only Aaron may access
if (!($_SERVER['REMOTE_USER'] ?? '') === 'aperkel') {
    die("Access denied.");
}

// helper to htmlspecialchars+trim
function sanitize(string $s): string
{
    return htmlspecialchars(trim($s), ENT_QUOTES);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Collect & validate inputs
    $billDate = $_POST['date'] ?? '';
    $item = $_POST['item'] ?? '';
    $total = $_POST['total'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $dueDate = $_POST['due'] ?? '';

    if (!$billDate || !$item || !$total || !$cost || !$dueDate || !isset($_FILES['view'])) {
        die("Missing one of: date, item, total, cost, due, or PDF");
    }

    // 2) Compute year folder
    $ts = strtotime($billDate);
    if ($ts === false) {
        die("Invalid billed date format");
    }
    $year = date('Y', $ts);

    // 3) Ensure ./public/YYYY/Item exists
    $uploadDir = __DIR__ . "/public/{$year}/{$item}/";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        die("Failed to create directory {$uploadDir}");
    }

    // 4) Handle PDF upload
    if ($_FILES['view']['error'] !== UPLOAD_ERR_OK) {
        die("Upload error code: " . $_FILES['view']['error']);
    }
    $origName = basename($_FILES['view']['name']);
    $dest = $uploadDir . $origName;
    if (!move_uploaded_file($_FILES['view']['tmp_name'], $dest)) {
        die("Failed to move uploaded file");
    }
    // store relative path for DB & later linking
    $dbPath = "public/{$year}/{$item}/{$origName}";

    // 5) Insert into tblUtilities
    $stmt = $pdo->prepare("
        INSERT INTO tblUtilities
          (fldDate,fldItem,fldTotal,fldCost,fldDue,fldStatus,fldView,fldOwe)
        VALUES
          (:date,:item,:total,:cost,:due,'Unpaid',:view,:owe)
    ");
    $stmt->execute([
        ':date' => sanitize($billDate),
        ':item' => sanitize($item),
        ':total' => sanitize($total),
        ':cost' => sanitize($cost),
        ':due' => sanitize($dueDate),
        ':view' => sanitize($dbPath),
        ':owe' => 'Aaron, Owen, Ben',
    ]);

    // 6) Rebuild calendar
    include 'update_ics.php';

    // 7) Email notifications
    $emailMap = [
        'Aaron' => 'aperkel@uvm.edu',
        'Owen' => 'oacook@uvm.edu',
        'Ben' => 'bquacken@uvm.edu',
    ];
    $subject = 'New Bill Posted';
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: 81 Buell Utilities <me@aaronperkel.com>',
    ]) . "\r\n";

    $body = "<p style=\"font:14pt serif;\">Hello,</p>"
        . "<p style=\"font:14pt serif;\">"
        . "A new <strong>{$item}</strong> bill was posted (billed on {$billDate}), due {$dueDate}."
        . "</p><ul style=\"font:14pt serif;\">"
        . "<li>Total: \${$total}</li>"
        . "<li>Per person: \${$cost}</li>"
        . "</ul>"
        . "<p style=\"font:14pt serif;\">"
        . "View/download it at <a href=\"/public/{$dbPath}\">the portal</a>."
        . "</p>"
        . "<p style=\"font:14pt serif;color:green;\">81 Buell Utilities<br>"
        . "P: (478)262-8935 | E: me@aaronperkel.com</p>";

    foreach ($emailMap as $to) {
        if (!mail($to, $subject, $body, $headers)) {
            error_log("Mail to $to failed");
        }
    }
    // confirm to yourself
    $conf = "<p style=\"font:12pt monospace;\">Emailed: "
        . implode(', ', $emailMap)
        . "</p><hr><p style=\"font:12pt monospace;\">Subject: {$subject}</p>";
    mail('aperkel@uvm.edu', 'Mail Sent', $conf, $headers);

    // 8) Redirect to avoid form-resubmission
    header('Location: portal.php');
    exit;
}

// ==== GET: fetch existing bills and render ====

$stmt = $pdo->query("
  SELECT pmkBillID,fldDate,fldItem,fldTotal,fldCost,fldDue,fldStatus,fldView,fldOwe
    FROM tblUtilities
   ORDER BY fldDate DESC
");
$cells = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <td><?= htmlspecialchars($c['fldDate']) ?></td>
                        <td><?= htmlspecialchars($c['fldItem']) ?></td>
                        <td>$<?= number_format($c['fldTotal'], 2) ?></td>
                        <td>$<?= number_format($c['fldCost'], 2) ?></td>
                        <td>
                            <?php if ($c['fldStatus'] !== 'Paid'): ?>
                                <form method="POST" action="send_reminder.php" style="margin:0">
                                    <input type="hidden" name="sendReminder" value="1">
                                    <input type="hidden" name="pmk" value="<?= $c['pmkBillID'] ?>">
                                    <button class="badge badge-unpaid">
                                        <?= htmlspecialchars($c['fldDue']) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-paid"><?= htmlspecialchars($c['fldDue']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="payment-cell">
                            <form method="POST" action="update_owe.php" style="display:inline">
                                <input type="hidden" name="updateNames" value="1">
                                <input type="hidden" name="id2" value="<?= $c['pmkBillID'] ?>">
                                <?php
                                $owed = array_map('trim', explode(',', $c['fldOwe']));
                                $all = ['Aaron', 'Owen', 'Ben'];
                                $paid = array_diff($all, $owed);
                                foreach ($all as $p) {
                                    $chk = in_array($p, $paid) ? 'checked' : '';
                                    echo "<label><input type=\"checkbox\" name=\"paidPeople[]\" value=\"$p\" $chk> $p</label> ";
                                }
                                ?>
                                <button>Update</button>
                            </form>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars("public/{$c['fldView']}") ?>" target="_blank"
                                class="icon-link">View</a>
                            |
                            <a href="<?= htmlspecialchars("public/{$c['fldView']}") ?>" download
                                class="icon-link">Download</a>
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

            <label for="view">PDF Upload</label>
            <input type="file" id="view" name="view" accept="application/pdf" required>

            <button type="submit">Submit New Bill</button>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>