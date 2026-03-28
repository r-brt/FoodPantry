<?php

/*
 * Each ShoppingCount represents one item counted during an inventory
 */

class ShoppingCount {

    private $id; // Primary key
    private $shoppingEventId; // Foreign Key
    private $itemCategoryId; // Foreign Key
    private $quantity;

    function __construct($id, $shoppingEventId, $itemCategoryId, $quantity) {
        $this->id = $id;
        $this->shoppingEventId = $shoppingEventId;
        $this->itemCategoryId = $itemCategoryId;
        $this->quantity = $quantity;
    }

    function getId() {
        return $this->id;
    }

    function getShoppingEvent() {
        return $this->shoppingEventId;
    }

    function getItemCategory() {
        return $this->itemCategoryId;
    }

    function getQuantity() {
        return $this->quantity;
    }

    public function __toString(){
        return "ID: {$this->id}, Shopping Event ID: {$this->shoppingEventId}, Item Category ID: {$this->itemCategoryId}, Quantity: {$this->quantity}";
    }

}
