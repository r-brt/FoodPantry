<?php
session_cache_expire(30);
session_start();
ini_set("display_errors", 1);
error_reporting(E_ALL);
date_default_timezone_set("America/New_York");

// Ensure admin authentication
if (!isset($_SESSION['access_level']) || $_SESSION['access_level'] < 1) {
    header('Location: login.php');
    die();
}

// Get current fiscal year
$currentMonth = date("m");
$currentYear = date("Y");
$fiscalYearStart = ($currentMonth >= 10) ? $currentYear : $currentYear - 1;
$fiscalYearEnd = $fiscalYearStart + 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Whiskey Valor | Attendance Reports</title>
    <!--<script src="js/data-filters.js" defer></script>-->
    <link href="css/base.css" rel="stylesheet">
    <?php require_once('header.php'); ?>
</head>
<body>
    <?php require_once('database/dbInventoryEvent.php');?>
    <?php require_once('database/dbPersons.php');?>

    <!-- Hero Section with Title -->
        <div class="center-header">
            <h1 style="color:black;">Generate Inventory Report</h1>
        </div>
                <!-- Info Section -->
        <section class="section-box">
            <p style="margin-top: 1rem;text-align:center;">
                Use this tool to generate weekly reports on food inventory.
            </p>
        </section>

    <main>
        <?php require_once('database/dbInventoryEvent.php')?>

        <div class="main-content-box">
            <!--<div class="text-center">
                <p style="font-size: 18px; color: #c2c2c2ff; margin-top: 0.5rem; margin-bottom: 0.5rem;">Fiscal Year: <?= $fiscalYearStart ?> - <?= $fiscalYearEnd ?></p>
            </div>-->

            <form method="POST" action="processInventoryReport.php">
                <!-- Date Range -->
                <div style="margin-bottom: 1.5rem;">
                    <label for="week" style="font-weight: 600;">Select Week</label>
                    <input name="week" id="week" required>
                    <option value="">-- Select Week --</option>
                    <?php
                    require_once('database/dbinfo.php');
                    $conn = connect();

                    $result = $conn->query("
                        SELECT DISTINCT inventoryEventId
                        FROM dbitemcounts
                        ORDER BY inventoryEventId DESC
                    ");

                    while ($row = $result->fetch_assoc()) {
                        $week = htmlspecialchars($row['inventoryEventId']);
                        echo "<option value='$week'>$week</option>";
                    }
                        ?>
                    </select>
                </div>
                <!-- Format -->
                <div style="margin-bottom: 1.5rem; margin-top: 1.5rem;">
                    <label for="format" style="font-weight: 600;">File Format</label>
                    <select name="format" id="format">
                        <option value="excel">Excel (.xls)</option>
                        <option value="csv">CSV (.csv)</option>
                    </select>
                </div>

                <div style="text-align: center; margin-top: 2rem;">
                    <input type="hidden" value="<?php echo $_SESSION['_id']; ?>" name="admin" id="admin">
                    <input type="hidden" value="<?php echo date("d-M-Y H:i:s e") ?>" name="time" id="time">
                    <input type="submit" value="Generate Report" class="button generate-btn">
                </div>
            </form>

        <!-- Return Button -->
        </div>
        <div style="text-align: center; margin-top: 2rem;">
            <a href="index.php" class="button" style="display: inline-block; text-decoration: none; width: 41%;">Return to Dashboard</a>
        </div>

    </main>

</body>
</html>

