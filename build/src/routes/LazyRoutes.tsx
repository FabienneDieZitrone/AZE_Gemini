import { lazy, Suspense } from 'react';
import { LoadingSpinner } from '../components/common/LoadingSpinner';

// Lazy load heavy components
export const TimeSheetView = lazy(() => import('../views/TimeSheetView').then(m => ({ default: m.TimeSheetView })));
export const MasterDataView = lazy(() => import('../views/MasterDataView').then(m => ({ default: m.MasterDataView })));
export const ApprovalsView = lazy(() => import('../views/ApprovalView').then(m => ({ default: m.ApprovalView })));
export const HistoryView = lazy(() => import('../views/ChangeHistoryView').then(m => ({ default: m.ChangeHistoryView })));
export const SettingsView = lazy(() => import('../views/GlobalSettingsView').then(m => ({ default: m.GlobalSettingsView })));

// Wrapper component for lazy loading
export const LazyRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  return (
    <Suspense fallback={<LoadingSpinner />}>
      {children}
    </Suspense>
  );
};
