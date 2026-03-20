<?php
/**
 * Encapsulated version of a dbs inventoryEvent.
 */
class ItemCategory {
    private $id;
    private $name;
    private $bananaBox;
    private $itemsPerBox;
    private $status;


    function __construct($id, $name, $bananaBox, $itemsPerBox, $status) {
        $this->id = $id;
        $this->name = $name;
        $this->bananaBox = $bananaBox;
        $this->status = $status;
        $this->itemsPerBox = $itemsPerBox;
    }

    function getId() {
        return $this->id;
    }

    function getName() {
        return $this->name;
    }

    function getBananaBox() {
        return $this->bananaBox;
    }

    function getItemsPerBox() {
        return $this->itemsPerBox;
    }
    function getStatus() {
        return $this->status;
    }

}