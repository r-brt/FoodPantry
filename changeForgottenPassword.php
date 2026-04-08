<?php

    session_start();
    ini_set("display_errors",1);
    error_reporting(E_ALL);

    require_once('include/input-validation.php');
    require_once('domain/Person.php');
    require_once('database/dbPersons.php');

    if (!isset($_GET['email'])) {
        die('Invalid Link');
    }
    $email = $_GET['email'];
    $user = retrieve_person_by_email($email);

    if(!$user) {
        die("invalid user");
    }

    $userID = $user->get_id();
    $error = "";
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {        
            if (!wereRequiredFieldsSubmitted($_POST, array('new-password', 'new-password-reenter'))) {
                echo "Args missing";
                die();
            }
            $newPassword = $_POST['new-password'];
            $reenteredPassword = $_POST['new-password-reenter'];
            $securePassword = isSecurePassword($newPassword);

            if ($newPassword !== $reenteredPassword) {
                $error = "Passwords you entered do not match";
            } elseif(!$securePassword) {
                $error = "Password needs to be at least 8 characters long, contain at least one number, one uppercase letter, and one lowercase letter!";

            }else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                change_password($userID, $hash);
                header('Location: index.php?pcSuccess');
                echo "Success";
                exit();
            }
        }
    

?>
<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="include/base.css">



        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="css/base.css" type="text/css" />
        

        <link rel="icon" type="image/x-icon" href="images/ccda-logo-white.svg">

        <title>CCDA | Change Password</title>
    </head>
    <body>
        <?php require_once('header.php') ?>

        <h1>Change Password</h1>
        <main class="login">
            <?php if (!empty($error)): ?>
                <p style="color:red;"><?php echo $error; ?></p>
            <?php endif; ?>
            <form id="password-change" method="post" >
                <label for="new-password">New Password</label>
                <input type="password" id="new-password" name="new-password" placeholder="Enter new password" required>
                <label for="new-password-reenter">Re-entered New Password</label>
                <input type="password" id="new-password-reenter" name="new-password-reenter" placeholder="Re-enter new password" required>
                <input type="submit" id="submit" name="submit" value="Change Password">
            </form>
        </main>
    </body>
</html>