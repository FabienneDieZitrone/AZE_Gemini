# Claude Flow Swarm - AZE Gemini Testing Framework

## Overview

This is a multi-agent testing framework designed to comprehensively test the AZE Gemini time tracking application. The swarm simulates multiple specialized agents working in parallel to test different aspects of the application.

## Architecture

### Agents

1. **Coordinator** - Orchestrates the testing process
2. **Researcher** - Analyzes codebase and identifies test areas
3. **Tester** - Executes functional tests
4. **Security Analyst** - Verifies security implementations
5. **API Tester** - Tests backend endpoints

### Components

- `swarm-init.js` - Initializes agents and task hierarchy
- `test-executor.js` - Executes tests across categories
- `run-swarm.js` - Main orchestrator
- `package.json` - Dependencies and scripts

## Usage

### Quick Start

```bash
# Navigate to test swarm directory
cd /app/build/test-swarm

# Install dependencies (if needed)
npm install

# Run the complete swarm
npm start
```

### Individual Components

```bash
# Initialize swarm only
npm run init

# Run tests only
npm run test

# Full swarm execution
npm run swarm
```

## Test Categories

1. **Authentication Tests**
   - Azure AD OAuth flow
   - Session management
   - Login security

2. **API Tests**
   - Endpoint availability
   - Response validation
   - Error handling

3. **Security Tests**
   - CSRF protection
   - XSS prevention
   - SQL injection protection
   - Security headers

4. **Frontend Tests**
   - Build integrity
   - Asset loading
   - Configuration

5. **Performance Tests**
   - Response times
   - Bundle size
   - Load testing

## Output

The swarm generates:
- Console output with real-time progress
- JSON test report with detailed results
- Recommendations for improvements
- Risk analysis

## Test Report

Reports are saved to: `test-report-{timestamp}.json`

Report includes:
- Summary statistics
- Error details
- Performance metrics
- Agent activity
- Recommendations

## Extending the Framework

To add new tests:

1. Add test definitions to `test-executor.js`
2. Create new agent in `swarm-init.js` if needed
3. Update task hierarchy for new test areas

## Requirements

- Node.js 18+
- Access to AZE Gemini codebase
- Configured environment (see `.env.example`)

## Notes

- This is a simulation framework for testing purposes
- Real HTTP requests are mocked in this version
- Can be extended to make actual API calls
- Designed to work with the existing AZE Gemini structure