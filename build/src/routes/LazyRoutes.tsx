import { lazy, Suspense } from 'react';
import { LoadingSpinner } from '../components/common/LoadingSpinner';

// Lazy load heavy components
export const TimeSheetView = lazy(() => import('../views/TimeSheetView'));
export const MasterDataView = lazy(() => import('../views/MasterDataView'));
export const ApprovalsView = lazy(() => import('../views/ApprovalsView'));
export const HistoryView = lazy(() => import('../views/HistoryView'));
export const SettingsView = lazy(() => import('../views/SettingsView'));

// Wrapper component for lazy loading
export const LazyRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  return (
    <Suspense fallback={<LoadingSpinner />}>
      {children}
    </Suspense>
  );
};