<?php

/*
 * Copyright © 2020 Lukas Buchs
 */

namespace lb;


class Uphill {
    /**
     * @var Db
     */
    protected $_db;

    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var Response
     */
    protected $_response;

    public function __construct(Db $db, Request $request, Response $response) {
        $this->_db = $db;
        $this->_request = $request;
        $this->_response = $response;
    }

    public function getContent(): void {
        $code = (string)$this->_request->params()->code;
        $attempt = $this->_getAttempt();

        // Noch nicht gestartet: Formular anzeigen
        if (!$attempt || $attempt['canceled'] || (!$attempt['started'] && !$code)) {

            $return = new \stdClass();
            $return->action = 'showForm';
            $return->data = $attempt;
            $this->_response->set($return);

        } else {
            $timestampSaved = false;

            // Es muss als erstes der Start gescannt werden.
            if (!$attempt['started'] && $code && !$this->_isStartCheckpoint($code)) {
                throw new Exception('Scannen Sie den Start-QR-Code.');
            }

            // ready und code gescannt: zwischenzeit speichern
            if ($code) {
                $timestampSaved = $this->_saveTimestamp($attempt['attemptId'], $code);
            }

            // Zeit anzeigen
            $return->action = 'showTime';
            $return->data = $attempt;
            $return->timestampSaved = $timestampSaved;
            $this->_response->set($return);
        }
    }

    public function saveForm(): void {
        $formPacket = isset($this->_request->params()->formPacket) ? $this->_request->params()->formPacket : null;
        $this->_createAttempt($formPacket);
    }

    protected function _createAttempt(\stdClass $formPacket): int {
        $st = $this->_db->pdo()->prepare('
            INSERT INTO `attempt` (
                `sessionId`, `category`, `gender`, `name`, `email`, `userAgent`, `ipAddress`
            ) VALUES (
                :sessionId, :category, :gender, :name, :email, :userAgent, :ipAddress
            )
        ');

        $st->bindValue(':sessionId', session_id(), \PDO::PARAM_STR);
        $st->bindValue(':category', isset($formPacket->category) ? (int)$formPacket->category : 1, \PDO::PARAM_INT);
        $st->bindValue(':gender', isset($formPacket->gender) ? $formPacket->gender : '', \PDO::PARAM_STR);
        $st->bindValue(':name', isset($formPacket->name) ? $formPacket->name : '', \PDO::PARAM_STR);
        $st->bindValue(':email', isset($formPacket->email) ? $formPacket->email : '', \PDO::PARAM_STR);
        $st->bindValue(':userAgent', (string)$_SERVER['HTTP_USER_AGENT'], \PDO::PARAM_STR);
        $st->bindValue(':ipAddress', (string)$_SERVER['REMOTE_ADDR'], \PDO::PARAM_STR);
        $st->execute();
        unset ($st);

        return (int)$this->_db->pdo()->lastInsertId();
    }

    protected function _getAttempt(): array {
        $st = $this->_db->pdo()->prepare('
            SELECT
                attempt.attemptId,
                attempt.category,
                attempt.gender,
                attempt.`name`,
                attempt.email,

                IF((
                    SELECT ts.timestampId
                    FROM `timestamp` AS ts
                    WHERE ts.attemptId = attempt.attemptId
                    AND ts.checkpointId = (SELECT checkpoint.checkpointId FROM checkpoint WHERE checkpoint.distance = 0 LIMIT 1)
                    LIMIT 1
                ) IS NULL, 0, 1) AS started,

                IF (attempt.canceled = 1 OR DATE(attempt.created) != CURDATE(), 1, 0) AS canceled,
                (
                    SELECT ts.time
                    FROM `timestamp` AS ts
                    WHERE ts.attemptId = attempt.attemptId
                    AND ts.checkpointId = (SELECT checkpoint.checkpointId FROM checkpoint WHERE checkpoint.distance = 0 LIMIT 1)
                    LIMIT 1
                ) AS startTime,

                IF(
                    (
                        SELECT `timestamp`.timestampId
                        FROM `timestamp`
                        WHERE `timestamp`.attemptId = attempt.attemptId
                        AND `timestamp`.checkpointId = (
                           SELECT checkpoint.checkpointId
                           FROM checkpoint
                           ORDER BY checkpoint.distance DESC
                           LIMIT 1
                        )
                    )
                IS NULL, 0, 1) AS finished

            FROM attempt
            WHERE attempt.sessionId = :sessionId
            ORDER BY attempt.created DESC
            LIMIT 1
        ');
        $st->bindValue(':sessionId', session_id(), \PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);

        if ($row) {
            $row['attemptId'] = (int)$row['attemptId'];
            $row['category'] = (int)$row['category'];
            $row['started'] = !!$row['started'];
            $row['finished'] = !!$row['finished'];
            $row['canceled'] = !!$row['canceled'];
            $row['startTime'] = $row['startTime'] ? strtotime($row['startTime']) : null;
        }

        return $row ?: array();
    }

    protected function _saveTimestamp(int $attemptId, string $code): bool {
        $time = isset($_SERVER["REQUEST_TIME_FLOAT"]) ? (float)$_SERVER["REQUEST_TIME_FLOAT"] : microtime(true);
        $timestamp = (int)floor($time);
        $microtime = (int)round(($time - $timestamp) * 1000000);
        $checkpointId = $this->_getCheckpointId($code);

        if (!$checkpointId) {
            throw new Exception('ungültiger checkpoint');
        }

        // bereits passiert?
        $st = $this->_db->pdo()->prepare('
            SELECT checkpoint.checkpointId
            FROM checkpoint
            INNER JOIN `timestamp` ON checkpoint.checkpointId = `timestamp`.checkpointId
            WHERE `timestamp`.attemptId = :attemptId
            AND checkpoint.distance >= (SELECT cp.distance FROM checkpoint AS cp WHERE cp.checkpointId = :checkpointId)
        ');
        $st->bindParam(':attemptId', $attemptId, \PDO::PARAM_INT);
        $st->bindParam(':checkpointId', $checkpointId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        $alreadyPassed = $row && $row['checkpointId'];
        unset ($st, $row);

        if (!$alreadyPassed) {
            $st = $this->_db->pdo()->prepare('
                    INSERT INTO `timestamp`
                        (`attemptId`, `checkpointId`, `time`, `microseconds`)
                    VALUES
                        (:attemptId, :checkpointId, :time, :microseconds);');
            $st->bindParam(':attemptId', $attemptId, \PDO::PARAM_INT);
            $st->bindParam(':checkpointId', $checkpointId, \PDO::PARAM_INT);
            $st->bindValue(':time', date('Y-m-d H:i:s', $timestamp), \PDO::PARAM_STR);
            $st->bindValue(':microseconds', $microtime, \PDO::PARAM_INT);
            $st->execute();
        }

        return !$alreadyPassed;
    }


    /**
     * checkpointId abfragen
     * @param string $code
     * @return int|null
     */
    protected function _getCheckpointId(string $code): ?int {
        $st = $this->_db->pdo()->prepare('SELECT checkpointId FROM checkpoint WHERE `code` = :code');
        $st->bindParam(':code', $code, \PDO::PARAM_STR);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['checkpointId'] ? (int)$row['checkpointId'] : null;
    }

    protected function _isStartCheckpoint(string $code): bool {
        $st = $this->_db->pdo()->prepare('SELECT checkpointId FROM checkpoint WHERE `code` = :code AND distance = 0');
        $st->bindParam(':code', $code, \PDO::PARAM_STR);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['checkpointId'] ? true : false;
    }

    protected function _isEndCheckpoint(string $code): bool {
        $st = $this->_db->pdo()->prepare('SELECT checkpoint.`code` FROM checkpoint ORDER BY distance DESC LIMIT 1');
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['code'] === $code;
    }
}
