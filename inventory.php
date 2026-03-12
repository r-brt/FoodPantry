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

// Fetch inventory items with category names
$sql = "
    SELECT 
        dic.id,
        dic.name as category_name,
        dic.status,
        COALESCE(SUM(dbic.quantity), 0) as total_quantity,
        COUNT(DISTINCT dbic.inventoryEventId) as event_count
    FROM dbItemCategory dic
    LEFT JOIN dbitemcounts dbic ON dic.id = dbic.itemCategoryId
    GROUP BY dic.id, dic.name, dic.status
    ORDER BY dic.name
";

$result = $conn->query($sql);
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
    <title>Inventory | Whiskey Valor Foundation</title>
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
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-active {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        .status-inactive {
            background-color: rgba(244, 67, 54, 0.2);
            color: #F44336;
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
    <?php require_once('header.php'); ?>
    <main>
        <div class="report-container">
            <h1 style="color: white;">Inventory</h1>

            <div class="report-section">
                <h2>Food Items</h2>
                <div class="table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Total Quantity</th>
                                <th>Events</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($items) > 0): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['category_name']) ?></td>
                                        <td>
                                            <span class="status-badge <?= $item['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                <?= htmlspecialchars($item['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($item['total_quantity']) ?></td>
                                        <td><?= htmlspecialchars($item['event_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">No items found.</td>
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




