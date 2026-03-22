<?php
session_cache_expire(30);
session_start();
ini_set("display_errors",1);
error_reporting(E_ALL);

// Admin check
if(!isset($_SESSION['_id'])) {
    header('Location: login.php');
    exit;
}

require_once(__DIR__ . '/database/dbinfo.php');

$conn = connect();

// Get all distinct inventory events with their dates
$eventResult = $conn->query("
    SELECT ie.date, MAX(ic.inventoryEventId) as inventoryEventId
    FROM dbitemcounts ic
    LEFT JOIN dbinventoryevent ie ON ic.inventoryEventId = ie.id
    GROUP BY ie.date
    ORDER BY MAX(ic.inventoryEventId) DESC
");
$events = [];
if ($eventResult) {
    while ($row = $eventResult->fetch_assoc()) {
        $events[] = $row;
    }
}

// Get the selected week from query params, default to latest
$selectedWeek = $_GET['week'] ?? (count($events) > 0 ? $events[0]['inventoryEventId'] : null);

// Fetch inventory items with box information for the selected date
if ($selectedWeek) {
    $sql = "
        SELECT 
            dic.id,
            dic.name as item_name,
            dic.itemsPerBox,
            dic.bananaBox,
            COALESCE(SUM(CASE WHEN ie.location = 'Warehouse' THEN dbic.quantity ELSE 0 END), 0) as warehouse_boxes,
            COALESCE(SUM(CASE WHEN ie.location = 'Pantry' THEN dbic.quantity ELSE 0 END), 0) as pantry_boxes,
            COALESCE(SUM(dbic.quantity), 0) as total_boxes
        FROM dbItemCategory dic
        INNER JOIN dbitemcounts dbic ON dic.id = dbic.itemCategoryId
        INNER JOIN dbinventoryevent ie ON dbic.inventoryEventId = ie.id
        WHERE dic.status = 'Active'
          AND DATE(ie.date) = (
            SELECT DATE(ie2.date)
            FROM dbinventoryevent ie2
            WHERE ie2.id = ?
          )
        GROUP BY dic.id, dic.name, dic.itemsPerBox, dic.bananaBox
        ORDER BY dic.name
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selectedWeek);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = null;
}

$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc'); ?>
    <title>Inventory | CCDA</title>
    <link rel="stylesheet" href="css/base.css">
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
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--inactive-font-color);
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
    </style>
</head>
<body>
    <?php require_once('header.php'); ?>
    <main>
        <div class="report-container">
            <h1 style="color: black;">Inventory</h1>

            <div class="report-section">
                <h2>Food Items</h2>
                
                <div class="week-selector">
                    <label for="weekSelect">View Inventory:</label>
                    <select id="weekSelect" name="week" onchange="window.location.href='?week=' + this.value">
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= htmlspecialchars($event['inventoryEventId']) ?>" <?= ($event['inventoryEventId'] == $selectedWeek) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($event['date'] ?? 'Unknown Date') ?>
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
                                <th>Warehouse</th>
                                <th>Pantry</th>
                                <th>Total Boxes</th>
                                <th>Banana Box</th>
                                <th>Items Per Box</th>
                                <th>Total Items</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($items) > 0): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= $item['warehouse_boxes'] > 0 ? htmlspecialchars($item['warehouse_boxes']) : '-' ?></td>
                                        <td><?= $item['pantry_boxes'] > 0 ? htmlspecialchars($item['pantry_boxes']) : '-' ?></td>
                                        <td><?= htmlspecialchars($item['total_boxes']) ?></td>
                                        <td><?= $item['bananaBox'] == 1 ? '✓' : '' ?></td>
                                        <td><?= htmlspecialchars($item['itemsPerBox']) ?></td>
                                        <td><?= htmlspecialchars($item['total_boxes'] * $item['itemsPerBox']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No items found.</td>
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




