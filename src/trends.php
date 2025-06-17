<?php include 'top.php';

// fetch monthly sums for Gas & Electric
$sql = "
  SELECT
    DATE_FORMAT(STR_TO_DATE(fldDate,'%Y-%m-%d'), '%Y-%m') AS month,
    fldItem,
    SUM(fldTotal) AS total
  FROM tblUtilities
  WHERE fldItem IN ('Gas','Electric')
  GROUP BY month, fldItem
  ORDER BY month
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// pivot into [month=>['Gas'=>..,'Electric'=>..]]
$monthly = [];
foreach ($data as $r) {
    $m = $r['month'];
    if (!isset($monthly[$m])) {
        $monthly[$m] = ['Gas' => 0, 'Electric' => 0];
    }
    $monthly[$m][$r['fldItem']] = (float) $r['total'];
}

$labels = array_keys($monthly);
$gasData = array_map(fn($m) => $monthly[$m]['Gas'], $labels);
$elecData = array_map(fn($m) => $monthly[$m]['Electric'], $labels);

// --- Insights Data Calculation ---

// Initialize insight data arrays
$last_year_totals = ['Gas' => 'N/A', 'Electric' => 'N/A', 'Internet' => 'N/A'];
$forecast_totals = ['Gas' => 'N/A', 'Electric' => 'N/A', 'Internet' => 'N/A'];
$bill_items = ['Gas', 'Electric', 'Internet'];

// --- This Time Last Year ---
$current_year = date('Y');
$current_month = date('m');
$last_year = $current_year - 1;
$last_year_month_str = $last_year . '-' . $current_month; // YYYY-MM format for query

$sql_last_year = "
    SELECT fldItem, SUM(fldTotal) as total
    FROM tblUtilities
    WHERE DATE_FORMAT(STR_TO_DATE(fldDate, '%Y-%m-%d'), '%Y-%m') = :last_year_month
      AND fldItem IN ('Gas', 'Electric', 'Internet')
    GROUP BY fldItem
";
$stmt_last_year = $pdo->prepare($sql_last_year);
$stmt_last_year->execute([':last_year_month' => $last_year_month_str]);
$last_year_data = $stmt_last_year->fetchAll(PDO::FETCH_ASSOC);

foreach ($last_year_data as $row) {
    if (in_array($row['fldItem'], $bill_items)) {
        $last_year_totals[$row['fldItem']] = (float)$row['total'];
    }
}
$last_year_display_month = date('F Y', strtotime("$last_year-$current_month-01"));


// --- Next Month's Expected Bills (Simple Forecast - average of last 6 months) ---
$six_months_ago_date = date('Y-m-d', strtotime('-6 months')); // For the WHERE clause

foreach ($bill_items as $item) {
    $sql_forecast = "
        SELECT AVG(fldTotal) as average_total
        FROM tblUtilities
        WHERE fldItem = :item
          AND STR_TO_DATE(fldDate, '%Y-%m-%d') >= STR_TO_DATE(:six_months_ago, '%Y-%m-%d')
    ";
    $stmt_forecast = $pdo->prepare($sql_forecast);
    $stmt_forecast->execute([':item' => $item, ':six_months_ago' => $six_months_ago_date]);
    $forecast_result = $stmt_forecast->fetch(PDO::FETCH_ASSOC);

    if ($forecast_result && $forecast_result['average_total'] !== null) {
        $forecast_totals[$item] = (float)$forecast_result['average_total'];
    }
}

?>
<main>
    <h2 class="section-title">Trends</h2>
    <div class="table-responsive">
        <canvas id="trendsChart"></canvas>
    </div>

    <div class="insights-section">
        <div class="insight-card">
            <h3>This Time Last Year (<?= htmlspecialchars($last_year_display_month) ?>)</h3>
            <?php if (empty($last_year_data)): ?>
                <p>No data available for this period last year.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($last_year_totals as $item => $total): ?>
                        <li><?= htmlspecialchars($item) ?>: <?= is_numeric($total) ? '$' . number_format($total, 2) : htmlspecialchars($total) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="insight-card">
            <h3>Next Month's Outlook (Forecast)</h3>
            <p>Based on the average of the last 6 months:</p>
            <ul>
                <?php foreach ($forecast_totals as $item => $total): ?>
                    <li><?= htmlspecialchars($item) ?>: <?= is_numeric($total) ? '$' . number_format($total, 2) : htmlspecialchars($total) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // original ISO labels
    const rawLabels = <?php echo json_encode($labels); ?>;
    const gasTotals = <?php echo json_encode($gasData); ?>;
    const elecTotals = <?php echo json_encode($elecData); ?>;
    const ctx = document.getElementById('trendsChart').getContext('2d');

    // month name lookup
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // turn ["2024-07","2024-08",…] into ["Jul 2024","Aug 2024",…]
    const labels = rawLabels.map(label => {
        const [year, mon] = label.split('-');
        return `${monthNames[parseInt(mon, 10) - 1]} ${year}`;
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Gas', data: gasTotals },
                { label: 'Electric', data: elecTotals }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: { title: { display: true, text: 'Month' } },
                y: { title: { display: true, text: 'Total ($)' } }
            }
        }
    });
</script>
<?php include 'footer.php'; ?>