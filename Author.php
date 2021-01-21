<?php

class Author {

    public $id;
    public $firstName;
    public $lastName;
    public $grade;

    public function __construct($firstName, $lastName, $grade) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->grade = $grade;
    }

    public function addId($id) {
        $this->id = $id;
    }

}
