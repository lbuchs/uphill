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
            try {
                $this->_pdo = new \PDO(UH_DB_DSN, UH_DB_USER, UH_DB_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            } catch (\PDOException $ex) {
                throw new \Exception('Datenbankverbindung fehlgeschlagen.');
            }
        }
        return $this->_pdo;
    }
}
