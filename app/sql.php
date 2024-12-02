<?php
include '../top.php';
?>
<main>
    <p>Create Table SQL</p>

    <pre>
    CREATE TABLE tblUtilities (
        pmkBillID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fldDate VARCHAR(50) DEFAULT NULL,
        fldItem VARCHAR(50) DEFAULT NULL,
        fldTotal VARCHAR(50) DEFAULT NULL,
        fldCost VARCHAR(50) DEFAULT NULL,
        fldDue VARCHAR(50) DEFAULT NULL,
        fldStatus VARCHAR(50) DEFAULT NULL,
        fldView VARCHAR(150) DEFAULT NULL,
        fldOwe VARCHAR(150) DEFAULT NULL
    );
    </pre>

    <h2>Insert records into the table</h2>
    <pre>
    INSERT INTO tblUtilities
    (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
    VALUES
    ('06/26/24', 'Gas', '$9.00', '$3.00', '06/27/24', 'Paid', '0613.pdf', 'Aaron, Owen, Ben');
    </pre>
</main>
<?php include '../footer.php'?>