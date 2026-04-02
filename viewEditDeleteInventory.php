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

    /* Get all inventory events sorted by date (newest first), then by ID (highest first) */
    $allEventObjects = get_all_inventoryEvents();
    usort($allEventObjects, function($a, $b) {
        $dateDiff = strtotime($b->getDate()) - strtotime($a->getDate());
        if ($dateDiff != 0) {
            return $dateDiff;
        }
        return $b->getId() - $a->getId();
    });
?>

<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Edit/Delete Inventory | Whiskey Valor Foundation</title>
    <style>
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1rem 2rem;
        }
        .title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--secondary-accent-color);
            margin-bottom: 1.5rem;
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .inventory-table th {
            background-color: var(--main-color);
            color: var(--button-font-color);
            padding: 1rem;
            text-align: left;
            font-weight: 500;
        }
        .inventory-table th:last-child,
        .inventory-table td:last-child {
            width: 220px;
        }
        .inventory-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--shadow-and-border-color);
            color: var(--page-font-color);
        }
        .inventory-table tr:hover {
            background-color: rgba(255,255,255,0.05);
        }
        .modify-btn {
            padding: 0.6rem 1.3rem;
            background-color: var(--accent-color);
            color: var(--button-font-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            margin-right: 0.5rem;
        }
        .modify-btn:hover {
            opacity: 0.85;
        }
        .delete-btn {
            padding: 0.6rem 1.3rem;
            background-color: #dc2626;
            color: white;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
        }
        .delete-btn:hover {
            background-color: #b91c1c;
        }
        .success-message {
            background-color: #dcfce7;
            color: #166534;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--inactive-font-color);
        }
        @media only screen and (max-width: 768px) {
            .inventory-table th,
            .inventory-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
            .main-container {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once('header.php') ?>
    <main class="main-container">
        <h1 class="title">Edit/Delete Inventory</h1>

        <!-- Success Message -->
        <?php if(isset($_GET['deleted']) && $_GET['deleted'] == 'success'): ?>
            <?php
                $deletedDate = $_GET['date'] ?? '';
                $deletedLocation = $_GET['location'] ?? '';
                $deletedEventId = $_GET['eventId'] ?? '';
                $formattedDate = $deletedDate ? date("F jS, Y", strtotime($deletedDate)) : '';
            ?>
            <h4 style="color:black;"><i>Inventory Event Deleted: <?= $formattedDate ?>  -  <?= htmlspecialchars($deletedLocation) ?>  -  Event ID: <?= htmlspecialchars($deletedEventId) ?></i></h4>
        <?php endif; ?>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Event ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($allEventObjects) > 0): ?>
                    <?php foreach($allEventObjects as $index => $event): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('M j, Y', strtotime($event->getDate())) ?></td>
                            <td><?= htmlspecialchars($event->getLocation()) ?></td>
                            <td><?= htmlspecialchars($event->getId()) ?></td>
                            <td style="white-space: nowrap;">
                                <a href="editInventoryEvent.php?id=<?= htmlspecialchars($event->getId()) ?>" style="display: inline-block;">
                                    <button class="modify-btn">Edit</button>
                                </a>
                                <a href="deleteInventoryEvent.php?id=<?= htmlspecialchars($event->getId()) ?>" style="display: inline-block;">
                                    <button class="delete-btn">Delete</button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            No inventory records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
