<?php include 'top.php'; ?>

<?php
// Calculate amount owed by current user
$userId = $_SERVER['REMOTE_USER'];
// map your LDAP uids to the names stored in fldOwe
$uidToName = [
    'aperkel' => 'Aaron',
    'oacook' => 'Owen',
    'bquacken' => 'Ben',
];

$userName = $uidToName[$userId] ?? $userId;


$sqlOwe = "
    SELECT SUM(fldCost) AS owed
    FROM tblUtilities
    WHERE fldStatus <> 'Paid'
      AND FIND_IN_SET(?, REPLACE(fldOwe, ' ', ''))
  ";
$stmtOwe = $pdo->prepare($sqlOwe);
$stmtOwe->execute([$userName]);
$owed = $stmtOwe->fetch()['owed'] ?? 0;

// Fetch all bills
$sql = '
    SELECT fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe
    FROM tblUtilities
    ORDER BY fldDate DESC
  ';
$stmt = $pdo->prepare($sql);
$stmt->execute();
$cells = $stmt->fetchAll();
?>

<?php
// group by year
$billsByYear = [];
foreach ($cells as $cell) {
    // parse the billed date (YYYY-MM-DD from your <input type="date">)
    $dt = new DateTime($cell['fldDate']);
    $year = $dt->format('Y');
    $billsByYear[$year][] = $cell;
}
// sort years descending
krsort($billsByYear);
?>
<main>
    <div class="banner">
        <p>You currently owe <strong>$<?= number_format($owed, 2) ?></strong></p>
    </div>

    <h2 class="section-title">Utility Bills</h2>

    <?php foreach ($billsByYear as $year => $yearCells): ?>
        <h3 class="section-subtitle"><?= htmlspecialchars($year) ?></h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date Billed</th>
                        <th>Item</th>
                        <th>Total</th>
                        <th class="col-cost">Per Person</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>See Bill</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yearCells as $cell):
                        $owedList = array_map('trim', explode(',', $cell['fldOwe']));
                        $isOwed = in_array($userName, $owedList);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($cell['fldDate']) ?></td>
                            <td><?= htmlspecialchars($cell['fldItem']) ?></td>
                            <td>$<?= number_format($cell['fldTotal'], 2) ?></td>
                            <td class="col-cost">$<?= number_format($cell['fldCost'], 2) ?></td>
                            <td><?= htmlspecialchars($cell['fldDue']) ?></td>
                            <td>
                                <?php if ($isOwed): ?>
                                    <span class="badge badge-unpaid">Unpaid</span>
                                <?php else: ?>
                                    <span class="badge badge-paid">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars($cell['fldView']) ?>" target="_blank" class="icon-link">View</a>
                                |
                                <a href="<?= htmlspecialchars($cell['fldView']) ?>" download class="icon-link">Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</main>

<?php include 'footer.php'; ?>