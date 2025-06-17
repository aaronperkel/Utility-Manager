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
// Define constants/thresholds for trend calculation
define('MIN_SUM_FOR_TREND_CALC', 1.00);
define('MAX_POSITIVE_TREND_ADJUSTMENT', 0.75); // +75%
define('MIN_NEGATIVE_TREND_ADJUSTMENT', -0.50); // -50%

$trend_adjustment_percentages = ['Gas' => 0.0, 'Electric' => 0.0, 'Internet' => 0.0];
$trend_status = ['Gas' => 'N/A', 'Electric' => 'N/A', 'Internet' => 'N/A']; // To store status of trend calc
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

    if ($previous_year_sum < MIN_SUM_FOR_TREND_CALC) {
        $trend_adjustment_percentages[$item] = 0.0;
        $trend_status[$item] = 'no_reliable_trend';
    } else {
        $percentage_change = ($current_year_sum - $previous_year_sum) / $previous_year_sum;
        if ($percentage_change > MAX_POSITIVE_TREND_ADJUSTMENT) {
            $trend_adjustment_percentages[$item] = MAX_POSITIVE_TREND_ADJUSTMENT;
            $trend_status[$item] = 'capped_positive';
        } elseif ($percentage_change < MIN_NEGATIVE_TREND_ADJUSTMENT) {
            $trend_adjustment_percentages[$item] = MIN_NEGATIVE_TREND_ADJUSTMENT;
            $trend_status[$item] = 'capped_negative';
        } else {
            $trend_adjustment_percentages[$item] = $percentage_change;
            $trend_status[$item] = 'calculated';
        }
    }
}

// 4. Calculate Final Forecast
// Re-initialize forecast_totals to ensure clean slate before applying new logic
$forecast_totals = ['Gas' => 'N/A', 'Electric' => 'N/A', 'Internet' => 'N/A'];

foreach ($bill_items as $item) {
    // Default to N/A, will be overwritten if calculation is possible
    $forecast_totals[$item] = 'N/A';

    if ($trend_status[$item] === 'no_reliable_trend') {
        if ($base_forecast_values[$item] >= MIN_SUM_FOR_TREND_CALC) {
            $forecast_totals[$item] = $base_forecast_values[$item]; // Use base value without trend
            // $trend_status[$item] remains 'no_reliable_trend' - will be indicated in display
        } else {
            // Fallback to simple average if base is also too low
            $sql_simple_avg_fallback = "
                SELECT AVG(fldTotal) as average_total
                FROM tblUtilities
                WHERE fldItem = :item
                  AND STR_TO_DATE(fldDate, '%Y-%m-%d') >= STR_TO_DATE(:six_months_ago, '%Y-%m-%d')
            ";
            $stmt_simple_avg_fallback = $pdo->prepare($sql_simple_avg_fallback);
            // Use $current_trend_start_date for the 6-month window
            $stmt_simple_avg_fallback->execute([':item' => $item, ':six_months_ago' => $current_trend_start_date]);
            $simple_avg_result_fallback = $stmt_simple_avg_fallback->fetch(PDO::FETCH_ASSOC);
            if ($simple_avg_result_fallback && $simple_avg_result_fallback['average_total'] !== null) {
                $forecast_totals[$item] = (float)$simple_avg_result_fallback['average_total'];
                $trend_status[$item] = 'fallback_avg'; // Update status to reflect fallback
            }
            // If still no data, it remains 'N/A' as initialized
        }
    } else { // Trend was 'calculated', 'capped_positive', or 'capped_negative'
        if ($base_forecast_values[$item] >= MIN_SUM_FOR_TREND_CALC) {
            $forecast_totals[$item] = $base_forecast_values[$item] * (1 + $trend_adjustment_percentages[$item]);
        } elseif ($base_forecast_values[$item] < MIN_SUM_FOR_TREND_CALC && $base_forecast_values[$item] > 0) {
            // Base value is small but not zero, proceed with trend but it might be less reliable
             $forecast_totals[$item] = $base_forecast_values[$item] * (1 + $trend_adjustment_percentages[$item]);
             // Optionally, change status here if we want to flag this specific case
        } else { // Base value is zero or effectively zero
            // Fallback to simple average as base is not usable
             $sql_simple_avg_fallback_no_base = "
                SELECT AVG(fldTotal) as average_total
                FROM tblUtilities
                WHERE fldItem = :item
                  AND STR_TO_DATE(fldDate, '%Y-%m-%d') >= STR_TO_DATE(:six_months_ago, '%Y-%m-%d')
            ";
            $stmt_simple_avg_fallback_no_base = $pdo->prepare($sql_simple_avg_fallback_no_base);
            $stmt_simple_avg_fallback_no_base->execute([':item' => $item, ':six_months_ago' => $current_trend_start_date]);
            $simple_avg_result_fallback_no_base = $stmt_simple_avg_fallback_no_base->fetch(PDO::FETCH_ASSOC);
            if ($simple_avg_result_fallback_no_base && $simple_avg_result_fallback_no_base['average_total'] !== null) {
                $forecast_totals[$item] = (float)$simple_avg_result_fallback_no_base['average_total'];
                $trend_status[$item] = 'fallback_avg_no_base'; // Update status
            }
            // If still no data, it remains 'N/A'
        }
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
                Trend adjustments are capped at <?= MAX_POSITIVE_TREND_ADJUSTMENT*100 ?>% increase or <?= abs(MIN_NEGATIVE_TREND_ADJUSTMENT*100) ?>% decrease.
                If historical data is insufficient for a reliable trend or base, a simple average of the last 6 months is used.
            </p>
            <ul>
                <?php foreach ($forecast_totals as $item => $total): ?>
                    <li>
                        <?= htmlspecialchars($item) ?>:
                        <?= is_numeric($total) ? '$' . number_format($total, 2) : htmlspecialchars($total) ?>
                        <?php
                        $status_text = '';
                        if (is_numeric($total)) {
                            switch ($trend_status[$item]) {
                                case 'calculated':
                                    $status_text = sprintf('(Trend: %+.1f%%)', $trend_adjustment_percentages[$item] * 100);
                                    break;
                                case 'capped_positive':
                                    $status_text = sprintf('(Trend: %+.1f%% Cap)', MAX_POSITIVE_TREND_ADJUSTMENT * 100);
                                    break;
                                case 'capped_negative':
                                    $status_text = sprintf('(Trend: %+.1f%% Cap)', MIN_NEGATIVE_TREND_ADJUSTMENT * 100);
                                    break;
                                case 'no_reliable_trend':
                                    // Check if base_forecast_value was used directly
                                    if ($base_forecast_values[$item] >= MIN_SUM_FOR_TREND_CALC && $forecast_totals[$item] == $base_forecast_values[$item]) {
                                       $status_text = '(Base Used - Trend N/A)';
                                    } else {
                                       // This case should ideally be handled by fallback_avg if base was also too low
                                       $status_text = '(Trend N/A)';
                                    }
                                    break;
                                case 'fallback_avg':
                                case 'fallback_avg_no_base':
                                    $status_text = '(Recent Avg.)';
                                    break;
                            }
                        }
                        echo ' ' . htmlspecialchars($status_text);
                        ?>
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