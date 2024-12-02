<?php
$sql = 'SELECT fldDue, fldStatus, fldItem FROM tblUtilities';
$statement = $pdo->query($sql);

$dates = [];
$status = [];
$item = [];
$bills = [];

while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $dates[] = $row['fldDue'];
    $status[] = $row['fldStatus'];
    $item[] = $row['fldItem'];
}

$ics_content = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//81 Buell Utilities//EN\n";

for ($i = 0; $i < count($dates); $i++) {
    // Parse date and format as required
    $due_date = DateTime::createFromFormat('Y-m-d', substr($dates[$i], 0, 10));
    $due_date_str = $due_date->format("Ymd");
    
    // Determine event status
    $event_status = "- PAID";
    if (strtolower($status[$i]) != "paid") {
        $event_status = "";
    }
    
    // Build the ICS event entry
    $summary = "{$item[$i]} Bill Due {$event_status}";
    $dtstamp = gmdate('Ymd\THis\Z');
    
    $ics_content .= "BEGIN:VEVENT\n";
    $ics_content .= "UID:{$item[$i]}-{$dates[$i]}";
    $ics_content .= "DTSTAMP:{$dtstamp}\n";
    $ics_content .= "DTSTART;VALUE=DATE:{$due_date_str}\n";
    $ics_content .= "DTEND;VALUE=DATE:{$due_date_str}\n";
    $ics_content .= "SUMMARY:{$summary}\n";
    $ics_content .= "END:VEVENT\n";
}

$ics_content .= "END:VCALENDAR";

$file = fopen("./cal.ics", "w");
fwrite($file, $ics_content);
fclose($file);
?>