<?php

include_once('dbinfo.php');
include_once(dirname(__FILE__).'/../domain/Consumption.php');

/*
 * add a Consumption to dbcomsumption table: return id generated from sql autoincrement
 */

function add_consumption($shoppingEventId, $itemCategoryId, $itemsConsumed, $personId, $date) {
    $con = connect();
    mysqli_query($con, 'INSERT INTO dbcomsumption (shoppingEventId, itemCategoryId, itemsConsumed, personId, date) VALUES("' .
            $shoppingEventId . '","' .
            $itemCategoryId . '","' .
            $itemsConsumed . '","' .
            $personId . '","' .
            $date . '");');
    $id = mysqli_insert_id($con);
    mysqli_close($con);

    return $id;
}

/*
 * remove a Consumption from dbcomsumption table: if it does not exist, return false
 */

function delete_consumption($id) {
    $con = connect();
    $query = 'SELECT * FROM dbcomsumption WHERE id = "' . $id . '"';
    $result = mysqli_query($con, $query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'DELETE FROM dbcomsumption WHERE id = "' . $id . '"';
    mysqli_query($con, $query);

    mysqli_close($con);
    return true;
}

/*
 * get a Consumption from dbcomsumption table by id: return null if not found
 */

function get_consumption_by_id($id) {
    $con = connect();
    $query = 'SELECT * FROM dbcomsumption WHERE id = "' . $id . '"';
    $sql_result = mysqli_query($con, $query);
    if ($sql_result == null || mysqli_num_rows($sql_result) == 0) {
        mysqli_close($con);
        return null;
    }
    $row = mysqli_fetch_array($sql_result, MYSQLI_ASSOC);
    $consumption = new Consumption($row['id'], $row['shoppingEventId'], $row['itemCategoryId'], $row['itemsConsumed'], $row['personId'], $row['date']);
    mysqli_close($con);
    return $consumption;
}

/*
 * get all Consumptions from dbcomsumption table with a given shoppingEventId
 */

function get_consumptions_by_shoppingEvent($shoppingEventId) {
    $con = connect();
    $query = 'SELECT * FROM dbcomsumption WHERE shoppingEventId = "' . $shoppingEventId . '"';
    $sql_result = mysqli_query($con, $query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $consumption_array = array();
    foreach ($array_result as $row) {
        $consumption_array[] = new Consumption($row['id'], $row['shoppingEventId'], $row['itemCategoryId'], $row['itemsConsumed'], $row['personId'], $row['date']);
    }
    mysqli_close($con);
    return $consumption_array;
}

/*
 * get all Consumptions from dbcomsumption table with a given itemCategoryId
 */

function get_consumptions_by_itemCategory($itemCategoryId) {
    $con = connect();
    $query = 'SELECT * FROM dbcomsumption WHERE itemCategoryId = "' . $itemCategoryId . '"';
    $sql_result = mysqli_query($con, $query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $consumption_array = array();
    foreach ($array_result as $row) {
        $consumption_array[] = new Consumption($row['id'], $row['shoppingEventId'], $row['itemCategoryId'], $row['itemsConsumed'], $row['personId'], $row['date']);
    }
    mysqli_close($con);
    return $consumption_array;
}

/*
 * get all Consumptions from dbcomsumption table with a given personId
 */

function get_consumptions_by_person($personId) {
    $con = connect();
    $query = 'SELECT * FROM dbcomsumption WHERE personId = "' . $personId . '"';
    $sql_result = mysqli_query($con, $query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $consumption_array = array();
    foreach ($array_result as $row) {
        $consumption_array[] = new Consumption($row['id'], $row['shoppingEventId'], $row['itemCategoryId'], $row['itemsConsumed'], $row['personId'], $row['date']);
    }
    mysqli_close($con);
    return $consumption_array;
}

/*
 * delete all Consumption rows for a given shoppingEventId + itemCategoryId pair
 */

function delete_consumption_by_event_and_category($shoppingEventId, $itemCategoryId) {
    $con = connect();
    mysqli_query($con, 'DELETE FROM dbcomsumption WHERE shoppingEventId = ' . (int)$shoppingEventId .
        ' AND itemCategoryId = ' . (int)$itemCategoryId);
    $deleted = mysqli_affected_rows($con);
    mysqli_close($con);
    return $deleted;
}

/*
 * delete all Consumption rows for an itemCategoryId across ALL shopping events and dates;
 * used when an item is removed from every basket so the weekly report shows N/A instead of a stale rate
 */

function delete_consumption_by_category($itemCategoryId) {
    $con = connect();
    mysqli_query($con, 'DELETE FROM dbcomsumption WHERE itemCategoryId = ' . (int)$itemCategoryId);
    $deleted = mysqli_affected_rows($con);
    mysqli_close($con);
    return $deleted;
}

/*
 * delete all Consumption rows for a set of itemCategoryIds within one shoppingEventId
 */

function delete_consumption_by_event_items($shoppingEventId, $itemCategoryIds) {
    if (empty($itemCategoryIds)) return 0;
    $con = connect();
    $ids = implode(',', array_map('intval', $itemCategoryIds));
    mysqli_query($con, 'DELETE FROM dbcomsumption WHERE shoppingEventId = ' . (int)$shoppingEventId .
        ' AND itemCategoryId IN (' . $ids . ')');
    $deleted = mysqli_affected_rows($con);
    mysqli_close($con);
    return $deleted;
}

/*
 * Compute current consumption rates per itemCategoryId directly from live shopping list,
 * client, and distribution data — same formula as viewConsumptionRates.php. Returns
 * an associative array of itemCategoryId => total rate (summed across family sizes),
 * or [] when there isn't enough data to compute. Used by the weekly report so it
 * reflects shopping list changes immediately, without waiting for the cached
 * dbcomsumption rows to be refreshed by a visit to viewConsumptionRates.php.
 */
function compute_current_consumption_rates_by_category() {
    $con = connect();

    // Latest distribution days
    $distRow = mysqli_fetch_assoc(mysqli_query($con,
        'SELECT distributionDays FROM dbdistribution ORDER BY date DESC, id DESC LIMIT 1'));
    if (!$distRow) { mysqli_close($con); return []; }
    $distDays = (int)$distRow['distributionDays'];
    if ($distDays <= 0) { mysqli_close($con); return []; }

    // Latest client record per shoppingEventId
    $latestClients = [];
    $totalClients  = 0;
    $cRes = mysqli_query($con,
        'SELECT c.shoppingEventId, c.numClients FROM dbclient c
         WHERE c.id IN (SELECT MAX(id) FROM dbclient GROUP BY shoppingEventId)');
    if ($cRes) {
        while ($row = mysqli_fetch_assoc($cRes)) {
            $latestClients[] = $row;
            $totalClients   += (float)$row['numClients'];
        }
    }
    if (empty($latestClients) || $totalClients <= 0) {
        mysqli_close($con);
        return [];
    }

    $clientsPerDay = round($totalClients / $distDays);
    if ($clientsPerDay <= 0) { mysqli_close($con); return []; }

    $rates = []; // categoryId => total rate (summed across family sizes)
    foreach ($latestClients as $lc) {
        $seId               = (int)$lc['shoppingEventId'];
        $groupClientsPerDay = (float)$lc['numClients'] / $clientsPerDay;

        $cntRes = mysqli_query($con,
            'SELECT itemCategoryId, quantity, groupId, excludeFromConsumption
             FROM dbshoppingcounts WHERE shoppingEventId = ' . $seId);

        $countsForEvent = [];
        $groupSizeMap   = [];
        if ($cntRes) {
            while ($cRow = mysqli_fetch_assoc($cntRes)) {
                $countsForEvent[] = $cRow;
                if ($cRow['groupId'] !== null && empty($cRow['excludeFromConsumption'])) {
                    $gId = (int)$cRow['groupId'];
                    $groupSizeMap[$gId] = ($groupSizeMap[$gId] ?? 0) + 1;
                }
            }
        }

        foreach ($countsForEvent as $sc) {
            if (!empty($sc['excludeFromConsumption'])) continue;
            $catId    = (int)$sc['itemCategoryId'];
            $qty      = (int)$sc['quantity'];
            $gId      = $sc['groupId'] !== null ? (int)$sc['groupId'] : null;
            $gSize    = ($gId !== null && isset($groupSizeMap[$gId])) ? $groupSizeMap[$gId] : 1;
            $baseRate = $groupClientsPerDay * $qty;
            $rate     = round($gSize > 1 ? $baseRate / $gSize : $baseRate, 2);

            if (!isset($rates[$catId])) $rates[$catId] = 0;
            $rates[$catId] += $rate;
        }
    }
    mysqli_close($con);
    return $rates;
}

/*
 * update the itemsConsumed for a Consumption in dbcomsumption table: if it does not exist, return false
 */

function update_consumption_itemsConsumed($id, $itemsConsumed) {
    $con = connect();
    $query = 'SELECT * FROM dbcomsumption WHERE id = "' . $id . '"';
    $result = mysqli_query($con, $query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'UPDATE dbcomsumption SET itemsConsumed = "' . $itemsConsumed . '" WHERE id = "' . $id . '"';
    mysqli_query($con, $query);

    mysqli_close($con);
    return true;
}
