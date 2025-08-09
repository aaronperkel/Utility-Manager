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
        $last_year_totals[$row['fldItem']] = (float) $row['total'];
    }
}
$last_year_display_month = date('F Y', strtotime("$last_year-$current_month-01"));

// --- Simplified Next Month's Expected Bills ---

$upcoming_month_date = strtotime('+1 month');
$upcoming_m = date('m', $upcoming_month_date);
$forecast_display_month = date('F Y', $upcoming_month_date);

$forecast_totals = ['Gas' => 'N/A', 'Electric' => 'N/A', 'Internet' => 'N/A'];
$forecast_method = ['Gas' => '', 'Electric' => '', 'Internet' => ''];

$seasonal_average_years = 3; // Number of past years to average for seasonal forecast.
$fallback_average_months = 6; // Number of recent months to average if seasonal data is unavailable.

foreach ($bill_items as $item) {
    // 1. Try to get a seasonal average from the last X years.
    $sql_seasonal_avg = "
        SELECT AVG(fldTotal) as average_total
        FROM tblUtilities
        WHERE fldItem = :item
          AND MONTH(STR_TO_DATE(fldDate, '%Y-%m-%d')) = :month
          AND STR_TO_DATE(fldDate, '%Y-%m-%d') >= DATE_SUB(NOW(), INTERVAL :years YEAR)
    ";
    $stmt_seasonal_avg = $pdo->prepare($sql_seasonal_avg);
    $stmt_seasonal_avg->execute([
        ':item' => $item,
        ':month' => $upcoming_m,
        ':years' => $seasonal_average_years
    ]);
    $seasonal_result = $stmt_seasonal_avg->fetch(PDO::FETCH_ASSOC);

    if ($seasonal_result && $seasonal_result['average_total'] !== null) {
        $forecast_totals[$item] = (float) $seasonal_result['average_total'];
        $forecast_method[$item] = "Avg. of last $seasonal_average_years years for this month";
    } else {
        // 2. Fallback: If no seasonal data, get a simple average of the last X months.
        $sql_fallback_avg = "
            SELECT AVG(fldTotal) as average_total
            FROM tblUtilities
            WHERE fldItem = :item
              AND STR_TO_DATE(fldDate, '%Y-%m-%d') >= DATE_SUB(NOW(), INTERVAL :months MONTH)
        ";
        $stmt_fallback_avg = $pdo->prepare($sql_fallback_avg);
        $stmt_fallback_avg->execute([
            ':item' => $item,
            ':months' => $fallback_average_months
        ]);
        $fallback_result = $stmt_fallback_avg->fetch(PDO::FETCH_ASSOC);

        if ($fallback_result && $fallback_result['average_total'] !== null) {
            $forecast_totals[$item] = (float) $fallback_result['average_total'];
            $forecast_method[$item] = "Avg. of last $fallback_average_months months";
        }
        // If still no data, it remains 'N/A'.
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
                        <li><?= htmlspecialchars($item) ?>:
                            <?= is_numeric($total) ? '$' . number_format($total, 2) : htmlspecialchars($total) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="insight-card">
            <h3>Next Month's Outlook (<?= htmlspecialchars($forecast_display_month) ?>)</h3>
            <p>
                Forecast based on a seasonal average of previous years, or a simple average of recent months if
                historical data is limited.
            </p>
            <ul>
                <?php foreach ($forecast_totals as $item => $total): ?>
                    <li>
                        <?= htmlspecialchars($item) ?>:
                        <?= is_numeric($total) ? '$' . number_format($total, 2) : htmlspecialchars($total) ?>
                        <?php if (is_numeric($total) && !empty($forecast_method[$item])): ?>
                            <span class="forecast-method">(<?= htmlspecialchars($forecast_method[$item]) ?>)</span>
                        <?php endif; ?>
                    </li>
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