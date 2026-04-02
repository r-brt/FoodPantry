<?php

include_once('dbinfo.php');
include_once(dirname(__FILE__).'/../domain/PalletCount.php');

/*
 * add an palletCount to dbpalletcounts table: return id generated from sql autoincrement
 */

function add_palletCount($palletEventId, $itemCategoryId, $quantity) {
    $con=connect();
    mysqli_query($con,'INSERT INTO dbpalletcounts (palletEventId, itemCategoryId, quantity) VALUES("' .
            $palletEventId . '","' . 
            $itemCategoryId . '","' . 
            $quantity . '");');							
    $id = mysqli_insert_id($con);
    mysqli_close($con);
    
    return $id;
}

/*
 * remove an palletCount from dbpalletcounts table: if it does not exist, return false
 */

function delete_palletCount($id){
    $con=connect();
    $query = 'SELECT * FROM dbpalletcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'DELETE FROM dbpalletcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);

    mysqli_close($con);
    return true;
}

/*
 * get palletCount from dbpalletcounts table with given palletEventId: return null if not found.
 */

function get_palletCount_by_id($id){
    $con=connect();
    $query = 'SELECT * FROM dbpalletcounts WHERE id = "' . $id . '"';
    $sql_result = mysqli_query($con,$query);
    if ($sql_result == null || mysqli_num_rows($sql_result) == 0) {
        mysqli_close($con);
        return null;
    }
    $array_result = mysqli_fetch_array($sql_result, MYSQLI_ASSOC);
    $itemCount = new PalletCount($array_result['id'],$array_result['palletEventId'],$array_result['itemCategoryId'],$array_result['quantity']);
    mysqli_close($con);
    return $itemCount;
}

/*
 * get all palletCounts from dbpalletcounts table that have a given palletEventId
 */

function get_palletCounts_by_palletEvent($palletEventId){
    $con=connect();
    $query = 'SELECT * FROM dbpalletcounts WHERE palletEventId = "' . $palletEventId . '"';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $itemCount_array = array();
    foreach($array_result as $result){
        $itemCount_array[] = new PalletCount($result['id'],$result['palletEventId'],$result['itemCategoryId'],$result['quantity']);
    }
    mysqli_close($con);
    return $itemCount_array;
}

/*
 * get all palletCounts from dbpalletcounts table that have a given itemCategoryId
 */

function get_palletCounts_by_itemCategory($itemCategoryId){
    $con=connect();
    $query = 'SELECT * FROM dbpalletcounts WHERE itemCategoryId = "' . $itemCategoryId . '"';
    $sql_result = mysqli_query($con,$query);
    $array_result = mysqli_fetch_all($sql_result, MYSQLI_ASSOC);
    $itemCount_array = array();
    foreach($array_result as $result){
        $itemCount_array[] = new PalletCount($result['id'],$result['palletEventId'],$result['itemCategoryId'],$result['quantity']);
    }
    mysqli_close($con);
    return $itemCount_array;
}

/*
 * change the quantity for an palletCount from dbpalletcounts table: if it does not exist, return false
 */

function update_quantity($id, $quantity){
    $con=connect();
    $query = 'SELECT * FROM dbpalletcounts WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);
    if ($result == null || mysqli_num_rows($result) == 0) {
        mysqli_close($con);
        return false;
    }
    $query = 'UPDATE dbpalletcounts SET quantity = "' . $quantity . '" WHERE id = "' . $id . '"';
    $result = mysqli_query($con,$query);

    mysqli_close($con);
    return true;
}