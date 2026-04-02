<?php

/**
 * Encapsulated version of a dbs palletEvent.
 */

class PalletEvent {

    private $id;
    private $name;
    private $personId;
    private $date;

    function __construct($id, $name, $personId, $date) {
        $this->id = $id;
        $this->name = $name;
        $this->personId = $personId;
        $this->date = $date;
    }

    function getId() {
        return $this->id;
    }

    function getName() {
        return $this->name;
    }

    function getPersonId() {
        return $this->personId;
    }

    function getDate() {
        return $this->date;
    }

}