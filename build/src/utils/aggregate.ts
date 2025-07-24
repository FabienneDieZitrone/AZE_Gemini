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
    const aggregatedMap = entries.reduce((acc, entry) => {
      const key = `${entry.date}-${entry.username}`;
      if (!acc[key]) {
          acc[key] = { ...entry, totalSeconds: 0, firstStart: entry.startTime, lastStop: entry.stopTime, pauseSeconds: 0 };
      }
      const currentDuration = calculateDurationInSeconds(entry.startTime, entry.stopTime);
      acc[key].totalSeconds += currentDuration;

      if (entry.startTime < acc[key].firstStart) acc[key].firstStart = entry.startTime;
      if (entry.stopTime > acc[key].lastStop) acc[key].lastStop = entry.stopTime;
      
      if(new Date(entry.updatedAt) > new Date(acc[key].updatedAt)) {
          acc[key].updatedAt = entry.updatedAt;
          acc[key].updatedBy = entry.updatedBy;
      }
      if(entry.isUnsynced) {
          acc[key].isUnsynced = true;
      }
      
      return acc;
    }, {} as Record<string, AggregatedTimeEntry>);

    const aggregatedList = Object.values(aggregatedMap);
    
    aggregatedList.forEach(agg => {
        const totalDuration = calculateDurationInSeconds(agg.firstStart, agg.lastStop);
        agg.pauseSeconds = totalDuration - agg.totalSeconds;
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
