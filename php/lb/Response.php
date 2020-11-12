<?php

/*
 * Copyright © 2020 Lukas Buchs
 */

namespace lb;


class Response {
    protected $_data;

    public function set($data) {
        $this->_data = $data;
    }

    public function flush() {
        header('Content-Type: application/json');
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: " . date('r', strtotime('1989-12-14 00:00:00')));

        $response = new \stdClass();

        if ($this->_data instanceof \Throwable) {
            $response->success = false;
            $response->msg = $this->_data->getMessage();
            $response->data = null;

        } else {
            $response->success = true;
            $response->data = $this->_data;
        }

        print json_encode($response);
        ob_flush();
        flush();
    }

}
