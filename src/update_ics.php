<?php
$sql = 'SELECT fldDue, fldStatus, fldItem FROM tblUtilities';
$stmt = $pdo->query($sql);

$EOL = "\r\n";
$ics = "BEGIN:VCALENDAR{$EOL}VERSION:2.0{$EOL}PRODID:-//81 Buell Utilities//EN{$EOL}";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $due = DateTime::createFromFormat('Y-m-d', $row['fldDue'])->format('Ymd');
    $paidFlag = strtolower($row['fldStatus']) === 'paid' ? ' - PAID' : '';
    $dtstamp = gmdate('Ymd\THis\Z');

    $ics .= "BEGIN:VEVENT{$EOL}";
    $ics .= "UID:{$row['fldItem']}-{$due}@81buell{$EOL}";
    $ics .= "DTSTAMP:{$dtstamp}{$EOL}";
    $ics .= "DTSTART;VALUE=DATE:{$due}{$EOL}";
    $ics .= "DTEND;VALUE=DATE:{$due}{$EOL}";
    $ics .= "SUMMARY:{$row['fldItem']} Bill Due{$paidFlag}{$EOL}";
    $ics .= "END:VEVENT{$EOL}";
}

$ics .= "END:VCALENDAR{$EOL}";
file_put_contents('../www-root/cal.ics', $ics);
?>