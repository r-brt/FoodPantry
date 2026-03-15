<?php
/**
 * Encapsulated version of a dbs inventoryEvent.
 */
class ItemCategory {
    private $id;
    private $name;
    private $itemsPerBox;
    private $status;


    function __construct($id, $name, $itemsPerBox, $status) {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->itemsPerBox = $itemsPerBox;
    }

    function getId() {
        return $this->id;
    }

    function getName() {
        return $this->name;
    }

    function getItemsPerBox() {
        return $this->itemsPerBox;
    }
    function getStatus() {
        return $this->status;
    }

}