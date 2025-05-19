<?php
// src/portal.php

include 'top.php';

if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}

$peopleList = ['Aaron', 'Owen', 'Ben'];

function getData(string $field): string
{
    return isset($_POST[$field])
        ? htmlspecialchars(trim($_POST[$field]), ENT_QUOTES)
        : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Gather form inputs
    $date = getData('date');
    $item = getData('item');
    $total = getData('total');
    $cost = getData('cost');
    $due = getData('due');
    $status = 'Unpaid';

    // 2) Build upload directory: ../public/YYYY/Item/
    $year = date('Y', strtotime($date));
    $baseDir = dirname(__DIR__) . '/public';
    $uploadDir = "{$baseDir}/{$year}/{$item}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 3) Handle PDF upload
    if (isset($_FILES['view']) && $_FILES['view']['error'] === UPLOAD_ERR_OK) {
        $origName = basename($_FILES['view']['name']);
        $dest = $uploadDir . $origName;
        move_uploaded_file($_FILES['view']['tmp_name'], $dest);
        // path stored in DB, relative to /public/
        $filePath = "{$year}/{$item}/{$origName}";
    } else {
        die("Upload failed");
    }

    // 4) Validate
    $dataIsGood = $date && $item && $total && $cost && $due && !empty($filePath);

    if ($dataIsGood) {
        // 5) Insert into database
        $sql = 'INSERT INTO tblUtilities
                (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $date,
            $item,
            $total,
            $cost,
            $due,
            $status,
            $filePath,
            implode(', ', $peopleList)
        ]);

        // 6) Rebuild .ics calendar
        include "update_ics.php";

        // 7) Send “New Bill Posted” emails
        $emailMap = [
            'Aaron' => 'aperkel@uvm.edu',
            'Owen' => 'oacook@uvm.edu',
            'Ben' => 'bquacken@uvm.edu',
        ];
        $subject = 'New Bill Posted';
        $headers = "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "From: 81 Buell Utilities <me@aaronperkel.com>\r\n";

        $body = "<p style=\"font:14pt serif;\">Hello,</p>"
            . "<p style=\"font:14pt serif;\">You have a new <strong>{$item}</strong> bill due on {$due}.</p>"
            . "<ul style=\"font:14pt serif;\">"
            . "<li>Bill total: \${$total}</li>"
            . "<li>Cost per person: \${$cost}</li>"
            . "</ul>"
            . "<p style=\"font:14pt serif;\">"
            . "Please login to <a href=\"https://utilities.aperkel.w3.uvm.edu\">81 Buell Utilities</a> for more info."
            . "</p>"
            . "<p style=\"font:14pt serif;color:green;\">"
            . "81 Buell Utilities<br>"
            . "P: (478)262-8935 | E: me@aaronperkel.com"
            . "</p>";

        foreach ($emailMap as $to) {
            mail($to, $subject, $body, $headers);
        }

        // 8) Confirmation email to yourself
        $confirmBody = "<p style=\"font:12pt monospace;\">Sent to: "
            . implode(', ', $emailMap)
            . "</p><hr>"
            . "<p style=\"font:12pt monospace;\">Subject: {$subject}</p>";
        mail('aperkel@uvm.edu', 'Mail Sent', $confirmBody, $headers);
    }

    // Redirect to avoid resubmission
    header('Location: portal.php');
    exit;
}

// FETCH existing bills for display
$sql = 'SELECT pmkBillID, fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe
         FROM tblUtilities
         ORDER BY fldDate DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute();
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
                            <?php if ($c['fldStatus'] !== "Paid"): ?>
                                <form method="POST" action="send_reminder.php" style="margin:0">
                                    <input type="hidden" name="sendReminder" value="1">
                                    <input type="hidden" name="pmk" value="<?= $c['pmkBillID'] ?>">
                                    <button type="submit" class="badge badge-unpaid">
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
                                    $checked = in_array($p, $paid) ? 'checked' : '';
                                    echo "<label style=\"margin-right:.5em\">
                                            <input type=\"checkbox\" name=\"paidPeople[]\" value=\"$p\" $checked>
                                            $p
                                          </label>";
                                }
                                ?>
                                <button type="submit">Update</button>
                            </form>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($c['fldView']) ?>" class="icon-link"
                                target="_blank">View</a>
                            |
                            <a href="<?= htmlspecialchars( $c['fldView']) ?>" download
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