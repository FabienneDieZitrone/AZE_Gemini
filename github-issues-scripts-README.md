# GitHub Issues Creation Scripts

This directory contains scripts to automatically create all 20 GitHub issues for the AZE Gemini project using GitHub CLI.

## Files

- **`create-github-issues.sh`** - Main executable script that creates all issues
- **`gh-commands-preview.sh`** - Shows the exact `gh` commands that will be executed
- **`github-issues/`** - Directory containing all 20 issue markdown files

## Prerequisites

1. **GitHub CLI installed and authenticated**
   ```bash
   # Install gh CLI
   curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
   echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
   sudo apt update
   sudo apt install gh
   
   # Authenticate
   gh auth login
   ```

2. **Repository access**
   - Navigate to your GitHub repository directory
   - Ensure you have write access to create issues

3. **Milestones created** (create these in GitHub first):
   - Phase 1: Critical Security
   - Phase 2: Performance and Monitoring
   - Phase 3: Infrastructure Modernization
   - Phase 4: Developer Experience
   - Phase 5: User Experience

## Usage

### Preview Commands
To see what commands will be executed:
```bash
./gh-commands-preview.sh
```

### Create All Issues
To create all 19 issues (Issue #001 is already resolved):
```bash
./create-github-issues.sh
```

## Issue Summary

### Critical Priority (5 issues)
- **Issue #002**: Missing Test Coverage - Critical Security Risk
- **Issue #004**: Database Backup Automation Missing
- **Issue #005**: No Disaster Recovery Plan
- **Issue #013**: Multi-Factor Authentication Implementation
- **Issue #014**: Security Incident Response Playbook

### High Priority (7 issues)
- **Issue #003**: No Application Performance Monitoring
- **Issue #006**: Implement Zero-Trust Security Architecture
- **Issue #008**: Performance Optimization - Caching Layer
- **Issue #009**: CI/CD Security Scanning Integration
- **Issue #010**: Infrastructure as Code Implementation
- **Issue #012**: Database Query Performance Monitoring
- **Issue #015**: Automated Security Testing Suite

### Medium Priority (7 issues)
- **Issue #007**: API Versioning Strategy Missing
- **Issue #011**: Frontend Bundle Size Optimization
- **Issue #016**: Component Reusability Improvements
- **Issue #017**: API Documentation Enhancement
- **Issue #018**: User Experience Monitoring
- **Issue #019**: Configuration Management Standardization
- **Issue #020**: Development Environment Consistency

## Labels Used

- **Priority**: `critical`, `high-priority`, `medium-priority`
- **Category**: `security`, `performance`, `infrastructure`, `frontend`, `backend`
- **Type**: `bug`, `enhancement`, `documentation`, `testing`
- **Technology**: `database`, `api`, `cicd`, `devops`, `mfa`, `monitoring`

## Customization

Before running the script:

1. **Replace assignees**: Change `@assignee-placeholder` to actual GitHub usernames
2. **Adjust milestones**: Modify milestone names if different in your repository
3. **Update labels**: Add or modify labels as needed for your project

## Manual Command Example

If you prefer to create issues manually, here's an example command:

```bash
gh issue create \
  --title "Missing Test Coverage - Critical Security Risk" \
  --label "critical,security,testing,technical-debt,infrastructure" \
  --milestone "Phase 1: Critical Security" \
  --assignee "your-username" \
  --body-file "github-issues/issue-002-missing-test-coverage.md"
```

## Notes

- Issue #001 (FTP Deployment Authentication) is already resolved and marked as completed
- All issue descriptions include detailed implementation steps, success criteria, and acceptance criteria
- Each issue has estimated effort, priority level, and related dependencies
- The issues are organized by implementation phases for better project management

## Troubleshooting

### Common Issues
- **Authentication Error**: Run `gh auth status` to check authentication
- **Repository Access**: Ensure you're in the correct repository directory
- **Milestone Not Found**: Create milestones in GitHub UI before running script
- **File Not Found**: Ensure all markdown files exist in `github-issues/` directory

### Verification
After running the script, verify issues were created:
```bash
gh issue list --limit 25
```