<?php
    session_cache_expire(30);
    session_start();

    $loggedIn = false;
    $accessLevel = 0;
    $userID = null;
    if (isset($_SESSION['_id'])) {
        $loggedIn = true;
        $accessLevel = $_SESSION['access_level'];
        $userID = $_SESSION['_id'];
    }

    // Add database includes here

    require_once('database/dbinfo.php');
    require_once('database/dbInventoryEvent.php');
    require_once('database/dbItemCategory.php');
    require_once('database/dbItemCounts.php');
    require_once('database/dbShoppingEvent.php');
    require_once('database/dbShoppingCount.php');

    // Consumption rates (items per day) - This is the available list for the moment.
    $consumptionRates = [
        'Pancake' => 10.97,
        'Oatmeal' => 10.97,
        'Mixed Veg' => 22.17,
        'Chicken' => 13.49,
        'Cereal' => 13.49,
        'Fruit' => 22.17,
        'Snacks' => 21.93,
        'Pasta' => 17.83,
        'Tomato - Canned' => 26.75,
        'Spaghetti Sauce' => 13.49,
        'Corn' => 35.19,
        'Beans - Canned' => 38.19,
        'Beans - Dry' => 22.41,
        'Tuna' => 26.75,
        'Ramen' => 53.25,
        'M&C' => 53.25,
        'Green Beans' => 35.19,
        'Canned Meals' => 13.73,
        'Spaghetti' => 17.59,
        'Soup' => 35.19,
        'Peanut Butter' => 13.25,
        'Jelly' => 13.25,
        'Oil' => 13.25
    ];

    // Get all inventory events sorted by date (newest first), then by ID (highest first)
    $allEventObjects = get_all_inventoryEvents();
    usort($allEventObjects, function($a, $b) {
        $dateDiff = strtotime($b->getDate()) - strtotime($a->getDate());
        if ($dateDiff != 0) {
            return $dateDiff;
        }
        return $b->getId() - $a->getId();
    });

    // Group events by date and keep only the latest event ID per date
    $dateToEventMap = array();
    $uniqueDates = array();
    foreach($allEventObjects as $event){
        $date = $event->getDate();
        if(!isset($dateToEventMap[$date])){
            $dateToEventMap[$date] = $event->getId(); // First one is latest (already sorted by ID DESC)
            $uniqueDates[] = $date;
        }
    }

    // Get the selected week from query params, default to latest
    $selectedWeek = $_GET['week'] ?? (count($dateToEventMap) > 0 ? reset($dateToEventMap) : null);

    // Get current week item counts (combined Warehouse + Pantry)
    $currentCounts = array();
    if ($selectedWeek) {
        $currentCountObjects = get_current_counts_by_event($selectedWeek);
        foreach($currentCountObjects as $count){
            $currentCounts[$count->getItemCategory()] = $count;
        }
    }

    // Get previous week item counts (check same day older events first, then previous date)
    $previousCounts = array();
    if ($selectedWeek) {
        $currentEvent = retrieve_inventoryEvent($selectedWeek);
        /* Check if event exists (prevents crash if event was deleted) */
        if(!$currentEvent) {
            header('Location: viewWeeklyReport.php');
            die();
        }
        $currentDate = $currentEvent->getDate();

        /* Get all events on the current date, sorted by ID DESC */
        $allEventsOnDate = get_all_inventoryEvents_by_date($currentDate);

        /* Separate events by location */
        $warehouseEvents = array();
        $pantryEvents = array();
        foreach($allEventsOnDate as $evt) {
            if($evt->getLocation() == 'Warehouse') {
                $warehouseEvents[] = $evt;
            } else if($evt->getLocation() == 'Pantry') {
                $pantryEvents[] = $evt;
            }
        }

        /* Find the 2nd newest event for each location (index [1] = 2nd newest) */
        $prevWarehouseEvent = isset($warehouseEvents[1]) ? $warehouseEvents[1] : null;
        $prevPantryEvent = isset($pantryEvents[1]) ? $pantryEvents[1] : null;

        /* If BOTH locations have no 2nd event, fall back to previous date */
        if($prevWarehouseEvent === null && $prevPantryEvent === null) {
            $previousCountObjects = get_previous_counts_by_event($selectedWeek);
            foreach($previousCountObjects as $count){
                $previousCounts[$count->getItemCategory()] = $count;
            }
        } else {
            /* Get item counts from each location independently (use 0 if location doesn't have 2nd event) */
            $prev_item_counts = array();
            if($prevWarehouseEvent !== null){
                $prev_item_counts = array_merge($prev_item_counts, get_itemCounts_by_inventoryEvent($prevWarehouseEvent->getId()));
            }
            if($prevPantryEvent !== null){
                $prev_item_counts = array_merge($prev_item_counts, get_itemCounts_by_inventoryEvent($prevPantryEvent->getId()));
            }

            /* Sum up totals by category (Warehouse + Pantry) */
            $prev_totals = array();
            foreach($prev_item_counts as $item){
                $categoryId = $item->getItemCategory();
                if(isset($prev_totals[$categoryId])){
                    $prev_totals[$categoryId] += $item->getQuantity();
                } else {
                    $prev_totals[$categoryId] = $item->getQuantity();
                }
            }

            /* Create ItemCount objects for consistency with current counts */
            foreach($prev_totals as $categoryId => $quantity){
                $previousCounts[$categoryId] = new ItemCount(0, 0, $categoryId, $quantity);
            }
        }
    }

    // Get all item categories
    $allCategories = get_all_ItemCategory();

    // Build category ID -> name map for basket lookup
    $categoryMap = array();
    foreach ($allCategories as $cat) {
        $categoryMap[$cat->getId()] = $cat->getName();
    }

    // Get all shopping events and extract unique family sizes
    $allShoppingEvents = get_all_shoppingEvents();
    $familySizes = array();
    foreach ($allShoppingEvents as $event) {
        $fs = $event->getFamilySize();
        if (!in_array($fs, $familySizes)) {
            $familySizes[] = $fs;
        }
    }
    sort($familySizes);

    // If a family size is selected, find the most recent event and load its counts
    $selectedFamilySize = isset($_GET['familySize']) ? $_GET['familySize'] : null;
    $basketItems = array();
    if ($selectedFamilySize !== null) {
        $filtered = array_filter($allShoppingEvents, function($e) use ($selectedFamilySize) {
            return $e->getFamilySize() == $selectedFamilySize;
        });
        usort($filtered, function($a, $b) {
            $dateDiff = strtotime($b->getDate()) - strtotime($a->getDate());
            if ($dateDiff != 0) return $dateDiff;
            return $b->getId() - $a->getId();
        });
        if (!empty($filtered)) {
            $latestEvent = reset($filtered);
            $counts = get_shoppingCounts_by_shoppingEvent($latestEvent->getId());
            foreach ($counts as $count) {
                $catId = $count->getItemCategory();
                $basketItems[] = array(
                    'item_name' => isset($categoryMap[$catId]) ? $categoryMap[$catId] : 'Unknown (ID: ' . $catId . ')',
                    'quantity'  => $count->getQuantity()
                );
            }
        }
    }

    // Build weekly items array
    $weeklyItems = array();
    foreach ($allCategories as $category) {
        if ($category->getStatus() != 'Active') continue;

        $categoryId = $category->getId();
        $itemName = $category->getName();
        $itemsPerBox = $category->getItemsPerBox();

        // Get current week data
        $currentBoxes = isset($currentCounts[$categoryId]) ? $currentCounts[$categoryId]->getQuantity() : 0;
        $totalItems = $currentBoxes * $itemsPerBox;

        // Get previous week data
        $previousBoxes = isset($previousCounts[$categoryId]) ? $previousCounts[$categoryId]->getQuantity() : null;

        // Calculate time remaining
        $daysLeft = "N/A";
        $weeksLeft = "N/A";
        $monthsLeft = "N/A";

        if (isset($consumptionRates[$itemName]) && $consumptionRates[$itemName] > 0 && $totalItems > 0) {
            $rate = $consumptionRates[$itemName];
            $daysLeft = round($totalItems / $rate);
            $weeksLeft = round($daysLeft / 4);
            $monthsLeft = round($weeksLeft / 4);
        }

        // Show all items including those with 0 quantity
        $weeklyItems[] = array(
            'item_name' => $itemName,
            'days_left' => $daysLeft,
            'previous_boxes' => $previousBoxes !== null ? $previousBoxes : 'N/A',
            'current_boxes' => $currentBoxes,
            'current_items_per_box' => $itemsPerBox,
            'total_items' => $totalItems,
            'weeks_left' => $weeksLeft,
            'months_left' => $monthsLeft
        );
    }
?>
    
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Weekly Inventory Report | Whiskey Valor Foundation</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
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
            width: auto;
        }
        .generate-btn:hover {
            opacity: 0.85;
        }
        .select {
            background-color: white !important;
        }
        .table-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toolbar-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            cursor: pointer;
            min-width: 180px;
        }
        .toolbar-select:hover {
            background-color: rgba(0,0,0,0.3);
        }
        .toolbar-search {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            min-width: 200px;
        }
        .toolbar-search::placeholder {
            color: var(--inactive-font-color);
        }
        .toolbar-btn-clear {
            padding: 0.5rem 1rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            cursor: pointer;
            font-weight: 500;
        }
        .toolbar-btn-clear:hover {
            background-color: rgba(0,0,0,0.3);
        }
        .row-number {
            text-align: center;
            color: var(--inactive-font-color);
            font-weight: 500;
        }
        .basket-qty-input {
            width: 80px;
            padding: 0.3rem 0.5rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            background: transparent;
            color: var(--page-font-color);
            font-size: inherit;
            text-align: center;
        }
        .basket-qty-input:hover,
        .basket-qty-input:focus {
            border-color: var(--accent-color);
            background: rgba(0,0,0,0.15);
            outline: none;
        }
        .drag-handle {
            cursor: grab;
            text-align: center;
            color: var(--inactive-font-color);
            font-size: 1.1rem;
            user-select: none;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        #basketTbody tr.drag-over {
            border-top: 2px solid var(--accent-color);
        }
        #basketTbody tr.dragging {
            opacity: 0.4;
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
            .table-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .toolbar-left,
            .toolbar-right {
                width: 100%;
            }
            .toolbar-select,
            .toolbar-search {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php require_once('header.php') ?>
    <main>
        <div class="report-container">
            <h1 class="title">Weekly Inventory Report</h1>

            <!-- Weekly Items -->
            <div class="report-section">
                <h2>Weekly Items</h2>

                <div class="week-selector">
                    <label for="weekSelect">View Week:</label>
                    <select class="select" id="weekSelect" name="week" onchange="window.location.href='?week=' + this.value">
                        <?php if (count($dateToEventMap) > 0): ?>
                            <?php foreach ($uniqueDates as $date): ?>
                                <?php $eventId = $dateToEventMap[$date]; ?>
                                <option value="<?= htmlspecialchars($eventId) ?>" <?= ($eventId == $selectedWeek) ? 'selected' : '' ?>>
                                    <?= date('M j, Y', strtotime($date)) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Toolbar: Sort and Search -->
                <div class="table-toolbar">
                    <div class="toolbar-left">
                        <label for="sortSelect" style="color: var(--page-font-color); margin-right: 0.5rem;">Sort by:</label>
                        <select id="sortSelect" class="toolbar-select">
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                            <option value="days-asc" selected>Days Left (Low to High)</option>
                            <option value="days-desc">Days Left (High to Low)</option>
                        </select>
                    </div>
                    <div class="toolbar-right">
                        <input type="text" id="searchInput" class="toolbar-search" placeholder="Search items...">
                        <button type="button" id="clearSearch" class="toolbar-btn-clear">Clear</button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="report-table" id="weeklyItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Item Name</th>
                                <th>Days Left</th>
                                <th>Previous Boxes</th>
                                <th>Current Boxes</th>
                                <th>Current Items Per Box</th>
                                <th>Total Items</th>
                                <th>Weeks Left</th>
                                <th>Months Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($weeklyItems) > 0): ?>
                                <?php foreach ($weeklyItems as $item): ?>
                                    <?php
                                        // Combined-days algorithm:
                                        // 1 week = 7 days, 1 month = 4 weeks = 28 days
                                        // totalColorDays = (months × 28) + (weeks × 7) + days
                                        $rowClass = '';
                                        $daysVal   = is_numeric($item['days_left'])   ? (int)$item['days_left']   : null;
                                        $weeksVal  = is_numeric($item['weeks_left'])  ? (int)$item['weeks_left']  : null;
                                        $monthsVal = is_numeric($item['months_left']) ? (int)$item['months_left'] : null;

                                        if ($daysVal !== null) {
                                            $totalColorDays = ($monthsVal * 28) + ($weeksVal * 7) + $daysVal;

                                            if ($totalColorDays >= 120) {
                                                $rowClass = 'row-green';   // multiple months left
                                            } elseif ($totalColorDays >= 50) {
                                                $rowClass = 'row-yellow';  // a few weeks left
                                            } else {
                                                $rowClass = 'row-red';     // 1 week and a few days left
                                            }
                                        }
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="row-number"></td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['days_left']) ?></td>
                                        <td><?= htmlspecialchars($item['previous_boxes']) ?></td>
                                        <td><?= htmlspecialchars($item['current_boxes']) ?></td>
                                        <td><?= htmlspecialchars($item['current_items_per_box']) ?></td>
                                        <td><?= htmlspecialchars($item['total_items']) ?></td>
                                        <td><?= htmlspecialchars($item['weeks_left']) ?></td>
                                        <td><?= htmlspecialchars($item['months_left']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="empty-state">No weekly items to display.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generate Basket -->
            <div class="report-section">
                <h2>Shopping List</h2>
                <p style="color: var(--page-font-color); margin-bottom: 1rem;">Select a family size to view the recommended basket items and quantities.</p>

                <div class="week-selector">
                    <label for="familySizeSelect">Family Size:</label>
                    <select class="select" id="familySizeSelect" name="familySize"
                        onchange="window.location.href='?week=<?= htmlspecialchars($selectedWeek ?? '') ?>&familySize=' + encodeURIComponent(this.value)">
                        <option value="">-- Select Family Size --</option>
                        <?php foreach ($familySizes as $fs): ?>
                            <option value="<?= htmlspecialchars($fs) ?>"
                                <?= ($fs == $selectedFamilySize) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fs) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selectedFamilySize !== null): ?>
                    <div class="table-wrapper" style="margin-top: 1rem;" id="basketTableWrapper">
                        <table class="report-table" id="basketTable">
                            <thead>
                                <tr>
                                    <th style="width: 36px;"></th>
                                    <th style="width: 50px;">#</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="basketTbody">
                                <?php if (!empty($basketItems)): ?>
                                    <?php foreach ($basketItems as $i => $item): ?>
                                        <tr draggable="true">
                                            <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                                            <td class="row-number"><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                            <td><input type="number" class="basket-qty-input" value="<?= htmlspecialchars($item['quantity']) ?>" min="0"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">No items found for this family size.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="generate-btn" id="generatePdfBtn" style="margin-top: 1.25rem;">Generate Shopping List PDF</button>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        $(function() {
            // Update row numbers
            function updateRowNumbers() {
                $('#weeklyItemsTable tbody tr:visible').each(function(index) {
                    $(this).find('.row-number').text(index + 1);
                });
            }

            // Initialize row numbers on page load
            updateRowNumbers();

            // Sorting functionality
            $('#sortSelect').change(function() {
                var sortValue = $(this).val();
                var $tbody = $('#weeklyItemsTable tbody');
                var $rows = $tbody.find('tr').get();

                if (sortValue === 'name-asc') {
                    // Sort by name A-Z
                    $rows.sort(function(a, b) {
                        var nameA = $(a).find('td').eq(1).text().toLowerCase();
                        var nameB = $(b).find('td').eq(1).text().toLowerCase();
                        return nameA.localeCompare(nameB);
                    });
                } else if (sortValue === 'name-desc') {
                    // Sort by name Z-A
                    $rows.sort(function(a, b) {
                        var nameA = $(a).find('td').eq(1).text().toLowerCase();
                        var nameB = $(b).find('td').eq(1).text().toLowerCase();
                        return nameB.localeCompare(nameA);
                    });
                } else if (sortValue === 'days-asc') {
                    // Sort by days left (low to high) - items at risk first
                    $rows.sort(function(a, b) {
                        var daysA = $(a).find('td').eq(2).text();
                        var daysB = $(b).find('td').eq(2).text();

                        // Handle N/A values - put them at the end
                        if (daysA === 'N/A') return 1;
                        if (daysB === 'N/A') return -1;

                        return parseInt(daysA) - parseInt(daysB);
                    });
                } else if (sortValue === 'days-desc') {
                    // Sort by days left (high to low)
                    $rows.sort(function(a, b) {
                        var daysA = $(a).find('td').eq(2).text();
                        var daysB = $(b).find('td').eq(2).text();

                        // Handle N/A values - put them at the end
                        if (daysA === 'N/A') return 1;
                        if (daysB === 'N/A') return -1;

                        return parseInt(daysB) - parseInt(daysA);
                    });
                }

                // Re-append rows in new order
                $.each($rows, function(index, row) {
                    $tbody.append(row);
                });

                // Update row numbers after sorting
                updateRowNumbers();
            });

            // Search functionality
            $('#searchInput').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();

                $('#weeklyItemsTable tbody tr').each(function() {
                    var itemName = $(this).find('td').eq(1).text().toLowerCase();

                    if (itemName.indexOf(searchTerm) > -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                // Update row numbers after filtering
                updateRowNumbers();
            });

            // Clear search button
            $('#clearSearch').click(function() {
                $('#searchInput').val('');
                $('#weeklyItemsTable tbody tr').show();
                updateRowNumbers();
            });

            // Store original order for default sorting
            $('#weeklyItemsTable tbody tr').each(function(index) {
                $(this).data('original-index', index);
            });

            // Trigger initial sort (Days Left Low to High)
            $('#sortSelect').trigger('change');

            // Basket drag-and-drop reordering
            var basketTbody = document.getElementById('basketTbody');
            if (basketTbody) {
                var dragSrc = null;

                basketTbody.addEventListener('dragstart', function(e) {
                    dragSrc = e.target.closest('tr');
                    dragSrc.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                basketTbody.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    var target = e.target.closest('tr');
                    if (target && target !== dragSrc) {
                        basketTbody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('drag-over'); });
                        target.classList.add('drag-over');
                    }
                });

                basketTbody.addEventListener('dragleave', function(e) {
                    var target = e.target.closest('tr');
                    if (target) target.classList.remove('drag-over');
                });

                basketTbody.addEventListener('drop', function(e) {
                    e.preventDefault();
                    var target = e.target.closest('tr');
                    if (target && target !== dragSrc) {
                        var rows = Array.from(basketTbody.querySelectorAll('tr'));
                        var srcIndex = rows.indexOf(dragSrc);
                        var tgtIndex = rows.indexOf(target);
                        if (srcIndex < tgtIndex) {
                            basketTbody.insertBefore(dragSrc, target.nextSibling);
                        } else {
                            basketTbody.insertBefore(dragSrc, target);
                        }
                        target.classList.remove('drag-over');
                        // Update row numbers
                        basketTbody.querySelectorAll('tr').forEach(function(r, i) {
                            var cell = r.querySelector('.row-number');
                            if (cell) cell.textContent = i + 1;
                        });
                    }
                });

                basketTbody.addEventListener('dragend', function(e) {
                    if (dragSrc) dragSrc.classList.remove('dragging');
                    basketTbody.querySelectorAll('tr').forEach(function(r) { r.classList.remove('drag-over'); });
                });
            }
            // Generate Shopping List PDF
            var pdfBtn = document.getElementById('generatePdfBtn');
            if (pdfBtn) {
                pdfBtn.addEventListener('click', function() {
                    var { jsPDF } = window.jspdf;
                    var doc = new jsPDF();

                    var familySize = '<?= htmlspecialchars($selectedFamilySize ?? '') ?>';
                    var today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    // Header
                    doc.setFontSize(18);
                    doc.setTextColor(40, 40, 40);
                    doc.text('Shopping List', 14, 20);

                    doc.setFontSize(11);
                    doc.setTextColor(100, 100, 100);
                    doc.text('Family Size: ' + familySize, 14, 29);
                    doc.text('Date: ' + today, 14, 36);

                    // Read rows in current DOM order
                    var rows = [];
                    document.querySelectorAll('#basketTbody tr').forEach(function(tr, i) {
                        var cells = tr.querySelectorAll('td');
                        if (cells.length < 4) return;
                        var itemName = cells[2].textContent.trim();
                        var qtyInput = cells[3].querySelector('input');
                        var qty = qtyInput ? qtyInput.value : cells[3].textContent.trim();
                        rows.push([(i + 1).toString(), itemName, qty]);
                    });

                    doc.autoTable({
                        startY: 44,
                        head: [['#', 'Item Name', 'Quantity']],
                        body: rows,
                        headStyles: {
                            fillColor: [44, 62, 80],
                            textColor: 255,
                            fontStyle: 'bold'
                        },
                        alternateRowStyles: {
                            fillColor: [245, 245, 245]
                        },
                        columnStyles: {
                            0: { cellWidth: 12, halign: 'center' },
                            2: { cellWidth: 30, halign: 'center' }
                        },
                        styles: { fontSize: 11 },
                        margin: { left: 14, right: 14 }
                    });

                    doc.save('shopping-list-' + familySize.replace(/[^a-z0-9]/gi, '-') + '.pdf');
                });
            }
        });
    </script>

</body>
</html>

