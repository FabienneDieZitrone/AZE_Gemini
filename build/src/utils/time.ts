/**
 * Titel: Zeit-Hilfsfunktionen
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Status: Final
 * Datei: /src/utils/time.ts
 * Beschreibung: Sammlung von Hilfsfunktionen für Datums- und Zeitberechnungen.
 */

export function getStartOfWeek(d: Date): Date {
  d = new Date(d);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1); // adjust when day is sunday
  return new Date(d.setDate(diff));
}

export const formatTime = (totalSeconds: number, showSeconds = true): string => {
    if (isNaN(totalSeconds)) return "00:00";
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = Math.floor(totalSeconds % 60);
    
    const paddedHours = hours.toString().padStart(2, '0');
    const paddedMinutes = minutes.toString().padStart(2, '0');
    
    if (!showSeconds) {
        return `${paddedHours}:${paddedMinutes}`;
    }
    const paddedSeconds = seconds.toString().padStart(2, '0');
    return `${paddedHours}:${paddedMinutes}:${paddedSeconds}`;
};

export const calculateDurationInSeconds = (start: string, end: string): number => {
  if (!start || !end) return 0;
  const startTime = new Date(`1970-01-01T${start}Z`);
  const endTime = new Date(`1970-01-01T${end}Z`);
  if (isNaN(startTime.getTime()) || isNaN(endTime.getTime())) return 0;
  return Math.round((endTime.getTime() - startTime.getTime()) / 1000);
};
