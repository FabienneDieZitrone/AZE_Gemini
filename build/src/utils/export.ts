/**
 * Titel: Export-Hilfsfunktionen
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Status: Final
 * Datei: /src/utils/export.ts
 * Beschreibung: Kapselt die Logik zum Exportieren von Daten als CSV und PDF.
 */
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { AggregatedTimeEntry, MasterData } from '../types';
import { formatTime } from './time';
import { TIME } from '../constants';

export const exportToCsv = (data: AggregatedTimeEntry[], masterData: Record<number, MasterData>) => {
    const headers = ['Username', 'Datum', 'Startzeit', 'Stoppzeit', 'Gesamtzeit', 'Pause', 'Soll/Ist-Diff.', 'Standort', 'Rolle'];
    const rows = data.map(entry => {
        const userMasterData = masterData[entry.userId];
        const dailySollTime = userMasterData && userMasterData.workdays.length > 0 ? (userMasterData.weeklyHours / userMasterData.workdays.length) * TIME.SECONDS_PER_HOUR : 0;
        const diffSeconds = entry.totalSeconds - dailySollTime;
        const diffSign = diffSeconds >= 0 ? '+' : '-';
        
        return [
            entry.username,
            new Date(entry.date + "T00:00:00").toLocaleDateString('de-DE'),
            entry.firstStart,
            entry.lastStop,
            formatTime(entry.totalSeconds),
            formatTime(entry.pauseSeconds),
            `${diffSign}${formatTime(Math.abs(diffSeconds))}`,
            entry.location,
            entry.role
        ].join(';');
    });

    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(';'), ...rows].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `arbeitszeiten_export_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

export const exportToPdf = (data: AggregatedTimeEntry[], masterData: Record<number, MasterData>) => {
    const doc = new jsPDF();
    
    const head = [['Username', 'Datum', 'Startzeit', 'Stoppzeit', 'Gesamtzeit', 'Pause', 'Soll/Ist-Diff.', 'Standort']];
    const body = data.map(entry => {
        const userMasterData = masterData[entry.userId];
        const dailySollTime = userMasterData && userMasterData.workdays.length > 0 ? (userMasterData.weeklyHours / userMasterData.workdays.length) * TIME.SECONDS_PER_HOUR : 0;
        const diffSeconds = entry.totalSeconds - dailySollTime;
        const diffSign = diffSeconds >= 0 ? '+' : '-';

        return [
            entry.username,
            new Date(entry.date + "T00:00:00").toLocaleDateString('de-DE'),
            entry.firstStart,
            entry.lastStop,
            formatTime(entry.totalSeconds),
            formatTime(entry.pauseSeconds),
            `${diffSign}${formatTime(Math.abs(diffSeconds))}`,
            entry.location
        ];
    });

    autoTable(doc, {
        head: head,
        body: body,
        headStyles: { fillColor: [0, 86, 179] }, // MP Blue
    });
    
    doc.save(`arbeitszeiten_export_${new Date().toISOString().split('T')[0]}.pdf`);
};
