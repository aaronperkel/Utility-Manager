<?php
include '../top.php';
?>
<main>
    <p>Current Create Table SQL for <code>tblUtilities</code> reflecting improved data types:</p>

    <pre>
    CREATE TABLE tblUtilities (
        pmkBillID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        fldDate DATE DEFAULT NULL,                            -- Changed from VARCHAR(50)
        fldItem VARCHAR(50) DEFAULT NULL,                     -- Retained VARCHAR, consider ENUM or FK in future
        fldTotal DECIMAL(10, 2) DEFAULT NULL,                 -- Changed from VARCHAR(50)
        fldCost DECIMAL(10, 2) DEFAULT NULL,                  -- Changed from VARCHAR(50)
        fldDue DATE DEFAULT NULL,                             -- Changed from VARCHAR(50)
        fldStatus VARCHAR(10) DEFAULT NULL,                   -- Changed from VARCHAR(50) to VARCHAR(10)
        fldView VARCHAR(150) DEFAULT NULL,                    -- Retained
        fldOwe VARCHAR(150) DEFAULT NULL                     -- Retained (denormalized, consider refactor in future)
    );
    </pre>

    <h2>Example <code>ALTER TABLE</code> statements (apply with caution after data backup and cleaning):</h2>
    <p><strong>Important:</strong> Before running these <code>ALTER</code> statements, ensure your existing data is compatible.
    For date fields (<code>fldDate</code>, <code>fldDue</code>), ensure they are in 'YYYY-MM-DD' format.
    For decimal fields (<code>fldTotal</code>, <code>fldCost</code>), ensure they contain only numeric values (e.g., remove '$', commas).</p>
    <pre>
    -- 1. Clean data (examples, adapt as needed for actual data):
    -- UPDATE tblUtilities SET fldDate = STR_TO_DATE(fldDate, '%Y-%m-%d') WHERE fldDate LIKE '%-%-%'; -- If already YYYY-MM-DD
    -- UPDATE tblUtilities SET fldDate = STR_TO_DATE(fldDate, '%m/%d/%Y') WHERE fldDate LIKE '%/%/%'; -- If MM/DD/YYYY
    -- UPDATE tblUtilities SET fldDate = STR_TO_DATE(fldDate, '%m/%d/%y') WHERE fldDate LIKE '%/%/__'; -- If MM/DD/YY
    -- -- Repeat similar STR_TO_DATE for fldDue based on its existing format(s)
    --
    -- UPDATE tblUtilities SET fldTotal = REPLACE(REPLACE(fldTotal, '$', ''), ',', '');
    -- UPDATE tblUtilities SET fldCost = REPLACE(REPLACE(fldCost, '$', ''), ',', '');

    -- 2. Alter table column types:
    ALTER TABLE tblUtilities
        MODIFY COLUMN fldDate DATE DEFAULT NULL,
        MODIFY COLUMN fldTotal DECIMAL(10, 2) DEFAULT NULL,
        MODIFY COLUMN fldCost DECIMAL(10, 2) DEFAULT NULL,
        MODIFY COLUMN fldDue DATE DEFAULT NULL,
        MODIFY COLUMN fldStatus VARCHAR(10) DEFAULT NULL;
    </pre>

    <h2>Example Insert records into the table (with new data types):</h2>
    <pre>
    INSERT INTO tblUtilities
    (fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView, fldOwe)
    VALUES
    ('2024-06-26', 'Gas', 9.00, 3.00, '2024-06-27', 'Paid', 'public/2024/Gas/0613.pdf', 'Aaron,Owen,Ben');
    </pre>
    <p>Notes on <code>fldItem</code>: Could be <code>ENUM('Gas', 'Electric', 'Internet')</code> if item types are strictly limited and rarely change.
    Alternatively, for more flexibility or if items have associated data, a foreign key to a separate <code>tblItems</code> could be used.</p>
    <p>Notes on <code>fldStatus</code>: Could be <code>ENUM('Unpaid', 'Paid')</code>. `VARCHAR(10)` is chosen as a balance if other statuses might appear or for simplicity if strict ENUM isn't desired immediately.</p>
    <p>Notes on <code>fldOwe</code>: This field stores a comma-separated list of names, which is a denormalized approach. For better relational integrity and querying, this could be refactored into a separate linking table (e.g., <code>tblBillOwedBy</code> with foreign keys to <code>tblUtilities</code> and a users table) in a future, more extensive database redesign.</p>
</main>
<?php include '../footer.php' ?>