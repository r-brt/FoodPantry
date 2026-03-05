<?php

include_once('dbinfo.php');
include_once(dirname(__FILE__).'/../domain/ItemCount.php');

/*
 * add an itemCount to dbItemCounts table: return id generated from sql autoincrement
 */

function add_itemCount($inventoryEventId, $itemCategoryId, $quantity) {
    $con=connect();
    mysqli_query($con,'INSERT INTO dbitemcounts (inventoryEventID, itemCategoryId, quantity) VALUES("' .
            $inventoryEventId . '","' . 
            $itemCategoryId . '","' . 
            $quantity . '");');							
    $id = mysqli_insert_id($con);
    mysqli_close($con);
    
    return $id;
}

/*
 * remove an itemCount from dbItemCounts table: if it does not exist, return false
 */

function delete_itemCount($id){
    $con=connect();
    $query = 'SELECT * FROM dbitemcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'DELETE FROM dbitemcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);

    mysqli_close($con);
    return true;
}

/*
 * get all itemCounts from dbItemCounts table that have a given inventoryEventId
 */

function get_itemCounts_by_inventoryEvent($inventoryEventId){
    $con=connect();
    $query = 'SELECT * FROM dbitemcounts WHERE inventoryEventId = "' . $inventoryEventId . '"';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $itemCount_array = array();
    foreach($array_result as $result){
        $itemCount_array[] = new ItemCount($result['id'],$result['inventoryEventId'],$result['itemCategoryId'],$result['quantity']);
    }
    mysqli_close($con);
    return $itemCount_array;
}

/*
 * get all itemCounts from dbItemCounts table that have a given itemCategoryId
 */

function get_itemCounts_by_itemCategory($itemCategoryId){
    $con=connect();
    $query = 'SELECT * FROM dbitemcounts WHERE itemCategoryId = "' . $itemCategoryId . '"';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $itemCount_array = array();
    foreach($array_result as $result){
        $itemCount_array[] = new ItemCount($result['id'],$result['inventoryEventId'],$result['itemCategoryId'],$result['quantity']);
    }
    mysqli_close($con);
    return $itemCount_array;
}

/*
 * change the quantity for an itemCount from dbItemCounts table: if it does not exist, return false
 */

function update_quantity($id, $quantity){
    $con=connect();
    $query = 'SELECT * FROM dbitemcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'UPDATE dbitemcounts SET quantity = "' . $quantity . '" WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);

    mysqli_close($con);
    return true;
}
