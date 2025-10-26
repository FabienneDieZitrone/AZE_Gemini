/**
 * Titel: Globale Einstellungen-Ansicht
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/views/GlobalSettingsView.tsx
 */
import React, { useState, useEffect } from 'react';
import { GlobalSettings } from '../types';

export const GlobalSettingsView: React.FC<{
    onBack: () => void;
    settings: GlobalSettings;
    onSave: (newSettings: GlobalSettings) => void;
}> = ({ onBack, settings, onSave }) => {
    const [formData, setFormData] = useState(settings);
    const [ipMap, setIpMap] = useState<{prefix: string; location: string}[]>([]);
    const [ipLoadError, setIpLoadError] = useState<string | null>(null);
    const [ipValidation, setIpValidation] = useState<{prefixOk: boolean; locationOk: boolean}[]>([]);
    const [newLocation, setNewLocation] = useState('');

    useEffect(() => {
        setFormData(settings);
    }, [settings]);

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        onSave(formData);
    };

    const handleAddLocation = () => {
        if (newLocation.trim() && !formData.locations.includes(newLocation.trim())) {
            setFormData(prev => ({...prev, locations: [...prev.locations, newLocation.trim()]}));
            setNewLocation('');
        }
    };
    
    const handleRemoveLocation = (locationToRemove: string) => {
        setFormData(prev => ({...prev, locations: prev.locations.filter(loc => loc !== locationToRemove)}));
        // Entferne auch eventuelle Mapping-Zeilen mit diesem Standort
        setIpMap(prev => prev.map(r => (r.location === locationToRemove ? { ...r, location: '' } : r)));
    };

    const handleRenameLocation = (oldName: string, newName: string) => {
        const n = newName.trim();
        if (!n) return; // keine leeren Namen
        setFormData(prev => {
            // Verhindere Duplikate
            if (prev.locations.includes(n) && oldName !== n) return prev;
            return { ...prev, locations: prev.locations.map(loc => loc === oldName ? n : loc) };
        });
        // Mapping-Einträge auf neuen Namen anpassen
        setIpMap(prev => prev.map(r => (r.location === oldName ? { ...r, location: newName } : r)));
    };

    // IP→Standort Map laden
    useEffect(() => {
        fetch('/api/ip-location-map.php', { credentials: 'include' })
            .then(r => r.json())
            .then(d => {
                if (d && Array.isArray(d.entries)) {
                  const sorted = [...d.entries].sort((a,b)=> (a.location||'').localeCompare(b.location||'', 'de', {sensitivity:'base'}));
                  setIpMap(sorted);
                }
            })
            .catch(() => setIpLoadError('Fehler beim Laden der IP-Zuordnung.'));
    }, []);

    // Simple IP prefix validation: allow patterns like 10.49.1. or 192.168.0.
    const isValidPrefix = (p: string) => /^\d{1,3}(?:\.\d{1,3}){1,3}\.?$/.test(p.trim());
    const canonicalizeLocation = (name: string) => {
        const n = name.trim();
        if (!n) return '';
        const found = formData.locations.find(l => l.localeCompare(n, 'de', {sensitivity:'base'}) === 0);
        return found || '';
    };
    useEffect(() => {
        setIpValidation(ipMap.map(r => {
            const p = (r.prefix||'').trim();
            const loc = (r.location||'').trim();
            // Leere Zeile ist okay (wird beim Speichern ignoriert)
            if (!p && !loc) return { prefixOk: true, locationOk: true };
            const prefixOk = !!p && isValidPrefix(p);
            const locationOk = !!loc && !!canonicalizeLocation(loc);
            return { prefixOk, locationOk };
        }));
    }, [ipMap, formData.locations]);

    const handleAddIpRow = () => setIpMap(prev => [...prev, { prefix: '', location: '' }]);
    const handleUpdateIpRow = (idx: number, key: 'prefix'|'location', val: string) => {
        setIpMap(prev => prev.map((e,i)=> i===idx ? ({...e, [key]: val}) : e));
    };
    const handleRemoveIpRow = (idx: number) => setIpMap(prev => prev.filter((_,i)=>i!==idx));

    // CSRF-Token abrufen
    const fetchCsrfToken = async (): Promise<string> => {
        try {
            const resp = await fetch('/api/csrf-token.php', { credentials: 'include' });
            if (!resp.ok) throw new Error('CSRF Token konnte nicht geladen werden');
            const data = await resp.json();
            return String(data?.csrfToken || '');
        } catch (err) {
            console.error('CSRF Token Fehler:', err);
            return '';
        }
    };

    const handleSaveIpMap = async () => {
        try {
            // CSRF-Token holen
            const csrfToken = await fetchCsrfToken();
            if (!csrfToken) {
                alert('Sicherheitstoken konnte nicht geladen werden. Bitte versuchen Sie es erneut.');
                return;
            }

            // Nur valide und vollständig ausgefüllte Zeilen übertragen
            const entries = ipMap
                .map(r => ({ prefix: (r.prefix||'').trim(), location: canonicalizeLocation(r.location||'') }))
                .filter(r => r.prefix && r.location && isValidPrefix(r.prefix));
            const res = await fetch('/api/ip-location-map.php', {
                method: 'PUT',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ entries })
            });
            if (!res.ok) {
                const errorText = await res.text();
                console.error('API Error:', errorText);
                throw new Error(errorText);
            }
            // Nach dem Speichern frisch laden und alphabetisch sortieren
            const re = await fetch('/api/ip-location-map.php', { credentials: 'include' });
            const d = await re.json().catch(()=>null);
            if (d && Array.isArray(d.entries)) {
              const sorted = [...d.entries].sort((a,b)=> (a.location||'').localeCompare(b.location||'', 'de', {sensitivity:'base'}));
              setIpMap(sorted);
            }
            alert('IP-Standort-Zuordnung gespeichert.');
        } catch (err) {
            console.error('Save Error:', err);
            alert('Speichern der IP-Standort-Zuordnung fehlgeschlagen.');
        }
    };

    return (
        <div className="view-container">
            <header className="view-header">
                <h2>Globale Einstellungen</h2>
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </header>
            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 16 }}>
                <button type="submit" form="settings-form" className="action-button">Einstellungen speichern</button>
            </div>
            <form id="settings-form" className="master-data-form" onSubmit={handleSave}>
                <div className="form-grid" style={{ gridTemplateColumns: '1fr', maxWidth: 800 }}>
                    <div className="form-group">
                        <label htmlFor="overtimeThreshold" style={{ fontWeight: 600 }}>Toleranzschwelle für Überstunden (Stunden)</label>
                        <input
                            type="number"
                            id="overtimeThreshold"
                            value={formData.overtimeThreshold}
                            onChange={(e) => setFormData(prev => ({ ...prev, overtimeThreshold: parseFloat(e.target.value) || 0 }))}
                            step="0.5"
                            style={{ width: '100%' }}
                        />
                    </div>
                    <div className="form-group">
                         <label htmlFor="changeReasons" style={{ fontWeight: 600 }}>Liste der Änderungsgründe</label>
                         <textarea
                            id="changeReasons"
                            value={formData.changeReasons.join('\n')}
                            onChange={(e) => setFormData(prev => ({ ...prev, changeReasons: e.target.value.split('\n') }))}
                            rows={6}
                            placeholder="Ein Grund pro Zeile"
                            style={{ width: '100%' }}
                         />
                    </div>
                    <div className="form-group location-manager">
                        <label style={{ fontWeight: 600 }} title="Stammliste">Standorte</label>
                        <div style={{ maxHeight: '200px', overflowY: 'auto', border: '1px solid #ccc', padding: 8, borderRadius: 4, width: '100%' }}>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                {[...formData.locations].sort((a, b) => a.localeCompare(b, 'de', {sensitivity: 'base'})).map(loc => (
                                    <div key={loc} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                        <input
                                            type="text"
                                            defaultValue={loc}
                                            onBlur={(e) => {
                                                const v = e.currentTarget.value;
                                                if (v !== loc) handleRenameLocation(loc, v);
                                            }}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    const v = (e.target as HTMLInputElement).value;
                                                    if (v !== loc) handleRenameLocation(loc, v);
                                                }
                                            }}
                                            title="Standortnamen bearbeiten und Enter drücken"
                                            style={{ flex: 1 }}
                                        />
                                        <button type="button" onClick={() => handleRemoveLocation(loc)}>&times;</button>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="add-location-group" style={{ marginTop: 8, display: 'flex', justifyContent: 'center', gap: 8 }}>
                            <input
                                type="text"
                                value={newLocation}
                                onChange={e => setNewLocation(e.target.value)}
                                placeholder="Neuer Standort"
                                style={{ maxWidth: 300 }}
                            />
                            <button type="button" className="action-button" onClick={handleAddLocation}>Hinzufügen</button>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'center', marginTop: 8 }}>
                            <button type="submit" className="action-button">Stammliste speichern</button>
                        </div>
                    </div>

                    <div className="form-group location-manager">
                        <label style={{ fontWeight: 600 }} title="Nur Standorte aus Stammliste möglich">IP-Adressen → Standort-Namen Zuordnung</label>
                        {ipLoadError && <div className="error">{ipLoadError}</div>}
                        <div style={{ maxHeight: '200px', overflowY: 'auto', border: '1px solid #ccc', padding: 8, borderRadius: 4, width: '100%' }}>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                {ipMap.map((row, idx) => (
                                    <div key={idx} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                        <input
                                            value={row.prefix}
                                            onChange={e=>handleUpdateIpRow(idx,'prefix',e.target.value)}
                                            placeholder="10.49.1."
                                            aria-invalid={!ipValidation[idx]?.prefixOk}
                                            title={ipValidation[idx]?.prefixOk ? '' : 'Format: z. B. 10.49.1.'}
                                            style={{ flex: 1 }}
                                        />
                                        <span>→</span>
                                        <input
                                            value={row.location}
                                            onChange={e=>handleUpdateIpRow(idx,'location',e.target.value)}
                                            placeholder="BER GRU"
                                            list="locations-list"
                                            aria-invalid={!ipValidation[idx]?.locationOk}
                                            title={ipValidation[idx]?.locationOk ? '' : 'Bitte einen vorhandenen Standort aus der Stammliste wählen'}
                                            style={{ flex: 1 }}
                                        />
                                        <button type="button" onClick={()=>handleRemoveIpRow(idx)}>&times;</button>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <datalist id="locations-list">
                          {[...formData.locations].sort((a,b)=>a.localeCompare(b,'de',{sensitivity:'base'})).map(loc => (<option key={loc} value={loc} />))}
                        </datalist>
                        <div style={{ display: 'flex', justifyContent: 'center', marginTop: 8 }}>
                            <button type="button" className="action-button" onClick={handleAddIpRow}>Zeile hinzufügen</button>
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'center', marginTop: 8 }}>
                            <button
                              type="button"
                              className="action-button"
                              onClick={handleSaveIpMap}
                              disabled={ipMap.some((r, i) => {
                                  const p = (r.prefix||'').trim();
                                  const l = (r.location||'').trim();
                                  // Komplette Leerzeilen ignorieren (dürfen bleiben)
                                  if (!p && !l) return false;
                                  const v = ipValidation[i] || {prefixOk:true, locationOk:true};
                                  // Unvollständige oder ungültige Zeilen blockieren
                                  return !(v.prefixOk && v.locationOk);
                              })}
                            >
                              Zuordnung speichern
                            </button>
                        </div>
                        {ipMap.some((r,i)=>{
                            const p=(r.prefix||'').trim(); const l=(r.location||'').trim();
                            if (!p && !l) return false;
                            const v=ipValidation[i]||{prefixOk:true,locationOk:true};
                            return !(v.prefixOk && v.locationOk);
                        }) && (
                          <div className="error" role="alert" style={{ marginTop: 8 }}>
                            Bitte korrigiere ungültige IP‑Präfixe (Format: z. B. 10.49.1.) und wähle nur Standorte aus der Stammliste.
                          </div>
                        )}
                    </div>
                </div>
            </form>

            <footer className="view-footer">
                <button className="nav-button" onClick={onBack}>Zurück zur Startseite</button>
            </footer>
        </div>
    );
};
