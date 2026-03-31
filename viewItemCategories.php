<?php
    session_cache_expire(30);
    session_start();

    $loggedIn = false;
    $accessLevel = 0;
    $userID = null;
    $errors = [];
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

    //New Categorys
        if (isset($_POST['add_category'])) {
            $cat_name = trim($_POST['cat_name']);
            $bananaBox = isset($_POST['bananaBox']) ? 1 : 0;
            $itemsPerBox = intval($_POST['itemsPerBox']);
            $status = "Active";

            if (retrieve_ItemCategory_by_name($cat_name)) {
                $errors[] = "Category already exists";
            } else {
                add_itemCategory($cat_name, $bananaBox, $itemsPerBox, $status);

                header("Location: viewItemCategories.php");
                exit();
            }
        }

?>
    
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>View Item Categories | CCDA</title>
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
            border-bottom: 1px solid var(--shadow-and-border-color);
            color: var(--page-font-color);
        }
        .report-table th {
            background-color: var(--main-color);
            color: var(--button-font-color);
            font-weight: 500;
            text-align:center;
        }
        .report-table td {
            text-align: left;
            border: 1px solid var(--shadow-and-border-color);
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
        .modify-btn {
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
        .modify-btn:hover {
            opacity: 0.85;
        }
        div.table-wrapper {
                overflow-x: auto;
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
        }
    </style>
</head>
<body>
    <?php require_once('header.php'); ?>
    <main>
        <div class="report-container">
            <h1 class="title">Item Categories</h1>

            <?php if (!empty($errors)): ?>
                <ul>
                    <?php foreach($errors AS $error): ?>
                        <li><?php echo("<h4 style=\"color:red;\"><i>".$error."</i></h4>"); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php 
                require_once('database/dbItemCategory.php');
                /* display table of Item Categories with a given status (Active/Inactive/Deleted) */
                $display_accounts_by_status = function($status, $accessLevel){
                    echo '
                    <div class="report-section">
                        <h2>'.$status.' Item Categories</h2>
                        <div class="table-wrapper">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Banana Box</th>
                                        <th>Items Per Box</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody> ';
                            
                                $num_active = 0;
                                $categories = get_all_ItemCategory();
                                foreach ($categories as $category) {
                                    if($category->getStatus() == $status){
                                        $num_active += 1;

                                        $name = $category->getName();
                                        $itemsPerBox = $category->getItemsPerBox();
                                        $bananaBox = $category->getBananaBox() == 1 ? "✓" : "";

                                        echo '
                                        <tr>
                                            <td>' . $name . '</td>
                                            <td style="text-align: center;">' . $bananaBox . '</td>
                                            <td>' . $itemsPerBox . '</td>';
                                        echo ' 
                                            <td><a href="viewModifyItemCategory.php?id=' . $category->getId() . '" class="text-blue-700 underline"><button class="modify-btn">Modify</button></a>
                                        </tr>';
                                    }
                                }
                                if($num_active == 0){
                                    echo '
                                    <tr>
                                        <td colspan="7" class="empty-state">No '.$status.' Categories</td>
                                    </tr>';
                                }

                    echo '
                                </tbody>
                            </table>
                        </div>
                    </div>';
                            }; ?>

                            <!-- Display Table of accounts for each Status -->
                            <?php 
                            $display_accounts_by_status("Active", $accessLevel);
                            $display_accounts_by_status("Inactive", $accessLevel);
                            /* Superadmin can see deleted accounts */
                            if($accessLevel >= 3){
                                $display_accounts_by_status("Deleted", $accessLevel);
                            }
                            ?>
                            <div class ="report-section">
                <h2>Add New Item Category</h2>
                <form method="POST" action= "viewItemCategories.php">
                    <div style="display:flex; flex-direction:column; gap:1rem; max-width:400px;">
                        <div>
                            <label>Category Name:</label><br>
                            <input type="text" name="cat_name" required>
                        </div>
                        <div>
                            <label>Items Per Box:</label><br>
                            <input type="number" name="itemsPerBox" min="0" value="0" required>
                        </div>
                        <div>
                            <label>
                                <input type="checkbox" name="bananaBox">
                                Banana Box
                            </label>
                        </div>
                        <div>
                            <input type="submit" name="add_category" value="Add Category" class="generate-btn">
                        </div>
                </form>
        </div>
        
                
    </main>

</body>
</html>
