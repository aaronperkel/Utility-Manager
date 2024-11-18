<?php include 'top.php';

$dataIsGood = false;

$date = '';
$item = '';
$total = '';
$cost = '';
$due =  '';
$status = '';

function getData($field) {
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
$due =  getData('due');
$status = getData('status');

$total = (string) $total;
$cost = (string) $cost;

if ($item == "Gas") {
    $uploadDir = 'Bills/Gas/';
} elseif ($item == "Electric") {
    $uploadDir = 'Bills/Electric/';
} elseif ($item == "Internet") {
    $uploadDir = 'Bills/Internet/';
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

$sql = 'INSERT INTO tblCSFair (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
$statement = $pdo->prepare($sql);
$data = array($date, $item, $total, $cost, $due, $status, $filePath, 'Alice, Bob, Charlie, David');

if ($dataIsGood) {
    $statement->execute($data);
    include "update_ics.php";
    $command = escapeshellcmd('python new_bill.py');
    $output = shell_exec($command);
}
?>
<main>
    <h2>Admin Portal</h2>
    <table>
        <tr>
            <th colspan="7" class="spanTwoMobile">Utilities</th>
        </tr>
        <tr>
            <th>Date Billed</th>
            <th>Item</th>
            <th>Bill Total</th>
            <th>Cost per Person</th>
            <th>Due Date</th>
            <th>Status</th>
            <th class="spanTwoMobile">See Bill</th>
        </tr>
        <?php
        $sql = 'SELECT pmkBillID, fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe FROM tblCSFair';
        $statement = $pdo->prepare($sql);
        $statement->execute();

        $cells = $statement->fetchAll();
        
        foreach($cells as $cell) {
            print '<tr>';
            print '<td class="hover">' . htmlspecialchars($cell['fldDate']) . '</td>';
            print '<td class="hover">' . htmlspecialchars($cell['fldItem']) . '</td>';
            print '<td class="hover">$' . htmlspecialchars($cell['fldTotal']) . '</td>';
            print '<td class="hover">$' . htmlspecialchars($cell['fldCost']) . '</td>';

            if ($cell['fldStatus'] == "Paid") {
                print '<td class="hover">' . htmlspecialchars($cell['fldDue']) . '</td>';
                print '<td class="paid">' . htmlspecialchars($cell['fldStatus']);
                print '<form method="POST" action="update_status_unpaid.php" class="status-action-form">';
                print '<input type="hidden" name="id" value="' . htmlspecialchars($cell['pmkBillID']) . '">';
                print '<input type="submit" name="updateStatus" class="paidButton" value="Mark as Unpaid">';
                print '</form></td>';
            } else {
                print '<td class="hover">' . htmlspecialchars($cell['fldDue']);
                print '<form method="POST" action="send_reminder.php" class="status-action-form">';
                print '<input type="hidden" name="pmk" value="' . htmlspecialchars($cell['pmkBillID']) . '">';
                print '<input type="submit" name="sendReminder" class="paidButton" value="Send Reminder" style="margin-top:6px">';
                print '</form></td>';

                print '<td class="notPaid">';
                $peopleList = ['Alice', 'Bob', 'Charlie', 'David'];
                $owedPeople = array_map('trim', explode(',', $cell['fldOwe']));

                // Start the form for updating owed names
                print '<form method="POST" action="update_owe.php" class="status-form">';
                print '<input type="hidden" name="id2" value="' . htmlspecialchars($cell['pmkBillID']) . '">';

                // Container for checkboxes
                print '<div class="checkbox-container">';

                foreach ($peopleList as $person) {
                    $isChecked = in_array($person, $owedPeople) ? '' : 'checked';
                    $personId = 'person_' . $cell['pmkBillID'] . '_' . strtolower($person);
                    print '<label class="checkbox-label" for="' . htmlspecialchars($personId) . '">';
                    print '<input type="checkbox" id="' . htmlspecialchars($personId) . '" name="paidPeople[]" value="' . htmlspecialchars($person) . '" ' . $isChecked . '> ';
                    if($isChecked) {
                        print '<span class="paid">';
                    } else {
                        print '<span class="notPaid">';
                    }
                    print htmlspecialchars($person) . '</span>';
                    print '</label>';
                }

                print '</div>'; // Close checkbox-container

                // Submit button for updating names
                print '<input type="submit" name="updateNames" class="paidButton" value="Update">';
                print '</form>';

                // Form to mark entire bill as paid
                print '<form method="POST" action="update_status_paid.php" class="status-action-form">';
                print '<input type="hidden" name="id" value="' . htmlspecialchars($cell['pmkBillID']) . '">';
                print '<input type="submit" name="updateStatus" class="paidButton" value="Mark as Paid">';
                print '</form></td>';
            }

            // PDF links
            print '<td class="hover spanTwoMobile"><a href="' . htmlspecialchars($cell['fldView']) . '" target="_blank">PDF</a>&nbsp;&nbsp;<a href="' . htmlspecialchars($cell['fldView']) . '" download><img alt="download button" src="images/dl.png" width="20" class="zoom"></a></td>';
            print '</tr>';
        }
        ?>
    </table>

    <table>
    <form action="#" id="newEntry" method="POST" enctype="multipart/form-data">
            <tr>
                <th colspan="7" class="spanTwoMobile">New Entry</th>
            </tr>
            <tr>
                <th>Date Billed</th>
                <th>Item</th>
                <th>Bill Total</th>
                <th>Cost per Person</th>
                <th>Due Date</th>
                <th>Status</th>
                <th class="spanTwoMobile">See Bill</th>
            </tr>
            <tr>
                <td><input type="date" id="date" name="date" required></td>
                <td>
                    <select id="item" name="item" required>
                        <option value="Gas">Gas</option>
                        <option value="Electric">Electric</option>
                        <option value="Internet">Internet</option>
                    </select>
                </td>
                <td><input type="number" id="total" name="total" oninput="updateField()" step=".01" required></td>
                <td><input type="number" id="cost" name="cost" step=".01" readonly></td>
                <td><input type="date" id="due" name="due" required></td>
                <td>
                    <div class="radio">
                        <input type="radio" id="unpaid" name="status" value="Unpaid" checked required>
                        <label for="unpaid">Unpaid</label>
                        <input type="radio" id="paid" name="status" value="Paid">
                        <label for="paid">Paid</label>
                    </div>
                </td>
                <td class="spanTwoMobile"><input type="text" id="view" name="view" required></td>
            </tr>
            <tr>
                <td colspan="7" class="spanTwoMobile"><input type="submit" value="Submit"></td>
            </tr>
        </form>
    </table>
</main>
<?php include 'footer.php' ?>