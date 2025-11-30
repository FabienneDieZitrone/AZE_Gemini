/**
 * Titel: IT-Adventskalender Modal
 * Version: 1.1
 * Datum: 30.11.2025
 * Beschreibung: Zeigt IT-Mitgliedern beim Timer-Start eine Einladung zum Adventskalender
 */

import React from 'react';
import './AdventCalendarModal.css';

interface AdventCalendarModalProps {
  doorNumber: number;
  onClose: () => void;
}

export const AdventCalendarModal: React.FC<AdventCalendarModalProps> = ({ doorNumber, onClose }) => {
  const wikiUrl = `https://wiki.mikropartner.de/books/afterwork/page/tur-${doorNumber}`;

  return (
    <div className="modal-overlay advent-overlay" onClick={onClose}>
      <div className="modal-content advent-modal" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close-btn" onClick={onClose} aria-label="Schließen">×</button>

        {/* Dekorative Elemente */}
        <div className="advent-decorations">
          <span className="snowflake sf1">❄</span>
          <span className="snowflake sf2">❅</span>
          <span className="snowflake sf3">❆</span>
          <span className="snowflake sf4">❄</span>
          <span className="snowflake sf5">❅</span>
        </div>

        <div className="advent-header">
          <span className="advent-tree">🎄</span>
          <h2>IT-Adventskalender</h2>
          <span className="advent-tree">🎄</span>
        </div>

        <div className="advent-door">
          <div className="door-frame">
            <span className="door-number">{doorNumber}</span>
          </div>
        </div>

        <p className="advent-message">
          Guten Morgen! 🌟<br />
          Hinter Türchen <strong>{doorNumber}</strong> wartet eine kleine Überraschung auf dich!
        </p>

        <a
          href={wikiUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="advent-link-button"
        >
          <span className="button-icon">🎁</span>
          Türchen öffnen
        </a>

        <p className="advent-footer">
          Frohe Adventszeit! 🎅
        </p>
      </div>
    </div>
  );
};
