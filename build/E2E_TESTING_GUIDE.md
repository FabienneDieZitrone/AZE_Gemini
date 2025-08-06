# AZE Gemini E2E Testing Guide

This document provides comprehensive guidance for implementing, running, and maintaining end-to-end (E2E) tests for the AZE Gemini time tracking application.

## Table of Contents

- [Overview](#overview)
- [Test Architecture](#test-architecture)
- [Setup and Installation](#setup-and-installation)
- [Running Tests](#running-tests)
- [Test Suites](#test-suites)
- [Page Object Models](#page-object-models)
- [Best Practices](#best-practices)
- [CI/CD Integration](#cicd-integration)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

## Overview

The AZE Gemini E2E test suite is built using [Playwright](https://playwright.dev/) and provides comprehensive testing coverage for:

- **Authentication flows** including OAuth and MFA
- **Core functionality** like time tracking and entry management
- **Role-based access control** for different user types
- **Security features** including CSRF protection and rate limiting
- **Cross-browser compatibility** across Chrome, Firefox, and Safari
- **Data export** functionality for PDF, CSV, and Excel formats
- **API integration** with real backend endpoints

### Key Features

- ✅ **Multi-browser testing** - Chrome, Firefox, Safari, and mobile browsers
- ✅ **Page Object Model** architecture for maintainable tests
- ✅ **Mocked and real API testing** - supports both development and production testing
- ✅ **Security testing** - comprehensive security validation
- ✅ **Accessibility testing** - ensures WCAG compliance
- ✅ **Visual regression testing** - screenshot comparison
- ✅ **Performance testing** - load time and responsiveness validation
- ✅ **CI/CD integration** - automated testing in GitHub Actions

## Test Architecture

### Directory Structure

```
build/
├── e2e/                           # E2E test files
│   ├── auth.spec.ts              # Authentication tests
│   ├── time-tracking.spec.ts     # Time tracking functionality
│   ├── approval-workflow.spec.ts # Approval process tests
│   ├── security.spec.ts          # Security testing
│   ├── rbac.spec.ts              # Role-based access control
│   ├── cross-browser.spec.ts     # Browser compatibility
│   ├── data-export.spec.ts       # Export functionality
│   ├── api-integration.spec.ts   # Real API testing
│   ├── pages/                    # Page Object Models
│   │   ├── LoginPage.ts          # Login page interactions
│   │   ├── MainAppPage.ts        # Main application page
│   │   └── TimeEntryModal.ts     # Time entry form/modal
│   └── utils/                    # Test utilities
│       └── test-helpers.ts       # Common helper functions
├── scripts/
│   └── run-e2e-tests.sh         # Test execution script
├── .github/
│   └── workflows/
│       └── e2e-tests.yml         # CI/CD configuration
└── playwright.config.ts          # Playwright configuration
```

### Test Categories

1. **Functional Tests** - Core application functionality
2. **Security Tests** - Security features and vulnerabilities
3. **Integration Tests** - API and backend integration
4. **Compatibility Tests** - Cross-browser and device testing
5. **Performance Tests** - Load times and responsiveness
6. **Accessibility Tests** - WCAG compliance and screen reader support

## Setup and Installation

### Prerequisites

- Node.js 16 or higher
- npm or yarn package manager
- Git for version control

### Installation Steps

1. **Install dependencies:**
   ```bash
   cd build
   npm install
   ```

2. **Install Playwright browsers:**
   ```bash
   npx playwright install
   ```

3. **Verify installation:**
   ```bash
   npx playwright --version
   ```

### Environment Configuration

Create environment-specific configuration:

```bash
# For local development
export TEST_ENV=local
export BASE_URL=http://localhost:3000
export REAL_BACKEND=false

# For staging environment
export TEST_ENV=staging
export BASE_URL=https://staging.aze.mikropartner.de
export REAL_BACKEND=true

# For production environment
export TEST_ENV=production
export BASE_URL=https://aze.mikropartner.de
export REAL_BACKEND=true
```

## Running Tests

### Quick Start

Run all tests with default settings:
```bash
npm run test:e2e
```

### Detailed Test Execution

Use the comprehensive test runner script:

```bash
# Run all tests
./scripts/run-e2e-tests.sh

# Run specific test suite
./scripts/run-e2e-tests.sh --suite auth

# Run in headed mode (visible browser)
./scripts/run-e2e-tests.sh --headed

# Run against real backend
./scripts/run-e2e-tests.sh --real-backend --base-url https://staging.aze.mikropartner.de

# Run with specific browser
./scripts/run-e2e-tests.sh --browser firefox

# Run with custom options
./scripts/run-e2e-tests.sh --suite security --browser chromium --headed
```

### NPM Scripts

Available test commands:

```bash
# Run all E2E tests
npm run test:e2e

# Run with UI mode for debugging
npm run test:e2e:ui

# Run in headed mode
npm run test:e2e:headed

# Run specific browser
npm run test:e2e:chrome
npm run test:e2e:firefox
npm run test:e2e:safari

# Run mobile tests
npm run test:e2e:mobile

# Show test report
npm run test:e2e:report

# Run all tests (unit + E2E)
npm run test:all
```

### Command Line Options

The test runner script supports these options:

| Option | Description | Default |
|--------|-------------|---------|
| `--suite SUITE` | Run specific test suite | All suites |
| `--browser BROWSER` | Browser to use (chromium, firefox, webkit) | chromium |
| `--headed` | Run tests in headed mode | false |
| `--real-backend` | Use real backend instead of mocks | false |
| `--base-url URL` | Base URL for testing | http://localhost:3000 |
| `--help` | Show help message | - |

## Test Suites

### 1. Authentication Tests (`auth.spec.ts`)

Tests the complete authentication flow:

- **Login page display** - Verify UI elements and accessibility
- **OAuth integration** - Microsoft login flow
- **Session management** - Session timeout and refresh
- **Security headers** - Proper security header implementation
- **Error handling** - Network errors and authentication failures

### 2. Time Tracking Tests (`time-tracking.spec.ts`)

Tests core time tracking functionality:

- **Timer operations** - Start, stop, pause functionality
- **Manual time entry** - Form validation and submission
- **Time entry CRUD** - Create, read, update, delete operations
- **Data validation** - Time logic and format validation
- **Real-time updates** - Timer display and synchronization

### 3. Approval Workflow Tests (`approval-workflow.spec.ts`)

Tests the complete approval process:

- **Employee requests** - Create and manage change requests
- **Supervisor actions** - Approve and reject requests
- **Status tracking** - Request status updates and history
- **Notifications** - Real-time approval notifications
- **Role permissions** - Access control for approval functions

### 4. Security Tests (`security.spec.ts`)

Comprehensive security validation:

- **CSRF protection** - Token validation and refresh
- **Rate limiting** - API and login rate limits
- **Multi-factor authentication** - MFA setup and verification
- **Data security** - Input sanitization and XSS prevention
- **Session security** - Secure cookies and session management

### 5. Role-Based Access Control (`rbac.spec.ts`)

Tests user role permissions:

- **Employee permissions** - Limited access to own data
- **Supervisor permissions** - Team data access and approvals
- **Administrator permissions** - Full system access
- **Permission enforcement** - API-level access control
- **Role transitions** - Handling role changes during sessions

### 6. Cross-Browser Compatibility (`cross-browser.spec.ts`)

Tests across different browsers and devices:

- **Core functionality** - Consistent behavior across browsers
- **Responsive design** - Mobile and tablet layouts
- **Touch interactions** - Mobile-specific interactions
- **Performance** - Load times and responsiveness
- **Accessibility** - Screen reader and keyboard navigation

### 7. Data Export Tests (`data-export.spec.ts`)

Tests data export functionality:

- **PDF generation** - Timesheet PDF export with formatting
- **CSV export** - Data integrity and special characters
- **Excel export** - Formatted spreadsheet generation
- **Date filtering** - Export specific date ranges
- **Permission validation** - Role-based export access

### 8. API Integration Tests (`api-integration.spec.ts`)

Tests with real backend APIs:

- **Authentication APIs** - Real OAuth and session management
- **CRUD operations** - Time entry lifecycle with real data
- **Approval APIs** - Real approval workflow integration
- **Error handling** - Real server error responses
- **Data consistency** - Data integrity across operations

## Page Object Models

### LoginPage

Handles all login page interactions:

```typescript
const loginPage = new LoginPage(page);
await loginPage.goto();
await loginPage.isDisplayed();
await loginPage.clickLogin();
await loginPage.waitForLoginComplete();
```

**Key Methods:**
- `goto()` - Navigate to login page
- `isDisplayed()` - Verify login page elements
- `clickLogin()` - Click login button
- `checkAccessibility()` - Verify accessibility compliance
- `testKeyboardNavigation()` - Test keyboard interaction

### MainAppPage

Manages main application interactions:

```typescript
const mainApp = new MainAppPage(page);
await mainApp.waitForLoad();
await mainApp.startTimer();
await mainApp.addTimeEntry();
await mainApp.navigateToApprovals();
```

**Key Methods:**
- `waitForLoad()` - Wait for application to load
- `startTimer()` / `stopTimer()` - Timer operations
- `addTimeEntry()` - Open time entry form
- `navigateToX()` - Navigate between sections
- `exportToPDF()` - Export functionality

### TimeEntryModal

Handles time entry form interactions:

```typescript
const modal = new TimeEntryModal(page);
await modal.waitForModal();
await modal.fillForm({ startTime: '09:00', stopTime: '17:00' });
await modal.save();
```

**Key Methods:**
- `waitForModal()` - Wait for modal to appear
- `fillForm()` - Fill form with data
- `save()` / `cancel()` - Form actions
- `hasValidationError()` - Check validation
- `checkAccessibility()` - Accessibility verification

## Best Practices

### Test Writing Guidelines

1. **Use descriptive test names:**
   ```typescript
   test('should allow employee to request time entry change with valid reason')
   ```

2. **Follow AAA pattern:**
   ```typescript
   test('should validate time entry data', async ({ page }) => {
     // Arrange
     await setupAuth(page, 'employee');
     
     // Act
     await page.fill('input[name="startTime"]', '17:00');
     await page.fill('input[name="stopTime"]', '09:00');
     await page.click('button[type="submit"]');
     
     // Assert
     await expect(page.locator('text=Invalid time range')).toBeVisible();
   });
   ```

3. **Use Page Object Models:**
   ```typescript
   test('should complete login flow', async ({ page }) => {
     const loginPage = new LoginPage(page);
     const mainApp = new MainAppPage(page);
     
     await loginPage.goto();
     await loginPage.clickLogin();
     await mainApp.waitForLoad();
     await mainApp.isLoggedIn();
   });
   ```

### Data Management

1. **Use test helpers for setup:**
   ```typescript
   await mockAuthentication(page, 'supervisor', {
     timeEntries: SAMPLE_TIME_ENTRIES,
     approvalRequests: []
   });
   ```

2. **Generate dynamic test data:**
   ```typescript
   const testEntry = generateTimeEntry({
     date: new Date().toISOString().split('T')[0],
     reason: 'Automated test entry'
   });
   ```

3. **Clean up test data:**
   ```typescript
   test.afterEach(async ({ page }) => {
     // Clean up any test data created
     await cleanupTestData(page);
   });
   ```

### Error Handling

1. **Use proper waiting strategies:**
   ```typescript
   // Wait for element to be visible
   await expect(page.locator('.success-message')).toBeVisible();
   
   // Wait for network idle
   await page.waitForLoadState('networkidle');
   
   // Wait for specific condition
   await page.waitForFunction(() => document.readyState === 'complete');
   ```

2. **Handle race conditions:**
   ```typescript
   // Wait for either success or error
   await Promise.race([
     expect(page.locator('.success-message')).toBeVisible(),
     expect(page.locator('.error-message')).toBeVisible()
   ]);
   ```

3. **Add debugging information:**
   ```typescript
   test('should handle complex interaction', async ({ page }) => {
     await debugScreenshot(page, 'before-interaction');
     // ... test logic
     await debugScreenshot(page, 'after-interaction');
   });
   ```

### Performance Considerations

1. **Minimize test setup time:**
   ```typescript
   // Use test.beforeAll for expensive setup
   test.beforeAll(async () => {
     await setupDatabase();
   });
   ```

2. **Parallel test execution:**
   ```typescript
   // Mark tests as independent
   test.describe.configure({ mode: 'parallel' });
   ```

3. **Efficient selectors:**
   ```typescript
   // Prefer data-testid selectors
   page.locator('[data-testid="submit-button"]')
   
   // Use specific selectors
   page.locator('button:has-text("Save")')
   ```

## CI/CD Integration

### GitHub Actions Workflow

The E2E tests are integrated into GitHub Actions with:

- **Multi-browser testing** - Chrome, Firefox, Safari
- **Environment-specific testing** - Staging and production
- **Parallel execution** - Tests run concurrently
- **Artifact collection** - Screenshots, videos, reports
- **PR integration** - Automatic comments on pull requests

### Workflow Triggers

Tests run on:

- **Push to main/develop** - Full test suite
- **Pull requests** - Affected tests only
- **Nightly schedule** - Complete regression testing
- **Manual dispatch** - On-demand testing with options

### Test Results

After test execution:

- **HTML reports** - Detailed test results with screenshots
- **JUnit XML** - For CI/CD integration
- **Screenshots** - On test failures
- **Videos** - For headed test runs
- **Performance metrics** - Lighthouse scores

### Environment Variables

Required CI/CD environment variables:

```bash
# Secrets
TEAMS_WEBHOOK_URL=https://outlook.office.com/webhook/...

# Configuration
NODE_VERSION=18
PLAYWRIGHT_BROWSERS_PATH=~/.cache/playwright
```

## Troubleshooting

### Common Issues

1. **Tests timing out:**
   ```bash
   # Increase timeout in playwright.config.ts
   timeout: 60000, // 60 seconds
   ```

2. **Browser not found:**
   ```bash
   # Reinstall browsers
   npx playwright install --with-deps
   ```

3. **Flaky tests:**
   ```typescript
   // Add proper waits
   await page.waitForLoadState('networkidle');
   await expect(element).toBeVisible();
   ```

4. **Authentication issues:**
   ```typescript
   // Verify mock setup
   await mockAuthentication(page, 'employee');
   await page.goto('/');
   ```

### Debug Mode

Run tests in debug mode:

```bash
# Interactive debugging
npm run test:e2e:ui

# Headed mode with devtools
npx playwright test --headed --debug

# Show browser during test
./scripts/run-e2e-tests.sh --headed
```

### Log Analysis

Check test logs:

```bash
# View test execution logs
cat build/e2e-reports/*/server.log

# Check browser console errors
npx playwright test --reporter=html
```

### Performance Issues

Profile test performance:

```bash
# Run with trace collection
npx playwright test --trace on

# Analyze trace files
npx playwright show-trace trace.zip
```

## Contributing

### Adding New Tests

1. **Choose appropriate test file** based on functionality
2. **Follow naming conventions** for test descriptions
3. **Use existing page objects** or create new ones
4. **Add proper documentation** and comments
5. **Test in multiple browsers** before submitting

### Page Object Updates

When updating page objects:

1. **Maintain backward compatibility** when possible
2. **Update all related tests** when changing interfaces
3. **Add JSDoc comments** for public methods
4. **Consider accessibility** in all interactions

### Test Data Management

1. **Use test helpers** for common data setup
2. **Avoid hard-coded values** where possible
3. **Clean up test data** after test execution
4. **Document test data requirements**

### Pull Request Guidelines

Before submitting:

1. **Run full test suite** locally
2. **Check test coverage** for new features
3. **Update documentation** as needed
4. **Add appropriate test categories**
5. **Ensure CI/CD passes** all checks

### Code Review Checklist

- [ ] Tests follow established patterns
- [ ] Page objects are used appropriately
- [ ] Error handling is comprehensive
- [ ] Accessibility considerations included
- [ ] Performance impact considered
- [ ] Documentation updated
- [ ] CI/CD integration verified

## Resources

### Documentation Links

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Testing Library Guidelines](https://testing-library.com/docs/guiding-principles/)
- [WCAG Accessibility Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

### Internal Resources

- [AZE Gemini API Documentation](./API_DOCUMENTATION.md)
- [Development Guidelines](./docs/DEVELOPMENT_GUIDELINES.md)
- [Security Implementation](./api/SECURITY_IMPLEMENTATION.md)
- [Deployment Guide](./DEPLOYMENT_GUIDE.md)

### Support Channels

- **GitHub Issues** - Bug reports and feature requests
- **Team Chat** - Quick questions and discussions
- **Code Reviews** - Pull request feedback and guidance
- **Documentation** - Comprehensive guides and references

---

This documentation is maintained by the development team and updated regularly. For questions or suggestions, please create an issue or contact the team directly.