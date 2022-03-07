-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 16. Dez 2021 um 13:11
-- Server-Version: 5.7.26-log-cll-lve
-- PHP-Version: 7.3.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Datenbank: `lubuch_runup`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `attempt`
--

CREATE TABLE `attempt` (
  `attemptId` int(10) UNSIGNED NOT NULL,
  `sessionId` varchar(50) NOT NULL DEFAULT '',
  `category` tinyint(4) NOT NULL DEFAULT '0' COMMENT '1. Fussgänger 2. Leicht 3. Schwer',
  `gender` char(1) NOT NULL DEFAULT '' COMMENT 'M / W',
  `name` varchar(50) NOT NULL DEFAULT '',
  `familyname` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(50) NOT NULL DEFAULT '',
  `userAgent` varchar(255) NOT NULL DEFAULT '',
  `ipAddress` varchar(100) NOT NULL DEFAULT '',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `endedByUser` tinyint(4) NOT NULL DEFAULT '0',
  `routeId` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `category`
--

CREATE TABLE `category` (
  `categoryId` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 NOT NULL DEFAULT '',
  `shortcut` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `checkpoint`
--

CREATE TABLE `checkpoint` (
  `checkpointId` int(10) UNSIGNED NOT NULL,
  `routeId` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `order` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `altitude` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `distance` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `code` varchar(32) DEFAULT '',
  `name` varchar(50) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `route`
--

CREATE TABLE `route` (
  `routeId` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '',
  `challengeName` varchar(50) NOT NULL DEFAULT '',
  `organizer` varchar(50) NOT NULL DEFAULT '',
  `rankingUrl` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `timestamp`
--

CREATE TABLE `timestamp` (
  `timestampId` int(10) UNSIGNED NOT NULL,
  `attemptId` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `checkpointId` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `microseconds` mediumint(8) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `attempt`
--
ALTER TABLE `attempt`
  ADD PRIMARY KEY (`attemptId`),
  ADD KEY `sessionId` (`sessionId`),
  ADD KEY `FK_attempt_route` (`routeId`);

--
-- Indizes für die Tabelle `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`categoryId`);

--
-- Indizes für die Tabelle `checkpoint`
--
ALTER TABLE `checkpoint`
  ADD PRIMARY KEY (`checkpointId`),
  ADD UNIQUE KEY `routeId_order` (`routeId`,`order`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indizes für die Tabelle `route`
--
ALTER TABLE `route`
  ADD PRIMARY KEY (`routeId`);

--
-- Indizes für die Tabelle `timestamp`
--
ALTER TABLE `timestamp`
  ADD PRIMARY KEY (`timestampId`),
  ADD UNIQUE KEY `attemptId_checkpointId` (`attemptId`,`checkpointId`),
  ADD KEY `FK_timestamp_checkpoint` (`checkpointId`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `attempt`
--
ALTER TABLE `attempt`
  MODIFY `attemptId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `category`
--
ALTER TABLE `category`
  MODIFY `categoryId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `checkpoint`
--
ALTER TABLE `checkpoint`
  MODIFY `checkpointId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `route`
--
ALTER TABLE `route`
  MODIFY `routeId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `timestamp`
--
ALTER TABLE `timestamp`
  MODIFY `timestampId` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `attempt`
--
ALTER TABLE `attempt`
  ADD CONSTRAINT `FK_attempt_route` FOREIGN KEY (`routeId`) REFERENCES `route` (`routeId`);

--
-- Constraints der Tabelle `checkpoint`
--
ALTER TABLE `checkpoint`
  ADD CONSTRAINT `FK_checkpoint_route` FOREIGN KEY (`routeId`) REFERENCES `route` (`routeId`);

--
-- Constraints der Tabelle `timestamp`
--
ALTER TABLE `timestamp`
  ADD CONSTRAINT `FK_timestamp_attempt` FOREIGN KEY (`attemptId`) REFERENCES `attempt` (`attemptId`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_timestamp_checkpoint` FOREIGN KEY (`checkpointId`) REFERENCES `checkpoint` (`checkpointId`);
COMMIT;
