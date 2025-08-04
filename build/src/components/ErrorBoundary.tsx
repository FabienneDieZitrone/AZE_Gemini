import React, { Component, ErrorInfo, ReactNode } from 'react';
import { Box, Typography, Button, Container, Paper } from '@mui/material';
import { ErrorOutline } from '@mui/icons-material';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error: null,
    errorInfo: null
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error, errorInfo: null };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('ErrorBoundary caught an error:', error, errorInfo);
    
    // Log to monitoring service in production
    if (process.env.NODE_ENV === 'production') {
      // TODO: Integrate with monitoring service (e.g., Sentry, LogRocket)
      console.error('Production error logged:', {
        error: error.toString(),
        componentStack: errorInfo.componentStack,
        timestamp: new Date().toISOString()
      });
    }
    
    this.setState({
      error,
      errorInfo
    });
  }

  private handleReset = () => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null
    });
    
    // Reload the page as a last resort
    window.location.reload();
  };

  public render() {
    if (this.state.hasError) {
      return (
        <Container maxWidth="sm" sx={{ mt: 8 }}>
          <Paper elevation={3} sx={{ p: 4, textAlign: 'center' }}>
            <ErrorOutline sx={{ fontSize: 60, color: 'error.main', mb: 2 }} />
            
            <Typography variant="h4" gutterBottom color="error">
              Oops! Etwas ist schiefgelaufen
            </Typography>
            
            <Typography variant="body1" color="text.secondary" paragraph>
              Es ist ein unerwarteter Fehler aufgetreten. Das tut uns leid.
            </Typography>
            
            {process.env.NODE_ENV === 'development' && this.state.error && (
              <Box sx={{ mt: 2, mb: 2, p: 2, bgcolor: 'grey.100', borderRadius: 1, textAlign: 'left' }}>
                <Typography variant="subtitle2" color="error" gutterBottom>
                  Fehlerdetails (nur in Entwicklung sichtbar):
                </Typography>
                <Typography variant="body2" component="pre" sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                  {this.state.error.toString()}
                </Typography>
                {this.state.errorInfo && (
                  <Typography variant="body2" component="pre" sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word', mt: 1 }}>
                    {this.state.errorInfo.componentStack}
                  </Typography>
                )}
              </Box>
            )}
            
            <Box sx={{ mt: 3 }}>
              <Button 
                variant="contained" 
                color="primary" 
                onClick={this.handleReset}
                sx={{ mr: 2 }}
              >
                Seite neu laden
              </Button>
              
              <Button 
                variant="outlined" 
                color="primary" 
                onClick={() => window.history.back()}
              >
                Zurück
              </Button>
            </Box>
            
            <Typography variant="body2" color="text.secondary" sx={{ mt: 3 }}>
              Wenn das Problem weiterhin besteht, kontaktieren Sie bitte den Support.
            </Typography>
          </Paper>
        </Container>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;