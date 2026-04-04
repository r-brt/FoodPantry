<?php
    session_cache_expire(30);
    session_start();

    $loggedIn = false;
    $accessLevel = 0;
    $userID = null;
    $personId = null;
    if (isset($_SESSION['_id'])) {
        $loggedIn = true;
        $accessLevel = $_SESSION['access_level'];
        $userID = $_SESSION['_id'];
        $personId = $_SESSION['_personId'];
    }

    // Add database includes here

    require_once('database/dbinfo.php');
    require_once('database/dbPersons.php');
    require_once('database/dbPalletEvent.php');
    require_once('database/dbItemCategory.php');
    require_once('database/dbPalletCounts.php');

    /* 
    * _POST is empty when the page is first loaded.
    *  Submitting the form on this page reloads the page with data in _POST
    *  if _POST is not empty, process data from form
    */
    $submit_success = false;
    $errors = [];
    if (!empty($_POST)) {
        $updatedItems = array();
        foreach($_POST as $cat => $value){
            if($cat == "name"){
                $name = $value;
                if($name == "Pallet"){
                    $name = "PALLET_PLACEHOLDER_NAME";
                }
                else if(!pallet_name_unique($name)){
                    $errors[] = "Pallet name already exists";
                    $updatedItems = array();
                    break;
                }
                continue;
            }
            /* only add items that have values to array */
            if(!empty($value)){

                /* try to convert value to a number. If it cannot convert, leave it as a string */
                try{
                    $value = +$value;
                }
                catch(TypeError  $e){ 
                    $value = " ";
                }

                /* if error is found, empty the array of items and stop checking */
                if(!is_int($value)){
                    $errors[] = 'Quantities must be in whole numbers';
                    $updatedItems = array();
                    break;
                }
                else if($value < 0){
                    $errors[] = 'Quantities must be greater than 0';
                    $updatedItems = array();
                    break;
                }
                /* accept items with 0 or greater quantity */
                else if($value >= 0){
                    $updatedItems[$cat] = $value;
                }
            }
        }

        /* if at least 1 item was updated, create inventory event and add items to database */
        if(count($updatedItems) > 0){
            $palletEventId = add_palletEvent($name, $personId);
            if($name == "PALLET_PLACEHOLDER_NAME"){
                $name = "Pallet " . $palletEventId;
                update_palletEvent_name($palletEventId, $name);
            }
            foreach($updatedItems as $categoryId => $quantity){
                add_palletCount($palletEventId, $categoryId, $quantity);
            }

            $submit_success = true;
        } 
        else{
            /* if errors have already been detected array was emptied. Do no show error for missing data */
            if(empty($errors)){
                $errors[] = 'Enter quantity for at least 1 item';
            }
        }
    }    

?>
    
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Add Pallet | CCDA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .updateInv-optionRow {
            display: flex;
            align-items: center;
            flex-direction: row;
            justify-content: left;
            gap: 1rem;
        }
        .updateInv-option {
            display: flex;
            align-items: center;
            flex-direction: row;
            width: 45%;
            gap: 1rem;
        }
        .updateInv-optionLabel {
            text-align: right;
        }
        .updateInv-allRows {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding: 2rem 1rem;
        }
        .updateInv-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        .updateInv-label {
            color: var(--page-font-color);
            width: 200px;
            max-width: 400px;
            min-width: 6rem;
            flex-grow: 1;
            flex-grow: 1;
            text-align: right;
            padding: 0rem  .5rem 0rem 0rem;
        }
        .updateInv-qty {
            width: 100px;
            max-width: 100px;
            margin-bottom: 0rem !important;
            padding: 0.4rem 0.6rem !important;
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
            .updateInv-optionRow {
                display: flex;
                align-items: right;
                flex-direction: column;
                justify-content: left;
                gap: 1rem;
            }
            .updateInv-option {
                display: flex;
                align-items: center;
                flex-direction: row;
                width: auto;
                gap: 1rem;
            }
            .updateInv-qty {
                max-width: 7rem;
                margin-right: 10%;
            }
        }
    </style>
</head>
<body>
    <?php require_once('header.php') ?>
    <main>
        <div class="report-container">
            <h1 class="title">Add New Pallet</h1>
            <?php 
                /* Display success message after submitting pallet */
                if($submit_success == true){
                    echo("<h4 style=\"color:black;\"><i>Pallet Saved: ".$name."</i></h4>");
                }
                /* Display errors from submitting pallet */
                if (!empty($errors)): ?>
                <ul>
                    <?php foreach($errors AS $error): ?>
                        <li><?php echo("<h4 style=\"color:red;\"><i>".$error."</i></h4>"); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Add Pallet -->
            <div class="report-section">
                <h2>Pallet Input</h2>              
                <form name="palletForm" method="POST" action="viewAddPallet.php">
                    <div class="updateInv-optionRow">
                        <div class="updateInv-option">
                            <label class="updateInv-optionLabel" for="name">Pallet Name:</label>
                            <input type="text" class="updateInv-qty" min="0" placeholder="Qty" 
                                            value="Pallet"
                                            name="name" 
                                            id="name">
                        </div>
                    </div>
                        <div class="table-wrapper">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Boxes</th>
                                        <th>Banana Box</th>
                                        <th>Items Per Box</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                        $categories = get_all_ItemCategory();
                        foreach($categories AS $category): ?>
                            <tr>
                                <div class="updateInv-row">
                                    <td><label class="updateInv-label" 
                                            for="qty_<?php echo($category->getId())?>">
                                            <?php echo($category->getName());?>
                                    </label></td>
                                    <td><input type="number" class="updateInv-qty" min="0" placeholder="Qty" 
                                            value="<?php if (!empty($errors)) echo($_POST[$category->getId()]);?>"
                                            name="<?php echo($category->getId())?>" 
                                            id="qty_<?php echo($category->getId())?>"></td>
                                    <td style="text-align: center;"><?= $category->getBananaBox() == 1 ? '✓' : '' ?></td>
                                    <td style="text-align: center;"><?php echo($category->getItemsPerBox())?></td>
                                </div>
                            </tr>  
                        <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <input type="submit" value="Add New Pallet" />
                </form>
            </div>

        </div>
    </main>
    

</body>
</html>
