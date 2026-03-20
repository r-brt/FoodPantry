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
        'Pancake' => 15.73,
        'Oatmeal' => 15.73,
        'Mixed Veg' => 32.03,
        'Chicken' => 18.31,
        'Cereal' => 31.47,
        'Fruit' => 32.03,
        'Snacks' => 31.47,
        'Pasta' => 25.17,
        'Tomato - Canned' => 36.05,
        'Corn' => 49.21,
        'Beans - Canned' => 81.80,
        'Beans - Dry' => 81.80,
        'Tuna' => 50.33,
        'Ramen' => 71.54,
        'M&C' => 71.54,
        'Green Beans' => 49.21,
        'Canned Meals' => 18.87,
        'Spaghetti' => 24.61,
        'Soup' => 49.21,
        'Peanut Butter' => 17.75,
        'Jelly' => 17.75,
        'Oil' => 17.75
    ];

    // Get all inventory events
    $allEventObjects = get_all_inventoryEvents();
    $events = array();
    foreach($allEventObjects as $event){
        $events[] = $event->getId();
    }
    rsort($events);

    // Get the selected week from query params, default to latest
    $selectedWeek = $_GET['week'] ?? (count($events) > 0 ? $events[0] : null);

    // Find the previous week
    $previousWeek = null;
    foreach ($events as $index => $eventId) {
        if ($eventId == $selectedWeek && isset($events[$index + 1])) {
            $previousWeek = $events[$index + 1];
            break;
        }
    }

    // Get current week item counts
    $currentCounts = array();
    if ($selectedWeek) {
        $currentCountObjects = get_most_recent_counts_up_to_event($selectedWeek);
        foreach($currentCountObjects as $count){
            $currentCounts[$count->getItemCategory()] = $count;
        }
    }

    // Get previous week item counts
    $previousCounts = array();
    if ($previousWeek) {
        $previousCountObjects = get_most_recent_counts_up_to_event($previousWeek);
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
            $weeksLeft = round($daysLeft / 7);
            $monthsLeft = round($weeksLeft / 4);
        }

        $weeklyItems[] = array(
            'item_name' => $itemName,
            'days_left' => $daysLeft,
            'previous_boxes' => $previousBoxes !== null ? $previousBoxes : 'N/A',
            'previous_items_per_box' => $itemsPerBox,
            'current_boxes' => $currentBoxes,
            'current_items_per_box' => $itemsPerBox,
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
            <h1 style="color:white;">Weekly Inventory Report</h1>

            <!-- Weekly Items -->
            <div class="report-section">
                <h2>Weekly Items</h2>

                <div class="week-selector">
                    <label for="weekSelect">View Week:</label>
                    <select id="weekSelect" name="week" onchange="window.location.href='?week=' + this.value">
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $index => $eventId): ?>
                                <option value="<?= htmlspecialchars($eventId) ?>" <?= ($eventId == $selectedWeek) ? 'selected' : '' ?>>
                                    Week <?= htmlspecialchars($eventId) ?>
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
                                <th>Previous Items Per Box</th>
                                <th>Current Boxes</th>
                                <th>Current Items Per Box</th>
                                <th>Weeks Left</th>
                                <th>Months Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($weeklyItems) > 0): ?>
                                <?php foreach ($weeklyItems as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= htmlspecialchars($item['days_left']) ?></td>
                                        <td><?= htmlspecialchars($item['previous_boxes']) ?></td>
                                        <td><?= htmlspecialchars($item['previous_items_per_box']) ?></td>
                                        <td><?= htmlspecialchars($item['current_boxes']) ?></td>
                                        <td><?= htmlspecialchars($item['current_items_per_box']) ?></td>
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

