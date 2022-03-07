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

        // Error Reporting komplett abschalten
        error_reporting(0);


        $this->_request = new Request();
        $this->_response = new Response();
        $this->_db = new Db();

        $this->_startSession();
    }

    // ----------------------------------
    // PUBLIC
    // ----------------------------------

    public function run() {
        try {

            $forbidden = false;
            if (filter_input(INPUT_SERVER, 'HTTP_ORIGIN')) {
                $whitelist = [
                    '*.lubu.ch',
                    'pdcs.ch',
                    '*.pdcs.ch',
                    'sac-wildstrubel.ch',
                    '*.sac-wildstrubel.ch',
                    'localhost',
                    '127.0.0.1'
                ];

                $requestDomain = mb_strtolower(parse_url(filter_input(INPUT_SERVER, 'HTTP_ORIGIN'), PHP_URL_HOST));

                $accept = false;
                foreach ($whitelist as $whitelistDomain) {
                    $pattern = str_replace('%%%', '.*', preg_quote(str_replace('*', '%%%', $whitelistDomain), '/'));
                    if (preg_match('/^' . $pattern . '$/i', $requestDomain)) {
                        $accept = true;
                        break;
                    }
                }

                if ($accept) {
                    header('Access-Control-Allow-Origin: ' . filter_input(INPUT_SERVER, 'HTTP_ORIGIN'));
                    header('Access-Control-Allow-Methods: POST');
                    header('Access-Control-Allow-Headers: Content-Type');
                    header('Access-Control-Allow-Credentials: true');

                    if ($this->_request->method() === 'OPTIONS') {
                        header('Access-Control-Max-Age: ' . (3600*24));
                    }

                } else {
                    header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
                    $forbidden = true;
                }
            }

            // bots forbidden
            if (preg_match('/bot\b|index|spider|crawl|wget|slurp|Mediapartners-Google/i', filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'))) {
                header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
                $forbidden = true;
            }

            if ($this->_request->method() === 'POST' && !$forbidden) {
                require_once 'Uphill.php';
                $uphill = new Uphill($this->_db, $this->_request, $this->_response);

                switch ($this->_request->action()) {
                    case 'getContent': $uphill->getContent(); break;
                    case 'saveForm': $uphill->saveForm(); break;
                    case 'saveQrScan': $uphill->saveQrScan(); break;
                    case 'endCurrentRun': $uphill->endCurrentRun(); break;
                    case 'getRanking': $uphill->getRanking(); break;
                    default: throw new \Exception('unknown action');
                }
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
            'samesite' => 'None'
        ]);

        // Session-ID übergeben
        if ($this->_request->sessionId()) {
            session_id($this->_request->sessionId());
        } else {
            session_id('nft' . time());
        }

        session_start();
    }
}
