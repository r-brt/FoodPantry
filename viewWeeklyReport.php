<?php
    session_cache_expire(30);
    session_start();

    // Handle AJAX quantity update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateQty') {
        require_once('database/dbShoppingCount.php');
        $id       = isset($_POST['id'])       ? (int)$_POST['id']       : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        header('Content-Type: application/json');
        if ($id > 0 && $quantity >= 0) {
            $result = update_shoppingCount_quantity($id, $quantity);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

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
    require_once('database/dbConsumption.php');

    // Build category map (id => name) used for both consumption rate lookup and weekly items
    $allCategories = get_all_ItemCategory();
    $categoryMap = array();
    foreach ($allCategories as $cat) {
        $categoryMap[$cat->getId()] = $cat->getName();
    }

    // Load saved consumption rates from dbcomsumption.
    // Each save from viewConsumptionRates.php writes one row per family size per item,
    // all sharing the same date. Sum all rows from the most recent date per category.
    $consumptionRates = [];
    $con = connect();
    $result = mysqli_query($con,
        'SELECT itemCategoryId, SUM(itemsConsumed) AS totalRate, date
         FROM dbcomsumption
         WHERE date = (SELECT MAX(date) FROM dbcomsumption)
         GROUP BY itemCategoryId');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $catId = (int)$row['itemCategoryId'];
            if (isset($categoryMap[$catId])) {
                $consumptionRates[$categoryMap[$catId]] = (float)$row['totalRate'];
            }
        }
    }
    mysqli_close($con);




    // Get all inventory events sorted by date (newest first), then by ID (highest first)
    $allEventObjects = get_all_inventoryEvents();
    usort($allEventObjects, function($a, $b) {
        $dateDiff = strtotime($b->getDate()) - strtotime($a->getDate());
        if ($dateDiff != 0) {
            return $dateDiff;
        }
        return $b->getId() - $a->getId();
    });

    // Build event triplets (warehouse + pantry + pallet)
    $eventPairs = array();
    foreach($allEventObjects as $event) {
        if($event->getLocation() == 'Warehouse') {
            $matches = get_matching_inventoryEvent($event);
            $pantryEvent = $matches['Pantry'] ?? null;
            $palletEvent = $matches['Pallet'] ?? null;
            $eventPairs[] = array(
                'warehouse' => $event,
                'pantry' => $pantryEvent,
                'pallet' => $palletEvent,
                'date' => $event->getDate(),
                'warehouseId' => $event->getId(),
                'pantryId' => $pantryEvent ? $pantryEvent->getId() : null,
                'palletId' => $palletEvent ? $palletEvent->getId() : null
            );
        }
    }

    // OLD CODE - orphan pantry logic (no longer needed with triplets)
    // foreach($allEventObjects as $event) {
    //     if($event->getLocation() == 'Pantry') {
    //         $warehouseEvent = get_matching_inventoryEvent($event);
    //         if($warehouseEvent === null) {
    //             $eventPairs[] = array(
    //                 'warehouse' => null,
    //                 'pantry' => $event,
    //                 'date' => $event->getDate(),
    //                 'warehouseId' => null,
    //                 'pantryId' => $event->getId()
    //             );
    //         }
    //     }
    // }

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

    // Add sequence numbers for same-date entries
    $dateCounts = array();
    foreach($eventPairs as $index => $pair) {
        $date = $pair['date'];
        if(!isset($dateCounts[$date])) {
            $dateCounts[$date] = 0;
        }
        $dateCounts[$date]++;
    }
    /* Assign sequence numbers (newest first, so #1 is most recent on that date) */
    $dateSeq = array();
    foreach($eventPairs as $index => $pair) {
        $date = $pair['date'];
        if(!isset($dateSeq[$date])) {
            $dateSeq[$date] = 1;
        }
        $eventPairs[$index]['seq'] = $dateSeq[$date];
        $eventPairs[$index]['totalOnDate'] = $dateCounts[$date];
        $dateSeq[$date]++;
    }

    // Get the selected week from query params, default to latest
    $selectedWeek = $_GET['week'] ?? (count($eventPairs) > 0 ? ($eventPairs[0]['warehouseId'] ?? $eventPairs[0]['pantryId']) : null);

    // Find the selected pair index
    $selectedPairIndex = null;
    foreach($eventPairs as $index => $pair) {
        $pairId = $pair['warehouseId'] ?? $pair['pantryId'];
        if($pairId == $selectedWeek) {
            $selectedPairIndex = $index;
            break;
        }
    }

    // Get current counts for selected triplet
    $currentCounts = array();
    if($selectedPairIndex !== null) {
        $selectedPair = $eventPairs[$selectedPairIndex];
        $current_item_counts = array();

        /* Get warehouse counts */
        if($selectedPair['warehouse']) {
            $current_item_counts = array_merge($current_item_counts, get_itemCounts_by_inventoryEvent($selectedPair['warehouseId']));
        }
        /* Get pantry counts */
        if($selectedPair['pantry']) {
            $current_item_counts = array_merge($current_item_counts, get_itemCounts_by_inventoryEvent($selectedPair['pantryId']));
        }
        /* Get pallet counts */
        if(isset($selectedPair['pallet']) && $selectedPair['pallet']) {
            $current_item_counts = array_merge($current_item_counts, get_itemCounts_by_inventoryEvent($selectedPair['palletId']));
        }

        /* Sum up totals by category */
        $current_totals = array();
        foreach($current_item_counts as $item) {
            $categoryId = $item->getItemCategory();
            if(isset($current_totals[$categoryId])) {
                $current_totals[$categoryId] += $item->getQuantity();
            } else {
                $current_totals[$categoryId] = $item->getQuantity();
            }
        }

        /* Create ItemCount objects */
        foreach($current_totals as $categoryId => $quantity) {
            $currentCounts[$categoryId] = new ItemCount(0, 0, $categoryId, $quantity);
        }
    }

    // Get previous counts (the triplet before selected in sorted list)
    $previousCounts = array();
    if($selectedPairIndex !== null && isset($eventPairs[$selectedPairIndex + 1])) {
        $previousPair = $eventPairs[$selectedPairIndex + 1];
        $prev_item_counts = array();

        /* Get warehouse counts */
        if($previousPair['warehouse']) {
            $prev_item_counts = array_merge($prev_item_counts, get_itemCounts_by_inventoryEvent($previousPair['warehouseId']));
        }
        /* Get pantry counts */
        if($previousPair['pantry']) {
            $prev_item_counts = array_merge($prev_item_counts, get_itemCounts_by_inventoryEvent($previousPair['pantryId']));
        }
        /* Get pallet counts */
        if(isset($previousPair['pallet']) && $previousPair['pallet']) {
            $prev_item_counts = array_merge($prev_item_counts, get_itemCounts_by_inventoryEvent($previousPair['palletId']));
        }

        /* Sum up totals by category */
        $prev_totals = array();
        foreach($prev_item_counts as $item) {
            $categoryId = $item->getItemCategory();
            if(isset($prev_totals[$categoryId])) {
                $prev_totals[$categoryId] += $item->getQuantity();
            } else {
                $prev_totals[$categoryId] = $item->getQuantity();
            }
        }

        /* Create ItemCount objects */
        foreach($prev_totals as $categoryId => $quantity) {
            $previousCounts[$categoryId] = new ItemCount(0, 0, $categoryId, $quantity);
        }
    }

    // Build weekly items array
    $weeklyItems = array();
    foreach ($allCategories as $category) {
        $categoryId = $category->getId();

        // Show if active OR has data in current/previous inventory
        $hasCurrentData = isset($currentCounts[$categoryId]);
        $hasPreviousData = isset($previousCounts[$categoryId]);
        if ($category->getStatus() != 'Active' && !$hasCurrentData && !$hasPreviousData) {
            continue;
        }
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

        if ($totalItems == 0) {
            // No items = 0 days/weeks/months left
            $daysLeft = 0;
            $weeksLeft = 0;
            $monthsLeft = 0;
        } else if (isset($consumptionRates[$itemName]) && $consumptionRates[$itemName] > 0) {
            // Has items and consumption rate - calculate
            $rate = $consumptionRates[$itemName];
            $daysLeft = round($totalItems / $rate);
            $weeksLeft = round($daysLeft / 4);
            $monthsLeft = round($weeksLeft / 4);
        }
        // else: items > 0 but no consumption rate - stays N/A

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
    <title>Weekly Inventory Report | CCDA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <style>
        pageheader {
            margin-top: 3rem;
            display: flex; justify-content: center; align-items: center;
            position: sticky;
            top: 1rem;
            z-index: 6;
        }
        .title {
            text-align: center;
            height: 3.5rem;
            width:auto;
            font-size: 2rem;
            font-weight: 600;
            color: var(--secondary-accent-color);
            padding-top: .4rem;
            border-radius: 10px;
            background-color: #ffffffee;
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
            position: sticky;
            top: 100px; /* height of page header */
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
        #basketTbody tr.drag-over-top {
            border-top: 2px solid var(--accent-color);
        }
        #basketTbody tr.drag-over-bottom {
            border-bottom: 2px solid var(--accent-color);
        }
        #basketTbody tr.dragging {
            opacity: 0.4;
        }
        .data-entry-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            max-width: 500px;
        }
        .data-entry-row {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .data-entry-label {
            color: var(--page-font-color);
            width: 160px;
            flex-shrink: 0;
            font-weight: 500;
        }
        .data-entry-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            flex: 1;
        }
        .feedback-msg {
            display: inline-block;
            margin-left: 1rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .feedback-success {
            color: rgb(34, 197, 94);
        }
        .feedback-error {
            color: rgb(239, 68, 68);
        }
        @media only screen and (max-width: 768px) {
            pageheader {
                top: 100px;
            }
            .title {
                border-radius: 0;
                background-color: #ffffff;
                width: 100%;
            }
            .report-table th,
            .report-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
                position: static;

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
            .data-entry-row {
                flex-direction: column;
                align-items: stretch;
            }
            .data-entry-label {
                width: auto;
            }
        }
    </style>
</head>
<pageheader>
    <h1 class="title">Weekly Inventory Report</h1>
</pageheader>
<body>
    <?php require_once('header.php') ?>
    <main>
        <div class="report-container">

            <!-- Weekly Items -->
            <div class="report-section">
                <h2>Weekly Items</h2>

                <div class="week-selector">
                    <label for="weekSelect">View Week:</label>
                    <select class="select" id="weekSelect" name="week" onchange="window.location.href='?week=' + this.value">
                        <?php if (count($eventPairs) > 0): ?>
                            <?php foreach ($eventPairs as $pair): ?>
                                <?php $eventId = $pair['warehouseId'] ?? $pair['pantryId']; ?>
                                <option value="<?= htmlspecialchars($eventId) ?>" <?= ($eventId == $selectedWeek) ? 'selected' : '' ?>>
                                    <?= date('m/d/Y', strtotime($pair['date'])) ?>
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
                                        $rowClass = '';
                                        $daysVal = is_numeric($item['days_left']) ? (int)$item['days_left'] : null;

                                        if ($daysVal !== null) {
                                            if ($daysVal >= 30) {
                                                $rowClass = 'row-green';
                                            } elseif ($daysVal >= 14) {
                                                $rowClass = 'row-yellow';
                                            } else {
                                                $rowClass = 'row-red';
                                            }
                                        }

                                        // $weeksVal  = is_numeric($item['weeks_left'])  ? (int)$item['weeks_left']  : null;
                                        // $monthsVal = is_numeric($item['months_left']) ? (int)$item['months_left'] : null;
                                        // $totalColorDays = ($monthsVal * 28) + ($weeksVal * 7) + $daysVal;
                                        // if ($totalColorDays >= 120) { $rowClass = 'row-green'; }
                                        // elseif ($totalColorDays >= 50) { $rowClass = 'row-yellow'; }
                                        // else { $rowClass = 'row-red'; }
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
                        basketTbody.querySelectorAll('tr').forEach(function(r) {
                            r.classList.remove('drag-over-top', 'drag-over-bottom');
                        });
                        var rect = target.getBoundingClientRect();
                        if (e.clientY < rect.top + rect.height / 2) {
                            target.classList.add('drag-over-top');
                        } else {
                            target.classList.add('drag-over-bottom');
                        }
                    }
                });

                basketTbody.addEventListener('dragleave', function(e) {
                    var target = e.target.closest('tr');
                    if (target) {
                        target.classList.remove('drag-over-top', 'drag-over-bottom');
                    }
                });

                basketTbody.addEventListener('drop', function(e) {
                    e.preventDefault();
                    var target = e.target.closest('tr');
                    if (target && target !== dragSrc) {
                        var rect = target.getBoundingClientRect();
                        if (e.clientY < rect.top + rect.height / 2) {
                            basketTbody.insertBefore(dragSrc, target);
                        } else {
                            basketTbody.insertBefore(dragSrc, target.nextSibling);
                        }
                        target.classList.remove('drag-over-top', 'drag-over-bottom');
                        // Update row numbers
                        basketTbody.querySelectorAll('tr').forEach(function(r, i) {
                            var cell = r.querySelector('.row-number');
                            if (cell) cell.textContent = i + 1;
                        });
                    }
                });

                basketTbody.addEventListener('dragend', function(e) {
                    if (dragSrc) dragSrc.classList.remove('dragging');
                    basketTbody.querySelectorAll('tr').forEach(function(r) {
                        r.classList.remove('drag-over-top', 'drag-over-bottom');
                    });
                });
            }
        });
    </script>

</body>
</html>

