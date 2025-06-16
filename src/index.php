<?php
// index.php
// Public-facing page to display utility bills and the amount owed by the current user.
// Features pagination for browsing bills.

include 'top.php'; // Includes header, navigation, and database connection (connect-DB.php).
                // .env variables are also loaded via connect-DB.php.

// --- Function Definitions ---

/**
 * Resolves the current user's display name.
 * This function maps a system-level username (e.g., from web server authentication like Shibboleth)
 * to a more friendly application-specific name using a configurable mapping.
 *
 * @param string $remoteUser The system username (e.g., `$_SERVER['REMOTE_USER']`).
 * @param array $uidToNameMapping An associative array mapping system UIDs to application names.
 * @return string The resolved application-specific user name, or the original remoteUser if no mapping found.
 */
function resolveCurrentUserName(string $remoteUser, array $uidToNameMapping): string
{
    return $uidToNameMapping[$remoteUser] ?? $remoteUser; // Return mapped name or original if not found.
}

/**
 * Calculates the total outstanding amount owed by a specific user for unpaid bills.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $userName The application-specific name of the user.
 * @return float The total amount owed by the user.
 */
function getUserOwedAmount(PDO $pdo, string $userName): float
{
    // Updated SQL to work with the normalized schema
    $sql = "
        SELECT SUM(u.fldCost) AS owed
        FROM tblUtilities u
        JOIN tblBillOwes bo ON u.pmkBillID = bo.billID
        JOIN tblPeople p ON bo.personID = p.personID
        WHERE p.personName = :userName
          AND u.fldStatus <> 'Paid'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':userName' => $userName]);
    return (float)($stmt->fetch(PDO::FETCH_ASSOC)['owed'] ?? 0);
}

/**
 * Fetches a subset of utility bills for a specific page, ordered by date.
 * (pmkBillID added, fldOwe removed)
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $limit The maximum number of bills to fetch for the page.
 * @param int $offset The starting offset for fetching bills (for pagination).
 * @return array An array of bill records for the current page.
 */
function getBillsForPage(PDO $pdo, int $limit, int $offset): array
{
    $sql = '
        SELECT pmkBillID, fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView
        FROM tblUtilities
        ORDER BY fldDate DESC
        LIMIT :limit OFFSET :offset
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all matching rows as associative arrays.
}

/**
 * Gets the total count of all utility bills in the database.
 * Used for calculating total pages for pagination.
 *
 * @param PDO $pdo The PDO database connection object.
 * @return int The total number of bills.
 */
function getTotalBillCount(PDO $pdo): int
{
    $sql = 'SELECT COUNT(*) FROM tblUtilities'; // Simple count of all rows.
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return (int)$stmt->fetchColumn(); // Returns the count as an integer.
}

/**
 * Groups an array of bill records by the year they were billed.
 * The resulting array is sorted by year in descending order (most recent year first).
 *
 * @param array $bills An array of bill records (associative arrays).
 * @return array An array where keys are years and values are arrays of bills for that year.
 */
function groupBillsByYear(array $bills): array
{
    $billsByYear = [];
    foreach ($bills as $bill) {
        try {
            // Create a DateTime object from the bill's date string.
            $dt = new DateTime($bill['fldDate']);
            $year = $dt->format('Y'); // Extract the year.
            $billsByYear[$year][] = $bill; // Add bill to the corresponding year's group.
        } catch (Exception $e) {
            // Log error if date format is invalid and prevents DateTime creation.
            error_log("Invalid date format for bill (ID: " . ($bill['pmkBillID'] ?? 'N/A') . "): " . $bill['fldDate'] . " - " . $e->getMessage());
        }
    }
    krsort($billsByYear); // Sort the groups by year in reverse (descending) order.
    return $billsByYear;
}

// --- Main Logic ---

// Load UID (User ID from web server auth) to Name mapping from .env file.
// This allows mapping system usernames (e.g., 'aperkel') to application-specific names (e.g., 'Aaron').
$uidToNameJson = $_ENV['APP_UID_TO_NAME_MAPPING'] ?? '{}'; // Default to empty JSON if not set.
$uidToNameMapping = json_decode($uidToNameJson, true); // Decode the JSON string into an associative array.
if (json_last_error() !== JSON_ERROR_NONE) { // Check for errors during JSON decoding.
    error_log("Failed to parse APP_UID_TO_NAME_MAPPING JSON: " . json_last_error_msg());
    // Fallback to a default mapping if JSON is invalid or missing, to ensure basic functionality.
    $uidToNameMapping = [
        'aperkel' => 'Aaron',
        'oacook' => 'Owen',
        'bquacken' => 'Ben',
    ];
}

// Determine the current user's application-specific name.
$currentRemoteUser = $_SERVER['REMOTE_USER'] ?? '';
$userName = resolveCurrentUserName($currentRemoteUser, $uidToNameMapping);

// Calculate the total amount this user owes for unpaid bills.
$userOwedAmount = getUserOwedAmount($pdo, $userName);

// Fetch IDs of bills the current user owes for (and are not globally 'Paid')
// This is done once to avoid N+1 queries in the loop.
$userOwedBillIDs = [];
if (!empty($userName)) {
    $userOwesStmt = $pdo->prepare("
        SELECT bo.billID
        FROM tblBillOwes bo
        JOIN tblPeople p ON bo.personID = p.personID
        JOIN tblUtilities u ON bo.billID = u.pmkBillID
        WHERE p.personName = :userName AND u.fldStatus <> 'Paid'
    ");
    $userOwesStmt->execute([':userName' => $userName]);
    $userOwedBillIDs = $userOwesStmt->fetchAll(PDO::FETCH_COLUMN);
}

// --- Pagination Setup ---
$billsPerPage = (int)($_ENV['APP_BILLS_PER_PAGE'] ?? 10);
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Get current page from URL query param.
if ($currentPage < 1) { // Ensure current page is not less than 1.
    $currentPage = 1;
}
$offset = ($currentPage - 1) * $billsPerPage; // Calculate the offset for SQL query.

$totalBills = getTotalBillCount($pdo); // Get total number of all bills.
$totalPages = $totalBills > 0 ? ceil($totalBills / $billsPerPage) : 1; // Calculate total pages, ensuring at least 1.

// If user tries to access a page beyond the total, redirect to the last valid page.
// This prevents errors or empty pages if an invalid page number is manually entered in URL.
if ($currentPage > $totalPages && $totalBills > 0) {
    header('Location: ?page=' . $totalPages);
    exit; // Stop script execution after redirect.
}

// Fetch the bills for the current page using the calculated limit and offset.
$billsForCurrentPage = getBillsForPage($pdo, $billsPerPage, $offset);
// Group these paged bills by year for display.
$billsByYear = groupBillsByYear($billsForCurrentPage);

?>
<main>
    <div class="banner">
        <p>You currently owe <strong>$<?= number_format($userOwedAmount, 2) ?></strong></p>
    </div>

    <h2 class="section-title">Utility Bills</h2>

    <?php if (empty($billsForCurrentPage)): ?>
        <p>No bills found for this page or no bills available.</p>
    <?php else: ?>
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
                            // Determine if the current user owes for this specific bill
                            $isOwedByCurrentUser = false;
                            if ($cell['fldStatus'] !== 'Paid') {
                                $isOwedByCurrentUser = in_array($cell['pmkBillID'], $userOwedBillIDs);
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($cell['fldDate']) ?></td>
                                <td><?= htmlspecialchars($cell['fldItem']) ?></td>
                                <td>$<?= htmlspecialchars(number_format((float)$cell['fldTotal'], 2)) ?></td>
                                <td class="col-cost">$<?= htmlspecialchars(number_format((float)$cell['fldCost'], 2)) ?></td>
                                <td><?= htmlspecialchars($cell['fldDue']) ?></td>
                                <td>
                                    <?php if ($isOwedByCurrentUser): ?>
                                        <span class="badge badge-unpaid">Unpaid by You</span>
                                    <?php else: ?>
                                        <span class="badge badge-paid">Paid by You</span>
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
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination">
            <span class="pagination-summary-text">Page <?= $currentPage ?> of <?= $totalPages ?></span>
            <ul class="pagination-links">
                <?php if ($currentPage > 1): ?>
                    <li><a href="?page=<?= $currentPage - 1 ?>">Previous</a></li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li>
                        <a href="?page=<?= $i ?>" class="<?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <li><a href="?page=<?= $currentPage + 1 ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>