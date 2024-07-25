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
    </main>
<?php include 'footer.php' ?>