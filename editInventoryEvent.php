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
    if(!$eventId) {
        echo "Invalid event ID";
        die();
    }

    /* Get event data */
    $event = retrieve_inventoryEvent($eventId);
    if(!$event) {
        echo "Event not found";
        die();
    }

    /* Get location and date for this specific event */
    $eventLocation = $event->getLocation();
    $eventDate = $event->getDate();

    /* Get item counts for this specific event only */
    $itemCounts = get_itemCounts_by_inventoryEvent($eventId);

    /* Build array mapping category ID to ItemCount object */
    $countsMap = array(); // categoryId => ItemCount object (with real ID)
    foreach($itemCounts as $count) {
        $categoryId = $count->getItemCategory();
        $countsMap[$categoryId] = $count;
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
            /* Update quantities for each item in this event */
            foreach($allCategories as $category) {
                if($category->getStatus() != 'Active') continue;

                $categoryId = $category->getId();
                $fieldName = 'qty_' . $categoryId;

                /* Update quantity if this category exists in the event */
                if(isset($_POST[$fieldName]) && isset($countsMap[$categoryId])) {
                    /* Convert to number */
                    try{
                        $newQty = +$_POST[$fieldName];
                    }
                    catch(TypeError $e){
                        $newQty = " ";
                    }

                    /* Validation */
                    if(!is_int($newQty)){
                        $errors[] = $category->getName() . ' quantity must be in whole numbers';
                        continue;
                    }
                    else if($newQty < 0){
                        $errors[] = $category->getName() . ' quantity cannot be negative';
                        continue;
                    }

                    /* Update using real database ID */
                    $itemCountId = $countsMap[$categoryId]->getId();
                    $oldQty = $countsMap[$categoryId]->getQuantity();

                    if($oldQty != $newQty) {
                        update_quantity($itemCountId, $newQty);
                    }
                }
            }

            if(empty($errors)) {
                $success = true;
                /* Refresh counts by re-fetching from database */
                $itemCounts = get_itemCounts_by_inventoryEvent($eventId);
                $countsMap = array();
                foreach($itemCounts as $count) {
                    $categoryId = $count->getItemCategory();
                    $countsMap[$categoryId] = $count;
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
        .success-message {
            background-color: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
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
        <h1 class="title">Edit Inventory</h1>

        <!-- Event Info (Read-Only) -->
        <div class="event-info">
            <p><strong>Date:</strong> <?= date('M j, Y', strtotime($eventDate)) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($eventLocation) ?></p>
            <p><strong>Event ID:</strong> <?= htmlspecialchars($eventId) ?></p>
        </div>

        <!-- Success Message -->
        <?php if($success): ?>
            <div class="success-message">
                ✓ Inventory updated successfully!
            </div>
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
                        <th>Item Name</th>
                        <th>Items Per Box</th>
                        <th>Boxes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allCategories as $category): ?>
                        <?php if($category->getStatus() != 'Active') continue; ?>
                        <?php
                        $categoryId = $category->getId();
                        $qty = isset($countsMap[$categoryId])
                            ? $countsMap[$categoryId]->getQuantity()
                            : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($category->getName()) ?></td>
                            <td><?= htmlspecialchars($category->getItemsPerBox()) ?></td>
                            <td>
                                <input type="number"
                                       name="qty_<?= $categoryId ?>"
                                       value="<?= $qty ?>"
                                       min="0"
                                       class="updateInv-qty"
                                       <?= isset($countsMap[$categoryId]) ? '' : 'disabled' ?>>
                                <?php if(!isset($countsMap[$categoryId])): ?>
                                    <small style="color: var(--inactive-font-color); display: block;">(Not in event)</small>
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
