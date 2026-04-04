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

    /* Get warehouse event ID parameter */
    $warehouseId = $_GET['warehouseId'] ?? null;
    if(!$warehouseId) {
        echo "Invalid warehouse event ID";
        die();
    }

    /* Get warehouse event */
    $warehouseEvent = retrieve_inventoryEvent($warehouseId);
    if(!$warehouseEvent || $warehouseEvent->getLocation() != 'Warehouse') {
        echo "Warehouse event not found";
        die();
    }

    /* Get paired pantry event using matching function */
    $pantryEvent = get_matching_inventoryEvent($warehouseEvent);

    /* Get event date for display */
    $eventDate = $warehouseEvent->getDate();

    /* Get item counts for warehouse (if exists) */
    $warehouseCountsMap = array();
    if($warehouseEvent) {
        $warehouseItemCounts = get_itemCounts_by_inventoryEvent($warehouseEvent->getId());
        foreach($warehouseItemCounts as $count) {
            $categoryId = $count->getItemCategory();
            $warehouseCountsMap[$categoryId] = $count;
        }
    }

    /* Get item counts for pantry (if exists) */
    $pantryCountsMap = array();
    if($pantryEvent) {
        $pantryItemCounts = get_itemCounts_by_inventoryEvent($pantryEvent->getId());
        foreach($pantryItemCounts as $count) {
            $categoryId = $count->getItemCategory();
            $pantryCountsMap[$categoryId] = $count;
        }
    }

    /* Get all item categories */
    $allCategories = get_all_ItemCategory();

    /* Handle form submission */
    $errors = [];
    $success = false;
    if (!empty($_POST)) {
        if(isset($_POST['cancel_button'])) {
            header('Location: viewEditDeleteInventory.php');
            die();
        }
        else if(isset($_POST['save_button'])) {
            /* Update quantities for warehouse (if exists) */
            if($warehouseEvent) {
                foreach($allCategories as $category) {
                    if($category->getStatus() != 'Active') continue;

                    $categoryId = $category->getId();
                    $fieldName = 'warehouse_qty_' . $categoryId;

                    if(isset($_POST[$fieldName]) && isset($warehouseCountsMap[$categoryId])) {
                        try{
                            $newQty = +$_POST[$fieldName];
                        }
                        catch(TypeError $e){
                            $newQty = " ";
                        }

                        if(!is_int($newQty)){
                            $errors[] = 'Warehouse - ' . $category->getName() . ' quantity must be in whole numbers';
                            continue;
                        }
                        else if($newQty < 0){
                            $errors[] = 'Warehouse - ' . $category->getName() . ' quantity cannot be negative';
                            continue;
                        }

                        $itemCountId = $warehouseCountsMap[$categoryId]->getId();
                        $oldQty = $warehouseCountsMap[$categoryId]->getQuantity();

                        if($oldQty != $newQty) {
                            update_quantity($itemCountId, $newQty);
                        }
                    }
                }
            }

            /* Update quantities for pantry (if exists) */
            if($pantryEvent) {
                foreach($allCategories as $category) {
                    if($category->getStatus() != 'Active') continue;

                    $categoryId = $category->getId();
                    $fieldName = 'pantry_qty_' . $categoryId;

                    if(isset($_POST[$fieldName]) && isset($pantryCountsMap[$categoryId])) {
                        try{
                            $newQty = +$_POST[$fieldName];
                        }
                        catch(TypeError $e){
                            $newQty = " ";
                        }

                        if(!is_int($newQty)){
                            $errors[] = 'Pantry - ' . $category->getName() . ' quantity must be in whole numbers';
                            continue;
                        }
                        else if($newQty < 0){
                            $errors[] = 'Pantry - ' . $category->getName() . ' quantity cannot be negative';
                            continue;
                        }

                        $itemCountId = $pantryCountsMap[$categoryId]->getId();
                        $oldQty = $pantryCountsMap[$categoryId]->getQuantity();

                        if($oldQty != $newQty) {
                            update_quantity($itemCountId, $newQty);
                        }
                    }
                }
            }

            if(empty($errors)) {
                $success = true;
                /* Refresh counts by re-fetching from database */
                if($warehouseEvent) {
                    $warehouseItemCounts = get_itemCounts_by_inventoryEvent($warehouseEvent->getId());
                    $warehouseCountsMap = array();
                    foreach($warehouseItemCounts as $count) {
                        $categoryId = $count->getItemCategory();
                        $warehouseCountsMap[$categoryId] = $count;
                    }
                }
                if($pantryEvent) {
                    $pantryItemCounts = get_itemCounts_by_inventoryEvent($pantryEvent->getId());
                    $pantryCountsMap = array();
                    foreach($pantryItemCounts as $count) {
                        $categoryId = $count->getItemCategory();
                        $pantryCountsMap[$categoryId] = $count;
                    }
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Edit Inventory | Whiskey Valor Foundation</title>
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
        .updateInv-qty {
            width: 80px;
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.05);
            color: var(--page-font-color);
        }
        .updateInv-qty:disabled {
            background-color: rgba(0,0,0,0.02);
            color: var(--inactive-font-color);
            cursor: not-allowed;
        }
        .modifyUsers-formBtns {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .modify-save-btn {
            padding: 0.6rem 1.5rem;
            background-color: var(--accent-color);
            color: var(--button-font-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
        }
        .modify-save-btn:hover {
            opacity: 0.85;
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
            .updateInv-qty {
                width: 80px;
            }
        }
    </style>
</head>
<body>
    <?php require_once('header.php') ?>
    <main class="edit-container">
        <a href="viewEditDeleteInventory.php" class="back-btn">← Back</a>

        <h1 class="title">Edit Inventory</h1>

        <!-- Event Info (Read-Only) -->
        <div class="event-info">
            <p><strong>Date:</strong> <?= date('M j, Y', strtotime($eventDate)) ?></p>
        </div>

        <!-- Success Message -->
        <?php if($success): ?>
            <h4 style="color:black;"><i>Inventory Updated: <?= date("F jS, Y", strtotime($eventDate)) ?></i></h4>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if(!empty($errors)): ?>
            <ul>
                <?php foreach($errors AS $error): ?>
                    <li><?php echo("<h4 style=\"color:red;\"><i>".$error."</i></h4>"); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- Edit Form -->
        <form method="POST">
            <table class="modify-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Items Per Box</th>
                        <th>Warehouse</th>
                        <th>Pantry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rowNum = 0;
                    foreach($allCategories as $category): ?>
                        <?php if($category->getStatus() != 'Active') continue; ?>
                        <?php $rowNum++; ?>
                        <?php
                        $categoryId = $category->getId();

                        // Warehouse quantity
                        $warehouseQty = isset($warehouseCountsMap[$categoryId])
                            ? $warehouseCountsMap[$categoryId]->getQuantity()
                            : null;

                        // Pantry quantity
                        $pantryQty = isset($pantryCountsMap[$categoryId])
                            ? $pantryCountsMap[$categoryId]->getQuantity()
                            : null;
                        ?>
                        <tr>
                            <td><?= $rowNum ?></td>
                            <td><?= htmlspecialchars($category->getName()) ?></td>
                            <td><?= htmlspecialchars($category->getItemsPerBox()) ?></td>

                            <!-- Warehouse Input -->
                            <td>
                                <?php if($warehouseEvent && $warehouseQty !== null): ?>
                                    <input type="number"
                                           name="warehouse_qty_<?= $categoryId ?>"
                                           value="<?= $warehouseQty ?>"
                                           min="0"
                                           class="updateInv-qty">
                                <?php else: ?>
                                    <span style="color: var(--inactive-font-color);">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Pantry Input -->
                            <td>
                                <?php if($pantryEvent && $pantryQty !== null): ?>
                                    <input type="number"
                                           name="pantry_qty_<?= $categoryId ?>"
                                           value="<?= $pantryQty ?>"
                                           min="0"
                                           class="updateInv-qty">
                                <?php else: ?>
                                    <span style="color: var(--inactive-font-color);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="modifyUsers-formBtns">
                <button type="submit" name="save_button" class="modify-save-btn">
                    Save Changes
                </button>
                <button type="submit" name="cancel_button" class="modify-cancel-btn">
                    Cancel
                </button>
            </div>
        </form>
    </main>
</body>
</html>
