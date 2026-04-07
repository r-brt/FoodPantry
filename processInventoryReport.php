<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_cache_expire(30);
session_start();

if (!isset($_SESSION['access_level']) || $_SESSION['access_level'] < 2) {
    header('Location: login.php');
    die();
}

//require_once('database/dbPersons.php');
//require_once('database/dbEvents.php');
require_once('database/dbinfo.php');

// 👉 Add month completeness check function
// function is_month_complete($dateFrom) {
//     $lastDayOfMonth = date("Y-m-t", strtotime($dateFrom));
//     $today = date("Y-m-d");
//     return $today > $lastDayOfMonth;
// }

$selectedWeekDate = $_POST['week'] ?? '';
$rawItemCategories = $_POST['name'] ?? '';
$selectedItemCategories = is_array($rawItemCategories) ? $rawItemCategories : [$rawItemCategories];

if (in_array('', $selectedItemCategories, true) || empty($selectedItemCategories)) {
    $selectedItemCategories = []; 
} else {
    $selectedItemCategories = array_values(array_filter($selectedItemCategories));
}

$selectedLocation = $_POST['location'] ?? '';
$format = $_POST['format'] ?? 'csv';

$con = connect();

$eventSql = "
    SELECT id 
    FROM dbinventoryevent 
    WHERE DATE(date) = ? AND location = ?
";

$eventStmt = $con->prepare($eventSql);
$eventStmt->bind_param("ss", $selectedWeekDate, $selectedLocation);
$eventStmt->execute();
$eventResult = $eventStmt->get_result();

if ($eventResult->num_rows === 0) {
    echo "No inventory event found for the selected date and location.";
    exit();
}

$eventRow = $eventResult->fetch_assoc();
$selectedWeek = $eventRow['id'];
$eventStmt->close();

$sql = "SELECT dic.id, dic.name as item_name, 
        dbic.quantity as boxes, 
        dic.itemsPerBox, 
        dbic.quantity * dic.itemsPerBox as total_count, 
        dbic.inventoryEventID as inventoryEventId 
        FROM dbitemcategory dic 
        INNER JOIN dbitemcounts dbic on dic.id = dbic.itemCategoryId 
        WHERE dbic.inventoryEventID = ?";
$params = [$selectedWeek];
$types = "s";

// managing selected item categories
if (!empty($selectedItemCategories)) {
    // adding placeholders based off of how many items were selected
    $placeholders = implode(',', array_fill(0, count($selectedItemCategories), '?'));
    $sql .= " AND dic.id IN ($placeholders)";
    // for the bind function to work with all the items
    $types .= str_repeat('s', count($selectedItemCategories));
    $params = array_merge($params, $selectedItemCategories); 
}

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();



$reportData = [];
while ($row = $result->fetch_assoc()) {
    $reportData[] = $row;
}

// CSV EXPORT
if ($format === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=inventory_report_{$selectedWeek}.csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen('php://output', 'w');
    fputcsv($output, ["Inventory Report"]);

    // Column Headers
    fputcsv($output, ["Item Name", "Boxes", "Items Per Box", "Total Count"]);

    // Data
    foreach ($reportData as $row) {
        fputcsv($output, [
            $row["item_name"],
            $row["boxes"],
            $row["itemsPerBox"],
            $row["total_count"]
        ]);
    }
    fclose($output);
    exit();
}

// EXCEL EXPORT
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=inventory_report.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
echo "<html><head><meta charset='UTF-8'></head><body>";
echo "<table border='1' style='border-collapse: collapse; font-family: Arial, sans-serif; text-align: center;'>";

// Report Title
echo "<tr><th colspan='4' >Inventory Report</th></tr>";

// Column Headers
echo "<tr>
        <th style='background-color: #88CCEE; padding: 5px;'>Item Name</th>
        <th style='background-color: #AA4499; padding: 5px;'>Boxes</th>
        <th style='background-color: #DDCC77; padding: 5px;'>Items Per Box</th>
        <th style='background-color: #88CCEE; padding: 5px;'>Total Count</th>
        </tr>";

// Data Rows
foreach ($reportData as $row) {
    echo "<tr>
            <td style='background-color: #EAEAEA; padding: 5px; text-align: center;'>{$row["item_name"]}</td>
            <td style='padding: 5px;'>{$row["boxes"]}</td>
            <td style='padding: 5px;'>{$row["itemsPerBox"]}</td>
            <td style='padding: 5px;'>{$row["total_count"]}</td>
            </tr>";
}

echo "</table>";
echo "</body></html>";
exit();
?>
