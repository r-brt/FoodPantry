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
require_once('database/dbInventoryEvent.php');
$conn = connect();

// Get all inventory events sorted by date (newest first), then by ID (highest first)
$allEventObjects = get_all_inventoryEvents();
usort($allEventObjects, function($a, $b) {
    $dateDiff = strtotime($b->getDate()) - strtotime($a->getDate());
    if ($dateDiff != 0) {
        return $dateDiff;
    }
    return $b->getId() - $a->getId();
});

// Build event pairs (warehouse + matching pantry)
$eventPairs = array();
foreach($allEventObjects as $event) {
    if($event->getLocation() == 'Warehouse') {
        $pantryEvent = get_matching_inventoryEvent($event);
        $eventPairs[] = array(
            'warehouse' => $event,
            'pantry' => $pantryEvent,
            'date' => $event->getDate(),
            'warehouseId' => $event->getId(),
            'pantryId' => $pantryEvent ? $pantryEvent->getId() : null
        );
    }
}

// Also add pantry events with no matching warehouse
foreach($allEventObjects as $event) {
    if($event->getLocation() == 'Pantry') {
        $warehouseEvent = get_matching_inventoryEvent($event);
        if($warehouseEvent === null) {
            $eventPairs[] = array(
                'warehouse' => null,
                'pantry' => $event,
                'date' => $event->getDate(),
                'warehouseId' => null,
                'pantryId' => $event->getId()
            );
        }
    }
}

// Re-sort pairs by date (newest first)
usort($eventPairs, function($a, $b) {
    $dateDiff = strtotime($b['date']) - strtotime($a['date']);
    if ($dateDiff != 0) {
        return $dateDiff;
    }
    $aId = $a['warehouseId'] ?? $a['pantryId'];
    $bId = $b['warehouseId'] ?? $b['pantryId'];
    return $bId - $aId;
});

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

// Fetch ALL items trend data for graphs
$allTrendData = [];
$sqlAll = "
    SELECT
        DATE(ie.date) as eventDate,
        dic.id as categoryId,
        dic.name as itemName,
        COALESCE(SUM(dbic.quantity), 0) as total_boxes
    FROM dbinventoryevent ie
    LEFT JOIN dbitemcounts dbic ON ie.id = dbic.inventoryEventId
    LEFT JOIN dbitemcategory dic ON dbic.itemCategoryId = dic.id
    WHERE dic.id IS NOT NULL
    GROUP BY DATE(ie.date), dic.id, dic.name
    ORDER BY DATE(ie.date) ASC, dic.name ASC
";
$resultAll = $conn->query($sqlAll);
if ($resultAll) {
    while ($row = $resultAll->fetch_assoc()) {
        $allTrendData[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inventory Analytics | CCDA</title>
    <link rel="icon" type="image/x-icon" href="images/ccda-logo-white.svg">
    <!--<script src="js/data-filters.js" defer></script>-->
    <link href="css/base.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <?php require_once('header.php'); ?>
    <style>
        pageheader {
            margin-top: 3rem;
            display: flex; justify-content: center; align-items: center;
        }
        .title {
            position: fixed;
            text-align: center;
            height: 3.5rem;
            width: 40%;
            z-index: 1000;
            font-size: 2rem;
            font-weight: 600;
            color: var(--secondary-accent-color);
            background-color: white;
            padding-top: 0;
            mask-image: linear-gradient(to right, transparent, black 20%, black 80%, transparent);
        }
        .report-container {
            max-width: 1100px;
            margin: 0 auto 4rem auto;
            padding: 0.5rem 1rem;
        }
        .report-section {
            background-color: white;
            /* border: 1px solid var(--shadow-and-border-color); */
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
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
        .report-type-select {
            padding: 0.6rem 1rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            cursor: pointer;
            min-width: 320px;
            width: auto;
            font-size: 1rem;
        }
        .report-type-select:hover {
            background-color: rgba(0,0,0,0.3);
        }
        .report-type-section {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
        }
        .report-type-section .form-section {
            margin-bottom: 0;
        }
        .date-range-selector {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .date-select-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .date-select-group label {
            color: var(--page-font-color);
            font-weight: 500;
        }
        .date-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            cursor: pointer;
            min-width: 160px;
            width: auto;
        }
        .date-select:hover {
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
        ul.checkbox {
            margin-left: 15px;
        }
        ul.checkbox li input {
            margin-right: .25rem;
        }
        ul.checkbox li {
            border: 1px transparent solid;
            display: inline-block;
            width: 12rem;
        }
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
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
<pageheader>
    <h1 class="title">Inventory Analytics</h1>
</pageheader>
<body>
    <main>
        <div class="report-container">

            <!-- Report Type Selector -->
            <div class="report-section report-type-section">
                <div class="form-section">
                    <label for="reportTypeSelect">Select Report Type</label>
                    <select id="reportTypeSelect" class="report-type-select">
                        <option value="export">Export Inventory to Spreadsheet</option>
                        <option value="trends">Trends</option>
                        <option value="graphs">Graphs</option>
                        <option value="monthly">Monthly Summaries</option>
                    </select>
                </div>
            </div>

            <!-- Export Section -->
            <div class="report-section" id="exportSection">
                <h2>Export Inventory to Spreadsheet</h2>
                
                <form method="POST" action="processInventoryReport.php">
                    <div class="form-section">
                        <label for="weekSelect">Select Week to Export</label>
                        <select name="week" id="weekSelect" required>
                            <option value="">-- Select Week --</option>
                            <?php if (count($eventPairs) > 0): ?>
                                <?php foreach ($eventPairs as $pair): ?>
                                    <?php $pairId = $pair['warehouseId'] ?? $pair['pantryId']; ?>
                                    <option value="<?= htmlspecialchars($pairId) ?>">
                                        <?= date('m/d/Y', strtotime($pair['date'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-section">
                        <label for="itemSelect">Item Categories</label>
                        <ul class="checkbox">
                                <li> <input name="name[]" class="checkbox" type="checkbox" value="">All</input> </li>
                                <?php foreach ($categories as $category): ?>
                                    <li> <input name="name[]" class="checklist-column" type="checkbox" value="<?= htmlspecialchars($category['id']) ?>" <?= ($category['id'] == $selectedCategory) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                </input> </li>
                                <?php endforeach; ?>
                            </select>
                        </ul>
                    </div>



                    <div class="form-section">
                        <label for="format">File Format</label>
                        <select name="format" id="format">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="excel">Excel (.xls)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>

                    <div style="margin-top: 2rem;">
                        <input type="hidden" value="<?php echo $_SESSION['_id']; ?>" name="admin" id="admin">
                        <input type="hidden" value="<?php echo date("m/d/Y H:i:s e") ?>" name="time" id="time">
                        <button type="submit" name="generate_button" class="generate-btn">Export to Spreadsheet</button>
                    </div>
                </form>
            </div>

            <!-- Category Trend Section -->
            <div class="report-section" id="trendsSection" style="display: none;">
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
                                        <td><?= date('m/d/Y', strtotime($row['eventDate'])) ?></td>
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

            <!-- Graphs Section -->
            <div class="report-section" id="graphsSection" style="display: none;">
                <h2>Graphs</h2>

                <!-- Date Range Selector -->
                <div class="form-section">
                    <label>Select Date Range</label>
                    <div class="date-range-selector">
                        <?php
                        // Get unique dates from event pairs
                        $uniqueDates = array_unique(array_column($eventPairs, 'date'));
                        sort($uniqueDates); // Oldest first
                        $uniqueDatesDesc = array_reverse($uniqueDates); // Newest first
                        ?>
                        <div class="date-select-group">
                            <label for="graphFromDate">From:</label>
                            <select id="graphFromDate" class="date-select">
                                <?php foreach ($uniqueDates as $date): ?>
                                    <option value="<?= htmlspecialchars($date) ?>">
                                        <?= date('m/d/Y', strtotime($date)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="date-select-group">
                            <label for="graphToDate">To:</label>
                            <select id="graphToDate" class="date-select">
                                <?php foreach ($uniqueDatesDesc as $date): ?>
                                    <option value="<?= htmlspecialchars($date) ?>">
                                        <?= date('m/d/Y', strtotime($date)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Item Selector -->
                <div class="form-section">
                    <label>Select Items</label>
                    <ul class="checkbox">
                        <li><input type="checkbox" id="graphSelectAll" class="graph-item-checkbox" value="all">All</input></li>
                        <?php foreach ($categories as $category): ?>
                            <li><input type="checkbox" class="graph-item-checkbox graph-item-single" value="<?= htmlspecialchars($category['id']) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </input></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Show Graph Button -->
                <div class="form-section">
                    <button type="button" id="showGraphBtn" class="generate-btn">Show Graph</button>
                </div>

                <!-- Graph Canvas -->
                <div class="form-section" id="graphContainer" style="display: none;">
                    <canvas id="inventoryChart"></canvas>
                    <div style="margin-top: 1rem;">
                        <button type="button" id="downloadPdfBtn" class="generate-btn">Export to PDF</button>
                    </div>
                </div>
            </div>

            <!-- Monthly Summaries Section -->
            <div class="report-section" id="monthlySection" style="display: none;">
                <h2>Monthly Summaries</h2>
            </div>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var reportTypeSelect = document.getElementById('reportTypeSelect');
            var exportSection = document.getElementById('exportSection');
            var trendsSection = document.getElementById('trendsSection');
            var graphsSection = document.getElementById('graphsSection');
            var monthlySection = document.getElementById('monthlySection');

            function showSelectedSection() {
                var selected = reportTypeSelect.value;

                // Hide all sections
                exportSection.style.display = 'none';
                trendsSection.style.display = 'none';
                graphsSection.style.display = 'none';
                monthlySection.style.display = 'none';

                // Show selected section
                if (selected === 'export') {
                    exportSection.style.display = 'block';
                } else if (selected === 'trends') {
                    trendsSection.style.display = 'block';
                } else if (selected === 'graphs') {
                    graphsSection.style.display = 'block';
                } else if (selected === 'monthly') {
                    monthlySection.style.display = 'block';
                }
            }

            // Initial display
            showSelectedSection();

            // Listen for changes
            reportTypeSelect.addEventListener('change', showSelectedSection);

            // Graph "All" checkbox handling
            var graphSelectAll = document.getElementById('graphSelectAll');
            var graphItemCheckboxes = document.querySelectorAll('.graph-item-single');

            graphSelectAll.addEventListener('change', function() {
                if (this.checked) {
                    var confirmed = confirm('Are you sure you want to select all items? This may make the graph harder to read.');
                    if (!confirmed) {
                        this.checked = false;
                    } else {
                        // Uncheck individual items when "All" is selected
                        graphItemCheckboxes.forEach(function(cb) {
                            cb.checked = false;
                        });
                    }
                }
            });

            // Uncheck "All" if any individual item is checked
            graphItemCheckboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    if (this.checked) {
                        graphSelectAll.checked = false;
                    }
                });
            });

            // All trend data from PHP
            var allTrendData = <?= json_encode($allTrendData) ?>;
            var categoriesList = <?= json_encode($categories) ?>;

            // Chart instance
            var inventoryChart = null;

            // Show Graph button handler
            document.getElementById('showGraphBtn').addEventListener('click', function() {
                var fromDate = document.getElementById('graphFromDate').value;
                var toDate = document.getElementById('graphToDate').value;
                var selectAll = document.getElementById('graphSelectAll').checked;
                var selectedItems = [];

                if (selectAll) {
                    selectedItems = categoriesList.map(function(c) { return c.id.toString(); });
                } else {
                    graphItemCheckboxes.forEach(function(cb) {
                        if (cb.checked) {
                            selectedItems.push(cb.value);
                        }
                    });
                }

                if (selectedItems.length === 0) {
                    alert('Please select at least one item.');
                    return;
                }

                // Filter data by date range and selected items
                var filteredData = allTrendData.filter(function(d) {
                    var dateMatch = d.eventDate >= fromDate && d.eventDate <= toDate;
                    var itemMatch = selectedItems.includes(d.categoryId.toString());
                    return dateMatch && itemMatch;
                });

                // Get unique dates for x-axis
                var dates = [...new Set(filteredData.map(function(d) { return d.eventDate; }))].sort();

                // Generate colors dynamically based on number of items
                function generateColors(count) {
                    var colors = [];
                    for (var i = 0; i < count; i++) {
                        var hue = (i * 360 / count) % 360;
                        colors.push('hsl(' + hue + ', 70%, 50%)');
                    }
                    return colors;
                }

                var colors = generateColors(selectedItems.length);
                var datasets = [];
                var colorIndex = 0;

                selectedItems.forEach(function(itemId) {
                    var itemData = filteredData.filter(function(d) { return d.categoryId.toString() === itemId; });
                    if (itemData.length > 0) {
                        var itemName = itemData[0].itemName;
                        var dataPoints = dates.map(function(date) {
                            var found = itemData.find(function(d) { return d.eventDate === date; });
                            return found ? parseInt(found.total_boxes) : 0;
                        });

                        datasets.push({
                            label: itemName,
                            data: dataPoints,
                            borderColor: colors[colorIndex],
                            backgroundColor: colors[colorIndex].replace('50%)', '50%, 0.2)').replace('hsl', 'hsla'),
                            tension: 0.3,
                            fill: false,
                            pointRadius: 0,
                            pointHoverRadius: 0,
                            spanGaps: true
                        });
                        colorIndex++;
                    }
                });

                // Show graph container
                document.getElementById('graphContainer').style.display = 'block';

                // Destroy previous chart if exists
                if (inventoryChart) {
                    inventoryChart.destroy();
                }

                // Create chart
                var ctx = document.getElementById('inventoryChart').getContext('2d');
                inventoryChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates.map(function(d) {
                            // Parse date string directly to avoid timezone issues
                            var parts = d.split('-');
                            return parts[1] + '/' + parts[2] + '/' + parts[0];
                        }),
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Graph'
                            },
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'line'
                                }
                            },
                            tooltip: {
                                enabled: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Total Boxes'
                                },
                                ticks: {
                                    stepSize: 10,
                                    callback: function(value) {
                                        return value;
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date'
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            });

            // PDF Download
            document.getElementById('downloadPdfBtn').addEventListener('click', function() {
                if (!inventoryChart) {
                    alert('Please generate a graph first.');
                    return;
                }

                var { jsPDF } = window.jspdf;
                var pdf = new jsPDF('landscape', 'mm', 'a4');

                // Title
                pdf.setFontSize(18);
                pdf.text('Graph', 14, 15);

                // Date range info
                pdf.setFontSize(11);
                var fromDate = document.getElementById('graphFromDate');
                var toDate = document.getElementById('graphToDate');
                var fromText = fromDate.options[fromDate.selectedIndex].text;
                var toText = toDate.options[toDate.selectedIndex].text;
                pdf.text('Date Range: ' + fromText + ' to ' + toText, 14, 25);
                pdf.text('Generated: ' + new Date().toLocaleDateString(), 14, 32);

                // Get chart as image
                var chartImage = inventoryChart.toBase64Image();
                pdf.addImage(chartImage, 'PNG', 14, 40, 270, 140);

                // Save
                pdf.save('graph_' + new Date().toISOString().slice(0,10).replace(/-/g, '_') + '.pdf');
            });
        });
    </script>

</body>
</html>

