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
    require_once('database/dbItemCategory.php');
    $con = connect();

    //New Categorys
        if (isset($_POST['add_category'])) {
            $cat_name = trim($_POST['cat_name']);
            $bananaBox = isset($_POST['bananaBox']) ? 1 : 0;
            $itemsPerBox = intval($_POST['itemsPerBox']);
            $status = "Active";
            var_dump($cat_name);

            if (retrieve_ItemCategory_by_name($cat_name)) {
                var_dump(retrieve_ItemCategoryStatus($cat_name));
                if(retrieve_ItemCategoryStatus($cat_name) == 'Deleted') {
                    var_dump(retrieve_ItemCategoryStatus($cat_name));
                    var_dump(retrieve_ItemID($cat_name));
                    activate_itemCategory(retrieve_ItemID($cat_name));
                } else {
                    $errors[] = "Category already exists";
                }
                
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
    <title>Add Item Category | CCDA</title>
    <style>
        pageheader {
            margin-top: 3rem;
            display: flex; justify-content: center; align-items: center;
            position: sticky;
            top: 1rem;
            z-index: 6;
        }
        .title {
            text-align: center;
            height: 3.5rem;
            width:auto;
            font-size: 2rem;
            font-weight: 600;
            color: var(--secondary-accent-color);
            padding-top: .4rem;
            border-radius: 10px;
            background-color: #ffffffee;
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
        @media only screen and (max-width: 768px) {
            pageheader {
                top: 100px;
            }
            .title {
                border-radius: 0;
                background-color: #ffffff;
                width: 100%;
            }
            .report-container {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<pageheader>
    <h1 class="title">Add Item Category</h1>
</pageheader>
<body>
    <?php require_once('header.php'); ?>
    <main>
        <div class="report-container">
            <a href="viewItemCategories.php" class="back-btn">← Back</a>

            <?php if (!empty($errors)): ?>
                <ul>
                    <?php foreach($errors AS $error): ?>
                        <li><?php echo("<h4 style=\"color:red;\"><i>".$error."</i></h4>"); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class ="report-section">
                <h2>Add New Item Category</h2>
                <form method="POST" action= "viewAddItemCategory.php">
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

        </div>
    </main>

</body>
</html>
