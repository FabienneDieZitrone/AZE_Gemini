import React, { type ReactNode } from 'react';
import { useTheme } from '@mui/material/styles';
import useMediaQuery from '@mui/material/useMediaQuery';

interface ResponsiveValues {
  isMobile: boolean;
  isTablet: boolean;
  isDesktop: boolean;
  isSmallScreen: boolean;
  isMediumScreen: boolean;
  isLargeScreen: boolean;
}

export const useResponsive = (): ResponsiveValues => {
  const theme = useTheme();
  
  // Material-UI Breakpoints
  const isMobile = useMediaQuery(theme.breakpoints.down('sm')); // < 600px
  const isTablet = useMediaQuery(theme.breakpoints.between('sm', 'md')); // 600-960px
  const isDesktop = useMediaQuery(theme.breakpoints.up('md')); // >= 960px
  
  // Custom Breakpoints für AZE_Gemini
  const isSmallScreen = useMediaQuery('(max-width: 480px)');
  const isMediumScreen = useMediaQuery('(min-width: 481px) and (max-width: 1024px)');
  const isLargeScreen = useMediaQuery('(min-width: 1025px)');
  
  return {
    isMobile,
    isTablet,
    isDesktop,
    isSmallScreen,
    isMediumScreen,
    isLargeScreen
  };
};

// Responsive Container Komponente
export const ResponsiveContainer: React.FC<{ children: ReactNode }> = ({ children }) => {
  const { isMobile } = useResponsive();
  
  return (
    <div style={{
      padding: isMobile ? '8px' : '16px',
      maxWidth: isMobile ? '100%' : '1200px',
      margin: '0 auto'
    }}>
      {children}
    </div>
  );
};
