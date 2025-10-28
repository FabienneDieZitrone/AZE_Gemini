/**
 * Titel: Aggregations-Hilfsfunktionen
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Status: Final
 * Datei: /src/utils/aggregate.ts
 * Beschreibung: Stellt Funktionen zur Aggregierung von Daten bereit, z.B. Zeiteinträge.
 */
import { TimeEntry, AggregatedTimeEntry } from '../types';
import { calculateDurationInSeconds } from './time';

export const aggregateTimeEntries = (entries: TimeEntry[]): AggregatedTimeEntry[] => {
    // Gruppiere Einträge nach Datum und Benutzername, speichere dabei auch die Original-Einträge
    const groupedEntries = entries.reduce((acc, entry) => {
      const key = `${entry.date}-${entry.username}`;
      if (!acc[key]) {
          acc[key] = {
              aggregated: { ...entry, totalSeconds: 0, firstStart: entry.startTime, lastStop: entry.stopTime, pauseSeconds: 0 },
              entries: []
          };
      }

      const currentDuration = calculateDurationInSeconds(entry.startTime, entry.stopTime);
      acc[key].aggregated.totalSeconds += currentDuration;
      acc[key].entries.push(entry);

      if (entry.startTime < acc[key].aggregated.firstStart) acc[key].aggregated.firstStart = entry.startTime;
      if (entry.stopTime > acc[key].aggregated.lastStop) acc[key].aggregated.lastStop = entry.stopTime;

      if(new Date(entry.updatedAt) > new Date(acc[key].aggregated.updatedAt)) {
          acc[key].aggregated.updatedAt = entry.updatedAt;
          acc[key].aggregated.updatedBy = entry.updatedBy;
      }
      if(entry.isUnsynced) {
          acc[key].aggregated.isUnsynced = true;
      }

      return acc;
    }, {} as Record<string, { aggregated: AggregatedTimeEntry; entries: TimeEntry[] }>);

    const aggregatedList = Object.values(groupedEntries).map(group => {
        // Sortiere Einträge nach Startzeit, um Lücken korrekt zu berechnen
        const sortedEntries = group.entries.sort((a, b) => {
            const aTime = new Date(`${a.date}T${a.startTime}`);
            const bTime = new Date(`${b.date}T${b.startTime}`);
            return aTime.getTime() - bTime.getTime();
        });

        // Berechne Pausen als Summe der Lücken zwischen Einträgen
        let pauseSeconds = 0;
        for (let i = 0; i < sortedEntries.length - 1; i++) {
            const currentEntry = sortedEntries[i];
            const nextEntry = sortedEntries[i + 1];

            // Erstelle DateTime-Objekte für Stop-Zeit des aktuellen und Start-Zeit des nächsten Eintrags
            let currentStopTime = new Date(`${currentEntry.date}T${currentEntry.stopTime}`);
            const nextStartTime = new Date(`${nextEntry.date}T${nextEntry.startTime}`);

            // WICHTIG: Wenn stopTime < startTime, dann wurde über Mitternacht gearbeitet
            if (currentEntry.stopTime < currentEntry.startTime) {
                // Stop-Zeit liegt am nächsten Tag
                currentStopTime = new Date(currentStopTime.getTime() + 86400000); // +1 Tag
            }

            // Berechne Lücke zwischen Stop des aktuellen und Start des nächsten Eintrags
            const gapSeconds = (nextStartTime.getTime() - currentStopTime.getTime()) / 1000;

            // Nur positive Lücken addieren (negative würden Überlappungen bedeuten)
            if (gapSeconds > 0) {
                pauseSeconds += gapSeconds;
            }
        }

        group.aggregated.pauseSeconds = pauseSeconds;
        return group.aggregated;
    });

    // Sort entries by date (newest first) and then by start time
    return aggregatedList.sort((a, b) => {
        if (a.date > b.date) return -1;
        if (a.date < b.date) return 1;
        if (a.firstStart > b.firstStart) return 1;
        if (a.firstStart < b.firstStart) return -1;
        return 0;
    });
};
