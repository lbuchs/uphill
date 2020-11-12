<?php

/*
 * Copyright © 2020 Lukas Buchs
 */

namespace lb;


class Db {

    /**
     * @var \PDO
     */
    protected $_pdo;


    /**
     * Get PDO
     * @return \PDO
     */
    public function pdo(): \PDO {
        if ($this->_pdo === null) {
            $this->_pdo = new \PDO(UH_DB_DSN, UH_DB_USER, UH_DB_PASSWORD);
        }
        return $this->_pdo;
    }
}
