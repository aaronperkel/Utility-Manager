<?php
include 'top.php';
?>
<main>
    <p>Create Table SQL</p>

    <pre>
    CREATE TABLE tblCSFair(
    pmkBillID int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fldDate VARCHAR(50) DEFAULT NULL,
    fldItem VARCHAR(50) DEFAULT NULL,
    fldTotal VARCHAR(50) DEFAULT NULL,
    fldCost VARCHAR(50) DEFAULT NULL,
    fldDue VARCHAR(50) DEFAULT NULL,
    fldStatus VARCHAR(50) DEFAULT NULL,
    fldView VARCHAR(150) DEFAULT NULL,
    fldOwe VARCHAR(150) DEFAULT NULL
    )
    </pre>

    <h2>Insert records into the table</h2>
    <pre>
    INSERT INTO tblCSFair
    (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
    VALUES
    ('2024-06-26', 'Gas', '9.00', '2.25', '2024-06-27', 'Paid', '0613.pdf', 'Alice, Bob, Charlie, David');
    </pre>
</main>
<?php include 'footer.php'?>