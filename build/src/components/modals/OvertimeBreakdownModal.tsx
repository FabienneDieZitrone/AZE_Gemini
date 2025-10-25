/**
 * Überstunden-Breakdown Modal
 * Zeigt detaillierte Übersicht über gearbeitete Stunden
 * - Täglich: Soll vs. Ist
 * - Wöchentlich: Summen pro Woche
 * - Monatlich: Summen pro Monat
 * - Kumulativ: Gesamtsumme aller Überstunden
 */
import React, { useMemo } from 'react';
import { TimeEntry, MasterData } from '../../types';
import { calculateDurationInSeconds } from '../../utils/time';
import { TIME } from '../../constants';
import './OvertimeBreakdownModal.css';

interface OvertimeBreakdownModalProps {
  timeEntries: TimeEntry[];
  userId: number;
  masterData: MasterData;
  onClose: () => void;
}

interface DayTotal {
  date: string;
  dayName: string;
  actualSeconds: number;
  shouldSeconds: number;
  differenceSeconds: number;
  weekNumber: number;
  monthKey: string;
}

interface WeekTotal {
  weekNumber: number;
  yearWeek: string;
  actualSeconds: number;
  shouldSeconds: number;
  differenceSeconds: number;
}

interface MonthTotal {
  monthKey: string;
  monthName: string;
  actualSeconds: number;
  shouldSeconds: number;
  differenceSeconds: number;
}

export const OvertimeBreakdownModal: React.FC<OvertimeBreakdownModalProps> = ({
  timeEntries,
  userId,
  masterData,
  onClose
}) => {
  const { dailyTotals, weeklyTotals, monthlyTotals, cumulativeTotal } = useMemo(() => {
    const md = masterData as any;
    if (!md || typeof md !== 'object') {
      return { dailyTotals: [], weeklyTotals: [], monthlyTotals: [], cumulativeTotal: 0 };
    }

    const workdays: string[] = Array.isArray(md.workdays) ? md.workdays : [];
    const flexible: boolean = !!md.flexibleWorkdays;
    const dailyHours: Record<string, number> = md.dailyHours || {};
    const weeklyHours: number = md.weeklyHours || 40;

    const dayNameByIndex: Record<number, string> = {
      0: 'So', 1: 'Mo', 2: 'Di', 3: 'Mi', 4: 'Do', 5: 'Fr', 6: 'Sa'
    };
    const workdaySet = new Set(workdays);

    // Group time entries by date
    const entriesByDate = timeEntries
      .filter(e => e.userId === userId)
      .reduce((acc, entry) => {
        const duration = calculateDurationInSeconds(entry.startTime, entry.stopTime);
        acc[entry.date] = (acc[entry.date] || 0) + duration;
        return acc;
      }, {} as Record<string, number>);

    // Calculate daily totals with week and month info
    const daily: DayTotal[] = [];
    for (const date in entriesByDate) {
      const d = new Date(date + 'T00:00:00');
      const dayName = dayNameByIndex[d.getDay()];
      const actual = entriesByDate[date];

      // Calculate should-time
      let should = 0;
      if (workdaySet.has(dayName)) {
        if (flexible) {
          should = (weeklyHours / workdays.length) * TIME.SECONDS_PER_HOUR;
        } else {
          const sollHours = dailyHours?.[dayName] ?? (weeklyHours / workdays.length);
          should = sollHours * TIME.SECONDS_PER_HOUR;
        }
      }

      // Get week number (ISO week)
      const weekNumber = getISOWeek(d);
      const year = d.getFullYear();

      // Get month key (YYYY-MM)
      const monthKey = `${year}-${String(d.getMonth() + 1).padStart(2, '0')}`;

      daily.push({
        date,
        dayName,
        actualSeconds: actual,
        shouldSeconds: should,
        differenceSeconds: actual - should,
        weekNumber,
        monthKey
      });
    }

    // Sort daily by date (newest first)
    daily.sort((a, b) => b.date.localeCompare(a.date));

    // Calculate weekly totals
    const weeklyMap: Record<string, WeekTotal> = {};
    for (const day of daily) {
      const d = new Date(day.date + 'T00:00:00');
      const year = d.getFullYear();
      const yearWeek = `${year}-W${String(day.weekNumber).padStart(2, '0')}`;

      if (!weeklyMap[yearWeek]) {
        weeklyMap[yearWeek] = {
          weekNumber: day.weekNumber,
          yearWeek,
          actualSeconds: 0,
          shouldSeconds: 0,
          differenceSeconds: 0
        };
      }

      weeklyMap[yearWeek].actualSeconds += day.actualSeconds;
      weeklyMap[yearWeek].shouldSeconds += day.shouldSeconds;
      weeklyMap[yearWeek].differenceSeconds += day.differenceSeconds;
    }
    const weekly = Object.values(weeklyMap).sort((a, b) => b.yearWeek.localeCompare(a.yearWeek));

    // Calculate monthly totals
    const monthlyMap: Record<string, MonthTotal> = {};
    for (const day of daily) {
      if (!monthlyMap[day.monthKey]) {
        const monthName = formatMonthName(day.monthKey);
        monthlyMap[day.monthKey] = {
          monthKey: day.monthKey,
          monthName,
          actualSeconds: 0,
          shouldSeconds: 0,
          differenceSeconds: 0
        };
      }

      monthlyMap[day.monthKey].actualSeconds += day.actualSeconds;
      monthlyMap[day.monthKey].shouldSeconds += day.shouldSeconds;
      monthlyMap[day.monthKey].differenceSeconds += day.differenceSeconds;
    }
    const monthly = Object.values(monthlyMap).sort((a, b) => b.monthKey.localeCompare(a.monthKey));

    // Calculate cumulative total
    const cumulative = daily.reduce((sum, day) => sum + day.differenceSeconds, 0);

    return {
      dailyTotals: daily,
      weeklyTotals: weekly,
      monthlyTotals: monthly,
      cumulativeTotal: cumulative
    };
  }, [timeEntries, userId, masterData]);

  const formatHours = (seconds: number): string => {
    const hours = Math.abs(seconds) / TIME.SECONDS_PER_HOUR;
    const sign = seconds >= 0 ? '+' : '-';
    return `${sign}${hours.toFixed(2)}h`;
  };

  const formatHoursNoSign = (seconds: number): string => {
    const hours = seconds / TIME.SECONDS_PER_HOUR;
    return `${hours.toFixed(2)}h`;
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content overtime-breakdown-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>Überstunden-Übersicht</h2>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>

        <div className="modal-body">
          {/* Cumulative Total - Prominent Display */}
          <div className="cumulative-total-card">
            <h3>Gesamt-Überstunden</h3>
            <div className={`cumulative-value ${cumulativeTotal >= 0 ? 'positive' : 'negative'}`}>
              {formatHours(cumulativeTotal)}
            </div>
            <p className="cumulative-hint">
              Summe aller Überstunden
            </p>
          </div>

          {/* Daily Totals */}
          <div className="breakdown-section">
            <h3>Tägliche Übersicht</h3>
            <table className="breakdown-table">
              <thead>
                <tr>
                  <th>Datum</th>
                  <th>Tag</th>
                  <th>Soll</th>
                  <th>Ist</th>
                  <th>Differenz</th>
                </tr>
              </thead>
              <tbody>
                {dailyTotals.slice(0, 30).map((day) => (
                  <tr key={day.date}>
                    <td>{day.date}</td>
                    <td><strong>{day.dayName}</strong></td>
                    <td>{formatHoursNoSign(day.shouldSeconds)}</td>
                    <td>{formatHoursNoSign(day.actualSeconds)}</td>
                    <td className={day.differenceSeconds >= 0 ? 'positive' : 'negative'}>
                      {formatHours(day.differenceSeconds)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {dailyTotals.length > 30 && (
              <p className="table-hint">Zeigt die letzten 30 Tage</p>
            )}
          </div>

          {/* Weekly Totals */}
          <div className="breakdown-section">
            <h3>Wöchentliche Übersicht</h3>
            <table className="breakdown-table">
              <thead>
                <tr>
                  <th>Woche</th>
                  <th>Soll</th>
                  <th>Ist</th>
                  <th>Differenz</th>
                </tr>
              </thead>
              <tbody>
                {weeklyTotals.slice(0, 12).map((week) => (
                  <tr key={week.yearWeek}>
                    <td><strong>{week.yearWeek}</strong></td>
                    <td>{formatHoursNoSign(week.shouldSeconds)}</td>
                    <td>{formatHoursNoSign(week.actualSeconds)}</td>
                    <td className={week.differenceSeconds >= 0 ? 'positive' : 'negative'}>
                      {formatHours(week.differenceSeconds)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {weeklyTotals.length > 12 && (
              <p className="table-hint">Zeigt die letzten 12 Wochen</p>
            )}
          </div>

          {/* Monthly Totals */}
          <div className="breakdown-section">
            <h3>Monatliche Übersicht</h3>
            <table className="breakdown-table">
              <thead>
                <tr>
                  <th>Monat</th>
                  <th>Soll</th>
                  <th>Ist</th>
                  <th>Differenz</th>
                </tr>
              </thead>
              <tbody>
                {monthlyTotals.map((month) => (
                  <tr key={month.monthKey}>
                    <td><strong>{month.monthName}</strong></td>
                    <td>{formatHoursNoSign(month.shouldSeconds)}</td>
                    <td>{formatHoursNoSign(month.actualSeconds)}</td>
                    <td className={month.differenceSeconds >= 0 ? 'positive' : 'negative'}>
                      {formatHours(month.differenceSeconds)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="modal-footer">
          <button className="modal-button secondary" onClick={onClose}>Schließen</button>
        </div>
      </div>
    </div>
  );
};

// Helper function to get ISO week number
function getISOWeek(date: Date): number {
  const d = new Date(date.getTime());
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + 4 - (d.getDay() || 7));
  const yearStart = new Date(d.getFullYear(), 0, 1);
  return Math.ceil((((d.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
}

// Helper function to format month name
function formatMonthName(monthKey: string): string {
  const [year, month] = monthKey.split('-');
  const monthNames = [
    'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
  ];
  return `${monthNames[parseInt(month) - 1]} ${year}`;
}
