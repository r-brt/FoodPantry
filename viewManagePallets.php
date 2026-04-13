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

?>
    
<!DOCTYPE html>
<html>
<head>
    <?php require_once('universal.inc') ?>
    <title>Manage Pallets | CCDA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .pallet-table-header {
            display: flex;
            align-items: center;
            flex-direction: row;
            justify-content: space-between;
            gap: 1rem;
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
            margin-bottom: 1rem;
        }
        .modify-btn:hover {
            opacity: 0.85;
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
        .modify-activate-btn:hover,
        .modify-deactivate-btn:hover,
        .generate-btn:hover {
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
        .pallet-linkBtns{
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
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
<pageheader>
    <h1 class="title">Manage Pallets</h1>
</pageheader>
<body>
    <?php require_once('header.php'); ?>
    <main>
        
        <div class="report-container">
                <a href="viewUpdateInventory.php" class="back-btn">← Back</a>
                <?php 
                /* display table for each pallet */
                foreach(get_all_palletEvents() as $pallet){
                    $palletCounts = get_palletCounts_by_palletEvent($pallet->getId());
                    echo '
                    <div class="report-section">
                    <div class="pallet-table-header">
                        <h2>'.$pallet->getName().' </h2>
                        <td><a href="viewModifyPallet.php?id=' . $pallet->getId() . '" class="text-blue-700 underline"><button class="modify-btn">Modify</button></a>
                    </div>
                        <div class="table-wrapper">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Boxes</th>
                                        <th>Banana Box</th>
                                        <th>Items Per Box</th>
                                    </tr>
                                </thead>
                                <tbody> ';
                                foreach($palletCounts as $count){
                                    $category = retrieve_ItemCategory($count->getItemCategory());
                                    echo '
                                        <tr>
                                            <td>' . $category->getName() . '</td>
                                            <td>' . $count->getQuantity() . '</td>
                                            <td style="text-align: center;">';
                                                if($category->getBananaBox() == 1){
                                                    echo '✓';
                                                }
                                    echo '
                                            </td>
                                            <td style="text-align: center;">'.$category->getItemsPerBox().'</td>
                                        </tr>';
                                }
                                if(count($palletCounts) == 0){
                                    echo '
                                    <tr>
                                        <td colspan="4" class="empty-state">Empty Pallet</td>
                                    </tr>';
                                }

                    echo '
                                </tbody>
                            </table>
                        </div>
                    </div>';
                    }?>

            <a href="viewAddPallet.php" class="pallet-linkBtns"><button name="add_button" class="modify-save-btn">Add New Pallet</button></a>
            <a href="viewUpdateInventory.php" class="pallet-linkBtns"><button name="cancel_button" class="modify-cancel-btn">Back</button></a>

                        
        </div>
         
    </main>

</body>
</html>
