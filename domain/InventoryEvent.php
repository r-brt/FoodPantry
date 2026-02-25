<?php
/**
 * Encapsulated version of a dbs inventoryEvent.
 */
class Event {
    private $id;
    private $personId;
    private $date;


    function __construct($id, $personId, $date) {
        $this->id = $id;
        $this->name = $personId;
        $this->type = $date;
    }

    function getID() {
        return $this->id;
    }

    function getPersonId() {
        return $this->personId;
    }

    function getDate() {
        return $this->date;
    }

}