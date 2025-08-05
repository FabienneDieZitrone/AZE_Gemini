/**
 * Validation utilities for AZE_Gemini
 * 
 * Provides specific validation messages for form fields
 */

export const ValidationRules = {
  email: {
    required: 'E-Mail-Adresse ist erforderlich',
    pattern: {
      value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
      message: 'Bitte geben Sie eine gültige E-Mail-Adresse ein (z.B. benutzer@beispiel.de)'
    }
  },
  
  hours: {
    required: 'Arbeitsstunden sind erforderlich',
    min: {
      value: 0.25,
      message: 'Mindestens 15 Minuten (0,25 Stunden) müssen erfasst werden'
    },
    max: {
      value: 24,
      message: 'Maximal 24 Stunden pro Tag erlaubt'
    },
    validate: {
      multipleOf15Min: (value: number) => {
        const minutes = value * 60;
        return minutes % 15 === 0 || 'Zeit muss in 15-Minuten-Schritten erfasst werden (0,25 / 0,5 / 0,75 / 1,0 etc.)'
      }
    }
  },
  
  date: {
    required: 'Datum ist erforderlich',
    validate: {
      notFuture: (value: string) => {
        const selected = new Date(value);
        const today = new Date();
        today.setHours(23, 59, 59, 999);
        return selected <= today || 'Zeiteinträge für zukünftige Daten sind nicht erlaubt';
      },
      notTooOld: (value: string) => {
        const selected = new Date(value);
        const cutoff = new Date();
        cutoff.setDate(cutoff.getDate() - 30);
        cutoff.setHours(0, 0, 0, 0);
        return selected >= cutoff || 'Zeiteinträge älter als 30 Tage können nicht mehr bearbeitet werden. Bitte wenden Sie sich an Ihren Vorgesetzten.';
      }
    }
  },
  
  reason: {
    required: 'Begründung ist erforderlich',
    minLength: {
      value: 10,
      message: 'Bitte geben Sie eine aussagekräftige Begründung ein (mindestens 10 Zeichen)'
    },
    maxLength: {
      value: 500,
      message: 'Begründung darf maximal 500 Zeichen lang sein'
    }
  },
  
  project: {
    required: 'Projekt muss ausgewählt werden'
  },
  
  activity: {
    required: 'Tätigkeit muss ausgewählt werden'
  },
  
  time: {
    required: 'Zeit ist erforderlich',
    pattern: {
      value: /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/,
      message: 'Bitte geben Sie die Zeit im Format HH:MM ein (z.B. 08:30)'
    },
    validate: {
      validTimeRange: (startTime: string, formValues: any) => {
        if (!formValues.endTime) return true;
        const [startHour, startMin] = startTime.split(':').map(Number);
        const [endHour, endMin] = formValues.endTime.split(':').map(Number);
        const startMinutes = startHour * 60 + startMin;
        const endMinutes = endHour * 60 + endMin;
        return startMinutes < endMinutes || 'Startzeit muss vor der Endzeit liegen';
      }
    }
  }
};

// Helper function to get field-specific error message
export function getFieldError(fieldName: string, error: any): string {
  if (!error) return '';
  
  if (error.type === 'required') {
    return ValidationRules[fieldName as keyof typeof ValidationRules]?.required || 'Dieses Feld ist erforderlich';
  }
  
  if (error.type === 'pattern' && error.message) {
    return error.message;
  }
  
  if (error.type === 'min' && error.message) {
    return error.message;
  }
  
  if (error.type === 'max' && error.message) {
    return error.message;
  }
  
  if (error.type === 'minLength' && error.message) {
    return error.message;
  }
  
  if (error.type === 'maxLength' && error.message) {
    return error.message;
  }
  
  if (error.type === 'validate' && error.message) {
    return error.message;
  }
  
  return error.message || 'Ungültige Eingabe';
}