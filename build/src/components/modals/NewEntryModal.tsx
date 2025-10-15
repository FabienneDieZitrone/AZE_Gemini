import React, { useState } from 'react';
import { Role, ReasonData } from '../../types';

type Props = {
  onClose: () => void;
  onSubmit: (data: { date: string; startTime: string; stopTime: string; location: string; role: Role; reasonData: ReasonData }) => void;
  locations: string[];
  defaultRole: Role;
  changeReasons: string[];
};

export const NewEntryModal: React.FC<Props> = ({ onClose, onSubmit, locations, defaultRole, changeReasons }) => {
  const today = new Date().toISOString().split('T')[0];
  const [date, setDate] = useState<string>(today);
  const [startTime, setStartTime] = useState<string>('08:00:00');
  const [stopTime, setStopTime] = useState<string>('16:00:00');
  const [location, setLocation] = useState<string>(locations[0] || '');
  const [role, setRole] = useState<Role>(defaultRole);
  const [reason, setReason] = useState<string>(changeReasons[0] || '');
  const [details, setDetails] = useState<string>('Neuer Eintrag erforderlich');

  const handleSubmit = () => {
    if (!date || !startTime || !stopTime) return;
    onSubmit({ date, startTime, stopTime, location, role, reasonData: { reason, details } });
  };

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal-content" onClick={e => e.stopPropagation()}>
        <header className="modal-header">
          <h3>Zeit nachtragen</h3>
          <button className="close-button" onClick={onClose}>&times;</button>
        </header>
        <div className="modal-body">
          <div className="form-group"><label>Datum<input type="date" value={date} onChange={e=>setDate(e.target.value)} /></label></div>
          <div className="form-group"><label>Startzeit<input type="time" step="1" value={startTime} onChange={e=>setStartTime(e.target.value.length===5? `${e.target.value}:00` : e.target.value)} /></label></div>
          <div className="form-group"><label>Stoppzeit<input type="time" step="1" value={stopTime} onChange={e=>setStopTime(e.target.value.length===5? `${e.target.value}:00` : e.target.value)} /></label></div>
          <div className="form-group"><label>Standort<select value={location} onChange={e=>setLocation(e.target.value)}>{locations.map(l=>(<option key={l} value={l}>{l}</option>))}</select></label></div>
          <div className="form-group"><label>Rolle<select value={role} onChange={e=>setRole(e.target.value as Role)}>
            {['Admin','Bereichsleiter','Standortleiter','Mitarbeiter','Honorarkraft'].map(r=>(<option key={r} value={r}>{r}</option>))}
          </select></label></div>
          <div className="form-group"><label>Grund<select value={reason} onChange={e=>setReason(e.target.value)}>{changeReasons.map(r=>(<option key={r} value={r}>{r}</option>))}</select></label></div>
          <div className="form-group"><label>Details<textarea rows={3} value={details} onChange={e=>setDetails(e.target.value)} /></label></div>
        </div>
        <footer className="modal-footer">
          <button className="nav-button" onClick={onClose}>Abbrechen</button>
          <button className="action-button" onClick={handleSubmit}>Beantragen</button>
        </footer>
      </div>
    </div>
  );
};
