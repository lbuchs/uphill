-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server Version:               10.1.11-MariaDB-log - mariadb.org binary distribution
-- Server Betriebssystem:        Win64
-- HeidiSQL Version:             11.1.0.6116
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Exportiere Struktur von Tabelle pdcs_runup.attempt
CREATE TABLE IF NOT EXISTS `attempt` (
  `attemptId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sessionId` varchar(50) NOT NULL DEFAULT '',
  `category` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1. Fussgänger 2. Leicht 3. Schwer',
  `gender` char(1) NOT NULL DEFAULT '' COMMENT 'M / W',
  `name` varchar(50) NOT NULL DEFAULT '',
  `familyname` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(50) NOT NULL DEFAULT '',
  `userAgent` varchar(255) NOT NULL DEFAULT '',
  `ipAddress` varchar(100) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `endedByUser` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`attemptId`),
  KEY `sessionId` (`sessionId`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;

-- Exportiere Daten aus Tabelle pdcs_runup.attempt: ~3 rows (ungefähr)
DELETE FROM `attempt`;
/*!40000 ALTER TABLE `attempt` DISABLE KEYS */;
INSERT INTO `attempt` (`attemptId`, `sessionId`, `category`, `gender`, `name`, `email`, `userAgent`, `ipAddress`, `created`, `endedByUser`) VALUES
	(2, 'a7de11e6766bb7e3e9c949ad4c180d7b', 1, '', 'Lukas Buchs', 'lukasbuchs@gmail.com', '', '', '2020-11-10 12:38:23', 0),
	(3, 'a7de11e6766bb7e3e9c949ad4c180d7b', 1, '', '', '', '', '', '2020-11-12 11:26:46', 0),
	(10, 'ao7ns1o695gnh990taj8s6gf53', 1, '', '', '', 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1', '172.30.10.104', '2020-11-12 14:27:37', 0);
/*!40000 ALTER TABLE `attempt` ENABLE KEYS */;

-- Exportiere Struktur von Tabelle pdcs_runup.checkpoint
CREATE TABLE IF NOT EXISTS `checkpoint` (
  `checkpointId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `altitude` smallint(5) unsigned NOT NULL DEFAULT '0',
  `distance` smallint(5) unsigned NOT NULL DEFAULT '0',
  `code` varchar(32) DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`checkpointId`),
  UNIQUE KEY `order` (`order`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;

-- Exportiere Daten aus Tabelle pdcs_runup.checkpoint: ~4 rows (ungefähr)
DELETE FROM `checkpoint`;
/*!40000 ALTER TABLE `checkpoint` DISABLE KEYS */;
INSERT INTO `checkpoint` (`checkpointId`, `order`, `altitude`, `distance`, `code`, `name`) VALUES
	(1, 1, 734, 0, '43cb364ea476a0ef4d149a5076fc4320', 'Start Boden'),
	(2, 2, 944, 1350, '3676f391dd06caffbf862d2550fbfe9a', 'Oberi Spittelweid'),
	(3, 3, 1135, 2290, '84cbf5ddfae24fbaca5c365522a420de', 'Möntschelenwald'),
	(4, 4, 1410, 3710, 'b4146af594193f6ad7054f342cb1ba6c', 'Ziel Möntschelenalp');
/*!40000 ALTER TABLE `checkpoint` ENABLE KEYS */;

-- Exportiere Struktur von Tabelle pdcs_runup.timestamp
CREATE TABLE IF NOT EXISTS `timestamp` (
  `timestampId` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attemptId` int(10) unsigned NOT NULL DEFAULT '0',
  `checkpointId` int(10) unsigned NOT NULL DEFAULT '0',
  `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `microseconds` mediumint(8) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`timestampId`),
  UNIQUE KEY `attemptId_checkpointId` (`attemptId`,`checkpointId`),
  KEY `FK_timestamp_checkpoint` (`checkpointId`),
  CONSTRAINT `FK_timestamp_attempt` FOREIGN KEY (`attemptId`) REFERENCES `attempt` (`attemptId`) ON DELETE CASCADE,
  CONSTRAINT `FK_timestamp_checkpoint` FOREIGN KEY (`checkpointId`) REFERENCES `checkpoint` (`checkpointId`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

-- Exportiere Daten aus Tabelle pdcs_runup.timestamp: ~4 rows (ungefähr)
DELETE FROM `timestamp`;
/*!40000 ALTER TABLE `timestamp` DISABLE KEYS */;
INSERT INTO `timestamp` (`timestampId`, `attemptId`, `checkpointId`, `time`, `microseconds`) VALUES
	(1, 2, 1, '2020-11-10 12:00:00', 0),
	(2, 2, 2, '2020-11-10 13:00:00', 0),
	(4, 2, 3, '2020-11-10 14:00:00', 0),
	(5, 2, 4, '2020-11-10 15:00:00', 0);
/*!40000 ALTER TABLE `timestamp` ENABLE KEYS */;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
