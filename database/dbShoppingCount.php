<?php

include_once('dbinfo.php');
include_once(dirname(__FILE__).'/../domain/ShoppingCount.php');

/*
 * add an ShoppingCount to dbshoppingcounts table: return id generated from sql autoincrement
 */

function add_shoppingCount($shoppingEventId, $itemCategoryId, $quantity) {
    $con=connect();
    mysqli_query($con,'INSERT INTO dbshoppingcounts (shoppingEventId, itemCategoryId, quantity) VALUES("' .
            $shoppingEventId . '","' . 
            $itemCategoryId . '","' . 
            $quantity . '");');							
    $id = mysqli_insert_id($con);
    mysqli_close($con);
    
    return $id;
}

/*
 * remove an ShoppingCount from dbshoppingcounts table: if it does not exist, return false
 */

function delete_shoppingCount($id){
    $con=connect();
    $query = 'SELECT * FROM dbshoppingcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'DELETE FROM dbshoppingcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);

    mysqli_close($con);
    return true;
}

/*
 * get ShoppingCount from dbshoppingcounts table with given shoppingEventId: return null if not found.
 */

function get_shoppingCount_by_id($id){
    $con=connect();
    $query = 'SELECT * FROM dbshoppingcounts WHERE id = "' . $id . '"';
    $sql_result = mysqli_query($con,$query);
    if ($sql_result == null || mysqli_num_rows($sql_result) == 0) {
        mysqli_close($con);
        return null;
    }
    $array_result = mysqli_fetch_array($sql_result, MYSQLI_ASSOC);
    $shoppingCount = new ShoppingCount($array_result['id'],$array_result['shoppingEventId'],$array_result['itemCategoryId'],$array_result['quantity']);
    mysqli_close($con);
    return $shoppingCount;
}

/*
 * get all ShoppingCounts from dbshoppingcounts table that have a given shoppingEventId
 */

function get_shoppingCounts_by_shoppingEvent($shoppingEventId){
    $con=connect();
    $query = 'SELECT * FROM dbshoppingcounts WHERE shoppingEventId = "' . $shoppingEventId . '"';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $shoppingCount_array = array();
    foreach($array_result as $result){
        $shoppingCount_array[] = new ShoppingCount($result['id'],$result['shoppingEventId'],$result['itemCategoryId'],$result['quantity']);
    }
    mysqli_close($con);
    return $shoppingCount_array;
}

/*
 * get all ShoppingCounts from dbshoppingcounts table that have a given itemCategoryId
 */

function get_shoppingCounts_by_itemCategory($itemCategoryId){
    $con=connect();
    $query = 'SELECT * FROM dbshoppingcounts WHERE itemCategoryId = "' . $itemCategoryId . '"';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $shoppingCount_array = array();
    foreach($array_result as $result){
        $shoppingCount_array[] = new ShoppingCount($result['id'],$result['shoppingEventId'],$result['itemCategoryId'],$result['quantity']);
    }
    mysqli_close($con);
    return $shoppingCount_array;
}

/*
 * change the quantity for an ShoppingCount from dbshoppingcounts table: if it does not exist, return false
 */

function update_quantity($id, $quantity){
    $con=connect();
    $query = 'SELECT * FROM dbshoppingcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'UPDATE dbshoppingcounts SET quantity = "' . $quantity . '" WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);

    mysqli_close($con);
    return true;
}

/*
 * get most recent ShoppingCount for each category where shoppingEventId <= given maxEventId
 */

function get_most_recent_counts_up_to_event($maxEventId){
    $con=connect();
    $dateQuery = 'SELECT date FROM dbshoppingevent WHERE id = "' . $maxEventId . '"';
    $dateResult = mysqli_query($con, $dateQuery);
    $dateRow = mysqli_fetch_assoc($dateResult);
    $maxDate = $dateRow['date'];

    $query = 'SELECT dic.* FROM dbshoppingcounts dic
              INNER JOIN dbshoppingevent die ON dic.shoppingEventId = die.id
              WHERE die.date <= "' . $maxDate . '"
              ORDER BY dic.itemCategoryId, die.date DESC, dic.shoppingEventId DESC';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $shoppingCount_array = array();
    $seen_categories = array();

    foreach($array_result as $result){
        $categoryId = $result['itemCategoryId'];
        if(!in_array($categoryId, $seen_categories)){
            $shoppingCount_array[] = new ShoppingCount($result['id'],$result['shoppingEventId'],$result['itemCategoryId'],$result['quantity']);
            $seen_categories[] = $categoryId;
        }
    }
    mysqli_close($con);
    return $shoppingCount_array;
}



