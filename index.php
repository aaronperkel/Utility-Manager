<!-- HOME PAGE -->
<?php include 'top.php'; ?>
    <main>
        <h2>Home</h2>
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
            $sql = 'SELECT fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe FROM tblUtilities ORDER BY fldDate DESC';
            $statement = $pdo->prepare($sql);
            $statement->execute();

            $cells = $statement->fetchAll();

            foreach($cells as $cell) {
                print '<tr class="hover">';
                print '<td>' . $cell['fldDate'] . '</td>';
                print '<td>' . $cell['fldItem'] . '</td>';
                print '<td>$' . number_format($cell['fldTotal'], 2) . '</td>';
                print '<td>$' . $cell['fldCost'] . '</td>';
                print '<td>' . $cell['fldDue'] . '</td>';
                
                // Corrected code for the Status column
                if ($cell['fldStatus'] == "Paid") {
                    print '<td class="paid">' . $cell['fldStatus'] . '</td>';
                } else {
                    print '<td class="notPaid">';
                    print '<div class="tooltip">' . $cell['fldStatus'] . '<span class="tooltiptext">Unpaid: ' . $cell['fldOwe'] . '</span></div>';
                    print '</td>';
                }
                print '<td class="spanTwoMobile hover"><a href=' . $cell['fldView'] . ' target="_blank">PDF</a>&nbsp&nbsp<a href="' . $cell['fldView'] . '" download><img src="images/dl.png" width="20" class="zoom"></a></td>';
                print '</tr>';
            }
            ?>
        </table>
        <p class="tableNote">&ast; A grey dashed line divides billing cycles.</p>
        <br>
        <p class="tableNote">Add to Calendar:</p>
        <a href="cal.ics"> <i class="fa fa-apple" aria-hidden="true"></i> Apple Calendar</a> | 
        <a href="https://calendar.google.com/calendar/embed?src=ac648aqcdoquvq16v1ab33tcckgmti35%40import.calendar.google.com&ctz=America%2FNew_York" target="_blank"> <i class="fa fa-google" aria-hidden="true"></i> Google Calendar</a> | 
        <a href="cal.ics" download> <i class="fa fa-ics" aria-hidden="true"></i> Download ICS</a>
    </main>
<?php include 'footer.php' ?>