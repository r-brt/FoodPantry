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

    require_once('database/dbinfo.php');
    require_once('database/dbInventoryEvent.php');
    require_once('database/dbItemCategory.php');
    require_once('database/dbItemCounts.php');

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

    // Get previous week item counts (hybrid: same-day progression or previous event)
    $previousCounts = array();
    if ($selectedWeek) {
        $previousCountObjects = get_previous_counts_by_event($selectedWeek);
        foreach($previousCountObjects as $count){
            $previousCounts[$count->getItemCategory()] = $count;
        }
    }

    // Get all item categories
    $allCategories = get_all_ItemCategory();

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
    <style>
        .report-container {
            max-width: 1100px;
            margin: 0 auto 4rem auto;
            padding: 1rem;
        }
        .report-section {
            background-color: rgba(0,0,0,0.15);
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .report-section h2 {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: var(--accent-color);
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
    <?php require_once('header.php') ?>
    <main>
        <div class="report-container">
            <h1 style="color:var(--accent-color);">Weekly Inventory Report</h1>

            <!-- Weekly Items -->
            <div class="report-section">
                <h2>Weekly Items</h2>

                <div class="week-selector">
                    <label for="weekSelect">View Week:</label>
                    <select id="weekSelect" name="week" onchange="window.location.href='?week=' + this.value">
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

                <div class="table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
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
                                    <td colspan="8" class="empty-state">No weekly items to display.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generate Basket -->
            <div class="report-section">
                <h2>Generate Basket</h2>
                <p style="color: var(--page-font-color); margin-bottom: 1rem;">Select item categories and quantities to generate a recommended food basket.</p>
                <div class="basket-options">
                    <div class="basket-row">
                        <label class="basket-label">Grains / Bread</label>
                        <input type="number" class="basket-qty" min="0" value="0" placeholder="Qty">
                    </div>
                    <div class="basket-row">
                        <label class="basket-label">Canned Goods</label>
                        <input type="number" class="basket-qty" min="0" value="0" placeholder="Qty">
                    </div>
                    <div class="basket-row">
                        <label class="basket-label">Produce</label>
                        <input type="number" class="basket-qty" min="0" value="0" placeholder="Qty">
                    </div>
                    <div class="basket-row">
                        <label class="basket-label">Dairy</label>
                        <input type="number" class="basket-qty" min="0" value="0" placeholder="Qty">
                    </div>
                    <div class="basket-row">
                        <label class="basket-label">Protein</label>
                        <input type="number" class="basket-qty" min="0" value="0" placeholder="Qty">
                    </div>
                </div>
                <button class="generate-btn">Generate Basket</button>

                <!-- Basket Result Table -->
                <div class="table-wrapper" style="margin-top: 1.5rem; display: none;" id="basketResult">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Qty Allocated</th>
                                <th>Available Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="empty-state">No basket generated yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

</body>
</html>

