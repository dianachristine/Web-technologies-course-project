<?php

class Book {

    public $id;
    public $title;
    public $grade;
    public $isRead;
    public $authors = [];

    public function __construct($title, $grade, $isRead) {
        $this->title = $title;
        $this->grade = $grade;
        $this->isRead = $isRead;
    }

    public function addId($id) {
        $this->id = $id;
    }

    public function addAuthor($author) {
        $this->authors[] = $author;
    }

}
