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
?>
<main>
    <h2 class="section-title">Trends</h2>
    <div class="table-responsive">
        <canvas id="trendsChart"></canvas>
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