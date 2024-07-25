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
            $sql = 'SELECT fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe FROM tblUtilities';
            $statement = $pdo->prepare($sql);
            $statement->execute();

            $cells = $statement->fetchAll();

            foreach($cells as $cell) {
                print '<tr>';
                print '<td class="hover">' . $cell['fldDate'] . '</td>';
                print '<td class="hover">' . $cell['fldItem'] . '</td>';
                print '<td class="hover">$' . $cell['fldTotal'] . '</td>';
                print '<td class="hover">$' . $cell['fldCost'] . '</td>';
                print '<td class="hover">' . $cell['fldDue'] . '</td>';
                print '<td class="hover ';

                if ($cell['fldStatus'] == "Paid") {
                    print 'paid';
                    print '">' . $cell['fldStatus'] . '</td>';
                }
                else {
                    print 'notPaid';
                    print '"><div class="tooltip">' . $cell['fldStatus'] . '<span class="tooltiptext">Unpaid: ' . $cell['fldOwe'] . '</span></div></td>';
                }
                print '<td class="spanTwoMobile hover"><a href=' . $cell['fldView'] . ' target="_blank">PDF</a>&nbsp&nbsp<a href="' . $cell['fldView'] . '" download><img src="images/dl.png" width="20" class="zoom"></a></td>';
                print '</tr>';
            }
            ?>
        </table>
        <p class="tableNote">&ast; A grey dashed line divides billing cycles.</p>
        <div class="dropdown tableNote">
            <button onclick="displayList()" class="dropbtn"><i class="fa fa-calendar-o" aria-hidden="true"></i> Add to Calendar</button>
            <div id="dropdown" class="dropdown-content tableNote">
                <a href="cal.ics"> <i class="fa fa-apple" aria-hidden="true"></i> Apple Calendar</a>
                <a href="https://calendar.google.com/calendar/embed?src=ac648aqcdoquvq16v1ab33tcckgmti35%40import.calendar.google.com&ctz=America%2FNew_York" target="_blank"> <i class="fa fa-google" aria-hidden="true"></i> Google Calendar</a>
                <a href="cal.ics" download> <i class="fa fa-ics" aria-hidden="true"></i> Download ICS</a>
            </div>
        </div>
    </main>
<?php include 'footer.php' ?>