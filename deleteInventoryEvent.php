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

    /* Access control - Managers only */
    if($accessLevel < 2) {
        header('Location: index.php');
        die();
    }

    require_once('database/dbinfo.php');
    require_once('database/dbInventoryEvent.php');
    require_once('database/dbItemCounts.php');
    require_once('database/dbItemCategory.php');

    /* Get event ID */
    $eventId = $_GET['id'] ?? null;
    $confirm = $_GET['confirm'] ?? 0;

    if(!$eventId) {
        header('Location: viewEditDeleteInventory.php');
        die();
    }

    /* Get event data */
    $event = retrieve_inventoryEvent($eventId);
    if(!$event) {
        echo "Event not found";
        die();
    }

    /* Get item counts for this event */
    $itemCounts = get_itemCounts_by_inventoryEvent($eventId);

    /* Build array mapping category ID to ItemCount object */
    $countsMap = array();
    foreach($itemCounts as $count) {
        $categoryId = $count->getItemCategory();
        $countsMap[$categoryId] = $count;
    }

    /* Get all item categories */
    $allCategories = get_all_ItemCategory();

    $errors = array();

    /* If confirmed, delete the event */
    if($confirm == 1) {
        /* Store event details before deletion */
        $eventDate = $event->getDate();
        $eventLocation = $event->getLocation();
        $eventIdForMessage = $event->getId();

        $result = remove_inventoryEvent($eventId);
        if($result) {
            /* Pass event details to success message */
            header('Location: viewEditDeleteInventory.php?deleted=success&date=' . urlencode($eventDate) . '&location=' . urlencode($eventLocation) . '&eventId=' . urlencode($eventIdForMessage));
            die();
        } else {
            $errors[] = "Failed to delete inventory event";
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Delete Inventory | Whiskey Valor Foundation</title>
    <style>
        .edit-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1.5rem;
            background-color: white;
            border-radius: 15px;
        }
        .title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--secondary-accent-color);
            margin-bottom: 1rem;
            text-align: center;
        }
        .event-info {
            background-color: rgba(0,0,0,0.05);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .event-info p {
            margin: 0.5rem 0;
            color: var(--page-font-color);
            font-weight: 500;
        }
        .modify-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        .modify-table th {
            background-color: var(--main-color);
            color: var(--button-font-color);
            padding: 0.75rem;
            text-align: left;
            font-weight: 500;
        }
        .modify-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--shadow-and-border-color);
            color: var(--page-font-color);
        }
        .modifyUsers-formBtns {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .modify-delete-btn {
            padding: 0.6rem 1.5rem;
            background-color: #dc2626;
            color: white;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
        }
        .modify-delete-btn:hover {
            background-color: #b91c1c;
        }
        .modify-cancel-btn {
            padding: 0.6rem 1.5rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
        }
        .modify-cancel-btn:hover {
            background-color: rgba(0,0,0,0.3);
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            text-decoration: none;
            border-radius: 0.25rem;
        }
        .back-btn:hover {
            background-color: rgba(0,0,0,0.3);
        }
        @media only screen and (max-width: 768px) {
            .modify-table th,
            .modify-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            .edit-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once('header.php') ?>
    <main class="edit-container">
        <a href="viewEditDeleteInventory.php" class="back-btn">← Back</a>

        <h1 class="title">Delete Inventory</h1>

        <!-- Warning Message -->
        <h2 class="title" style="color:red;">Warning: This action will permanently delete this inventory.</h2>

        <?php if(!empty($errors)): ?>
            <ul>
                <?php foreach($errors as $error): ?>
                    <li><?php echo("<h4 style=\"color:red;\"><i>".$error."</i></h4>"); ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="viewEditDeleteInventory.php" class="modify-cancel-btn">Back to List</a>
        <?php else: ?>
            <!-- Event Info (Read-Only) -->
            <div class="event-info">
                <p><strong>Date:</strong> <?= date('M j, Y', strtotime($event->getDate())) ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars($event->getLocation()) ?></p>
                <p><strong>Event ID:</strong> <?= htmlspecialchars($event->getId()) ?></p>
            </div>

            <!-- Items List (Read-Only) -->
            <h3 style="margin-bottom: 1rem;">Items in this inventory:</h3>
            <table class="modify-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Items Per Box</th>
                        <th>Boxes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $hasItems = false;
                    $rowNum = 0;
                    foreach($allCategories as $category): ?>
                        <?php if($category->getStatus() != 'Active') continue; ?>
                        <?php
                        $categoryId = $category->getId();
                        if(!isset($countsMap[$categoryId])) continue;
                        $hasItems = true;
                        $rowNum++;
                        $qty = $countsMap[$categoryId]->getQuantity();
                        ?>
                        <tr>
                            <td><?= $rowNum ?></td>
                            <td><?= htmlspecialchars($category->getName()) ?></td>
                            <td><?= htmlspecialchars($category->getItemsPerBox()) ?></td>
                            <td><?= $qty ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(!$hasItems): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--inactive-font-color);">
                                No items in this inventory event
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Confirmation Form -->
            <form method="GET" onsubmit="return confirmDelete()">
                <input type="hidden" name="id" value="<?= htmlspecialchars($eventId) ?>">
                <input type="hidden" name="confirm" value="1">
                <div class="modifyUsers-formBtns">
                    <button type="submit" class="modify-delete-btn">
                        Delete
                    </button>
                    <button type="button" class="modify-cancel-btn" onclick="window.location.href='viewEditDeleteInventory.php'">
                        Cancel
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </main>
    <script>
        /* Final confirmation before deleting inventory event */
        function confirmDelete() {
            return confirm("<?= date('m/d/Y', strtotime($event->getDate())) ?>\n" +
                          "<?= htmlspecialchars($event->getLocation()) ?>\n\n" +
                          "Are you sure you want to delete?");
        }
    </script>
</body>
</html>
