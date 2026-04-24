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
