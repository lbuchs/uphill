<?php

namespace lb;


class Main {

    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var Response
     */
    protected $_response;

    /**
     * @var Db
     */
    protected $_db;

    public function __construct() {
        require_once 'Request.php';
        require_once 'Response.php';
        require_once 'Db.php';

        $this->_startSession();

        $this->_request = new Request();
        $this->_response = new Response();
        $this->_db = new Db();
    }

    // ----------------------------------
    // PUBLIC
    // ----------------------------------

    public function run() {
        try {
            require_once 'Uphill.php';
            $uphill = new Uphill($this->_db, $this->_request, $this->_response);

            switch ($this->_request->action()) {
                case 'getContent': $uphill->getContent(); break;
                case 'saveForm': $uphill->saveForm(); break;
                default: throw new Exception('unknown action');
            }


        } catch (\Throwable $ex) {
            $this->_response->set($ex);

        } finally {
            $this->_response->flush();
        }
    }


    // ----------------------------------
    // PROTECTED
    // ----------------------------------

    protected function _startSession() {
        session_name('PDCSUPHILL');
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 365, // 1 jahr
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}
