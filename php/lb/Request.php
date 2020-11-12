<?php

/*
 * Copyright © 2020 Lukas Buchs
 */

namespace lb;


class Request {
    protected $_action = '';
    protected $_params;

    public function __construct() {
        $input = \file_get_contents('php://input');
        $this->_params = new \stdClass();

        if ($input) {
            $input = \json_decode($input);
            if (\is_object($input) && isset($input->action)) {
                $this->_action = (string)$input->action;
            }
            if (\is_object($input) && isset($input->params)) {
                $this->_params = $input->params;
            }
        }
    }

    public function action() {
        return $this->_action;
    }

    public function params() {
        return $this->_params;
    }
}
