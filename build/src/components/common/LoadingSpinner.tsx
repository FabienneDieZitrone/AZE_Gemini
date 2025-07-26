/**
 * Titel: LoadingSpinner Komponente
 * Version: 1.0
 * Letzte Aktualisierung: 08.11.2024
 * Autor: MP-IT
 * Datei: /src/components/common/LoadingSpinner.tsx
 */

export const LoadingSpinner = () => (
  <div 
    className="loading-spinner" 
    data-testid="loading-spinner"
    role="status"
    aria-label="Lädt..."
  >
    Lädt...
  </div>
);