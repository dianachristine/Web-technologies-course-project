<?php

class Request {

    private $request;

    public function __construct($request) {
        $this->request = $request;
    }

    public function param($key) {
        return isset($this->request[$key])
            ? $this->request[$key]
            : '';
    }

    public function __toString() {
        return sprintf('<pre>%s</pre>', print_r($this->request, true));
    }

}