
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
    if (!empty($_POST)) {
        $updatedItems = array();
        foreach($_POST as $name => $value){
            if($name == "location"){
                $location = $value;               
            }
            else if($name == "date"){
                $date = $value;
            }
            else{
                /* only add items that have updated values to array */
                if(!empty($value) && $value > 0){
                    $updatedItems[$name] = $value;
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
        .basket-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .basket-row {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .basket-label {
            color: var(--page-font-color);
            width: 160px;
            flex-shrink: 0;
        }
        .basket-qty {
            width: 100px;
            padding: 0.4rem 0.6rem;
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
            ?>

            <!-- Update Inventory -->
            <div class="report-section">
                <h2>Inventory Input</h2>              
                <form name="invForm" onsubmit="return validateFormDate()" method="POST" action="viewUpdateInventory.php">
                    <div class="basket-row">
                        <label for="date">Inventory Date:</label>
                        <input type="date" name="date" id="date" value="<?php echo date('Y-m-d');?>">
                        <label for="location">Choose a Location:</label>
                        <select name="location" id="location">
                            <option value="Pantry">Pantry</option>
                            <option value="Warehouse">Warehouse</option>
                        </select>
                    </div>
                    <div class="basket-options">
                        <?php 
                        $categories = get_all_ItemCategory();
                        foreach($categories AS $category): ?>
                            <div class="basket-row">
                                <label class="basket-label" 
                                        for="qty_<?php echo($category->getId())?>">
                                        <?php echo($category->getName());?>
                                </label>
                                <input type="number" class="basket-qty" min="0" placeholder="Qty" 
                                        name="<?php echo($category->getId())?>" 
                                        id="qty_<?php echo($category->getId())?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="submit" value="Submit Inventory" />
                </form>
                <script>
                    function validateFormDate() {
                        const [formYear,formMonth,formDay] = document.forms["invForm"]["date"].value.split("-");
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
