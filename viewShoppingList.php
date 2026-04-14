<?php
    session_cache_expire(30);
    session_start();

    // Handle AJAX basket quantity update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateQty') {
        require_once('database/dbShoppingCount.php');
        $id       = isset($_POST['id'])       ? (int)$_POST['id']       : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        header('Content-Type: application/json');
        if ($id > 0 && $quantity >= 0) {
            $result = update_shoppingCount_quantity($id, $quantity);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX client count save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'saveClient') {
        require_once('database/dbinfo.php');
        require_once('database/dbClient.php');
        $personId   = isset($_SESSION['_id']) ? $_SESSION['_id'] : 0;
        $familySize = isset($_POST['familySize']) ? trim($_POST['familySize']) : '';
        $numClients = isset($_POST['numClients']) ? (float)$_POST['numClients'] : -1;
        $date       = isset($_POST['date']) ? trim($_POST['date']) : '';
        header('Content-Type: application/json');
        if (!empty($familySize) && $numClients >= 0 && !empty($date)) {
            $con    = connect();
            $query  = 'SELECT id FROM dbshoppingevent WHERE familySize = "' . mysqli_real_escape_string($con, $familySize) . '" ORDER BY date DESC, id DESC LIMIT 1';
            $result = mysqli_query($con, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $row             = mysqli_fetch_assoc($result);
                $shoppingEventId = $row['id'];
                mysqli_close($con);
                $id = add_client($shoppingEventId, $personId, $numClients, $date);
                echo json_encode(['success' => $id > 0, 'id' => $id]);
            } else {
                mysqli_close($con);
                echo json_encode(['success' => false, 'error' => 'No shopping event found for family size: ' . htmlspecialchars($familySize)]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }

    // Handle AJAX consumption rate save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'saveConsumption') {
        require_once('database/dbinfo.php');
        require_once('database/dbConsumption.php');
        $personId = isset($_SESSION['_id']) ? $_SESSION['_id'] : 0;
        $records  = isset($_POST['records']) ? json_decode($_POST['records'], true) : [];
        header('Content-Type: application/json');
        if (!empty($records) && is_array($records)) {
            $saved = 0;
            foreach ($records as $rec) {
                $shoppingEventId = isset($rec['shoppingEventId']) ? (int)$rec['shoppingEventId'] : 0;
                $itemCategoryId  = isset($rec['itemCategoryId'])  ? (int)$rec['itemCategoryId']  : 0;
                $itemsConsumed   = isset($rec['consumptionRate']) ? (float)$rec['consumptionRate'] : 0;
                $date            = isset($rec['date'])            ? trim($rec['date'])            : '';
                if ($shoppingEventId > 0 && $itemCategoryId > 0 && !empty($date)) {
                    add_consumption($shoppingEventId, $itemCategoryId, $itemsConsumed, $personId, $date);
                    $saved++;
                }
            }
            echo json_encode(['success' => true, 'saved' => $saved]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No records provided']);
        }
        exit;
    }

    // Handle AJAX distribution days save
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'saveDistribution') {
        require_once('database/dbinfo.php');
        require_once('database/dbDistribution.php');
        $personId         = isset($_SESSION['_id']) ? $_SESSION['_id'] : 0;
        $distributionDays = isset($_POST['distributionDays']) ? (int)$_POST['distributionDays'] : 0;
        $date             = isset($_POST['date']) ? trim($_POST['date']) : '';
        header('Content-Type: application/json');
        if ($distributionDays > 0 && !empty($date)) {
            $id = add_distribution($distributionDays, $personId, $date);
            echo json_encode(['success' => $id > 0, 'id' => $id]);
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
    require_once('database/dbClient.php');
    require_once('database/dbDistribution.php');

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

    // Build shopping event ID → family size map
    $shoppingEventFamilyMap = array();
    foreach ($allShoppingEvents as $se) {
        $shoppingEventFamilyMap[$se->getId()] = $se->getFamilySize();
    }

    // If a family size is selected, load its basket counts
    $selectedFamilySize = isset($_GET['familySize']) ? $_GET['familySize'] : null;
    $basketItems = array();
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
            $latestEvent = reset($filtered);
            $counts = get_shoppingCounts_by_shoppingEvent($latestEvent->getId());
            foreach ($counts as $count) {
                $catId = $count->getItemCategory();
                $basketItems[] = array(
                    'id'        => $count->getId(),
                    'item_name' => isset($categoryMap[$catId]) ? $categoryMap[$catId] : 'Unknown (ID: ' . $catId . ')',
                    'quantity'  => $count->getQuantity()
                );
            }
        }
    }

    // ---- Consumption Rate Calculation ----
    $con = connect();

    // Latest distribution days record
    $distRow = mysqli_fetch_assoc(mysqli_query($con,
        'SELECT * FROM dbdistribution ORDER BY date DESC, id DESC LIMIT 1'));
    $latestDistribution = $distRow
        ? new Distribution($distRow['id'], $distRow['distributionDays'], $distRow['personId'], $distRow['date'])
        : null;

    // Most recent date's client records
    $latestClients    = array();
    $latestClientDate = null;
    $clientResult = mysqli_query($con, 'SELECT * FROM dbclient ORDER BY date DESC, id DESC');
    if ($clientResult) {
        while ($row = mysqli_fetch_assoc($clientResult)) {
            if ($latestClientDate === null) $latestClientDate = $row['date'];
            if ($row['date'] !== $latestClientDate) break;
            $latestClients[] = new Client($row['id'], $row['shoppingEventId'], $row['personId'], $row['numClients'], $row['date']);
        }
    }
    mysqli_close($con);

    // Compute consumption rates
    $consumptionRateRows = array();
    $clientsPerDay       = null;

    if (!empty($latestClients) && $latestDistribution !== null && $latestDistribution->getDistributionDays() > 0) {
        $totalClients = 0;
        foreach ($latestClients as $c) { $totalClients += $c->getNumClients(); }
        $clientsPerDay = round($totalClients / $latestDistribution->getDistributionDays(), 0);

        if ($clientsPerDay > 0) {
            foreach ($latestClients as $c) {
                $seId       = $c->getShoppingEventId();
                $familySize = isset($shoppingEventFamilyMap[$seId]) ? $shoppingEventFamilyMap[$seId] : 'Unknown';
                $counts     = get_shoppingCounts_by_shoppingEvent($seId);
                foreach ($counts as $sc) {
                    $catId = $sc->getItemCategory();
                    $qty   = $sc->getQuantity();
                    $rate  = round(($c->getNumClients() / $clientsPerDay) * $qty, 2);
                    $consumptionRateRows[] = array(
                        'shoppingEventId' => $seId,
                        'itemCategoryId'  => $catId,
                        'itemName'        => isset($categoryMap[$catId]) ? $categoryMap[$catId] : 'Unknown',
                        'familySize'      => $familySize,
                        'clientsInGroup'  => $c->getNumClients(),
                        'clientsPerDay'   => $clientsPerDay,
                        'itemsPerCart'    => $qty,
                        'consumptionRate' => $rate,
                        'date'            => $c->getDate()
                    );
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Shopping List | Whiskey Valor Foundation</title>
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
        .data-entry-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            max-width: 500px;
        }
        .data-entry-row  { display: flex; align-items: center; gap: 1rem; }
        .data-entry-label {
            color: var(--page-font-color);
            width: 160px;
            flex-shrink: 0;
            font-weight: 500;
        }
        .data-entry-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            flex: 1;
        }
        .feedback-msg    { display: inline-block; margin-left: 1rem; font-size: 0.9rem; font-weight: 500; }
        .feedback-success { color: rgb(34, 197, 94); }
        .feedback-error   { color: rgb(239, 68, 68); }
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
                                    <th style="width: 36px;"></th>
                                    <th style="width: 50px;">#</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="basketTbody">
                                <?php if (!empty($basketItems)): ?>
                                    <?php foreach ($basketItems as $i => $item): ?>
                                        <tr draggable="true" data-count-id="<?= $item['id'] ?>">
                                            <td class="drag-handle" title="Drag to reorder">&#8597;</td>
                                            <td class="row-number"><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                            <td><input type="number" class="basket-qty-input" value="<?= htmlspecialchars($item['quantity']) ?>" min="0"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">No items found for this family size.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Monthly Client Count -->
            <div class="report-section">
                <h2>Monthly Client Count</h2>
                <p style="color: var(--page-font-color); margin-bottom: 1rem;">Record the monthly number of clients for a family size.</p>

                <div class="data-entry-grid">
                    <div class="data-entry-row">
                        <label class="data-entry-label" for="clientFamilySize">Family Size:</label>
                        <select id="clientFamilySize" class="data-entry-input select">
                            <option value="">-- Select Family Size --</option>
                            <?php foreach ($familySizes as $fs): ?>
                                <option value="<?= htmlspecialchars($fs) ?>"><?= htmlspecialchars($fs) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="data-entry-row">
                        <label class="data-entry-label" for="clientDate">Month:</label>
                        <input type="date" id="clientDate" class="data-entry-input">
                    </div>
                    <div class="data-entry-row">
                        <label class="data-entry-label" for="numClients">Number of Clients:</label>
                        <input type="number" id="numClients" class="data-entry-input" min="0" step="0.01" placeholder="e.g. 50">
                    </div>
                </div>
                <button class="generate-btn" id="saveClientBtn">Save Client Count</button>
                <span id="clientFeedback" class="feedback-msg"></span>
            </div>

            <!-- Distribution Days -->
            <div class="report-section">
                <h2>Distribution Days</h2>
                <p style="color: var(--page-font-color); margin-bottom: 1rem;">Record the number of distribution days for a specific date.</p>

                <div class="data-entry-grid">
                    <div class="data-entry-row">
                        <label class="data-entry-label" for="distDate">Date:</label>
                        <input type="date" id="distDate" class="data-entry-input">
                    </div>
                    <div class="data-entry-row">
                        <label class="data-entry-label" for="distributionDays">Distribution Days:</label>
                        <input type="number" id="distributionDays" class="data-entry-input" min="1" step="1" placeholder="e.g. 5">
                    </div>
                </div>
                <button class="generate-btn" id="saveDistributionBtn">Save Distribution Days</button>
                <span id="distributionFeedback" class="feedback-msg"></span>
            </div>

            <!-- Consumption Rate -->
            <div class="report-section">
                <h2>Consumption Rate</h2>
                <p style="color: var(--page-font-color); margin-bottom: 0.5rem;">
                    Calculated from the most recent client counts and distribution days on record.<br>
                    <strong>Clients/Day</strong> = Total Clients &divide; Distribution Days &nbsp;&nbsp;
                    <strong>Items/Day</strong> = (Group Clients &divide; Clients/Day) &times; Items per Cart
                </p>

                <?php if ($latestDistribution === null || empty($latestClients)): ?>
                    <p class="empty-state" style="text-align:left; padding: 1.5rem 0;">
                        No data yet. Enter client counts and distribution days above, then return here to view rates.
                    </p>
                <?php else: ?>
                    <div style="display:flex; gap:2rem; margin-bottom:1.25rem; flex-wrap:wrap;">
                        <span style="color:var(--page-font-color);">
                            <strong>Distribution Days:</strong> <?= htmlspecialchars($latestDistribution->getDistributionDays()) ?>
                            &nbsp;(<?= htmlspecialchars($latestDistribution->getDate()) ?>)
                        </span>
                        <span style="color:var(--page-font-color);">
                            <strong>Total Clients:</strong>
                            <?= htmlspecialchars(array_sum(array_map(function($c){ return $c->getNumClients(); }, $latestClients))) ?>
                            &nbsp;(<?= htmlspecialchars($latestClientDate) ?>)
                        </span>
                        <span style="color:var(--page-font-color);">
                            <strong>Clients/Day:</strong> <?= htmlspecialchars($clientsPerDay) ?>
                        </span>
                    </div>

                    <?php if (empty($consumptionRateRows)): ?>
                        <p class="empty-state" style="text-align:left; padding:1rem 0;">
                            No shopping list baskets found for the client records. Make sure shopping events exist for those dates.
                        </p>
                    <?php else: ?>
                        <?php $uniqueConsumptionFamilySizes = array_values(array_unique(array_column($consumptionRateRows, 'familySize'))); ?>
                        <div class="week-selector" style="margin-bottom: 1rem;">
                            <label for="consumptionFamilyFilter" style="color: var(--page-font-color); font-weight: 500;">Family Size:</label>
                            <select id="consumptionFamilyFilter" class="select" style="padding: 0.5rem 0.75rem; border: 1px solid var(--shadow-and-border-color); border-radius: 0.25rem; background-color: rgba(0,0,0,0.2); color: var(--page-font-color); cursor: pointer; min-width: 180px;">
                                <option value="">All Family Sizes</option>
                                <?php foreach ($uniqueConsumptionFamilySizes as $fs): ?>
                                    <option value="<?= htmlspecialchars($fs) ?>"><?= htmlspecialchars($fs) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="table-wrapper">
                            <table class="report-table" id="consumptionRateTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Family Size</th>
                                        <th>Item Name</th>
                                        <th>Group Clients</th>
                                        <th>Clients/Day</th>
                                        <th>Items per Cart</th>
                                        <th>Items/Day (Consumption Rate)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($consumptionRateRows as $i => $row): ?>
                                        <tr data-family-size="<?= htmlspecialchars($row['familySize']) ?>">
                                            <td class="row-number"><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($row['familySize']) ?></td>
                                            <td><?= htmlspecialchars($row['itemName']) ?></td>
                                            <td><?= htmlspecialchars($row['clientsInGroup']) ?></td>
                                            <td><?= htmlspecialchars($row['clientsPerDay']) ?></td>
                                            <td><?= htmlspecialchars($row['itemsPerCart']) ?></td>
                                            <td><strong><?= htmlspecialchars($row['consumptionRate']) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="display:flex; gap:0.75rem; margin-top:1.25rem; flex-wrap:wrap; align-items:center;">
                            <button class="generate-btn" id="saveConsumptionBtn">Save Consumption Rates to Database</button>
                            <span id="consumptionFeedback" class="feedback-msg"></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        $(function() {
            // ---- Basket drag-and-drop ----
            var basketTbody = document.getElementById('basketTbody');
            if (basketTbody) {
                var dragSrc = null;

                basketTbody.addEventListener('dragstart', function(e) {
                    dragSrc = e.target.closest('tr');
                    dragSrc.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                basketTbody.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    var target = e.target.closest('tr');
                    if (target && target !== dragSrc) {
                        basketTbody.querySelectorAll('tr').forEach(function(r) {
                            r.classList.remove('drag-over-top', 'drag-over-bottom');
                        });
                        var rect = target.getBoundingClientRect();
                        if (e.clientY < rect.top + rect.height / 2) {
                            target.classList.add('drag-over-top');
                        } else {
                            target.classList.add('drag-over-bottom');
                        }
                    }
                });
                basketTbody.addEventListener('dragleave', function(e) {
                    var target = e.target.closest('tr');
                    if (target) target.classList.remove('drag-over-top', 'drag-over-bottom');
                });
                basketTbody.addEventListener('drop', function(e) {
                    e.preventDefault();
                    var target = e.target.closest('tr');
                    if (target && target !== dragSrc) {
                        var rect = target.getBoundingClientRect();
                        if (e.clientY < rect.top + rect.height / 2) {
                            basketTbody.insertBefore(dragSrc, target);
                        } else {
                            basketTbody.insertBefore(dragSrc, target.nextSibling);
                        }
                        target.classList.remove('drag-over-top', 'drag-over-bottom');
                        basketTbody.querySelectorAll('tr').forEach(function(r, i) {
                            var cell = r.querySelector('.row-number');
                            if (cell) cell.textContent = i + 1;
                        });
                    }
                });
                basketTbody.addEventListener('dragend', function(e) {
                    if (dragSrc) dragSrc.classList.remove('dragging');
                    basketTbody.querySelectorAll('tr').forEach(function(r) {
                        r.classList.remove('drag-over-top', 'drag-over-bottom');
                    });
                });
            }

            // ---- Save basket quantities ----
            $('#saveQuantitiesBtn').on('click', function() {
                var btn = $(this);
                var requests = [];
                $('#basketTbody tr').each(function() {
                    var row = $(this);
                    var countId  = row.data('count-id');
                    var quantity = parseInt(row.find('.basket-qty-input').val(), 10);
                    if (!countId || isNaN(quantity) || quantity < 0) return;
                    requests.push($.post('viewShoppingList.php', { action: 'updateQty', id: countId, quantity: quantity }));
                });
                $.when.apply($, requests).done(function() {
                    btn.text('Saved!');
                    setTimeout(function() { btn.text('Save Quantities'); }, 2000);
                });
            });

            // ---- Generate PDF ----
            var pdfBtn = document.getElementById('generatePdfBtn');
            if (pdfBtn) {
                pdfBtn.addEventListener('click', function() {
                    var { jsPDF } = window.jspdf;
                    var doc = new jsPDF();
                    var familySize = '<?= htmlspecialchars($selectedFamilySize ?? '') ?>';
                    var today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    doc.setFontSize(18);
                    doc.setTextColor(40, 40, 40);
                    doc.text('Shopping List', 14, 20);
                    doc.setFontSize(11);
                    doc.setTextColor(100, 100, 100);
                    doc.text('Family Size: ' + familySize, 14, 29);
                    doc.text('Date: ' + today, 14, 36);

                    var rows = [];
                    document.querySelectorAll('#basketTbody tr').forEach(function(tr, i) {
                        var cells = tr.querySelectorAll('td');
                        if (cells.length < 4) return;
                        var itemName = cells[2].textContent.trim();
                        var qtyInput = cells[3].querySelector('input');
                        var qty = qtyInput ? qtyInput.value : cells[3].textContent.trim();
                        rows.push([(i + 1).toString(), itemName, qty]);
                    });

                    doc.autoTable({
                        startY: 44,
                        head: [['#', 'Item Name', 'Quantity']],
                        body: rows,
                        headStyles: { fillColor: [44, 62, 80], textColor: 255, fontStyle: 'bold' },
                        alternateRowStyles: { fillColor: [245, 245, 245] },
                        columnStyles: { 0: { cellWidth: 12, halign: 'center' }, 2: { cellWidth: 30, halign: 'center' } },
                        styles: { fontSize: 11 },
                        margin: { left: 14, right: 14 }
                    });

                    doc.save('shopping-list-' + familySize.replace(/[^a-z0-9]/gi, '-') + '.pdf');
                });
            }

            // ---- Save Monthly Client Count ----
            $('#saveClientBtn').on('click', function() {
                var btn = $(this);
                var familySize = $('#clientFamilySize').val();
                var date       = $('#clientDate').val();
                var numClients = $('#numClients').val();
                var $feedback  = $('#clientFeedback');

                if (!familySize || !date || numClients === '') {
                    $feedback.removeClass('feedback-success').addClass('feedback-error').text('Please fill in all fields.');
                    return;
                }
                btn.prop('disabled', true).text('Saving...');
                $feedback.removeClass('feedback-success feedback-error').text('');

                $.post('viewShoppingList.php', {
                    action: 'saveClient', familySize: familySize, date: date, numClients: numClients
                }, function(data) {
                    if (data.success) {
                        $feedback.removeClass('feedback-error').addClass('feedback-success').text('Saved successfully!');
                        $('#numClients').val('');
                    } else {
                        $feedback.removeClass('feedback-success').addClass('feedback-error').text(data.error || 'Save failed.');
                    }
                    btn.prop('disabled', false).text('Save Client Count');
                    setTimeout(function() { $feedback.text(''); }, 4000);
                }, 'json').fail(function() {
                    $feedback.removeClass('feedback-success').addClass('feedback-error').text('Server error. Please try again.');
                    btn.prop('disabled', false).text('Save Client Count');
                });
            });

            // ---- Save Distribution Days ----
            $('#saveDistributionBtn').on('click', function() {
                var btn = $(this);
                var date             = $('#distDate').val();
                var distributionDays = $('#distributionDays').val();
                var $feedback        = $('#distributionFeedback');

                if (!date || !distributionDays) {
                    $feedback.removeClass('feedback-success').addClass('feedback-error').text('Please fill in all fields.');
                    return;
                }
                btn.prop('disabled', true).text('Saving...');
                $feedback.removeClass('feedback-success feedback-error').text('');

                $.post('viewShoppingList.php', {
                    action: 'saveDistribution', date: date, distributionDays: distributionDays
                }, function(data) {
                    if (data.success) {
                        $feedback.removeClass('feedback-error').addClass('feedback-success').text('Saved successfully!');
                        $('#distributionDays').val('');
                    } else {
                        $feedback.removeClass('feedback-success').addClass('feedback-error').text(data.error || 'Save failed.');
                    }
                    btn.prop('disabled', false).text('Save Distribution Days');
                    setTimeout(function() { $feedback.text(''); }, 4000);
                }, 'json').fail(function() {
                    $feedback.removeClass('feedback-success').addClass('feedback-error').text('Server error. Please try again.');
                    btn.prop('disabled', false).text('Save Distribution Days');
                });
            });

            // ---- Consumption Rate family size filter ----
            $('#consumptionFamilyFilter').on('change', function() {
                var selected     = $(this).val();
                var visibleIndex = 1;
                $('#consumptionRateTable tbody tr').each(function() {
                    var rowFamily = $(this).data('family-size');
                    if (!selected || rowFamily === selected) {
                        $(this).show();
                        $(this).find('.row-number').text(visibleIndex++);
                    } else {
                        $(this).hide();
                    }
                });
            });

            // ---- Save Consumption Rates ----
            var consumptionRecords = <?= json_encode(array_map(function($r) {
                return [
                    'shoppingEventId' => $r['shoppingEventId'],
                    'itemCategoryId'  => $r['itemCategoryId'],
                    'consumptionRate' => $r['consumptionRate'],
                    'date'            => $r['date']
                ];
            }, $consumptionRateRows)) ?>;

            $('#saveConsumptionBtn').on('click', function() {
                var btn      = $(this);
                var $feedback = $('#consumptionFeedback');
                if (!consumptionRecords || consumptionRecords.length === 0) {
                    $feedback.removeClass('feedback-success').addClass('feedback-error').text('No rates to save.');
                    return;
                }
                btn.prop('disabled', true).text('Saving...');
                $feedback.removeClass('feedback-success feedback-error').text('');

                $.post('viewShoppingList.php', {
                    action: 'saveConsumption', records: JSON.stringify(consumptionRecords)
                }, function(data) {
                    if (data.success) {
                        $feedback.removeClass('feedback-error').addClass('feedback-success')
                            .text('Saved ' + data.saved + ' record(s) successfully!');
                    } else {
                        $feedback.removeClass('feedback-success').addClass('feedback-error')
                            .text(data.error || 'Save failed.');
                    }
                    btn.prop('disabled', false).text('Save Consumption Rates to Database');
                    setTimeout(function() { $feedback.text(''); }, 5000);
                }, 'json').fail(function() {
                    $feedback.removeClass('feedback-success').addClass('feedback-error').text('Server error. Please try again.');
                    btn.prop('disabled', false).text('Save Consumption Rates to Database');
                });
            });
        });
    </script>
</body>
</html>
