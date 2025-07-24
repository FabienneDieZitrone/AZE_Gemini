-- Titel: Datenbank-Schema für Arbeitszeiterfassung (AZE)
-- Version: 1.4
-- Letzte Aktualisierung: 20.07.2025
-- Autor: MP-IT
-- Status: Final & Corrected
-- Datei: /schema.sql
-- Beschreibung: Korrigiert die `users`-Tabelle, um zwischen `username` (eindeutige E-Mail) und `display_name` (Anzeigename) zu unterscheiden.
--              Korrigiert Beispieldaten in `users` für `azure_oid`.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `approval_requests`
--

CREATE TABLE `approval_requests` (
  `id` varchar(36) NOT NULL,
  `type` enum('edit','delete') NOT NULL,
  `entry_id` int(11) NOT NULL,
  `original_entry_data` json NOT NULL,
  `new_data` json DEFAULT NULL,
  `reason_data` json DEFAULT NULL,
  `requested_by` varchar(255) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `resolved_by` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `global_settings`
--

CREATE TABLE `global_settings` (
  `id` int(11) NOT NULL,
  `overtime_threshold` decimal(5,2) NOT NULL DEFAULT 5.00,
  `change_reasons` json NOT NULL,
  `locations` json NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `global_settings`
--

INSERT INTO `global_settings` (`id`, `overtime_threshold`, `change_reasons`, `locations`) VALUES
(1, 5.00, '[\"Arzttermin\",\"Dienstgang\",\"Vergessen einzustempeln\",\"Vergessen auszustempeln\",\"Technische Störung\",\"Sonstige\"]', '[\"Zentrale Berlin\",\"Standort Hamburg\",\"Standort Köln\"]');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `master_data`
--

CREATE TABLE `master_data` (
  `user_id` int(11) NOT NULL,
  `weekly_hours` decimal(5,2) NOT NULL DEFAULT 40.00,
  `workdays` json NOT NULL,
  `can_work_from_home` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `master_data`
--

INSERT INTO `master_data` (`user_id`, `weekly_hours`, `workdays`, `can_work_from_home`) VALUES
(1, 40.00, '[\"Mo\", \"Di\", \"Mi\", \"Do\", \"Fr\"]', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `time_entries`
--

CREATE TABLE `time_entries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `stop_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL COMMENT 'Die eindeutige E-Mail-Adresse des Benutzers.',
  `display_name` varchar(255) NOT NULL COMMENT 'Der Anzeigename des Benutzers, kann Duplikate enthalten.',
  `role` enum('Admin','Bereichsleiter','Standortleiter','Mitarbeiter','Honorarkraft') NOT NULL DEFAULT 'Mitarbeiter',
  `azure_oid` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- ####################################################################################
-- # WICHTIGER HINWEIS ZUR `users`-TABELLE:
-- # Die Spalte `azure_oid` muss die eindeutige "Object ID" (OID) des Benutzers aus Azure Active Directory enthalten.
-- # Sie finden diese ID im Azure-Portal unter "Benutzer" -> (Benutzer auswählen) -> "Übersicht".
-- # Es ist NICHT die "Anwendungs-ID" (Client-ID) der App-Registrierung.
-- # Beispiel-OID: a1b2c3d4-e5f6-7890-1234-567890abcdef
-- ####################################################################################
--

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `username`, `display_name`, `role`, `azure_oid`, `created_at`) VALUES
(1, 'max.mustermann@mikropartner.de', 'Max Mustermann', 'Admin', 'IHRE_ECHTE_AZURE_OID_HIER_EINFUEGEN', '2024-11-12 10:00:00');

--
-- Indizes für die Tabelle `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entry_id` (`entry_id`);

--
-- Indizes für die Tabelle `global_settings`
--
ALTER TABLE `global_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `master_data`
--
ALTER TABLE `master_data`
  ADD PRIMARY KEY (`user_id`);

--
-- Indizes für die Tabelle `time_entries`
--
ALTER TABLE `time_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `date` (`date`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `azure_oid` (`azure_oid`);

--
-- AUTO_INCREMENT für Tabelle `time_entries`
--
ALTER TABLE `time_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints der Tabelle `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD CONSTRAINT `approval_requests_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `time_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `master_data`
--
ALTER TABLE `master_data`
  ADD CONSTRAINT `master_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `time_entries`
--
ALTER TABLE `time_entries`
  ADD CONSTRAINT `time_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;