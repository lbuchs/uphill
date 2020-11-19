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
            $return->currentTime = round(microtime(true)*1000);
            $this->_response->set($return);

        } else {
            $timestampSaved = false;

            // Es muss als erstes der Start gescannt werden.
            if (!$attempt['started'] && $code && !$this->_isStartCheckpoint($code)) {
                throw new \Exception('Scannen Sie den Start-QR-Code.');
            }

            // ready und code gescannt: zwischenzeit speichern
            if ($code) {
                $timestampSaved = $this->_saveTimestamp($attempt['attemptId'], $code);
            }

            // Zeit anzeigen
            $return = new \stdClass();
            $return->action = 'showTime';
            $return->data = $this->_getAttempt();
            $return->html = $this->_getTimeHtml($return->data);
            $return->timestampSaved = $timestampSaved;
            $this->_response->set($return);
        }
    }

    public function saveForm(): void {
        $formPacket = isset($this->_request->params()->formPacket) ? $this->_request->params()->formPacket : null;
        $this->_createAttempt($formPacket);
    }








    // *********************
    // PROTECTED
    // *********************

    protected function _getTimeHtml(array $attempt): string {
        $checkpoints = $this->_getCheckpoints($attempt['attemptId']);
        $html = '';

        foreach ($checkpoints as $checkpoint) {
            $alltimeBest = $this->_getAlltimeBest($checkpoint['checkpointId']);
            $userBest = $this->_getUserBest($checkpoint['checkpointId'], $attempt['attemptId']);
            $womanBest = $this->_getAlltimeBest($checkpoint['checkpointId'], 'W');
            $categoryBest = $this->_getAlltimeBest($checkpoint['checkpointId'], null, $attempt['category']);

            $html .= '<div class="turnpoint">';
            $html .= '<p class="name">' . htmlspecialchars($checkpoint['name']) . '</p><p class="detail">→ '
                    . round($checkpoint['distance']/1000,2) . 'km, ↑ '
                    . $checkpoint['altitude'] . 'm</p>';


            $html .= '<table>';

            $categoryName = '';
            switch ($attempt['category']) {
                case 1: $categoryName = 'Fussgänger'; break;
                case 2: $categoryName = 'Leichtausrüstung'; break;
                case 3: $categoryName = 'Sherpa'; break;
            }

            // Eigene Zeit
            $html .= '<tr>';
            $html .= '<td><p>Deine Zeit</p></td>';
            if ($checkpoint['userTime']) {
                $html .= '<td>' . htmlspecialchars($checkpoint['userTime']) . '</td>';
            } else if ($checkpoint['skipped']) {
                $html .= '<td class="skipped">Verpasst</td>';
            } else {
                $html .= '<td>--:--:--</td>';
            }
            $html .= '<td>&nbsp;</td>'; // differenz
            $html .= '</tr>';

            // Eigene Bestzeit
            if ($userBest) {
                $html .= '<tr>';
                $html .= '<td><p>Deine Bestzeit</p></td>';
                $html .= '<td>' . htmlspecialchars($userBest->walkTime) . '</td>';
                if ($checkpoint['userTime']) {
                    $html .= $this->_timeDiff($userBest->walkTime, $checkpoint['userTime'], 'td'); // differenz
                } else {
                    $html .= '<td>--:--:--</td>';
                }
                $html .= '</tr>';
            }

            // Frauen Bestzeit
            if ($womanBest) {
                $html .= '<tr>';
                $html .= '<td><p>Frauen Bestzeit</p><p class="name">Von ' . htmlspecialchars($womanBest->name) . '</p></td>';
                $html .= '<td>' . htmlspecialchars($womanBest->walkTime) . '</td>';
                if ($checkpoint['userTime']) {
                    $html .= $this->_timeDiff($womanBest->walkTime, $checkpoint['userTime'], 'td'); // differenz
                } else {
                    $html .= '<td>--:--:--</td>';
                }
                $html .= '</tr>';
            }

            // Kategorie Bestzeit
            if ($categoryBest) {
                $html .= '<tr>';
                $html .= '<td><p>' . htmlspecialchars($categoryName) . ' Bestzeit</p><p class="name">Von ' . htmlspecialchars($categoryBest->name) . '</p></td>';
                $html .= '<td>' . htmlspecialchars($categoryBest->walkTime) . '</td>';
                if ($checkpoint['userTime']) {
                    $html .= $this->_timeDiff($categoryBest->walkTime, $checkpoint['userTime'], 'td'); // differenz
                } else {
                    $html .= '<td>--:--:--</td>';
                }
                $html .= '</tr>';
            }

            // Absolute Bestzeit
            if ($alltimeBest) {
                $html .= '<tr>';
                $html .= '<td><p>Absolute Bestzeit</p><p class="name">Von ' . htmlspecialchars($alltimeBest->name) . '</p></td>';
                $html .= '<td>' . htmlspecialchars($alltimeBest->walkTime) . '</td>';
                if ($checkpoint['userTime']) {
                    $html .= $this->_timeDiff($alltimeBest->walkTime, $checkpoint['userTime'], 'td'); // differenz
                } else {
                    $html .= '<td>--:--:--</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</table>';
            $html .= '</div>';
        }

        return $html;

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
        $st->bindValue(':email', isset($formPacket->email) ? mb_strtolower($formPacket->email) : '', \PDO::PARAM_STR);
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
                IS NULL, 0, 1) AS finished,

                (
                    SELECT `timestamp`.time
                    FROM `timestamp`
                    WHERE `timestamp`.attemptId = attempt.attemptId
                    AND `timestamp`.checkpointId = (
                       SELECT checkpoint.checkpointId
                       FROM checkpoint
                       ORDER BY checkpoint.distance DESC
                       LIMIT 1
                    )
                ) AS finishTime

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
            $row['finishTime'] = $row['finishTime'] ? strtotime($row['finishTime']) : null;
        }

        return $row ?: array();
    }

    protected function _saveTimestamp(int $attemptId, string $code): bool {
        $time = isset($_SERVER["REQUEST_TIME_FLOAT"]) ? (float)$_SERVER["REQUEST_TIME_FLOAT"] : microtime(true);
        $timestamp = (int)floor($time);
        $microtime = (int)round(($time - $timestamp) * 1000000);
        $checkpointId = $this->_getCheckpointId($code);

        if (!$checkpointId) {
            throw new \Exception('Ungültiger Checkpoint');
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
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['checkpointId'] ? (int)$row['checkpointId'] : null;
    }

    protected function _isStartCheckpoint(string $code): bool {
        $st = $this->_db->pdo()->prepare('SELECT checkpointId FROM checkpoint WHERE `code` = :code AND distance = 0');
        $st->bindParam(':code', $code, \PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['checkpointId'] ? true : false;
    }

    protected function _isEndCheckpoint(string $code): bool {
        $st = $this->_db->pdo()->prepare('SELECT checkpoint.`code` FROM checkpoint ORDER BY distance DESC LIMIT 1');
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['code'] === $code;
    }

    /**
     * Gibt die Allzeit Bestzeit zurück
     * @param int $checkpointId
     * @param string|null $gender
     * @param int|null $category
     * @return \stdClass|null
     */
    protected function _getAlltimeBest(int $checkpointId, ?string $gender=null, ?int $category=null): ?\stdClass {

        $genderWhere = $gender === null ? '' : 'AND attempt.gender = :gender';
        $categoryWhere = $category === null ? '' : 'AND attempt.category = :category';

        $st = $this->_db->pdo()->prepare('
            SELECT
                attempt.`name`,
                TIMEDIFF(
                   `timestamp`.`time`,
                   (
                      SELECT subTimestamp.`time`
                      FROM `timestamp` AS subTimestamp
                      WHERE subTimestamp.attemptId = attempt.attemptId
                      AND subTimestamp.checkpointId = (
                         SELECT checkpoint.checkpointId
                         FROM checkpoint
                         WHERE checkpoint.distance = 0
                      )
                   )
                ) AS walkTime

            FROM `attempt`
            INNER JOIN `timestamp` ON `timestamp`.attemptId = `attempt`.attemptId
            INNER JOIN checkpoint ON `timestamp`.checkpointId = checkpoint.checkpointId

            WHERE checkpoint.checkpointId = :checkpointId
            AND attempt.`name` <> \'\'
            AND attempt.`email` <> \'\'
            ' . $genderWhere . '
            ' . $categoryWhere . '
            AND `timestamp`.checkpointId <> (
               SELECT startCp.checkpointId
               FROM checkpoint AS startCp
               WHERE startCp.distance = 0
            )
            AND (SELECT COUNT(*) FROM `timestamp` WHERE `timestamp`.attemptId = attempt.attemptId) = (SELECT COUNT(*) FROM checkpoint)

            ORDER BY `walkTime` ASC
            LIMIT 1
        ');
        $st->bindParam(':checkpointId', $checkpointId, \PDO::PARAM_INT);

        if ($gender !== null) {
            $st->bindParam(':gender', $gender, \PDO::PARAM_STR);
        }
        if ($category !== null) {
            $st->bindParam(':category', $category, \PDO::PARAM_INT);
        }

        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);

        if ($row) {
            $return = new \stdClass();
            $return->name = $row['name'];
            $return->walkTime = $row['walkTime'];
            return $return;
        }

        return null;
    }


    /**
     * Gibt die Bestzeit vom User zurück
     * @param int $checkpointId
     * @param int $currentAttemptId
     * @return \stdClass|null
     */
    protected function _getUserBest(int $checkpointId, int $currentAttemptId): ?\stdClass {
        $st = $this->_db->pdo()->prepare('
            SELECT
                attempt.`attemptId`,
                attempt.`name`,
                TIMEDIFF(
                   `timestamp`.`time`,
                   (
                      SELECT subTimestamp.`time`
                      FROM `timestamp` AS subTimestamp
                      WHERE subTimestamp.attemptId = attempt.attemptId
                      AND subTimestamp.checkpointId = (
                         SELECT checkpoint.checkpointId
                         FROM checkpoint
                         WHERE checkpoint.distance = 0
                      )
                   )
                ) AS walkTime

            FROM `attempt`
            INNER JOIN `timestamp` ON `timestamp`.attemptId = `attempt`.attemptId
            INNER JOIN checkpoint ON `timestamp`.checkpointId = checkpoint.checkpointId

            WHERE checkpoint.checkpointId = :checkpointId
            AND attempt.`name` <> \'\'
            AND attempt.`email` <> \'\'
            AND attempt.`email` = (SELECT curAttempt.`email` FROM attempt AS curAttempt WHERE cuAttempt.attemptId = :currentAttemptId)
            AND `timestamp`.checkpointId <> (
               SELECT startCp.checkpointId
               FROM checkpoint AS startCp
               WHERE startCp.distance = 0
            )
            AND (SELECT COUNT(*) FROM `timestamp` WHERE `timestamp`.attemptId = attempt.attemptId) = (SELECT COUNT(*) FROM checkpoint)

            ORDER BY `walkTime` ASC
            LIMIT 1
        ');
        $st->bindParam(':checkpointId', $checkpointId, \PDO::PARAM_INT);
        $st->bindParam(':currentAttemptId', $currentAttemptId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);

        if ($row) {
            $return = new \stdClass();
            $return->attemptId = (int)$row['attemptId'];
            $return->name = $row['name'];
            $return->walkTime = $row['walkTime'];
            return $return;
        }

        return null;
    }

    /**
     * Gibt die Checkpoints zurück
     * @param int $attemptId
     * @return array
     */
    protected function _getCheckpoints(int $attemptId): array {
        $st = $this->_db->pdo()->prepare('
            SELECT
               checkpoint.checkpointId,
               checkpoint.distance,
               checkpoint.altitude,
               checkpoint.name,

               TIMEDIFF((
                  SELECT `timestamp`.`time`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId = checkpoint.checkpointId
                  AND `timestamp`.attemptId = :attemptId
               ),(
                  SELECT `timestamp`.`time`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId = (SELECT cp.checkpointId FROM checkpoint AS cp WHERE cp.distance = 0)
                  AND `timestamp`.attemptId = :attemptId
               )) AS userTime,

               IF((
                  SELECT `timestamp`.`timestampId`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId = checkpoint.checkpointId
                  AND `timestamp`.attemptId = :attemptId
               ) IS NULL AND (
                  SELECT `timestamp`.`timestampId`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId IN (SELECT cp.checkpointId FROM checkpoint AS cp WHERE cp.distance > checkpoint.distance)
                  AND `timestamp`.attemptId = :attemptId
               ) IS NOT NULL, 1, 0) AS skipped

            FROM checkpoint
            ORDER BY checkpoint.distance ASC;
        ');
        $st->bindParam(':attemptId', $attemptId, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        unset ($st);

        // types
        foreach ($rows as $row) {
            $row['checkpointId'] = (int)$row['checkpointId'];
            $row['distance'] = (int)$row['distance'];
            $row['altitude'] = (int)$row['altitude'];
            $row['skipped'] = !!$row['skipped'];
        }

        return $rows;
    }

    protected function _timeDiff(string $oldTime, string $newTime, ?string $htmlEl=null): string {
        $t1 = explode(':', $oldTime);
        $t2 = explode(':', $newTime);
        if (count($t1) !== 3 || count($t2) !== 3) {
            throw new Exception('invalid time format');
        }

        $t1 = array_map('intval', $t1);
        $t2 = array_map('intval', $t2);

        $ts1 = (new \DateTime())->setTime($t1[0], $t1[1], $t1[2]);
        $ts2 = (new \DateTime())->setTime($t2[0], $t2[1], $t2[2]);
        $diff = $ts1->diff($ts2)->format('%R%H:%I:%S');

        $slower = substr($diff, 0, 1) === '+' && $ts1->getTimestamp() !== $ts2->getTimestamp();

        if ($htmlEl) {
            $html = '<' . $htmlEl . ' ';
            $html .= $slower ? 'class="slower"' : 'class="faster"';
            $html .= '>';
            $html .= htmlspecialchars($diff);
            $html .= '</' . $htmlEl . '>';
            return $html;

        } else {
            return $diff;
        }
    }
}
