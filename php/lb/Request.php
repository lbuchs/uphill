<?php

/*
 * Copyright © 2020 Lukas Buchs
 */

namespace lb;


class Request {
    protected $_method = '';
    protected $_action = '';
    protected $_params;
    protected $_sessionId;

    public function __construct() {

        $this->_method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
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
            if (\is_object($input) && isset($input->sessionId)) {
                $this->_sessionId = $input->sessionId;
            }
        }
    }

    public function action() {
        return $this->_action;
    }

    public function method() {
        return $this->_method;
    }

    public function params() {
        return $this->_params;
    }

    public function sessionId() {
        return $this->_sessionId;
    }
}
