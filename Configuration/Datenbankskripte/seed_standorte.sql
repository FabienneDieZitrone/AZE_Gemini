--
-- seed_standorte.sql
-- Beispieldaten fuer Standorte
--
INSERT INTO Standorte (Id, Bezeichnung, IPRangeStart, IPRangeEnd)
VALUES
  (1, 'Hauptsitz Berlin', '192.168.1.0', '192.168.1.255'),
  (2, 'Filiale Hamburg', '192.168.2.0', '192.168.2.255');
