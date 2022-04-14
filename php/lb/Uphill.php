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

    /**
     * Gibt den Hauptinhalt zurück.
     * @return void
     */
    public function getContent(): void {
        $attempt = $this->_getAttempt();

        // Noch nicht gestartet: Formular anzeigen
        if (!$attempt || $attempt['endedByUser']) {

            $return = new \stdClass();
            $return->action = 'showForm';
            $return->data = $attempt;
            $return->currentTime = round(microtime(true)*1000);
            $this->_response->set($return);

        } else {

            // Zeit anzeigen
            $return = new \stdClass();
            $return->action = 'showTime';
            $return->data = $this->_getAttempt();
            $return->html = $this->_getTimeHtml($return->data);
            $return->timestampSaved = $timestampSaved;
            $this->_response->set($return);
        }
    }

    /**
     * Speichert den Inhalt des Registrierungsformulars
     * @return void
     */
    public function saveForm(): void {
        $formPacket = isset($this->_request->params()->formPacket) ? $this->_request->params()->formPacket : null;

        // Daten validieren
        if (filter_var($formPacket->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \Exception('invalid email address');
        }
        $formPacket->name = filter_var($formPacket->name, FILTER_SANITIZE_STRING);
        $formPacket->familyname = filter_var($formPacket->familyname, FILTER_SANITIZE_STRING);
        $formPacket->gender = filter_var($formPacket->gender, FILTER_SANITIZE_STRING);

        $this->_createAttempt($formPacket);
    }


    /**
     * Speichert einen QR-Code
     * @return void
     * @throws Exception
     */
    public function saveQrScan(): void {
        $code = isset($this->_request->params()->code) ? $this->_request->params()->code : null;
        $img = isset($this->_request->params()->img) ? $this->_request->params()->img : null;

        // Code aus URL
        if ($code) {
            $m = array();
            if (preg_match('/([a-z0-9]{20,})/', $code, $m)) {
                $code = $m[1];
            } else {
                $code = '';
            }
        }

        $attempt = $this->_getAttempt();

        if (!$attempt || $attempt['endedByUser']) {
            throw new \Exception('invalid attempt');
        }

        if (!$attempt['started'] && !$this->_isStartCheckpoint($code)) {
            throw new \Exception('Zurück zum Start! Du hast den Start verpasst.');
        }

        // Speichern
        $timestampSaved = $this->_saveTimestamp($attempt['attemptId'], $code);

        // Bild ablegen
        if ($timestampSaved && $img && $code) {
            if (substr($img, 0, strlen('data:image/jpeg;base64,')) === 'data:image/jpeg;base64,') {
                $jpg = base64_decode(substr($img, strlen('data:image/jpeg;base64,')));
                if ($jpg) {
                    $dataDir = '../data';
                    file_put_contents($dataDir . '/' . str_pad((string)$attempt['attemptId'], 4, '0', STR_PAD_LEFT) . '-' . $code . '.jpg', $jpg);
                }
            }
        }

        $isStart = $this->_isStartCheckpoint($code);
        $isEnd = $this->_isEndCheckpoint($code);

        // mail versenden
        if ($timestampSaved && $isEnd) {
            $attempt = $this->_getAttempt();
            if ($attempt['completed']) { // Bestätigungsmail
                $this->_sendMail($attempt);
            }
        }

        // Route bei attempt eintragen
        if ($timestampSaved && $isStart) {
            $this->_setRouteId((int)$attempt['attemptId']);
        }

        // Rückgabe
        $return = new \stdClass();
        $return->saved = $timestampSaved;
        $return->isStart = $isStart;
        $return->isEnd = $isEnd;
        $this->_response->set($return);
    }


    /**
     * Lauf neu starten
     * @return void
     */
    public function endCurrentRun(): void {
        $attempt = $this->_getAttempt();

        if (!$attempt) {
            throw new \Exception('invalid attempt');
        }

        $st = $this->_db->pdo()->prepare('UPDATE attempt SET endedByUser = 1 WHERE attemptId = :attemptId');
        $st->bindParam(':attemptId', $attempt['attemptId'], \PDO::PARAM_INT);
        $st->execute();
    }


    /**
     * Gibt das Ranking für die Webseite zurück
     * @return void
     */
    public function getRanking(): void {
        $routeId = (int)$this->_request->params()->routeId;
        $season = isset($this->_request->params()->season) ? (int)$this->_request->params()->season : null;

        $st = $this->_db->pdo()->prepare('SELECT checkpointId FROM checkpoint WHERE routeId = :routeId ORDER BY checkpoint.order ASC ');
        $st->bindParam(':routeId', $routeId, \PDO::PARAM_INT);
        $st->execute();
        $cps = $st->fetchAll(\PDO::FETCH_ASSOC);
        unset ($st);

        $checkpointIds = [];
        foreach ($cps as $cp) {
            $checkpointIds[] = (int)$cp['checkpointId'];
        }
        $startCheckpointId = $checkpointIds ? $checkpointIds[0] : 0;
        $goalCheckpointId = $checkpointIds ? $checkpointIds[count($checkpointIds)-1] : 0;

        $tpSelect = '';

        // für jede zwischenzeit eine Abfrage
        for ($i= 1; $i < count($checkpointIds)-1; $i++) {
            $tpSelect = '(
                   SELECT `timestamp`.time
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = ' . $checkpointIds[$i] . '
                ) AS tp' . $i . '_time, ';
        }

        $seasonWhere = '';
        if ($season === 0) { // nur aktuelle Saison
            $seasonWhere = 'AND season.`start` <= CURDATE() AND season.`end` >= CURDATE()';

        } else if (is_int($season)) { // Jahreszahl Saisonstart
            $seasonWhere = 'AND YEAR(season.`start`) = ' . intval($season);
        }

        $st = $this->_db->pdo()->prepare('
            SELECT
                attempt.attemptId,
                attempt.category,
                (SELECT category.name FROM category WHERE category.categoryId = attempt.category) AS categoryName,
                (SELECT category.shortcut FROM category WHERE category.categoryId = attempt.category) AS categoryShortcut,
                attempt.gender,
                CONCAT(attempt.name, \' \', attempt.familyname) AS fullname,
                MD5(attempt.email) AS user,
                season.name AS seasonName,

                (
                   SELECT `timestamp`.time
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = :startCheckpointId
                ) AS start_time,

                (
                   SELECT `timestamp`.time
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = (SELECT checkpoint.checkpointId FROM checkpoint WHERE checkpoint.routeId = attempt.routeId AND checkpoint.`order` = 2)
                ) AS tp1_time,

                (
                   SELECT `timestamp`.time
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = (SELECT checkpoint.checkpointId FROM checkpoint WHERE checkpoint.routeId = attempt.routeId AND checkpoint.`order` = 3)
                ) AS tp2_time,


                (
                   SELECT `timestamp`.time
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = :goalCheckpointId
                ) AS goal_time,

                (
                   SELECT UNIX_TIMESTAMP(`timestamp`.time)
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = :goalCheckpointId
                ) - (
                   SELECT UNIX_TIMESTAMP(`timestamp`.time)
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                   AND `timestamp`.checkpointId = :startCheckpointId
                ) AS walkTime


            FROM attempt

            LEFT JOIN season ON season.`start` <= DATE(attempt.created) AND season.`end` >= DATE(attempt.created) AND season.routeId = attempt.routeId

            WHERE attempt.routeId = :routeId
            AND (
               SELECT COUNT(*)
               FROM timestamp
               WHERE timestamp.attemptId = attempt.attemptId
            ) = (
               SELECT COUNT(*)
               FROM checkpoint
               WHERE checkpoint.routeId = attempt.routeId
            )

            ' . $seasonWhere . '

            ORDER BY walkTime ASC');
        $st->bindParam(':routeId', $routeId, \PDO::PARAM_INT);
        $st->bindParam(':startCheckpointId', $startCheckpointId, \PDO::PARAM_INT);
        $st->bindParam(':goalCheckpointId', $goalCheckpointId, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        unset ($st);

        $ranking = array();
        $categorys = array();
        $title = $seasonWhere ? '' : 'Gesamtrangliste';
        foreach($rows as $row) {
            $tmp = array(
                'seasonName' => $row['seasonName'],
                'attemptId' => (int)$row['attemptId'],
                'category' => (int)$row['category'],
                'categoryName' => $row['categoryName'],
                'categoryShortcut' => $row['categoryShortcut'],
                'gender' => $row['gender'],
                'fullname' => $row['fullname'],
                'user' => $row['user'],
                'start' => strtotime($row['start_time']),
                'goal' => '+' . $this->_timeDiffString(strtotime($row['start_time']), strtotime($row['goal_time'])),
                'walkTime' => (int)$row['walkTime'],
                'tpCount' => count($checkpointIds) -2 // Anzahl Zwischenzeiten
            );

            // Zwischenzeiten
            for ($i= 1; $i < count($checkpointIds)-1; $i++) {
                $tmp['tp' . $i] = '+' . $this->_timeDiffString(strtotime($row['start_time']), strtotime($row['tp' .$i . '_time']));
            }

            $ranking[] = $tmp;

            if (!array_key_exists('CAT_' . $row['category'], $categorys)) {
                $categorys['CAT_' . $row['category']] = [
                    'category' => (int)$row['category'],
                    'categoryName' => $row['categoryName'],
                    'categoryShortcut' => $row['categoryShortcut']
                ];
            }

            // Kategorie Frauen
            if ($row['gender'] === 'W') {
                if (!array_key_exists('CAT_W', $categorys)) {
                    $categorys['CAT_W'] = [
                        'category' => null,
                        'categoryName' => 'Damen',
                        'categoryShortcut' => null
                    ];
                }
            }

            // Titel
            if (!$title) {
                $title = $row['seasonName'];
            }
        }

        // Rückgabe
        $return = new \stdClass();
        $return->ranking = $ranking;
        $return->categorys = $categorys;
        $return->title = $title;
        $this->_response->set($return);
    }



    // *********************
    // PROTECTED
    // *********************

    protected function _getTimeHtml(array $attempt): string {
        $checkpoints = $this->_getCheckpoints($attempt['attemptId']);
        $html = '';
        $altitude = null;

        foreach ($checkpoints as $checkpoint) {
            $alltimeBest = $this->_getCheckpointBest($checkpoint['checkpointId']);
            $saisonBest =  $this->_getCheckpointBest($checkpoint['checkpointId'], null, null, true);
            $userBest = $this->_getUserBest($checkpoint['checkpointId'], $attempt['attemptId']);
            $womanBest = $attempt['gender'] === 'W' ? $this->_getCheckpointBest($checkpoint['checkpointId'], 'W') : [];
            $womanSaisonBest = $attempt['gender'] === 'W' ? $this->_getCheckpointBest($checkpoint['checkpointId'], 'W', null, true) : [];
            $categoryBest = $this->_getCheckpointBest($checkpoint['checkpointId'], null, $attempt['category']);

            // Differenz Höhe anzeigen
            $altiStr = '';
            if ($altitude === null) {
                $altiStr = '0m';
                $altitude = $checkpoint['altitude'];
            } else {
                $altiStr = ($checkpoint['altitude'] - $altitude) . 'm';
            }

            $html .= '<div class="turnpoint">';
            $html .= '<p class="name">' . htmlspecialchars($checkpoint['name']) . '</p><p class="detail">→ '
                    . round($checkpoint['distance']/1000,2) . 'km, ↑ '
                    . $altiStr . '</p>';


            $html .= '<table>';

            $categoryName = $attempt['categoryName'];
            if (!$categoryName) {
                switch ($attempt['category']) {
                    case 1: $categoryName = 'Fussgänger'; break;
                    case 2: $categoryName = 'Leichtausrüstung'; break;
                    case 3: $categoryName = 'Sherpa'; break;
                }
            }

            // Eigene Zeit
            $html .= '<tr>';
            $html .= '<td><p>Deine Zeit:</p></td>';
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

            // Frauen Bestzeit
            if ($womanSaisonBest) {
                $html .= '<tr>';
                $html .= '<td><p>Frauen Saison Bestzeit</p><p class="name">Von ' . htmlspecialchars($womanSaisonBest->name) . '</p></td>';
                $html .= '<td>' . htmlspecialchars($womanSaisonBest->walkTime) . '</td>';
                if ($checkpoint['userTime']) {
                    $html .= $this->_timeDiff($womanSaisonBest->walkTime, $checkpoint['userTime'], 'td'); // differenz
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

            // Saisonbestzeit
            if ($saisonBest) {
                $html .= '<tr>';
                $html .= '<td><p>Saison Bestzeit</p><p class="name">Von ' . htmlspecialchars($saisonBest->name) . '</p></td>';
                $html .= '<td>' . htmlspecialchars($saisonBest->walkTime) . '</td>';
                if ($checkpoint['userTime']) {
                    $html .= $this->_timeDiff($saisonBest->walkTime, $checkpoint['userTime'], 'td'); // differenz
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
                `sessionId`, `category`, `gender`, `name`, `familyname`, `email`, `userAgent`, `ipAddress`
            ) VALUES (
                :sessionId, :category, :gender, :name, :familyname, :email, :userAgent, :ipAddress
            )
        ');

        $st->bindValue(':sessionId', session_id(), \PDO::PARAM_STR);
        $st->bindValue(':category', isset($formPacket->category) ? (int)$formPacket->category : 1, \PDO::PARAM_INT);
        $st->bindValue(':gender', isset($formPacket->gender) ? $formPacket->gender : '', \PDO::PARAM_STR);
        $st->bindValue(':name', isset($formPacket->name) ? $formPacket->name : '', \PDO::PARAM_STR);
        $st->bindValue(':familyname', isset($formPacket->familyname) ? $formPacket->familyname : '', \PDO::PARAM_STR);
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
                (SELECT category.name FROM category WHERE category.categoryId = attempt.category LIMIT 1) AS categoryName,
                (SELECT route.challengeName FROM route WHERE route.routeId = attempt.routeId LIMIT 1) AS challengeName,
                (SELECT route.organizer FROM route WHERE route.routeId = attempt.routeId LIMIT 1) AS organizer,
                (SELECT route.rankingUrl FROM route WHERE route.routeId = attempt.routeId LIMIT 1) AS rankingUrl,

                attempt.gender,
                attempt.`name`,
                attempt.`familyname`,
                attempt.email,

                IF (attempt.routeId IS NULL, \'\', (
                   SELECT route.name
                   FROM route
                   WHERE route.routeId = attempt.routeId
                )) AS routeName,

                IF((
                    SELECT ts.timestampId
                    FROM `timestamp` AS ts
                    WHERE ts.attemptId = attempt.attemptId
                    AND ts.checkpointId = (
                        SELECT checkpoint.checkpointId
                        FROM checkpoint
                        WHERE checkpoint.order = 1
                        AND checkpoint.routeId = IFNULL(attempt.routeId, -1)
                        LIMIT 1
                    )
                    LIMIT 1
                ) IS NULL, 0, 1) AS started,

                IF (attempt.endedByUser = 1 OR DATE(attempt.created) != CURDATE(), 1, 0) AS endedByUser,
                (
                    SELECT ts.time
                    FROM `timestamp` AS ts
                    WHERE ts.attemptId = attempt.attemptId
                    AND ts.checkpointId = (
                        SELECT checkpoint.checkpointId
                        FROM checkpoint
                        WHERE checkpoint.order = 1
                        AND checkpoint.routeId = IFNULL(attempt.routeId, -1)
                        LIMIT 1
                    )
                    LIMIT 1
                ) AS startTime,

                IF(
                    (
                        SELECT `timestamp`.timestampId
                        FROM `timestamp`
                        WHERE `timestamp`.attemptId = attempt.attemptId
                        AND `timestamp`.checkpointId = IFNULL((
                           SELECT checkpoint.checkpointId
                           FROM checkpoint
                           WHERE checkpoint.routeId = IFNULL(attempt.routeId, -1)
                           ORDER BY checkpoint.distance DESC
                           LIMIT 1
                        ), -1)
                        LIMIT 1
                    )
                IS NULL, 0, 1) AS finished,

                (
                    SELECT `timestamp`.time
                    FROM `timestamp`
                    WHERE `timestamp`.attemptId = attempt.attemptId
                    AND `timestamp`.checkpointId = IFNULL((
                       SELECT checkpoint.checkpointId
                       FROM checkpoint
                       WHERE checkpoint.routeId = IFNULL(attempt.routeId, -1)
                       ORDER BY checkpoint.distance DESC
                       LIMIT 1
                    ), -1)
                    LIMIT 1
                ) AS finishTime,

                IF((SELECT COUNT(*)
                   FROM `timestamp`
                   WHERE `timestamp`.attemptId = attempt.attemptId
                ) = (SELECT COUNT(*)
                   FROM checkpoint
                   WHERE checkpoint.routeId = IFNULL(attempt.routeId, 1)
                ), 1, 0) AS completed


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
            $row['completed'] = !!$row['completed'];
            $row['endedByUser'] = !!$row['endedByUser'];
            $row['startTime'] = $row['startTime'] ? strtotime($row['startTime']) : null;
            $row['finishTime'] = $row['finishTime'] ? strtotime($row['finishTime']) : null;

            // Endzeit eintragen, wenn sie nicht vorhanden ist.
            if ($row['endedByUser'] && $row['startTime'] && !$row['finishTime']) {
                $row['finishTime'] = $row['startTime'];
            }
        }

        return $row ?: array();
    }

    protected function _saveTimestamp(int $attemptId, string $code): bool {
        $time = isset($_SERVER["REQUEST_TIME_FLOAT"]) ? (float)$_SERVER["REQUEST_TIME_FLOAT"] : microtime(true);
        $timestamp = (int)floor($time);
        $microtime = (int)round(($time - $timestamp) * 1000000);
        $checkpointId = $this->_getCheckpointId($code, $attemptId);

        if (!$checkpointId) {
            throw new \Exception('Ungültiger Checkpoint');
        }

        // bereits passiert?
        $st = $this->_db->pdo()->prepare('
            SELECT checkpoint.checkpointId
            FROM checkpoint
            INNER JOIN `timestamp` ON checkpoint.checkpointId = `timestamp`.checkpointId
            WHERE `timestamp`.attemptId = :attemptId
            AND checkpoint.`order` >= (SELECT cp.`order` FROM checkpoint AS cp WHERE cp.checkpointId = :checkpointId)
            AND checkpoint.routeId = (SELECT cp.routeId FROM checkpoint AS cp WHERE cp.checkpointId = :checkpointId)
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
     * @param int $attemptId
     * @return int|null
     */
    protected function _getCheckpointId(string $code, int $attemptId): ?int {
        $st = $this->_db->pdo()->prepare('
                SELECT
                    checkpoint.checkpointId,

                    IF((
                        (SELECT attempt.routeId FROM attempt WHERE attempt.attemptId = :attemptId) IS NULL
                        OR
                        (SELECT attempt.routeId FROM attempt WHERE attempt.attemptId = :attemptId) = checkpoint.routeId
                    ), 1, 0) AS checkpointFromRoute

                FROM checkpoint
                WHERE checkpoint.`code` = :code
                ');
        $st->bindParam(':code', $code, \PDO::PARAM_STR);
        $st->bindParam(':attemptId', $attemptId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);

        if (!$row['checkpointFromRoute']) {
            throw new \Exception('Der gescannte Checkpoint ist von einer anderen Route.');
        }

        return $row && $row['checkpointId'] ? (int)$row['checkpointId'] : null;
    }

    protected function _isStartCheckpoint(string $code): bool {
        $st = $this->_db->pdo()->prepare('SELECT checkpointId FROM checkpoint WHERE `code` = :code AND `order` = 1');
        $st->bindParam(':code', $code, \PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['checkpointId'] ? true : false;
    }

    protected function _isEndCheckpoint(string $code): bool {
        $st = $this->_db->pdo()->prepare('
                SELECT checkpoint.`code`
                FROM checkpoint
                WHERE checkpoint.routeId = (SELECT cp.routeId FROM checkpoint AS cp WHERE cp.`code` = :code LIMIT 1)
                ORDER BY `order` DESC
                LIMIT 1');
        $st->bindParam(':code', $code, \PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        unset ($st);
        return $row && $row['code'] === $code;
    }

    /**
     * Setzt dem attempt die Route
     * @param int $attemptId
     * @return void
     */
    protected function _setRouteId(int $attemptId): void {
        $st = $this->_db->pdo()->prepare('
                UPDATE attempt
                SET attempt.routeId = (
                    SELECT checkpoint.routeId
                    FROM `timestamp`
                    INNER JOIN `checkpoint` ON checkpoint.checkpointId = `timestamp`.checkpointId
                    WHERE `timestamp`.attemptId = attempt.attemptId
                    LIMIT 1
                )
                WHERE attempt.attemptId = :attemptId
            ');
        $st->bindParam(':attemptId', $attemptId, \PDO::PARAM_INT);
        $st->execute();
    }

    /**
     * Gibt die Allzeit Bestzeit zurück
     * @param int $checkpointId
     * @param string|null $gender
     * @param int|null $category
     * @return \stdClass|null
     */
    protected function _getCheckpointBest(int $checkpointId, ?string $gender=null, ?int $category=null, $curSaison=false): ?\stdClass {

        $genderWhere = $gender === null ? '' : 'AND attempt.gender = :gender';
        $categoryWhere = $category === null ? '' : 'AND attempt.category = :category';
        $saisonWhere = $curSaison ? 'AND season.`start` <= CURDATE() AND season.`end` >= CURDATE()' : '';

        $st = $this->_db->pdo()->prepare('
            SELECT
                CONCAT(attempt.`name`, \' \', attempt.`familyname`) AS `name`,
                season.`name` AS seasonName,
                TIMEDIFF(
                   `timestamp`.`time`,
                   (
                      SELECT subTimestamp.`time`
                      FROM `timestamp` AS subTimestamp
                      WHERE subTimestamp.attemptId = attempt.attemptId
                      AND subTimestamp.checkpointId = (
                         SELECT cp.checkpointId
                         FROM checkpoint AS cp
                         WHERE cp.`order` = 1
                         AND cp.routeId = IFNULL(attempt.routeId, -1)
                      )
                   )
                ) AS walkTime

            FROM `attempt`
            INNER JOIN `timestamp` ON `timestamp`.attemptId = `attempt`.attemptId
            INNER JOIN checkpoint ON `timestamp`.checkpointId = checkpoint.checkpointId
            INNER JOIN season ON `attempt`.routeId = season.routeId AND season.`start` <= DATE(attempt.created) AND season.`end` >= DATE(attempt.created)

            WHERE checkpoint.checkpointId = :checkpointId
            AND attempt.`name` <> \'\'
            AND attempt.`email` <> \'\'
            ' . $genderWhere . '
            ' . $categoryWhere . '
            ' . $saisonWhere . '
            AND checkpoint.`order` <> 1
            AND (SELECT COUNT(*) FROM `timestamp` AS `tp` WHERE `tp`.attemptId = attempt.attemptId)
                = (SELECT COUNT(*) FROM checkpoint AS cp WHERE cp.routeId = checkpoint.routeId)

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
                CONCAT(attempt.`name`, \' \', attempt.`familyname`) AS `name`,
                TIMEDIFF(
                   `timestamp`.`time`,
                   (
                      SELECT subTimestamp.`time`
                      FROM `timestamp` AS subTimestamp
                      WHERE subTimestamp.attemptId = attempt.attemptId
                      AND subTimestamp.checkpointId = (
                         SELECT checkpoint.checkpointId
                         FROM checkpoint
                         WHERE checkpoint.`order` = 1
                         AND checkpoint.routeId = IFNULL(attempt.routeId, -1)
                         LIMIT 1
                      )
                   )
                ) AS walkTime

            FROM `attempt`
            INNER JOIN `timestamp` ON `timestamp`.attemptId = `attempt`.attemptId
            INNER JOIN checkpoint ON `timestamp`.checkpointId = checkpoint.checkpointId

            WHERE checkpoint.checkpointId = :checkpointId
             AND checkpoint.`order` > 1
             AND attempt.`email` = (SELECT curAttempt.`email` FROM attempt AS curAttempt WHERE curAttempt.attemptId = :currentAttemptId)
             AND (SELECT COUNT(*) FROM `timestamp` WHERE `timestamp`.attemptId = attempt.attemptId) = (SELECT COUNT(*) FROM checkpoint WHERE checkpoint.routeId = attempt.routeId)

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
                  LIMIT 1
               ),(
                  SELECT `timestamp`.`time`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId = (
                        SELECT cp.checkpointId
                        FROM checkpoint AS cp
                        WHERE cp.order = 1
                        AND cp.routeId = route.routeId
                        LIMIT 1
                    )
                  AND `timestamp`.attemptId = :attemptId
               )) AS userTime,

               IF((
                  SELECT `timestamp`.`timestampId`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId = checkpoint.checkpointId
                  AND `timestamp`.attemptId = :attemptId
                  LIMIT 1
               ) IS NULL AND (
                  SELECT `timestamp`.`timestampId`
                  FROM `timestamp`
                  WHERE `timestamp`.checkpointId IN (
                        SELECT cp.checkpointId
                        FROM checkpoint AS cp
                        WHERE cp.order > checkpoint.order
                        AND cp.routeId = route.routeId
                    )
                  AND `timestamp`.attemptId = :attemptId
                  LIMIT 1
               ) IS NOT NULL, 1, 0) AS skipped

            FROM checkpoint
            INNER JOIN route ON checkpoint.routeId = route.routeId
            WHERE route.`routeId` = IFNULL((SELECT attempt.routeId FROM attempt WHERE attempt.attemptId = :attemptId), -1)
            ORDER BY checkpoint.`order` ASC;
        ');
        $st->bindParam(':attemptId', $attemptId, \PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        unset ($st);

        // types
        foreach ($rows as &$row) {
            $row['checkpointId'] = (int)$row['checkpointId'];
            $row['distance'] = (int)$row['distance'];
            $row['altitude'] = (int)$row['altitude'];
            $row['skipped'] = !!$row['skipped'];
        }
        unset ($row);

        return $rows;
    }

    protected function _timeDiff(string $oldTime, string $newTime, ?string $htmlEl=null): string {
        $t1 = explode(':', $oldTime);
        $t2 = explode(':', $newTime);
        if (count($t1) !== 3 || count($t2) !== 3) {
            throw new \Exception('invalid time format');
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

    /**
     * Gibt die Zeitdifferenz in HH:MM:SS zurück
     * @param int $t1
     * @param int $t2
     * @return string
     */
    protected function _timeDiffString(int $t1, int $t2): string {
        $seconds = abs($t1 - $t2);
        $hours = floor($seconds / 3600);
        $seconds -= ($hours * 3600);
        $minutes = floor($seconds / 60);
        $seconds -= ($minutes * 60);

        return str_pad((string)$hours, 2, '0', STR_PAD_LEFT) . ':' .
                str_pad((string)$minutes, 2, '0', STR_PAD_LEFT) . ':' .
                str_pad((string)$seconds, 2, '0', STR_PAD_LEFT);
    }


    protected function _sendMail(array $attempt) {
        $html = $this->_buildMailHtml($this->_getTimeHtml($attempt), $attempt);

        $headers = array();
        $headers['Content-Type'] = 'text/html; charset=UTF-8';
        $headers['Content-Transfer-Encoding'] = 'quoted-printable';
        $headers['From'] = 'PDCS Uphill Challenge <info@pdcs.ch>';

        $subject = $attempt['organizer'] . ' ' . $attempt['challengeName'] . ': ' . $attempt['routeName'];
        mail($attempt['email'], $subject, quoted_printable_encode($html), $headers);
    }


    /**
     * baut das HTML-Email auf.
     * @param string $timeHtml
     * @param array $data
     * @return string
     */
    protected function _buildMailHtml(string $timeHtml, array $data) {
        $mailHtml .= '<html><head>';
        $mailHtml .= '<meta charset="UTF-8">';

        // css laden
        $css = file_get_contents('../resources/css/mail.css');
        $css = str_replace("\n", " ", $css);
        $cnt = 1;
        while ($cnt > 0) {
            $css = str_replace("  ", " ", $css, $cnt);
        }

        $mailHtml .= '<style>';
        $mailHtml .= $css;
        $mailHtml .= '</style>';

        $mailHtml .= '</head><body><header>';
        $mailHtml .= 'Gratulation, ' . htmlspecialchars($data['name']) . '!';
        $mailHtml .= '</header>';

//        body main > div div.times
        $mailHtml .= '<main><div><div class="times">';
        $mailHtml .= '<p>Vielen Dank für deine Teilnahme an der ' . htmlspecialchars($data['challengeName']) .'. ';
        $mailHtml .= 'Nachfolgend deine Zeiten und die Rekordzeiten.<br />';

        if ($data['rankingUrl']) {
            $mailHtml .= 'Die Rangliste findest du auf unserer <a href="' . htmlspecialchars($data['rankingUrl']) . '">Webseite</a>.';
        }
        $mailHtml .= '</p>';

        $mailHtml .= '<p>Bis zum nächsten Versuch!<br />';
        $mailHtml .= htmlspecialchars($data['organizer']) . '</p>';
        $mailHtml .= $timeHtml;
        $mailHtml .= '</div></div>';
        $mailHtml .= '</main></body></html>';
        return $mailHtml;
    }
}
