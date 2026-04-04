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

$accessLevel = $_SESSION['access_level'] ?? 0;

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
        FROM dbitemcategory dic
        INNER JOIN dbitemcounts dbic ON dic.id = dbic.itemCategoryId
        INNER JOIN dbinventoryevent ie ON dbic.inventoryEventId = ie.id
        WHERE dic.status = 'Active'
          AND DATE(ie.date) = (
            SELECT DATE(ie2.date)
            FROM dbinventoryevent ie2
            WHERE ie2.id = ?
          )
        GROUP BY dic.id, dic.name, dic.itemsPerBox, dic.bananaBox
        HAVING total_boxes > 0
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
    </style>
</head>
<body>
    <?php require_once('header.php'); ?>
    <main>
        <div class="report-container">
            <h1 class="title">Inventory Log</h1>

            <?php if($accessLevel >= 2): ?>
                <div style="margin-bottom: 1.5rem;">
                    <a href="viewEditDeleteInventory.php" style="text-decoration: none;">
                        <button style="padding: 0.75rem 1.5rem; background-color: #dc2626; color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 1rem; font-weight: 600;">
                            Edit/Delete Inventory
                        </button>
                    </a>
                </div>
            <?php endif; ?>

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

                <div class="table-toolbar">
                    <div class="toolbar-left">
                        <label for="sortSelect" style="color: var(--page-font-color); margin-right: 0.5rem;">Sort by:</label>
                        <select id="sortSelect" class="toolbar-select">
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                        </select>
                    </div>
                    <div class="toolbar-right">
                        <input type="text" id="searchInput" class="toolbar-search" placeholder="Search items...">
                        <button type="button" id="clearSearch" class="toolbar-btn-clear">Clear</button>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="report-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
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
                                        <td class="row-number"></td>
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
                                    <td colspan="8" class="empty-state">No items found.</td>
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
                $('#inventoryTable tbody tr:visible').each(function(index) {
                    $(this).find('.row-number').text(index + 1);
                });
            }

            // Initialize row numbers on page load
            updateRowNumbers();

            // Sorting functionality
            $('#sortSelect').change(function() {
                var sortValue = $(this).val();
                var $tbody = $('#inventoryTable tbody');
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

                $('#inventoryTable tbody tr').each(function() {
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
                $('#inventoryTable tbody tr').show();
                updateRowNumbers();
            });

            // Store original order for default sorting
            $('#inventoryTable tbody tr').each(function(index) {
                $(this).data('original-index', index);
            });
        });
    </script>

</body>
</html>




