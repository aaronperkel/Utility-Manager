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

// --- Revised Next Month's Expected Bills (Seasonal Trend Adjusted Forecast) ---

// 1. Determine Target Months
$upcoming_month_date = strtotime('+1 month');
$upcoming_m = date('m', $upcoming_month_date);
$upcoming_y = date('Y', $upcoming_month_date);

$base_forecast_m = $upcoming_m;
$base_forecast_y = $upcoming_y - 1;

$forecast_display_month = date('F Y', $upcoming_month_date);
$forecast_base_period_display = date('F Y', strtotime("$base_forecast_y-$base_forecast_m-01"));

// 2. Fetch Base Forecast Data (Upcoming Month, Previous Year)
$base_forecast_values = ['Gas' => 0.0, 'Electric' => 0.0, 'Internet' => 0.0];
$sql_base_forecast = "
    SELECT fldItem, fldTotal
    FROM tblUtilities
    WHERE DATE_FORMAT(STR_TO_DATE(fldDate, '%Y-%m-%d'), '%Y-%m') = :base_period
      AND fldItem IN ('Gas', 'Electric', 'Internet')
"; // Assuming one bill per item per month, or SUM if multiple. For simplicity, taking fldTotal. If multiple, SUM is better.
   // Let's adjust to SUM in case there are multiple entries for an item in a month (though unlikely for main utilities)
$sql_base_forecast_sum = "
    SELECT fldItem, SUM(fldTotal) as total
    FROM tblUtilities
    WHERE DATE_FORMAT(STR_TO_DATE(fldDate, '%Y-%m-%d'), '%Y-%m') = :base_period
      AND fldItem IN ('Gas', 'Electric', 'Internet')
    GROUP BY fldItem
";
$stmt_base_forecast = $pdo->prepare($sql_base_forecast_sum);
$stmt_base_forecast->execute([':base_period' => "$base_forecast_y-$base_forecast_m"]);
$base_data = $stmt_base_forecast->fetchAll(PDO::FETCH_ASSOC);

foreach ($base_data as $row) {
    if (isset($base_forecast_values[$row['fldItem']])) {
        $base_forecast_values[$row['fldItem']] = (float)$row['total'];
    }
}

// 3. Calculate Trend Adjustment Percentage (based on last 6 completed months)
$trend_adjustment_percentages = ['Gas' => 0.0, 'Electric' => 0.0, 'Internet' => 0.0];
$trend_period_months = 6;

// Current year trend period: last `trend_period_months` completed months
$current_trend_end_date = date('Y-m-t', strtotime('-1 month')); // End of last month
$current_trend_start_date = date('Y-m-01', strtotime("-$trend_period_months months", strtotime($current_trend_end_date)));

// Previous year trend period: same calendar months, previous year
$previous_trend_start_date = date('Y-m-01', strtotime($current_trend_start_date . " -1 year"));
$previous_trend_end_date = date('Y-m-t', strtotime($current_trend_end_date . " -1 year"));

$sql_trend_sum = "
    SELECT fldItem, SUM(fldTotal) as period_total
    FROM tblUtilities
    WHERE fldItem = :item
      AND STR_TO_DATE(fldDate, '%Y-%m-%d') BETWEEN STR_TO_DATE(:start_date, '%Y-%m-%d') AND STR_TO_DATE(:end_date, '%Y-%m-%d')
    GROUP BY fldItem
";

foreach ($bill_items as $item) {
    // Current year sum
    $stmt_current_trend = $pdo->prepare($sql_trend_sum);
    $stmt_current_trend->execute([
        ':item' => $item,
        ':start_date' => $current_trend_start_date,
        ':end_date' => $current_trend_end_date
    ]);
    $current_year_sum_result = $stmt_current_trend->fetch(PDO::FETCH_ASSOC);
    $current_year_sum = $current_year_sum_result ? (float)$current_year_sum_result['period_total'] : 0.0;

    // Previous year sum
    $stmt_previous_trend = $pdo->prepare($sql_trend_sum);
    $stmt_previous_trend->execute([
        ':item' => $item,
        ':start_date' => $previous_trend_start_date,
        ':end_date' => $previous_trend_end_date
    ]);
    $previous_year_sum_result = $stmt_previous_trend->fetch(PDO::FETCH_ASSOC);
    $previous_year_sum = $previous_year_sum_result ? (float)$previous_year_sum_result['period_total'] : 0.0;

    if ($previous_year_sum > 0) {
        $trend_adjustment_percentages[$item] = ($current_year_sum - $previous_year_sum) / $previous_year_sum;
    } elseif ($current_year_sum > 0) { // Previous sum is 0 or less, current is positive
        $trend_adjustment_percentages[$item] = 1.0; // Consider as 100% increase if no baseline
    } else {
        $trend_adjustment_percentages[$item] = 0.0; // Both are 0 or previous is negative
    }
}

// 4. Calculate Final Forecast
foreach ($bill_items as $item) {
    if ($base_forecast_values[$item] > 0) { // Only forecast if there's a base value
      $forecast_totals[$item] = $base_forecast_values[$item] * (1 + $trend_adjustment_percentages[$item]);
    } else {
      // If no base data for last year's upcoming month, use simple average of last 6 months as fallback
        $sql_simple_avg = "
            SELECT AVG(fldTotal) as average_total
            FROM tblUtilities
            WHERE fldItem = :item
              AND STR_TO_DATE(fldDate, '%Y-%m-%d') >= STR_TO_DATE(:six_months_ago, '%Y-%m-%d')
        ";
        // $six_months_ago_date was defined in previous version of code, re-define or ensure it is available.
        // For simplicity, let's use the $current_trend_start_date (start of last 6 completed months)
        $stmt_simple_avg = $pdo->prepare($sql_simple_avg);
        $stmt_simple_avg->execute([':item' => $item, ':six_months_ago' => $current_trend_start_date]);
        $simple_avg_result = $stmt_simple_avg->fetch(PDO::FETCH_ASSOC);
        if ($simple_avg_result && $simple_avg_result['average_total'] !== null) {
            $forecast_totals[$item] = (float)$simple_avg_result['average_total'];
        } else {
            $forecast_totals[$item] = 'N/A'; // Still N/A if no data for simple average either
        }
    }
}
// Initialize $forecast_totals again before filling to ensure 'N/A' for items with no forecast
foreach ($bill_items as $item_key) {
    if (!isset($forecast_totals[$item_key])) {
        $forecast_totals[$item_key] = 'N/A';
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
            <h3>Next Month's Outlook (<?= htmlspecialchars($forecast_display_month) ?>)</h3>
            <p>
                Forecast based on bills from <?= htmlspecialchars($forecast_base_period_display) ?>,
                adjusted by year-over-year trends from the last <?= $trend_period_months ?> months.
                If base data from <?= htmlspecialchars($forecast_base_period_display) ?> is unavailable, a simple average of the last 6 months is used.
            </p>
            <ul>
                <?php foreach ($forecast_totals as $item => $total): ?>
                    <li>
                        <?= htmlspecialchars($item) ?>:
                        <?= is_numeric($total) ? '$' . number_format($total, 2) : htmlspecialchars($total) ?>
                        <?php if (is_numeric($total) && $base_forecast_values[$item] > 0 && $trend_adjustment_percentages[$item] != 0): ?>
                            (Trend: <?= sprintf('%+.1f%%', $trend_adjustment_percentages[$item] * 100) ?>)
                        <?php elseif (is_numeric($total) && $base_forecast_values[$item] == 0 && $total > 0) : ?>
                            (Recent Avg.)
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