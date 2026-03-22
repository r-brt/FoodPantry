
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

    require_once('database/dbinfo.php');
    require_once('database/dbPersons.php');
    require_once('database/dbInventoryEvent.php');
    require_once('database/dbItemCategory.php');
    require_once('database/dbItemCounts.php');
    
    $con = connect();

    /* 
    * _POST is empty when the page is first loaded.
    *  Submitting the form on this page reloads the page with data in _POST
    *  if _POST is not empty, process data from form
    */
    $submit_success = false;
    $errors = [];
    if (!empty($_POST)) {
        $updatedItems = array();
        foreach($_POST as $name => $value){
            if($name == "location"){
                $location = $value;
                if($location != "Warehouse" && $location != "Pantry"){
                    $errors[] = 'Invalid Location';
                }               
            }
            else if($name == "date"){
                $date = $value;
            }
            else{
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
                    /* do not add items that have 0 quantity */
                    else if($value > 0){
                        $updatedItems[$name] = $value;
                    }
                }
            }
        }
        
        /* if at least 1 item was updated, create inventory event and add items to database */
        if(count($updatedItems) > 0){
            $personId = retrieve_person($userID)->get_personId();
            $inventoryEventId = add_inventoryEvent($personId, $location, $date);
            foreach($updatedItems as $categoryId => $quantity){
                add_itemCount($inventoryEventId, $categoryId, $quantity);
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

    /* get the previous inventory event pair (warehouse and pantry) before the today's date */
    $previous_event_pair = get_previous_inventoryEvent_pair(new InventoryEvent(0, 0, 0, date('Y-m-d')));

    /* if previous inventory was found, get item counts for the pair (warehouse and pantry) */
    $prev_item_counts = array();
    if($previous_event_pair[0]){
        $prev_item_counts = get_itemCounts_by_inventoryEvent($previous_event_pair[0]->getId());
    }
    /* its possible there is only 1 event in the pair. For example: The pantry may not have had anything this week */
    if($previous_event_pair[1]){
        $prev_item_counts = array_merge($prev_item_counts, get_itemCounts_by_inventoryEvent($previous_event_pair[1]->getId()));
    }

    /* get the total for each category (warehouse + pantry = total) */
    $prev_totals = array();
    foreach($prev_item_counts as $item){
        if(isset($prev_totals[$item->getItemCategory()]))
            $prev_totals[$item->getItemCategory()] += $item->getQuantity();
        else
            $prev_totals[$item->getItemCategory()] = $item->getQuantity();
    }
    

?>
    
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Update Inventory | CCDA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h1 style="color:black;">Update Inventory</h1>
            <?php 
                /* Display success message after submitting inventory */
                if($submit_success == true){
                    echo("<h4 style=\"color:black;\"><i>Inventory Submitted: ".date("F jS, Y", strtotime($date))."  -  ".$location."</i></h4>");
                }
                /* Display errors from submitting inventory */
                if (!empty($errors)): ?>
                <ul>
                    <?php foreach($errors AS $error): ?>
                        <li><?php echo("<h4 style=\"color:red;\"><i>".$error."</i></h4>"); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Update Inventory -->
            <div class="report-section">
                <h2>Inventory Input</h2>              
                <form name="invForm" onsubmit="return validateFormDate()" method="POST" action="viewUpdateInventory.php">
                    <div class="updateInv-optionRow">
                        <div class="updateInv-option">
                            <label class="updateInv-optionLabel" for="location">Choose a Location:</label>
                            <select name="location" id="location">
                                <option value="Pantry">Pantry</option>
                                <option value="Warehouse" <?php if (!empty($_POST) && $_POST['location'] == "Warehouse") echo("selected");?>>Warehouse</option>
                            </select>
                        </div>
                        <div class="updateInv-option">
                            <label class="updateInv-optionLabel" for="date">Inventory Date:</label>
                            <input type="date" name="date" id="date" 
                                value="<?php if (!empty($errors)) echo($_POST['date']); else echo date('Y-m-d');?>">
                        </div>
                    </div>
                        <div class="table-wrapper">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>Quantity</th>
                                        <th>Previous Total<br>
                                            <?php if($previous_event_pair[0])
                                                    echo(date("m/d/Y", strtotime($previous_event_pair[0]->getDate())))?>
                                        </th>
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
                                    <td><?php if(isset($prev_totals[$category->getId()]))
                                                    echo($prev_totals[$category->getId()])?></td>
                                    <td style="text-align: center;"><?= $category->getBananaBox() == 1 ? '✓' : '' ?></td>
                                    <td style="text-align: center;"><?php echo($category->getItemsPerBox())?></td>
                                </div>
                            </tr>  
                        <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <input type="submit" value="Submit Inventory" />
                </form>
                <script>
                    /* if form Date is in the past or the future, confirm before submitting form */
                    function validateFormDate() {
                        const [formYear,formMonth,formDay] = document.forms["invForm"]["date"].value.split("-");
                        /* check for invalid input */
                        if(formYear == null || formMonth == null || formDay == null){
                            alert("Invalid Date");
                            return false;
                        }
                        /* check for invalid date */
                        else if(isNaN(new Date(document.forms["invForm"]["date"].value))){
                            alert("Invalid Date");
                            return false;
                        }

                        let currDate = new Date();
                        let compareDates = 0;
                        if(formYear==currDate.getFullYear()){
                            if(formMonth == currDate.getMonth()+1){
                                compareDates = formDay-currDate.getDate();
                            }
                            else{
                                compareDates = formMonth-(currDate.getMonth()+1);
                            }
                        }
                        else{
                            compareDates = formYear-currDate.getFullYear();
                        }

                        if(compareDates == 0){
                            return true;
                        }
                        else if(compareDates < 0){
                            return confirm("PAST DATE: "+formMonth+"/"+formDay+"/"+formYear+
                                            "\nAre you sure you want to submit?");
                        }
                        else if(compareDates > 0){
                            return confirm("FUTURE DATE: "+formMonth+"/"+formDay+"/"+formYear+
                                            "\nAre you sure you want to submit?");
                        }                      
                    }
                </script>
            </div>

        </div>
    </main>
    

</body>
</html>
