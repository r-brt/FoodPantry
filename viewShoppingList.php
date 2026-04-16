<?php
    session_cache_expire(30);
    session_start();

    // Handle AJAX basket quantity + notes update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateQty') {
        require_once('database/dbShoppingCount.php');
        $id       = isset($_POST['id'])       ? (int)$_POST['id']       : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $notes    = isset($_POST['notes'])    ? trim($_POST['notes'])    : null;
        header('Content-Type: application/json');
        if ($id > 0 && $quantity >= 0) {
            $result = update_shoppingCount_quantity($id, $quantity);
            if ($notes !== null) {
                update_shoppingCount_notes($id, $notes);
            }
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX: create a new group from two ungrouped items
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'createGroup') {
        require_once('database/dbinfo.php');
        require_once('database/dbShoppingCount.php');
        $item1Id         = isset($_POST['item1Id'])         ? (int)$_POST['item1Id']         : 0;
        $item2Id         = isset($_POST['item2Id'])         ? (int)$_POST['item2Id']         : 0;
        $shoppingEventId = isset($_POST['shoppingEventId']) ? (int)$_POST['shoppingEventId'] : 0;
        $groupName       = isset($_POST['groupName'])       ? trim($_POST['groupName'])       : 'New Group';
        header('Content-Type: application/json');
        if ($item1Id > 0 && $item2Id > 0 && $shoppingEventId > 0) {
            $groupId = create_shoppingCount_group($shoppingEventId, $groupName);
            assign_to_group($item1Id, $groupId);
            assign_to_group($item2Id, $groupId);
            echo json_encode(['success' => true, 'groupId' => $groupId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX: add one item to an existing group
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'addToGroup') {
        require_once('database/dbShoppingCount.php');
        $itemId  = isset($_POST['itemId'])  ? (int)$_POST['itemId']  : 0;
        $groupId = isset($_POST['groupId']) ? (int)$_POST['groupId'] : 0;
        header('Content-Type: application/json');
        if ($itemId > 0 && $groupId > 0) {
            assign_to_group($itemId, $groupId);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX: rename a group
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renameGroup') {
        require_once('database/dbShoppingCount.php');
        $groupId   = isset($_POST['groupId'])   ? (int)$_POST['groupId']         : 0;
        $groupName = isset($_POST['groupName']) ? trim($_POST['groupName'])       : '';
        header('Content-Type: application/json');
        if ($groupId > 0 && $groupName !== '') {
            echo json_encode(['success' => rename_shoppingCount_group($groupId, $groupName)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX: remove one item from its group (auto-deletes group when < 2 members remain)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'removeFromGroup') {
        require_once('database/dbinfo.php');
        require_once('database/dbShoppingCount.php');
        $itemId = isset($_POST['itemId']) ? (int)$_POST['itemId'] : 0;
        header('Content-Type: application/json');
        if ($itemId > 0) {
            $con    = connect();
            $r      = mysqli_fetch_assoc(mysqli_query($con, 'SELECT groupId FROM dbshoppingcounts WHERE id = ' . $itemId));
            $oldGid = $r ? (int)$r['groupId'] : 0;
            mysqli_close($con);

            remove_from_group($itemId);

            if ($oldGid > 0) {
                $con2 = connect();
                $cnt  = mysqli_fetch_assoc(mysqli_query($con2, 'SELECT COUNT(*) as c FROM dbshoppingcounts WHERE groupId = ' . $oldGid));
                if ((int)$cnt['c'] <= 1) {
                    // Dissolve the last remaining member back to ungrouped
                    mysqli_query($con2, 'UPDATE dbshoppingcounts SET groupId = NULL WHERE groupId = ' . $oldGid);
                    mysqli_query($con2, 'DELETE FROM dbshoppingcountgroup WHERE id = ' . $oldGid);
                }
                mysqli_close($con2);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX: delete an entire group (ungroups all members)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteGroup') {
        require_once('database/dbShoppingCount.php');
        $groupId = isset($_POST['groupId']) ? (int)$_POST['groupId'] : 0;
        header('Content-Type: application/json');
        if ($groupId > 0) {
            delete_shoppingCount_group($groupId);
            echo json_encode(['success' => true]);
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

    if ($accessLevel < 2) {
        header('Location: index.php');
        die();
    }

    require_once('database/dbinfo.php');
    require_once('database/dbItemCategory.php');
    require_once('database/dbShoppingEvent.php');
    require_once('database/dbShoppingCount.php');

    // Get all item categories and build category ID → name map
    $allCategories = get_all_ItemCategory();
    $categoryMap   = array();
    foreach ($allCategories as $cat) {
        $categoryMap[$cat->getId()] = $cat->getName();
    }

    // Get all shopping events, extract unique family sizes (sorted)
    $allShoppingEvents = get_all_shoppingEvents();
    $familySizes = array();
    foreach ($allShoppingEvents as $event) {
        $fs = $event->getFamilySize();
        if (!in_array($fs, $familySizes)) {
            $familySizes[] = $fs;
        }
    }
    sort($familySizes);

    // If a family size is selected, load its basket counts (with group info)
    // Default to the first (smallest) family size so the page is never blank on load
    $selectedFamilySize      = isset($_GET['familySize']) ? $_GET['familySize'] : (count($familySizes) > 0 ? $familySizes[0] : null);
    $selectedShoppingEventId = null;
    $basketGroups            = array(); // groupId => ['id','name','items'=>[...]]
    $basketUngrouped         = array();

    if ($selectedFamilySize !== null) {
        $filtered = array_filter($allShoppingEvents, function($e) use ($selectedFamilySize) {
            return $e->getFamilySize() == $selectedFamilySize;
        });
        usort($filtered, function($a, $b) {
            $dateDiff = strtotime($b->getDate()) - strtotime($a->getDate());
            if ($dateDiff != 0) return $dateDiff;
            return $b->getId() - $a->getId();
        });
        if (!empty($filtered)) {
            $latestEvent             = reset($filtered);
            $selectedShoppingEventId = $latestEvent->getId();

            $con2 = connect();

            // Load group definitions for this event
            $grpRes = mysqli_query($con2,
                'SELECT * FROM dbshoppingcountgroup WHERE shoppingEventId = ' . $selectedShoppingEventId . ' ORDER BY id ASC');
            if ($grpRes) {
                while ($gr = mysqli_fetch_assoc($grpRes)) {
                    $basketGroups[(int)$gr['id']] = ['id' => (int)$gr['id'], 'name' => $gr['groupName'], 'items' => []];
                }
            }

            // Load counts with groupId
            $cntRes = mysqli_query($con2,
                'SELECT * FROM dbshoppingcounts WHERE shoppingEventId = ' . $selectedShoppingEventId . ' ORDER BY id ASC');
            if ($cntRes) {
                while ($row = mysqli_fetch_assoc($cntRes)) {
                    $catId = (int)$row['itemCategoryId'];
                    $item  = [
                        'id'        => (int)$row['id'],
                        'item_name' => isset($categoryMap[$catId]) ? $categoryMap[$catId] : 'Unknown (ID: ' . $catId . ')',
                        'quantity'  => (int)$row['quantity'],
                        'groupId'   => $row['groupId'] !== null ? (int)$row['groupId'] : null,
                        'notes'     => $row['notes'] ?? '',
                    ];
                    if ($item['groupId'] !== null && isset($basketGroups[$item['groupId']])) {
                        $basketGroups[$item['groupId']]['items'][] = $item;
                    } else {
                        $basketUngrouped[] = $item;
                    }
                }
            }

            mysqli_close($con2);
        }
    }

?>
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Shopping List | CCDA</title>
    <script src="js/jspdf.umd.min.js"></script>
    <script src="js/jspdf.plugin.autotable.min.js"></script>
    <style>
        pageheader {
            margin-top: 3rem;
            display: flex; justify-content: center; align-items: center;
        }
        .title {
            position: fixed;
            text-align: center;
            height: 3.5rem;
            width: 40%;
            z-index: 1000;
            font-size: 2rem;
            font-weight: 600;
            color: var(--secondary-accent-color);
            background-color: white;
            padding-top: 0;
            mask-image: linear-gradient(to right, transparent, black 20%, black 80%, transparent);
        }
        .report-container {
            max-width: 1100px;
            margin: 0 auto 4rem auto;
            padding: 1rem;
        }
        .report-section {
            background-color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
        .week-selector select:hover { background-color: rgba(0,0,0,0.3); }
        .select { background-color: white !important; }
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
        .generate-btn:hover { opacity: 0.85; }
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
        .drag-handle:active { cursor: grabbing; }
        #basketTbody tr.drag-over-top    { border-top: 2px solid var(--accent-color); }
        #basketTbody tr.drag-over-bottom { border-bottom: 2px solid var(--accent-color); }
        #basketTbody tr.dragging         { opacity: 0.4; }
        .row-number { text-align: center; color: var(--inactive-font-color); font-weight: 500; }
        /* ---- Notes input ---- */
        .basket-notes-input {
            width: 100%;
            padding: 0.3rem 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid transparent;
            background: transparent;
            color: var(--page-font-color);
            font-size: inherit;
        }
        .basket-notes-input:hover,
        .basket-notes-input:focus {
            border-color: var(--accent-color);
            background: rgba(0,0,0,0.12);
            outline: none;
        }
        /* give the Notes column a real floor so it can't collapse */
        #basketTable th:last-child,
        #basketTable td:last-child { min-width: 160px; }
        /* notes cell wrapper — always a div inside td */
        .basket-notes-cell {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 0.25rem;
            width: 100%;
        }
        .basket-notes-cell .basket-notes-input {
            width: 100%;
            min-width: 0;
        }

        /* ---- Item-group styles ---- */
        .group-header-row td {
            background-color: var(--main-color);
            color: var(--button-font-color);
            font-weight: 600;
            padding: 0.5rem 1rem;
        }
        .group-header-row .drag-handle { color: var(--button-font-color); }
        .group-header-cell { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
        .group-name-display {
            cursor: pointer;
            flex: 1;
        }
        .group-name-display:hover { text-decoration: underline dotted; }
        .group-name-input {
            flex: 1;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid rgba(255,255,255,0.4);
            background: rgba(255,255,255,0.15);
            color: var(--button-font-color);
            font-size: inherit;
            font-weight: 600;
        }
        .ungroup-all-btn {
            background: rgba(255,255,255,0.12);
            color: var(--button-font-color);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 0.25rem;
            padding: 0.15rem 0.6rem;
            cursor: pointer;
            font-size: 0.78rem;
            white-space: nowrap;
        }
        .ungroup-all-btn:hover { background: rgba(255,255,255,0.25); }
        /* group item rows: subtle tint + left accent bar */
        .group-item-row td {
            background-color: rgba(0,0,0,0.035);
        }
        .group-item-row td:first-child {
            border-left: 3px solid var(--accent-color);
            padding-left: calc(1rem - 3px);
        }
        .group-item-row td:nth-child(3) { padding-left: 2rem !important; }
        /* remove the row hover that would wash out the tint */
        #basketTbody .group-item-row:hover td { background-color: rgba(0,0,0,0.06); }
        .group-indent { color: var(--inactive-font-color); margin-right: 0.3rem; }
        .remove-from-group-btn {
            background: transparent;
            color: var(--inactive-font-color);
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            line-height: 1;
            padding: 0 0.15rem;
            flex-shrink: 0;
        }
        .remove-from-group-btn:hover { color: rgb(239, 68, 68); }
        #basketTbody tr.drag-over-center {
            outline: 2px dashed var(--accent-color);
            outline-offset: -2px;
            background-color: rgba(0,0,0,0.08);
        }
        @media only screen and (max-width: 768px) {
            .report-table th, .report-table td { padding: 0.5rem; font-size: 0.8rem; }
            .report-container { padding: 0.5rem; }
            div.table-wrapper { overflow-x: auto; }
            .data-entry-row  { flex-direction: column; align-items: stretch; }
            .data-entry-label { width: auto; }
        }
    </style>
</head>
<pageheader>
    <h1 class="title">Shopping List</h1>
</pageheader>
<body>
    <?php require_once('header.php') ?>
    <main>
        <div class="report-container">

            <!-- Shopping List -->
            <div class="report-section">
                <h2>Basket by Family Size</h2>
                <p style="color: var(--page-font-color); margin-bottom: 1rem;">Select a family size to view the recommended basket items and quantities.</p>

                <div class="week-selector">
                    <label for="familySizeSelect">Family Size:</label>
                    <select class="select" id="familySizeSelect" name="familySize"
                        onchange="window.location.href='?familySize=' + encodeURIComponent(this.value)">
                        <option value="">-- Select Family Size --</option>
                        <?php foreach ($familySizes as $fs): ?>
                            <option value="<?= htmlspecialchars($fs) ?>"
                                <?= ($fs == $selectedFamilySize) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fs) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selectedFamilySize !== null): ?>
                    <div style="display: flex; gap: 0.75rem; margin-top: 1.25rem; flex-wrap: wrap;">
                        <button class="generate-btn" id="saveQuantitiesBtn">Save Quantities</button>
                        <button class="generate-btn" id="generatePdfBtn">Generate PDF</button>
                    </div>
                    <div class="table-wrapper" style="margin-top: 1rem;" id="basketTableWrapper">
                        <table class="report-table" id="basketTable">
                            <thead>
                                <tr>
                                    <th style="width: 0px;"></th>
                                    <th style="width: 25px;">#</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="basketTbody">
                                <?php
                                $hasBasketItems = !empty($basketGroups) || !empty($basketUngrouped);
                                if ($hasBasketItems):
                                    $rowNum = 1;

                                    // ---- Render groups first ----
                                    foreach ($basketGroups as $group):
                                ?>
                                    <tr class="group-header-row" data-group-id="<?= $group['id'] ?>" draggable="true">
                                        <td class="drag-handle" title="Drag to reorder group">&#8597;</td>
                                        <td></td>
                                        <td colspan="3" class="group-header-cell">
                                            <span class="group-name-display" title="Click to rename"><?= htmlspecialchars($group['name']) ?></span>
                                            <input class="group-name-input" value="<?= htmlspecialchars($group['name']) ?>" style="display:none" aria-label="Group name">
                                            <button class="ungroup-all-btn" data-group-id="<?= $group['id'] ?>">Ungroup All</button>
                                        </td>
                                    </tr>
                                    <?php foreach ($group['items'] as $item): ?>
                                    <tr class="group-item-row" draggable="true" data-count-id="<?= $item['id'] ?>" data-group-id="<?= $group['id'] ?>">
                                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                                        <td class="row-number"><?= $rowNum++ ?></td>
                                        <td data-item-name="<?= htmlspecialchars($item['item_name']) ?>"><span class="group-indent">&#8627;</span><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><input type="number" class="basket-qty-input" value="<?= $item['quantity'] ?>" min="0" draggable="false"></td>
                                        <td><div class="basket-notes-cell">
                                            <input type="text" class="basket-notes-input" placeholder="Optional..." value="<?= htmlspecialchars($item['notes']) ?>" draggable="false">
                                            <button class="remove-from-group-btn" data-item-id="<?= $item['id'] ?>" data-group-id="<?= $group['id'] ?>" title="Remove from group">&#215;</button>
                                        </div></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>

                                    <?php // ---- Render ungrouped items ----
                                    foreach ($basketUngrouped as $item): ?>
                                    <tr draggable="true" data-count-id="<?= $item['id'] ?>">
                                        <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                                        <td class="row-number"><?= $rowNum++ ?></td>
                                        <td data-item-name="<?= htmlspecialchars($item['item_name']) ?>"><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><input type="number" class="basket-qty-input" value="<?= $item['quantity'] ?>" min="0" draggable="false"></td>
                                        <td><input type="text" class="basket-notes-input" placeholder="Optional..." value="<?= htmlspecialchars($item['notes']) ?>" draggable="false"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">No items found for this family size.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        $(function() {
            var selectedShoppingEventId = <?= $selectedShoppingEventId !== null ? (int)$selectedShoppingEventId : 'null' ?>;

            // ---- Utility ----
            function escHtml(str) {
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            function renumberRows() {
                var n = 1;
                document.querySelectorAll('#basketTbody tr:not(.group-header-row)').forEach(function(r) {
                    var cell = r.querySelector('.row-number');
                    if (cell) cell.textContent = n++;
                });
            }

            // ---- Group header: inline rename + ungroup-all ----
            function bindGroupHeaderEvents(headerRow) {
                var nameDisplay = headerRow.querySelector('.group-name-display');
                var nameInput   = headerRow.querySelector('.group-name-input');
                var ungroupBtn  = headerRow.querySelector('.ungroup-all-btn');
                var groupId     = headerRow.dataset.groupId;

                nameDisplay.addEventListener('click', function() {
                    nameDisplay.style.display = 'none';
                    nameInput.style.display   = 'inline-block';
                    nameInput.focus();
                    nameInput.select();
                });

                function saveGroupName() {
                    var newName = nameInput.value.trim() || 'New Group';
                    nameInput.style.display   = 'none';
                    nameDisplay.style.display = '';
                    nameDisplay.textContent   = newName;
                    $.post('viewShoppingList.php', { action: 'renameGroup', groupId: groupId, groupName: newName });
                }
                nameInput.addEventListener('blur',    saveGroupName);
                nameInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter')  { e.preventDefault(); saveGroupName(); }
                    if (e.key === 'Escape') { nameInput.style.display = 'none'; nameDisplay.style.display = ''; }
                });

                ungroupBtn.addEventListener('click', function() {
                    var label = nameDisplay.textContent;
                    if (!confirm('Ungroup all items in "' + label + '"?')) return;
                    $.post('viewShoppingList.php', { action: 'deleteGroup', groupId: groupId }, function(data) {
                        if (!data.success) return;
                        // Remove group indicators from member rows
                        document.querySelectorAll('#basketTbody tr[data-group-id="' + groupId + '"]:not(.group-header-row)')
                            .forEach(function(r) {
                                delete r.dataset.groupId;
                                r.classList.remove('group-item-row');
                                var nc = r.querySelector('td[data-item-name]');
                                if (nc) { nc.innerHTML = escHtml(nc.dataset.itemName); }
                                var wrapper = r.querySelector('.basket-notes-cell');
                                if (wrapper) {
                                    var ni = wrapper.querySelector('.basket-notes-input');
                                    var parentTd = wrapper.parentNode;
                                    if (ni) { parentTd.insertBefore(ni, wrapper); }
                                    wrapper.remove();
                                }
                            });
                        headerRow.remove();
                        renumberRows();
                    }, 'json');
                });
            }

            // ---- Remove-from-group button ----
            function bindRemoveFromGroupBtn(btn) {
                btn.addEventListener('click', function() {
                    var itemId  = btn.dataset.itemId;
                    var groupId = btn.dataset.groupId;
                    $.post('viewShoppingList.php', { action: 'removeFromGroup', itemId: itemId }, function(data) {
                        if (!data.success) return;
                        var row = document.querySelector('#basketTbody tr[data-count-id="' + itemId + '"]');
                        if (row) {
                            delete row.dataset.groupId;
                            row.classList.remove('group-item-row');
                            // Restore item name (remove indent arrow)
                            var nc = row.querySelector('td[data-item-name]');
                            if (nc) { nc.innerHTML = escHtml(nc.dataset.itemName); }
                            // Unwrap notes input from .basket-notes-cell div
                            var wrapper = row.querySelector('.basket-notes-cell');
                            if (wrapper) {
                                var ni = wrapper.querySelector('.basket-notes-input');
                                var parentTd = wrapper.parentNode;
                                if (ni) { parentTd.insertBefore(ni, wrapper); }
                                wrapper.remove();
                            }
                        }
                        // If server dissolved the group (≤1 member left), remove header & last member's indicators
                        var remainingMembers = document.querySelectorAll(
                            '#basketTbody tr[data-group-id="' + groupId + '"]:not(.group-header-row)');
                        if (remainingMembers.length === 0) {
                            var header = document.querySelector('#basketTbody .group-header-row[data-group-id="' + groupId + '"]');
                            if (header) header.remove();
                        } else if (remainingMembers.length === 1) {
                            // Server already ungrouped the last one — clean up the header
                            var header2 = document.querySelector('#basketTbody .group-header-row[data-group-id="' + groupId + '"]');
                            if (header2) {
                                var lastRow = remainingMembers[0];
                                lastRow.classList.remove('group-item-row');
                                delete lastRow.dataset.groupId;
                                var nc2 = lastRow.querySelector('td[data-item-name]');
                                if (nc2) { nc2.innerHTML = escHtml(nc2.dataset.itemName); }
                                var wrapper2 = lastRow.querySelector('.basket-notes-cell');
                                if (wrapper2) {
                                    var ni2 = wrapper2.querySelector('.basket-notes-input');
                                    var ptd2 = wrapper2.parentNode;
                                    if (ni2) { ptd2.insertBefore(ni2, wrapper2); }
                                    wrapper2.remove();
                                }
                                header2.remove();
                            }
                        }
                        renumberRows();
                    }, 'json');
                });
            }

            // ---- Add remove-btn + group-item-row class to a row ----
            function markAsGroupItem(row, groupId) {
                row.classList.add('group-item-row');
                row.dataset.groupId = groupId;

                // Prefix item name with indent arrow
                var nc = row.querySelector('td[data-item-name]');
                if (nc && !nc.querySelector('.group-indent')) {
                    nc.innerHTML = '<span class="group-indent">&#8627;</span>' + escHtml(nc.dataset.itemName);
                }

                // Wrap the notes input in a .basket-notes-cell div and append the × button
                var ntd = row.querySelector('td:last-child');
                if (ntd && !ntd.querySelector('.remove-from-group-btn')) {
                    var ni = ntd.querySelector('.basket-notes-input');
                    var wrapper = document.createElement('div');
                    wrapper.className = 'basket-notes-cell';
                    // Move the input into the wrapper (or create placeholder if absent)
                    if (ni) {
                        ni.setAttribute('draggable', 'false');
                        ntd.removeChild(ni);
                        wrapper.appendChild(ni);
                    }
                    var btn = document.createElement('button');
                    btn.className       = 'remove-from-group-btn';
                    btn.dataset.itemId  = row.dataset.countId;
                    btn.dataset.groupId = groupId;
                    btn.title           = 'Remove from group';
                    btn.innerHTML       = '&#215;';
                    bindRemoveFromGroupBtn(btn);
                    wrapper.appendChild(btn);
                    ntd.appendChild(wrapper);
                }
            }

            // ---- Create new group (two ungrouped items) ----
            function createGroup(srcRow, targetRow) {
                if (!selectedShoppingEventId) return;
                var item1Id = srcRow.dataset.countId;
                var item2Id = targetRow.dataset.countId;
                $.post('viewShoppingList.php', {
                    action: 'createGroup',
                    item1Id: item1Id,
                    item2Id: item2Id,
                    shoppingEventId: selectedShoppingEventId,
                    groupName: 'New Group'
                }, function(data) {
                    if (!data.success) return;
                    var groupId = data.groupId;

                    // Build header row
                    var htr = document.createElement('tr');
                    htr.className          = 'group-header-row';
                    htr.dataset.groupId    = groupId;
                    htr.draggable          = true;
                    htr.innerHTML =
                        '<td class="drag-handle" title="Drag to reorder group">&#8597;</td>' +
                        '<td></td>' +
                        '<td colspan="3" class="group-header-cell">' +
                            '<span class="group-name-display" title="Click to rename">New Group</span>' +
                            '<input class="group-name-input" value="New Group" style="display:none" aria-label="Group name">' +
                            '<button class="ungroup-all-btn" data-group-id="' + groupId + '">Ungroup All</button>' +
                        '</td>';
                    bindGroupHeaderEvents(htr);

                    // Insert header before whichever of the two rows appears first
                    var rows    = Array.from(basketTbody.rows);
                    var srcIdx  = rows.indexOf(srcRow);
                    var tgtIdx  = rows.indexOf(targetRow);
                    var firstRow = srcIdx < tgtIdx ? srcRow : targetRow;
                    basketTbody.insertBefore(htr, firstRow);

                    // Move both rows directly after the header
                    basketTbody.insertBefore(srcRow,    htr.nextSibling);
                    basketTbody.insertBefore(targetRow, srcRow.nextSibling);

                    markAsGroupItem(srcRow,    groupId);
                    markAsGroupItem(targetRow, groupId);
                    renumberRows();
                }, 'json');
            }

            // ---- Add item to existing group ----
            function addToGroup(srcRow, groupId) {
                var itemId = srcRow.dataset.countId;
                $.post('viewShoppingList.php', { action: 'addToGroup', itemId: itemId, groupId: groupId }, function(data) {
                    if (!data.success) return;
                    // Place row after last member of that group
                    var members = Array.from(
                        basketTbody.querySelectorAll('tr[data-group-id="' + groupId + '"]:not(.group-header-row)'));
                    var anchor  = members.length ? members[members.length - 1] : basketTbody.querySelector('.group-header-row[data-group-id="' + groupId + '"]');
                    if (anchor) basketTbody.insertBefore(srcRow, anchor.nextSibling);
                    markAsGroupItem(srcRow, groupId);
                    renumberRows();
                }, 'json');
            }

            // ---- Drag-and-drop ----
            var basketTbody = document.getElementById('basketTbody');
            if (basketTbody) {
                var dragSrc = null;

                basketTbody.addEventListener('dragstart', function(e) {
                    dragSrc = e.target.closest('tr');
                    if (dragSrc) dragSrc.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                basketTbody.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    var target = e.target.closest('tr');
                    if (!target || target === dragSrc) return;

                    // Don't highlight group members that belong to the group being dragged
                    if (dragSrc && dragSrc.classList.contains('group-header-row') &&
                        target.dataset.groupId === dragSrc.dataset.groupId) return;

                    basketTbody.querySelectorAll('tr').forEach(function(r) {
                        r.classList.remove('drag-over-top', 'drag-over-bottom', 'drag-over-center');
                    });

                    var srcIsHeader = dragSrc && dragSrc.classList.contains('group-header-row');

                    // Dropping on group header → always "add to group" indicator (unless dragging a header)
                    if (target.classList.contains('group-header-row') && !srcIsHeader) {
                        target.classList.add('drag-over-center');
                        return;
                    }

                    var rect = target.getBoundingClientRect();
                    var pct  = (e.clientY - rect.top) / rect.height;

                    if (srcIsHeader || pct < 0.25 || pct > 0.75) {
                        target.classList.add(pct <= 0.5 ? 'drag-over-top' : 'drag-over-bottom');
                    } else {
                        target.classList.add('drag-over-center');
                    }
                });

                basketTbody.addEventListener('dragleave', function(e) {
                    var target = e.target.closest('tr');
                    if (target) target.classList.remove('drag-over-top', 'drag-over-bottom', 'drag-over-center');
                });

                basketTbody.addEventListener('drop', function(e) {
                    e.preventDefault();
                    var target = e.target.closest('tr');
                    if (!target || target === dragSrc) return;

                    // Zone was stored reliably by the second dragover listener
                    var finalZone = basketTbody.dataset.pendingZone || 'top';
                    delete basketTbody.dataset.pendingZone;

                    target.classList.remove('drag-over-top', 'drag-over-bottom', 'drag-over-center');

                    if (finalZone === 'center') {
                        // GROUP ACTION
                        var srcIsHeader   = dragSrc.classList.contains('group-header-row');
                        var srcGroupId    = dragSrc.dataset.groupId || null;
                        var targetGroupId = target.classList.contains('group-header-row')
                            ? target.dataset.groupId
                            : (target.dataset.groupId || null);

                        if (!srcIsHeader && dragSrc.dataset.countId) {
                            if (targetGroupId) {
                                // Add to existing group
                                addToGroup(dragSrc, targetGroupId);
                            } else if (target.dataset.countId) {
                                // Create new group
                                createGroup(dragSrc, target);
                            }
                        }
                    } else {
                        // REORDER ACTION
                        var srcIsGroupHeader = dragSrc.classList.contains('group-header-row');
                        var insertBefore     = finalZone === 'top';

                        if (srcIsGroupHeader && dragSrc.dataset.groupId) {
                            // Move the entire group (header + all members) together
                            var gid      = dragSrc.dataset.groupId;
                            var groupRows = [dragSrc];
                            basketTbody.querySelectorAll('tr[data-group-id="' + gid + '"]:not(.group-header-row)')
                                .forEach(function(r) { groupRows.push(r); });
                            var refNode = insertBefore ? target : target.nextSibling;
                            groupRows.forEach(function(r) { basketTbody.insertBefore(r, refNode); });
                        } else {
                            if (insertBefore) {
                                basketTbody.insertBefore(dragSrc, target);
                            } else {
                                basketTbody.insertBefore(dragSrc, target.nextSibling);
                            }
                        }
                        renumberRows();
                    }
                });

                // Store drop zone during dragover so the drop handler can read it reliably
                basketTbody.addEventListener('dragover', function(e) {
                    var target = e.target.closest('tr');
                    if (!target || target === dragSrc) return;
                    var srcIsHeader = dragSrc && dragSrc.classList.contains('group-header-row');
                    if (target.classList.contains('group-header-row') && !srcIsHeader) {
                        basketTbody.dataset.pendingZone = 'center';
                        return;
                    }
                    var rect = target.getBoundingClientRect();
                    var pct  = (e.clientY - rect.top) / rect.height;
                    if (srcIsHeader || pct < 0.25 || pct > 0.75) {
                        basketTbody.dataset.pendingZone = pct <= 0.5 ? 'top' : 'bottom';
                    } else {
                        basketTbody.dataset.pendingZone = 'center';
                    }
                });

                basketTbody.addEventListener('dragend', function() {
                    if (dragSrc) dragSrc.classList.remove('dragging');
                    dragSrc = null;
                    basketTbody.querySelectorAll('tr').forEach(function(r) {
                        r.classList.remove('drag-over-top', 'drag-over-bottom', 'drag-over-center', 'dragging');
                    });
                    delete basketTbody.dataset.pendingZone;
                });

                // Wire up any groups that PHP already rendered on page load
                basketTbody.querySelectorAll('.group-header-row').forEach(bindGroupHeaderEvents);
                basketTbody.querySelectorAll('.remove-from-group-btn').forEach(bindRemoveFromGroupBtn);
            }

            // ---- Save basket quantities + notes ----
            $('#saveQuantitiesBtn').on('click', function() {
                var btn = $(this);
                var requests = [];
                $('#basketTbody tr').each(function() {
                    var row      = $(this);
                    var countId  = row.data('count-id');
                    var quantity = parseInt(row.find('.basket-qty-input').val(), 10);
                    if (!countId || isNaN(quantity) || quantity < 0) return;
                    var notes = row.find('.basket-notes-input').val() || '';
                    requests.push($.post('viewShoppingList.php', {
                        action: 'updateQty', id: countId, quantity: quantity, notes: notes
                    }));
                });
                $.when.apply($, requests).done(function() {
                    btn.text('Saved!');
                    setTimeout(function() { btn.text('Save Quantities'); }, 2000);
                });
            });

            // ---- Generate PDF ----
            // Colours matched to CSS variables: --main-color #2A435A, --accent-color #ffc20e
            var PDF_MAIN        = [42,  67,  90];   // #2A435A – column header + page title
            var PDF_GROUP_HDR   = [255, 236, 153];  // light yellow – group banner (distinct from dark column header)
            var PDF_GROUP_TEXT  = [42,  67,  90];   // dark text on yellow banner
            var PDF_ACCENT      = [255, 236, 153];  // light yellow – left bar on grouped item rows (matches group banner)
            var PDF_TINT        = [245, 248, 251];  // subtle tint for grouped item rows
            var PDF_ALT         = [245, 245, 245];  // alternating fill for ungrouped rows

            var pdfBtn = document.getElementById('generatePdfBtn');
            if (pdfBtn) {
                pdfBtn.addEventListener('click', function() {
                    var { jsPDF } = window.jspdf;
                    var doc        = new jsPDF();
                    var familySize = '<?= htmlspecialchars($selectedFamilySize ?? '') ?>';
                    var today      = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    // ---- Header text ----
                    doc.setFontSize(18);
                    doc.setTextColor(42, 67, 90);   // --main-color
                    doc.text('Shopping List', 14, 20);
                    doc.setFontSize(11);
                    doc.setTextColor(100, 100, 100);
                    doc.text('Family Size: ' + familySize, 14, 29);
                    doc.text('Date: ' + today, 14, 36);

                    // ---- Build table body ----
                    // rowMeta[i] = 'group-header' | 'grouped' | 'normal'
                    var body    = [];
                    var rowMeta = [];
                    var rowNum  = 1;
                    // track even/odd separately for ungrouped rows only
                    var ungroupedIdx = 0;

                    document.querySelectorAll('#basketTbody tr').forEach(function(tr) {

                        if (tr.classList.contains('group-header-row')) {
                            // --- Group header banner ---
                            var nameEl    = tr.querySelector('.group-name-display');
                            var groupName = nameEl ? nameEl.textContent.trim() : 'Group';
                            body.push([{
                                content:  groupName,
                                colSpan:  4,
                                styles: {
                                    fontStyle:   'bold',
                                    fillColor:   PDF_GROUP_HDR,
                                    textColor:   PDF_GROUP_TEXT,
                                    cellPadding: { top: 5, bottom: 5, left: 8, right: 8 },
                                    fontSize:    11
                                }
                            }]);
                            rowMeta.push('group-header');

                        } else {
                            // --- Item row ---
                            var cells = tr.querySelectorAll('td');
                            if (cells.length < 5) return;

                            var nameCell   = cells[2];
                            var itemName   = (nameCell.dataset.itemName || nameCell.textContent).trim();
                            var qtyInput   = cells[3].querySelector('input');
                            var qty        = qtyInput ? qtyInput.value.trim() : '';
                            var notesInput = cells[4].querySelector('input.basket-notes-input');
                            var notes      = notesInput ? notesInput.value.trim() : '';
                            var isGrouped  = !!tr.dataset.groupId;

                            // Indent grouped items with leading spaces (no Unicode arrows — font may not support them)
                            var displayName = isGrouped ? ('      ' + itemName) : itemName;

                            body.push([
                                { content: rowNum.toString(),  styles: { halign: 'center' } },
                                { content: displayName },
                                { content: qty,   styles: { halign: 'center' } },
                                { content: notes }
                            ]);
                            rowMeta.push(isGrouped ? 'grouped' : 'normal');
                            rowNum++;
                            if (!isGrouped) ungroupedIdx++;
                        }
                    });

                    // ---- Render table ----
                    doc.autoTable({
                        startY: 44,
                        head: [['#', 'Item Name', 'Quantity', 'Notes']],
                        body:  body,

                        headStyles: {
                            fillColor:  PDF_MAIN,
                            textColor:  [255, 255, 255],
                            fontStyle:  'bold',
                            fontSize:   11
                        },

                        // Default alternating rows (overridden per-row below)
                        alternateRowStyles: { fillColor: PDF_ALT },

                        columnStyles: {
                            0: { cellWidth: 16,     halign: 'center' },
                            1: { cellWidth: 70 },
                            2: { cellWidth: 30,     halign: 'center' },
                            3: { cellWidth: 'auto' }
                        },
                        styles: { fontSize: 11, overflow: 'linebreak', cellPadding: 4 },
                        margin: { left: 14, right: 14 },

                        // Per-row fill colours
                        didParseCell: function(data) {
                            if (data.section !== 'body') return;
                            var meta = rowMeta[data.row.index];

                            if (meta === 'group-header') {
                                // Lock group banner to accent yellow (distinct from the dark column header)
                                data.cell.styles.fillColor = PDF_GROUP_HDR;
                                data.cell.styles.textColor = PDF_GROUP_TEXT;

                            } else if (meta === 'grouped') {
                                // All grouped item cells get the tint; left padding reserved for the accent bar
                                data.cell.styles.fillColor = PDF_TINT;
                                if (data.column.index === 0) {
                                    data.cell.styles.cellPadding = { top: 4, bottom: 4, left: 7, right: 4 };
                                }
                            }
                        },

                        // Draw the yellow left accent bar on grouped item rows
                        didDrawCell: function(data) {
                            if (data.section !== 'body') return;
                            if (rowMeta[data.row.index] === 'grouped' && data.column.index === 0) {
                                doc.setFillColor(PDF_ACCENT[0], PDF_ACCENT[1], PDF_ACCENT[2]);
                                doc.rect(data.cell.x, data.cell.y, 3, data.cell.height, 'F');
                            }
                        }
                    });

                    doc.save('shopping-list-' + familySize.replace(/[^a-z0-9]/gi, '-') + '.pdf');
                });
            }

        });
    </script>
</body>
</html>
