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
$status = '';
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
$status = getData('status');

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
    include "update_ics.php";
    $command = escapeshellcmd('python scripts/new_bill.py');
    $output = shell_exec($command);
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
                    <th>Date</th>
                    <th>Item</th>
                    <th>Total</th>
                    <th>Per Person</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cells as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['fldDate']) ?></td>
                        <td><?= htmlspecialchars($c['fldItem']) ?></td>
                        <td>$<?= htmlspecialchars($c['fldTotal']) ?></td>
                        <td>$<?= htmlspecialchars($c['fldCost']) ?></td>
                        <td>
                            <?= htmlspecialchars($c['fldDue']) ?>
                            <?php if ($c['fldStatus'] !== "Paid"): ?>
                                <form method="POST" action="send_reminder.php" style="display:inline">
                                    <input type="hidden" name="pmk" value="<?= $c['pmkBillID'] ?>">
                                    <button class="badge badge-unpaid">Send Reminder</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['fldStatus'] === "Paid"): ?>
                                <span class="badge badge-paid">Paid</span>
                                <form method="POST" action="update_status_unpaid.php" style="display:inline">
                                    <input type="hidden" name="id" value="<?= $c['pmkBillID'] ?>">
                                    <button class="badge badge-unpaid">Mark Unpaid</button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-unpaid" data-tooltip="Owe: <?= $c['fldOwe'] ?>">Unpaid</span>
                                <form method="POST" action="update_status_paid.php" style="display:inline">
                                    <input type="hidden" name="id" value="<?= $c['pmkBillID'] ?>">
                                    <button class="badge badge-paid">Mark Paid</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= $c['fldView'] ?>" target="_blank" class="icon-link">View</a>
                            | 
                            <a href="<?= $c['fldView'] ?>" download class="icon-link">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="section-title">Add New Bill</h2>
    <div class="form-panel">
        <form method="POST" action="portal.php" enctype="multipart/form-data">
            <label for="date">Date Billed</label>
            <input type="date" id="date" name="date" required>

            <label for="item">Item</label>
            <select id="item" name="item" required>
                <option>Gas</option>
                <option>Electric</option>
                <option>Internet</option>
            </select>

            <label for="total">Bill Total</label>
            <input type="number" id="total" name="total" oninput="updateField()" step="0.01" required>

            <label for="cost">Cost per Person</label>
            <input type="number" id="cost" name="cost" readonly step="0.01">

            <label for="due">Due Date</label>
            <input type="date" id="due" name="due" required>

            <label>Status</label>
            <div style="margin-bottom:1em;">
                <input type="radio" id="unpaid" name="status" value="Unpaid" checked>
                <label for="unpaid">Unpaid</label>
                <input type="radio" id="paid" name="status" value="Paid">
                <label for="paid">Paid</label>
            </div>

            <label for="view">PDF Path/URL</label>
            <input type="text" id="view" name="view" required>

            <button type="submit">Submit New Bill</button>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>