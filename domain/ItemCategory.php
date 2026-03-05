<?php
/**
 * Encapsulated version of a dbs inventoryEvent.
 */
class Event {
    private $id;
    private $name;
    private $status;


    function __construct($id, $name, $status) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $status;
    }

    function getId() {
        return $this->id;
    }

    function getName() {
        return $this->name;
    }

    function getStatus() {
        return $this->status;
    }

}