<?php
session_cache_expire(30);
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);
date_default_timezone_set("America/New_York");

// Ensure admin authentication
if (!isset($_SESSION['access_level']) || $_SESSION['access_level'] < 1) {
    header('Location: login.php');
    die();
}

// Get current fiscal year
$currentMonth = date("m");
$currentYear = date("Y");
$fiscalYearStart = ($currentMonth >= 10) ? $currentYear : $currentYear -1;
$fiscalYearEnd = $fiscalYearStart + 1;

// Database connection and data fetching
require_once('database/dbinfo.php');
$conn = connect();

// Fetch inventory events with dates and locations
$eventResult = $conn->query("
    SELECT DISTINCT ie.date, ie.location
    FROM dbinventoryevent ie
    ORDER BY ie.date DESC, ie.location ASC
");
$events = [];
if ($eventResult) {
    while ($row = $eventResult->fetch_assoc()) {
        $events[] = $row;
    }
}

// Fetch food categories
$categoryResult = $conn->query("
    SELECT id, name
    FROM dbitemcategory
    WHERE status = 'Active'
    ORDER BY name ASC
");
$categories = [];
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get selected category for trend view
$selectedCategory = isset($_GET['category']) ? intval($_GET['category']) : (count($categories) > 0 ? $categories[0]['id'] : null);

// Fetch trend data for selected category (consolidated by date across locations)
$trendData = [];
if ($selectedCategory) {
    $sql = "
        SELECT 
            DATE(ie.date) as eventDate,
            dic.name as itemName,
            dic.itemsPerBox,
            COALESCE(SUM(dbic.quantity), 0) as total_boxes
        FROM dbinventoryevent ie
        LEFT JOIN dbitemcounts dbic ON ie.id = dbic.inventoryEventId
        LEFT JOIN dbitemcategory dic ON dbic.itemCategoryId = dic.id
        WHERE dic.id = ?
        GROUP BY DATE(ie.date), dic.name, dic.itemsPerBox
        ORDER BY DATE(ie.date) ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selectedCategory);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $trendData[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Whiskey Valor | Attendance Reports</title>
    <!--<script src="js/data-filters.js" defer></script>-->
    <link href="css/base.css" rel="stylesheet">
    <?php require_once('header.php'); ?>
    <style>
        .title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--secondary-accent-color); 
        }
        .report-container {
            max-width: 1100px;
            margin: 0 auto 4rem auto;
            padding: 1rem;
        }
        .report-section {
            background-color: white;
            /* border: 1px solid var(--shadow-and-border-color); */
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .report-section h1 {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: var(--secondary-accent-color);
        }
        .report-section h2 {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: var(--secondary-accent-color);
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th,
        .report-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--shadow-and-border-color);
            color: var(--page-font-color);
        }
        .report-table th {
            background-color: var(--main-color);
            color: var(--button-font-color);
            font-weight: 500;
        }
        .report-table tr:hover {
            background-color: rgba(255,255,255,0.05);
        }
        .low-stock-badge {
            display: inline-block;
            background-color: var(--error-toast-background-color);
            color: var(--error-toast-font-color);
            padding: 0.2rem 0.6rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .expired-text {
            color: var(--error-toast-background-color);
            font-weight: 600;
        }
        .chart-wrapper {
            position: relative;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .chart-controls {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .chart-controls button {
            padding: 0.4rem 1rem;
            border: 2px solid var(--accent-color);
            border-radius: 0.25rem;
            background-color: transparent;
            color: var(--page-font-color);
            cursor: pointer;
            font-weight: 500;
            width: auto;
            font-size: 0.85rem;
        }
        .chart-controls button.active,
        .chart-controls button:hover {
            background-color: var(--accent-color);
            color: var(--button-font-color);
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--inactive-font-color);
        }
        .week-selector {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .week-selector label {
            color: var(--page-font-color);
            font-weight: 500;
        }
        .week-selector select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            cursor: pointer;
            min-width: 200px;
        }
        .week-selector select:hover {
            background-color: rgba(0,0,0,0.3);
        }
        .row-green {
            background-color: rgba(34, 197, 94, 0.15) !important;
        }
        .row-green:hover {
            background-color: rgba(34, 197, 94, 0.25) !important;
        }
        .row-yellow {
            background-color: rgba(234, 179, 8, 0.15) !important;
        }
        .row-yellow:hover {
            background-color: rgba(234, 179, 8, 0.25) !important;
        }
        .row-red {
            background-color: rgba(239, 68, 68, 0.15) !important;
        }
        .row-red:hover {
            background-color: rgba(239, 68, 68, 0.25) !important;
        }
        .basket-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .basket-row {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .basket-label {
            color: var(--page-font-color);
            width: 160px;
            flex-shrink: 0;
        }
        .basket-qty {
            width: 100px;
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            font-size: 0.9rem;
        }
        .generate-btn {
            padding: 0.5rem 1.5rem;
            background-color: var(--accent-color);
            color: var(--button-font-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            max-width: 500px;
        }
        .generate-btn:hover {
            opacity: 0.85;
        }
        .form-section {
            margin-bottom: 1.5rem;
        }
        .form-section label {
            display: block;
            color: var(--page-font-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .form-section select {
            width: 100%;
            max-width: 300px;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            cursor: pointer;
        }
        .form-section select:hover {
            background-color: rgba(0,0,0,0.3);
        }
        .category-trend-row {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .change-percentage {
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            min-width: 80px;
            text-align: center;
        }
        .change-positive {
            background-color: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .change-negative {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .change-neutral {
            background-color: rgba(156, 163, 175, 0.2);
            color: var(--page-font-color);
        }
        .row-number {
            text-align: center;
            color: var(--inactive-font-color);
            font-weight: 500;
            width: 50px;
        }
        @media only screen and (max-width: 768px) {
            .report-table th,
            .report-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            .report-container {
                padding: 0.5rem;
            }
            div.table-wrapper {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section with Title -->
        <div class="center-header">
            <h1 class="title">Inventory Analytics</h1>
        </div>

    <main>
        <div class="report-container">
            
            <!-- Export Section -->
            <div class="report-section">
                <h2>Export Inventory to Spreadsheet</h2>
                
                <form method="POST" action="processInventoryReport.php">
                    <div class="form-section">
                        <label for="weekSelect">Select Week to Export</label>
                        <select name="week" id="weekSelect" required>
                            <option value="">-- Select Week --</option>
                            <?php 
                                $uniqueDates = [];
                                foreach ($events as $event) {
                                    if (!in_array($event['date'], $uniqueDates)) {
                                        $uniqueDates[] = $event['date'];
                                    }
                                }
                                foreach ($uniqueDates as $date): ?>
                                <option value="<?= htmlspecialchars($date) ?>">
                                    <?= htmlspecialchars($date) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-section">
                        <label for="locationSelect">Location</label>
                        <select name="location" id="locationSelect" required>
                            <option value="">-- Select Location --</option>
                            <option value="Warehouse">Warehouse</option>
                            <option value="Pantry">Pantry</option>
                        </select>
                    </div>

                    <div class="form-section">
                        <label for="format">File Format</label>
                        <select name="format" id="format">
                            <option value="excel">Excel (.xls)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>

                    <div style="margin-top: 2rem;">
                        <input type="hidden" value="<?php echo $_SESSION['_id']; ?>" name="admin" id="admin">
                        <input type="hidden" value="<?php echo date("d-M-Y H:i:s e") ?>" name="time" id="time">
                        <button type="submit" name="generate_button" class="generate-btn">Export to Spreadsheet</button>
                    </div>
                </form>
            </div>

            <!-- Category Trend Section -->
            <div class="report-section">
                <h2>Food Item Trends</h2>
                
                <div class="form-section">
                    <label for="categorySelect">Select Food Item Category</label>
                    <select id="categorySelect" onchange="window.location.href='?category=' + this.value">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['id']) ?>" <?= ($category['id'] == $selectedCategory) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-wrapper">
                    <table class="report-table" id="trendTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Date</th>
                                <th>Total Boxes</th>
                                <th>Total Items</th>
                                <th>Change</th>
                                <th>% Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($trendData) > 0): ?>
                                <?php foreach ($trendData as $index => $row): ?>
                                    <?php
                                        $previousBoxes = ($index > 0) ? $trendData[$index - 1]['total_boxes'] : null;
                                        $currentBoxes = $row['total_boxes'];
                                        $changeBoxes = ($previousBoxes !== null) ? ($currentBoxes - $previousBoxes) : 0;
                                        $percentChange = ($previousBoxes !== null && $previousBoxes > 0) ? (($changeBoxes / $previousBoxes) * 100) : 0;
                                        $totalItems = $currentBoxes * $row['itemsPerBox'];
                                    ?>
                                    <tr>
                                        <td class="row-number"><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($row['eventDate']) ?></td>
                                        <td><?= htmlspecialchars($currentBoxes) ?></td>
                                        <td><?= htmlspecialchars($totalItems) ?></td>
                                        <td><?= ($previousBoxes !== null) ? ($changeBoxes >= 0 ? '+' : '') . htmlspecialchars($changeBoxes) : '—' ?></td>
                                        <td>
                                            <?php if ($previousBoxes !== null): ?>
                                                <span class="change-percentage <?= $changeBoxes > 0 ? 'change-positive' : ($changeBoxes < 0 ? 'change-negative' : 'change-neutral') ?>">
                                                    <?= ($changeBoxes >= 0 ? '+' : '') . number_format($percentChange, 1) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="change-percentage change-neutral">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No data available for this category.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

</body>
</html>

