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

    if (isset($_FILES['view']) && $_FILES['view']['error'] === UPLOAD_ERR_OK) {
        if ($item == "Gas") {
        $uploadDir = 'Bills/Gas/';
        } elseif ($item == "Electric") {
            $uploadDir = 'Bills/Electric/';
        } elseif ($item == "Internet") {
            $uploadDir = 'Bills/Internet/';
        }
        $uploadFile = $uploadDir . basename($_FILES['view']['name']);
        
        if (move_uploaded_file($_FILES['view']['tmp_name'], $uploadFile)) {
            $filePath = $uploadFile;
        }
    } else {
        $filePath = null; // No file uploaded
    }

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
        $command = escapeshellcmd('python new_bill.py');
        $output = shell_exec($command);
    }
    ?>
    <main>
        <h2>Admin Portal</h2>
        <table>
            <tr>
                <th colspan="7" class="spanTwoMobile">Utilites</th>
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
            $sql = 'SELECT pmkBillID, fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe FROM tblUtilities';
            $statement = $pdo->prepare($sql);
            $statement->execute();

            $cells = $statement->fetchAll();
            
            foreach($cells as $cell) {
                print '<tr>';
                print '<td class="hover">' . $cell['fldDate'] . '</td>';
                print '<td class="hover">' . $cell['fldItem'] . '</td>';
                print '<td class="hover">$' . $cell['fldTotal'] . '</td>';
                print '<td class="hover">$' . $cell['fldCost'] . '</td>';

                if ($cell['fldStatus'] == "Paid") {
                    print '<td class="hover">' . $cell['fldDue'] . '</td>';
                    print '<td class="paid">' . $cell['fldStatus'];
                    print '<form method="POST" action="update_status_unpaid.php">';
                    print '<input type="hidden" name="id" value="' . $cell['pmkBillID'] . '">';
                    print '<input type="submit" name="updateStatus" class="paidButton" value="Mark as Unpaid">';
                    print '</form></td>';
                } else {
                    print '<td class="hover">' . $cell['fldDue'];
                    print '<form method="POST" action="send_reminder.php">';
                    print '<input type="hidden" name="pmk" value="' . $cell['pmkBillID'] . '">';
                    print '<input type="submit" name="sendReminder" class="paidButton" value="Send Reminder" style="margin-top:6px">';
                    print '</form></td>';
                    print '<td class="notPaid">';

                    print '<form method="POST" action="update_owe.php">';
                    print '<input type="hidden" name="id2" value="' . $cell['pmkBillID'] . '">';
                    print '<input type="text" name="updateNames" value="'. $cell['fldOwe'] . '">';
                    print '</form>';

                    print '<form method="POST" action="update_status_paid.php">';
                    print '<input type="hidden" name="id" value="' . $cell['pmkBillID'] . '">';
                    print '<input type="submit" name="updateStatus" class="paidButton" value="Mark as Paid">';
                    print '</form></td>';
                }
                print '<td class="hover spanTwoMobile"><a href=' . $cell['fldView'] . ' target="_blank">PDF</a>&nbsp;&nbsp;<a href="' . $cell['fldView'] . '" download><img alt="download button" src="images/dl.png" width="20" class="zoom"></a></td>';
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
                    <td class="spanTwoMobile">
                        <label for="view" class="custom-file-upload">
                            <input type="file" id="view" name="view" required>
                            Upload File
                        </label>
                    </td>
                </tr>
                <tr>
                    <td colspan="7" class="spanTwoMobile"><input type="submit" value="Submit"></td>
                </tr>
            </form>
        </table>
    </main>
<?php include 'footer.php' ?>
