<?php include 'top.php'; ?>

<?php
// Calculate amount owed by current user
$user = $_SERVER['REMOTE_USER'];
$sqlOwe = '
    SELECT SUM(fldCost) AS owed
    FROM tblUtilities
    WHERE fldStatus <> "Paid"
      AND FIND_IN_SET(?, fldOwe)
  ';
$stmtOwe = $pdo->prepare($sqlOwe);
$stmtOwe->execute([$user]);
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
<main>
    <div class="banner">
        <p>You currently owe <strong>$<?= number_format($owed, 2) ?></strong></p>
    </div>

    <h2 class="section-title">Outstanding Utility Bills</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date Billed</th>
                    <th>Item</th>
                    <th>Bill Total</th>
                    <th>Cost per Person</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>See Bill</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cells as $cell): ?>
                    <tr>
                        <td><?= htmlspecialchars($cell['fldDate']) ?></td>
                        <td><?= htmlspecialchars($cell['fldItem']) ?></td>
                        <td>$<?= number_format($cell['fldTotal'], 2) ?></td>
                        <td>$<?= number_format($cell['fldCost'], 2) ?></td>
                        <td><?= htmlspecialchars($cell['fldDue']) ?></td>
                        <td>
                            <?php if ($cell['fldStatus'] === 'Paid'): ?>
                                <span class="badge badge-paid">Paid</span>
                            <?php else: ?>
                                <span class="badge badge-unpaid" data-tooltip="Unpaid: <?= htmlspecialchars($cell['fldOwe']) ?>">
                                    Unpaid
                                </span>
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
</main>

<?php include 'footer.php'; ?>