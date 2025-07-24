/**
 * Titel: Dashboard-Ansicht
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/DashboardView.tsx
 */
import React, { useMemo } from 'react';
import { TimeEntry, User } from '../types';
import { calculateDurationInSeconds } from '../utils/time';

export const DashboardView: React.FC<{
    onBack: () => void;
    timeEntries: TimeEntry[];
    users: User[];
    currentUser: User;
    locations: string[];
}> = ({ onBack, timeEntries, users, currentUser, locations }) => {
    const isSupervisor = ['Admin', 'Bereichsleiter', 'Standortleiter'].includes(currentUser.role);

    const chartData = useMemo(() => {
        // Logik für Supervisoren
        if (isSupervisor) {
            const subordinateEntries = timeEntries.filter(e => e.userId !== currentUser.id);
            const hoursPerUser = users
                .filter(user => user.id !== currentUser.id)
                .map(user => {
                    const userEntries = subordinateEntries.filter(e => e.userId === user.id);
                    const totalSeconds = userEntries.reduce((sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime), 0);
                    return { name: user.name, hours: totalSeconds / 3600 };
                }).filter(d => d.hours > 0);
            
            const hoursPerLocation = locations.map(loc => {
                const locEntries = subordinateEntries.filter(e => e.location === loc);
                const totalSeconds = locEntries.reduce((sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime), 0);
                return { name: loc, hours: totalSeconds / 3600 };
            }).filter(d => d.hours > 0);

            return { type: 'supervisor' as const, hoursPerUser, hoursPerLocation };
        } 
        // Logik für normale Mitarbeiter
        else {
            const userEntries = timeEntries.filter(e => e.userId === currentUser.id);
            const hoursPerDay = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"].map((day, index) => {
                 const dayEntries = userEntries.filter(e => new Date(e.date + "T00:00:00").getDay() === (index + 1) % 7);
                 const totalSeconds = dayEntries.reduce((sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime), 0);
                 return { name: day, hours: totalSeconds / 3600 };
            });

            const hoursPerLocation = locations.map(loc => {
                const locEntries = userEntries.filter(e => e.location === loc);
                const totalSeconds = locEntries.reduce((sum, e) => sum + calculateDurationInSeconds(e.startTime, e.stopTime), 0);
                return { name: loc, hours: totalSeconds / 3600 };
            }).filter(d => d.hours > 0);
            
            return { type: 'employee' as const, hoursPerDay, hoursPerLocation };
        }
    }, [timeEntries, users, currentUser, isSupervisor, locations]);

    const pieChartGradient = useMemo(() => {
        const data = chartData.hoursPerLocation;
        if(!data) return 'var(--light-grey-color)';

        const total = data.reduce((sum, d) => sum + d.hours, 0);
        if (total === 0) return 'var(--light-grey-color)';
        const colors = ['#0056b3', '#28a745', '#ffc107', '#dc3545'];
        let currentAngle = 0;
        const gradientParts = data.map((loc, index) => {
            const percentage = (loc.hours / total) * 100;
            const angle = (percentage / 100) * 360;
            const part = `${colors[index % colors.length]} ${currentAngle}deg ${currentAngle + angle}deg`;
            currentAngle += angle;
            return part;
        });
        return `conic-gradient(${gradientParts.join(', ')})`;
    }, [chartData.hoursPerLocation]);

    const maxHours = useMemo(() => {
        const data = chartData.type === 'supervisor' ? chartData.hoursPerUser : chartData.hoursPerDay;
        return Math.max(...(data?.map(u => u.hours) || [0]), 0) || 1
    }, [chartData]);


    return (
        <div className="view-container">
            <header className="view-header">
                <h2>{isSupervisor ? 'Dashboard für Vorgesetzte' : 'Mein persönliches Dashboard'}</h2>
            </header>

            <div className="dashboard-grid">
                <div className="chart-container">
                    <h3>{isSupervisor ? 'Stunden pro Mitarbeiter' : 'Meine Stunden pro Wochentag'}</h3>
                    <div className="bar-chart">
                        {(chartData.type === 'supervisor' ? chartData.hoursPerUser : chartData.hoursPerDay)?.map(item => (
                            <div key={item.name} className="bar-wrapper">
                                <div className="bar" style={{ height: `${(item.hours / maxHours) * 100}%` }}>
                                    <span className="bar-label">{item.hours.toFixed(1)}h</span>
                                </div>
                                <span className="bar-name">{item.name}</span>
                            </div>
                        ))}
                    </div>
                </div>
                 <div className="chart-container">
                    <h3>{isSupervisor ? 'Arbeitszeit pro Standort (Team)' : 'Meine Arbeitsorte'}</h3>
                    <div className="pie-chart-wrapper">
                       <div className="pie-chart" style={{ background: pieChartGradient }}></div>
                       <div className="pie-legend">
                           {chartData.hoursPerLocation?.map((loc, index) => (
                               <div key={loc.name} className="legend-item">
                                   <span className="legend-color" style={{ backgroundColor: ['#0056b3', '#28a745', '#ffc107', '#dc3545'][index % 4] }}></span>
                                   {loc.name} ({loc.hours.toFixed(1)}h)
                               </div>
                           ))}
                       </div>
                    </div>
                </div>
            </div>

            <footer className="view-footer">
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
