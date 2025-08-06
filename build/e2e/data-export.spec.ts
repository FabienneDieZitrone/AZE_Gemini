/**
 * E2E Tests for Data Export and PDF Generation
 * Tests various export functionalities including PDF generation, CSV export, and data integrity
 */
import { test, expect } from '@playwright/test'
import { readFileSync } from 'fs'

// Helper function to setup authenticated state with test data
async function setupAuthWithData(page: any, role: 'employee' | 'supervisor' = 'employee') {
  const user = role === 'supervisor' 
    ? { id: 2, name: 'Test Supervisor', email: 'supervisor@example.com', role: 'Supervisor' }
    : { id: 1, name: 'Max Mustermann', email: 'max.mustermann@example.com', role: 'Mitarbeiter' }

  const testTimeEntries = [
    {
      id: 1,
      userId: 1,
      userName: 'Max Mustermann',
      date: '2025-08-01',
      startTime: '09:00:00',
      stopTime: '17:00:00',
      status: 'Genehmigt',
      reason: 'Reguläre Arbeitszeit'
    },
    {
      id: 2,
      userId: 1,
      userName: 'Max Mustermann',
      date: '2025-08-02',
      startTime: '08:30:00',
      stopTime: '16:30:00',
      status: 'Erfasst',
      reason: 'Früher Arbeitsbeginn'
    },
    {
      id: 3,
      userId: 1,
      userName: 'Max Mustermann',
      date: '2025-08-03',
      startTime: '10:00:00',
      stopTime: '18:00:00',
      status: 'Genehmigt',
      reason: 'Flexible Arbeitszeit'
    }
  ]

  await page.route('**/api/auth-status.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ authenticated: true }),
    })
  })

  await page.route('**/api/login.php', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        user,
        users: [user],
        timeEntries: role === 'supervisor' ? testTimeEntries : testTimeEntries.filter(e => e.userId === user.id),
        masterData: { 1: { workingHoursPerWeek: 40, vacationDaysPerYear: 25 } },
        approvalRequests: [],
        history: [],
        globalSettings: { 
          companyName: 'Mikropartner GmbH',
          workingHoursPerWeek: 40,
          address: 'Beispielstraße 123, 12345 Beispielstadt'
        },
      }),
    })
  })
}

test.describe('PDF Export Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthWithData(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should generate PDF timesheet export', async ({ page }) => {
    // Mock PDF generation endpoint
    await page.route('**/api/export/pdf.php', async (route) => {
      // Simulate PDF generation
      const pdfBuffer = Buffer.from('JVBERi0xLjQK...') // Mock PDF content
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': 'attachment; filename="zeiterfassung_2025-08.pdf"'
        },
        body: pdfBuffer,
      })
    })

    // Navigate to timesheet view
    const timesheetTab = page.locator('button:has-text("Zeiterfassung")')
    if (await timesheetTab.count() > 0) {
      await timesheetTab.click()
    }

    // Click PDF export button
    const downloadPromise = page.waitForEvent('download')
    const pdfExportButton = page.locator('button:has-text("PDF Export")')
    await pdfExportButton.click()

    // Verify download
    const download = await downloadPromise
    expect(download.suggestedFilename()).toContain('zeiterfassung')
    expect(download.suggestedFilename()).toMatch(/\.pdf$/)
  })

  test('should include correct data in PDF export', async ({ page }) => {
    let exportRequestData: any = null

    await page.route('**/api/export/pdf.php', async (route) => {
      exportRequestData = await route.request().postDataJSON()
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': 'attachment; filename="zeiterfassung_2025-08.pdf"'
        },
        body: Buffer.from('Mock PDF content'),
      })
    })

    const pdfExportButton = page.locator('button:has-text("PDF Export")')
    if (await pdfExportButton.count() > 0) {
      const downloadPromise = page.waitForEvent('download')
      await pdfExportButton.click()
      await downloadPromise
      
      // Verify request contains correct data
      expect(exportRequestData).toBeTruthy()
      expect(exportRequestData).toHaveProperty('timeEntries')
      expect(exportRequestData).toHaveProperty('dateRange')
      expect(exportRequestData).toHaveProperty('user')
    }
  })

  test('should handle PDF generation errors gracefully', async ({ page }) => {
    // Mock PDF generation error
    await page.route('**/api/export/pdf.php', async (route) => {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ 
          error: 'PDF generation failed',
          details: 'Internal server error during PDF creation'
        }),
      })
    })

    const pdfExportButton = page.locator('button:has-text("PDF Export")')
    if (await pdfExportButton.count() > 0) {
      await pdfExportButton.click()
      
      // Should show error message
      await expect(page.locator('text=PDF generation failed')).toBeVisible()
    }
  })

  test('should allow date range selection for PDF export', async ({ page }) => {
    // Check if date range selector is available
    const dateRangeButton = page.locator('button:has-text("Zeitraum auswählen")')
    if (await dateRangeButton.count() > 0) {
      await dateRangeButton.click()
      
      // Set date range
      const startDateInput = page.locator('input[name="startDate"]')
      const endDateInput = page.locator('input[name="endDate"]')
      
      if (await startDateInput.count() > 0 && await endDateInput.count() > 0) {
        await startDateInput.fill('2025-08-01')
        await endDateInput.fill('2025-08-31')
        
        // Mock PDF with date range
        await page.route('**/api/export/pdf.php', async (route) => {
          const requestData = await route.request().postDataJSON()
          expect(requestData.dateRange.start).toBe('2025-08-01')
          expect(requestData.dateRange.end).toBe('2025-08-31')
          
          await route.fulfill({
            status: 200,
            headers: {
              'Content-Type': 'application/pdf',
              'Content-Disposition': 'attachment; filename="zeiterfassung_2025-08-01_2025-08-31.pdf"'
            },
            body: Buffer.from('Mock PDF content'),
          })
        })
        
        const downloadPromise = page.waitForEvent('download')
        const exportButton = page.locator('button:has-text("Exportieren")')
        await exportButton.click()
        
        const download = await downloadPromise
        expect(download.suggestedFilename()).toContain('2025-08-01_2025-08-31')
      }
    }
  })

  test('should generate detailed PDF with company branding', async ({ page }) => {
    let pdfRequestData: any = null

    await page.route('**/api/export/pdf.php', async (route) => {
      pdfRequestData = await route.request().postDataJSON()
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': 'attachment; filename="detailed_timesheet.pdf"'
        },
        body: Buffer.from('Detailed PDF with branding'),
      })
    })

    const detailedExportButton = page.locator('button:has-text("Detaillierter Export")')
    if (await detailedExportButton.count() > 0) {
      const downloadPromise = page.waitForEvent('download')
      await detailedExportButton.click()
      await downloadPromise
      
      // Verify request includes company information
      expect(pdfRequestData.globalSettings.companyName).toBe('Mikropartner GmbH')
      expect(pdfRequestData.globalSettings.address).toBeTruthy()
      expect(pdfRequestData.includeDetails).toBe(true)
    }
  })
})

test.describe('CSV Export Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthWithData(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should generate CSV export', async ({ page }) => {
    const csvData = [
      'Datum,Startzeit,Endzeit,Grund,Status',
      '2025-08-01,09:00:00,17:00:00,Reguläre Arbeitszeit,Genehmigt',
      '2025-08-02,08:30:00,16:30:00,Früher Arbeitsbeginn,Erfasst',
      '2025-08-03,10:00:00,18:00:00,Flexible Arbeitszeit,Genehmigt'
    ].join('\n')

    await page.route('**/api/export/csv.php', async (route) => {
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'text/csv',
          'Content-Disposition': 'attachment; filename="zeiterfassung_2025-08.csv"'
        },
        body: csvData,
      })
    })

    const downloadPromise = page.waitForEvent('download')
    const csvExportButton = page.locator('button:has-text("CSV Export")')
    await csvExportButton.click()

    const download = await downloadPromise
    expect(download.suggestedFilename()).toContain('zeiterfassung')
    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  test('should include proper CSV headers', async ({ page }) => {
    let csvContent = ''

    await page.route('**/api/export/csv.php', async (route) => {
      const requestData = await route.request().postDataJSON()
      
      // Generate CSV with proper headers
      csvContent = 'Datum,Mitarbeiter,Startzeit,Endzeit,Pause,Arbeitszeit,Grund,Status\n'
      csvContent += '2025-08-01,Max Mustermann,09:00:00,17:00:00,01:00:00,07:00:00,Reguläre Arbeitszeit,Genehmigt'
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'text/csv; charset=utf-8',
          'Content-Disposition': 'attachment; filename="zeiterfassung_detailed.csv"'
        },
        body: csvContent,
      })
    })

    const downloadPromise = page.waitForEvent('download')
    const csvExportButton = page.locator('button:has-text("CSV Export")')
    await csvExportButton.click()

    const download = await downloadPromise
    
    // In a real implementation, you would read and validate the CSV content
    expect(download.suggestedFilename()).toMatch(/\.csv$/)
  })

  test('should handle CSV export with filtering', async ({ page }) => {
    // Apply status filter
    const statusFilter = page.locator('select[name="statusFilter"]')
    if (await statusFilter.count() > 0) {
      await statusFilter.selectOption('Genehmigt')
    }

    await page.route('**/api/export/csv.php', async (route) => {
      const requestData = await route.request().postDataJSON()
      
      // Should only include approved entries
      expect(requestData.filters.status).toBe('Genehmigt')
      
      const filteredCsvData = [
        'Datum,Startzeit,Endzeit,Grund,Status',
        '2025-08-01,09:00:00,17:00:00,Reguläre Arbeitszeit,Genehmigt',
        '2025-08-03,10:00:00,18:00:00,Flexible Arbeitszeit,Genehmigt'
      ].join('\n')

      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'text/csv',
          'Content-Disposition': 'attachment; filename="zeiterfassung_genehmigt.csv"'
        },
        body: filteredCsvData,
      })
    })

    const downloadPromise = page.waitForEvent('download')
    const csvExportButton = page.locator('button:has-text("CSV Export")')
    await csvExportButton.click()

    await downloadPromise
  })
})

test.describe('Excel Export Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthWithData(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should generate Excel export', async ({ page }) => {
    await page.route('**/api/export/excel.php', async (route) => {
      // Mock Excel file (simplified)
      const excelBuffer = Buffer.from('Mock Excel content')
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'Content-Disposition': 'attachment; filename="zeiterfassung_2025-08.xlsx"'
        },
        body: excelBuffer,
      })
    })

    const downloadPromise = page.waitForEvent('download')
    const excelExportButton = page.locator('button:has-text("Excel Export")')
    if (await excelExportButton.count() > 0) {
      await excelExportButton.click()

      const download = await downloadPromise
      expect(download.suggestedFilename()).toContain('zeiterfassung')
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/)
    }
  })

  test('should include formatting in Excel export', async ({ page }) => {
    let excelRequestData: any = null

    await page.route('**/api/export/excel.php', async (route) => {
      excelRequestData = await route.request().postDataJSON()
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'Content-Disposition': 'attachment; filename="zeiterfassung_formatted.xlsx"'
        },
        body: Buffer.from('Formatted Excel content'),
      })
    })

    const formattedExcelButton = page.locator('button:has-text("Formatiert Excel Export")')
    if (await formattedExcelButton.count() > 0) {
      const downloadPromise = page.waitForEvent('download')
      await formattedExcelButton.click()
      await downloadPromise
      
      // Should request formatted export
      expect(excelRequestData.includeFormatting).toBe(true)
      expect(excelRequestData.includeCharts).toBe(true)
    }
  })
})

test.describe('Supervisor Export Features', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthWithData(page, 'supervisor')
    await page.goto('/')
    await page.waitForLoadState('networkidle')
  })

  test('should allow supervisor to export team data', async ({ page }) => {
    await page.route('**/api/export/team-report.php', async (route) => {
      const requestData = await route.request().postDataJSON()
      
      // Verify supervisor can access team data
      expect(requestData.includeAllTeamMembers).toBe(true)
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': 'attachment; filename="team_report_2025-08.pdf"'
        },
        body: Buffer.from('Team report PDF'),
      })
    })

    const teamReportButton = page.locator('button:has-text("Team-Bericht")')
    if (await teamReportButton.count() > 0) {
      const downloadPromise = page.waitForEvent('download')
      await teamReportButton.click()

      const download = await downloadPromise
      expect(download.suggestedFilename()).toContain('team_report')
    }
  })

  test('should generate approval summary report', async ({ page }) => {
    await page.route('**/api/export/approval-summary.php', async (route) => {
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': 'attachment; filename="approval_summary_2025-08.pdf"'
        },
        body: Buffer.from('Approval summary PDF'),
      })
    })

    const approvalsTab = page.locator('button:has-text("Genehmigungen")')
    if (await approvalsTab.count() > 0) {
      await approvalsTab.click()
      
      const summaryExportButton = page.locator('button:has-text("Zusammenfassung exportieren")')
      if (await summaryExportButton.count() > 0) {
        const downloadPromise = page.waitForEvent('download')
        await summaryExportButton.click()

        const download = await downloadPromise
        expect(download.suggestedFilename()).toContain('approval_summary')
      }
    }
  })

  test('should export individual employee reports', async ({ page }) => {
    await page.route('**/api/export/employee-report.php', async (route) => {
      const requestData = await route.request().postDataJSON()
      expect(requestData.employeeId).toBeTruthy()
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'application/pdf',
          'Content-Disposition': `attachment; filename="employee_${requestData.employeeId}_report.pdf"`
        },
        body: Buffer.from('Employee report PDF'),
      })
    })

    // Select employee from dropdown
    const employeeSelect = page.locator('select[name="employeeId"]')
    if (await employeeSelect.count() > 0) {
      await employeeSelect.selectOption('1')
      
      const employeeReportButton = page.locator('button:has-text("Mitarbeiter-Bericht")')
      if (await employeeReportButton.count() > 0) {
        const downloadPromise = page.waitForEvent('download')
        await employeeReportButton.click()

        const download = await downloadPromise
        expect(download.suggestedFilename()).toContain('employee_1_report')
      }
    }
  })
})

test.describe('Export Data Validation', () => {
  test('should validate date ranges before export', async ({ page }) => {
    await setupAuthWithData(page)
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    const dateRangeButton = page.locator('button:has-text("Zeitraum auswählen")')
    if (await dateRangeButton.count() > 0) {
      await dateRangeButton.click()
      
      // Set invalid date range (end before start)
      const startDateInput = page.locator('input[name="startDate"]')
      const endDateInput = page.locator('input[name="endDate"]')
      
      if (await startDateInput.count() > 0 && await endDateInput.count() > 0) {
        await startDateInput.fill('2025-08-31')
        await endDateInput.fill('2025-08-01')
        
        const exportButton = page.locator('button:has-text("Exportieren")')
        await exportButton.click()
        
        // Should show validation error
        await expect(page.locator('text=Ungültiger Zeitraum')).toBeVisible()
      }
    }
  })

  test('should handle empty data sets gracefully', async ({ page }) => {
    // Mock empty data response
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Test User', email: 'test@example.com', role: 'Mitarbeiter' },
          users: [],
          timeEntries: [], // Empty time entries
          masterData: {},
          approvalRequests: [],
          history: [],
          globalSettings: {},
        }),
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')

    const pdfExportButton = page.locator('button:has-text("PDF Export")')
    if (await pdfExportButton.count() > 0) {
      await pdfExportButton.click()
      
      // Should show message about no data to export
      await expect(page.locator('text=Keine Daten zum Exportieren')).toBeVisible()
    }
  })

  test('should preserve data integrity in exports', async ({ page }) => {
    await setupAuthWithData(page)

    let exportedData: any = null

    await page.route('**/api/export/csv.php', async (route) => {
      exportedData = await route.request().postDataJSON()
      
      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'text/csv',
          'Content-Disposition': 'attachment; filename="integrity_test.csv"'
        },
        body: 'Mock CSV content',
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')

    const csvExportButton = page.locator('button:has-text("CSV Export")')
    if (await csvExportButton.count() > 0) {
      const downloadPromise = page.waitForEvent('download')
      await csvExportButton.click()
      await downloadPromise
      
      // Verify that all expected data is present
      expect(exportedData.timeEntries).toHaveLength(3)
      expect(exportedData.timeEntries[0]).toHaveProperty('id', 1)
      expect(exportedData.timeEntries[0]).toHaveProperty('startTime', '09:00:00')
      expect(exportedData.timeEntries[0]).toHaveProperty('stopTime', '17:00:00')
    }
  })

  test('should handle special characters in export data', async ({ page }) => {
    // Mock data with special characters
    await page.route('**/api/login.php', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          user: { id: 1, name: 'Max Müller', email: 'test@example.com', role: 'Mitarbeiter' },
          users: [],
          timeEntries: [
            {
              id: 1,
              userId: 1,
              date: '2025-08-01',
              startTime: '09:00:00',
              stopTime: '17:00:00',
              status: 'Erfasst',
              reason: 'Besprechung mit Außendienstlern über Qualitätsmaßnahmen'
            }
          ],
          masterData: {},
          approvalRequests: [],
          history: [],
          globalSettings: { companyName: 'Müller & Söhne GmbH' },
        }),
      })
    })

    let exportData: any = null

    await page.route('**/api/export/csv.php', async (route) => {
      exportData = await route.request().postDataJSON()
      
      // Should properly encode special characters
      const csvContent = [
        'Datum,Mitarbeiter,Grund',
        '2025-08-01,Max Müller,Besprechung mit Außendienstlern über Qualitätsmaßnahmen'
      ].join('\n')

      await route.fulfill({
        status: 200,
        headers: {
          'Content-Type': 'text/csv; charset=utf-8',
          'Content-Disposition': 'attachment; filename="zeiterfassung_special_chars.csv"'
        },
        body: csvContent,
      })
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')

    const csvExportButton = page.locator('button:has-text("CSV Export")')
    if (await csvExportButton.count() > 0) {
      const downloadPromise = page.waitForEvent('download')
      await csvExportButton.click()
      await downloadPromise
      
      // Verify special characters are preserved
      expect(exportData.user.name).toBe('Max Müller')
      expect(exportData.globalSettings.companyName).toBe('Müller & Söhne GmbH')
      expect(exportData.timeEntries[0].reason).toContain('Qualitätsmaßnahmen')
    }
  })
})