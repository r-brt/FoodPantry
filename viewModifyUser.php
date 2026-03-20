
<?php
    session_cache_expire(30);
    session_start();
    ini_set("display_errors",1);
    error_reporting(E_ALL);
    $loggedIn = false;
    $accessLevel = 0;
    $userID = null;
    if (isset($_SESSION['_id'])) {
        $loggedIn = true;
        // 0 = not logged in, 1 = standard user, 2 = manager (Admin), 3 super admin (TBI)
        $accessLevel = $_SESSION['access_level'];
        $userID = $_SESSION['_id'];
    }

    // Was an ID supplied?
    if ($_SERVER["REQUEST_METHOD"] == "GET" && !isset($_GET['id'])) {
        header('Location: index.php');
        die();
    } 

    // Is user authorized to view this page?
    if ($accessLevel < 2) {
        header('Location: index.php');
        die();
    }

    // Does the person exist?
    require_once('database/dbPersons.php');
    $thePerson = retrieve_person_by_personId($_GET['id']);
    if (!$thePerson) {
        echo "That user does not exist";
        die();
    }
    
    // Is user authorized to view this page?
    if ($accessLevel < 2) {
        header('Location: index.php');
        die();
    }    

    /* 
    * _POST is empty when the page is first loaded.
    *  Submitting the form on this page reloads the page with data in _POST
    *  if _POST is not empty, process data from form
    */
    $submit_success = false;
    $errors = [];
    if (!empty($_POST)) {
        if(isset($_POST["cancel_button"])){
            header('Location: viewAuditUsers.php');
            die();
        }
        else if(isset($_POST["deactivate_button"])){
            deactivate_person($thePerson->get_personId());
            header('Location: viewAuditUsers.php');
            die();
        }
        else if(isset($_POST["activate_button"])){
            activate_person($thePerson->get_personId());
            header('Location: viewAuditUsers.php');
            die();
        }
        else if(isset($_POST["delete_button"])){
            delete_person($thePerson->get_personId());
            header('Location: viewAuditUsers.php');
            die();
        }
        else if(isset($_POST["save_button"])){
            if(update_person_by_personId(
                $thePerson->get_personId(),
                $_POST["id"],
                $_POST["fname"],
                $_POST["lname"],
                $_POST["email"],
                $_POST["role"]
            )){
                header('Location: viewAuditUsers.php');
                die();
            }
            else{
                if(retrieve_person($_POST["id"])){
                    $errors[] = "Username already exists";
                }
            }
        }
    }
?>
    
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Modify User | CCDA</title>
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
            max-width: 300px;
            margin-right: 30%;
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--shadow-and-border-color);
            border-radius: 0.25rem;
            background-color: rgba(0,0,0,0.2);
            color: var(--page-font-color);
            font-size: 0.9rem;
        }
        .modify-role-select {
            max-width: 300px;
            margin-right: 30%;
            padding: 0.4rem 0.6rem;
        }
        .modify-status-label {
            max-width: 300px;
            margin-right: 30%;
            padding: 0.4rem 0.6rem;
        }
        .modify-save-btn,
        .modify-cancel-btn,
        .modify-delete-btn,
        .modify-activate-btn,
        .modify-deactivate-btn {
            padding: 0.5rem 1.5rem;
            background-color: var(--accent-color);
            color: var(--button-font-color);
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            max-width: 500px;
        }
        .modify-delete-btn {
            background-color: darkred;
            color: var(--button-font-color);
        }
        .modify-deactivate-btn {
            color: red;
        }
        .modify-activate-btn {
            color: green;
        }
        .modify-delete-btn:hover{
            opacity: 0.75;
            background-color: darkred;
        }
        .modify-save-btn:hover,
        .modify-cancel-btn:hover,
        .modify-activate-btn:hover {
            opacity: 0.85;
        }
        .modifyUsers-formBtns{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
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
            <h1 style="color:black;">Modify User</h1>
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
                <h2>User: <?php echo $thePerson->get_id();?></h2>              
                <form name="invForm" onsubmit="return validateFormDate()" method="POST" action="viewModifyUser.php?id=<?php echo $thePerson->get_personId();?>">
                    <div class="updateInv-allRows">
                        <div class="updateInv-row">
                            <label class="updateInv-label" for="id">Username: </label>
                            <input type="text" class="updateInv-qty" 
                                value="<?php echo($thePerson->get_id())?>"
                                name="id" 
                                id="id">
                        </div>
                        <div class="updateInv-row">
                            <label class="updateInv-label" for="fname">First Name: </label>
                            <input type="text" class="updateInv-qty" 
                                value="<?php echo($thePerson->get_first_name())?>"
                                name="fname" 
                                id="fname">
                        </div>
                        <div class="updateInv-row">
                            <label class="updateInv-label" for="lname">Last Name: </label>
                            <input type="text" class="updateInv-qty" 
                                value="<?php echo($thePerson->get_last_name())?>"
                                name="lname" 
                                id="lname">
                        </div>
                        <div class="updateInv-row">
                            <label class="updateInv-label" for="email">Email: </label>
                            <input type="text" class="updateInv-qty" 
                                value="<?php echo($thePerson->get_email())?>"
                                name="email" 
                                id="email">
                        </div>
                        <div class="updateInv-row">
                            <label class="updateInv-label" for="role">Role: </label>
                            <select name="role" class="modify-role-select" id="role">
                                <option value="superadmin">superadmin</option>
                                <option value="admin"
                                    <?php if ($thePerson->get_type() == "admin") echo("selected");?>
                                    >admin</option>
                                <option value="inventory_counter" 
                                    <?php if ($thePerson->get_type() == "inventory_counter") echo("selected");?>
                                    >inventory_counter</option>
                            </select>
                        </div>
                        <div class="updateInv-row">
                            <label class="updateInv-label">Status: </label>
                            <?php
                                if($thePerson->get_status() == "Active"){
                                    echo '
                                        <label name="status_label" class="modify-status-label" style="color: green;font-weight: 500;">Active</label>
                                    ';
                                }
                                else if($thePerson->get_status() == "Inactive"){
                                    echo '
                                        <label name="status_label" class="modify-status-label" style="color: red;font-weight: 500;">Inactive</label>
                                    ';
                                }
                                else if($thePerson->get_status() == "Deleted"){
                                    echo '
                                        <label name="status_label" class="modify-status-label" style="color: black;font-weight: 500;">Deleted</label>
                                    ';
                                }
                            ?>
                        </div>
                    </div>
                    <div class="modifyUsers-formBtns">
                        <button name="save_button" class="modify-save-btn">Save Changes</button>
                        <button name="cancel_button" class="modify-cancel-btn">Cancel</button>
                        <hr>
                        <?php
                            if($thePerson->get_status() == "Active"){
                                echo '
                                    <button name="deactivate_button" class="modify-deactivate-btn">Deactivate</button>
                                ';
                            }
                            else {
                                echo '
                                    <button name="activate_button" class="modify-activate-btn">Activate</button>
                                ';
                            }
                        ?>
                        <hr>
                        <button name="delete_button" name="delete_button" class="modify-delete-btn" 
                            onclick="return confirm('Are you sure you want to\nDELETE USER: <?php echo $thePerson->get_id();?>?')"
                            >Delete User
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
    

</body>
</html>
