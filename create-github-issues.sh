#!/bin/bash
# GitHub Issues Creation Script for AZE Gemini Project
# This script creates all 20 GitHub issues using the gh CLI
# Run this script when gh CLI is available and authenticated

set -e  # Exit on any error

echo "Creating GitHub issues for AZE Gemini Project..."
echo "================================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to create an issue
create_issue() {
    local issue_number=$1
    local title="$2"
    local labels="$3"
    local milestone="$4"
    local assignee="$5"
    local body_file="$6"
    
    echo -e "${BLUE}Creating Issue #${issue_number}: ${title}${NC}"
    
    # Build the gh command
    local gh_cmd="gh issue create --title \"${title}\""
    
    if [ -n "$labels" ]; then
        gh_cmd="$gh_cmd --label \"$labels\""
    fi
    
    if [ -n "$milestone" ]; then
        gh_cmd="$gh_cmd --milestone \"$milestone\""
    fi
    
    if [ -n "$assignee" ]; then
        gh_cmd="$gh_cmd --assignee \"$assignee\""
    fi
    
    # Add body from file
    gh_cmd="$gh_cmd --body-file \"$body_file\""
    
    echo "$gh_cmd"
    echo ""
}

# CRITICAL PRIORITY ISSUES
echo -e "${RED}CRITICAL PRIORITY ISSUES${NC}"
echo "=========================="

# Issue 001 - Already resolved, skip
echo -e "${GREEN}Issue #001: FTP Deployment Authentication Failure - ALREADY RESOLVED ✅${NC}"
echo ""

# Issue 002: Missing Test Coverage
create_issue "002" \
    "Missing Test Coverage - Critical Security Risk" \
    "critical,security,testing,technical-debt,infrastructure" \
    "Phase 1: Critical Security" \
    "@assignee-placeholder" \
    "github-issues/issue-002-missing-test-coverage.md"

# Issue 004: Database Backup Automation
create_issue "004" \
    "Database Backup Automation Missing" \
    "critical,database,backup,infrastructure,automation" \
    "Phase 1: Critical Security" \
    "@assignee-placeholder" \
    "github-issues/issue-004-database-backup-automation.md"

# Issue 005: Disaster Recovery Plan
create_issue "005" \
    "No Disaster Recovery Plan" \
    "critical,disaster-recovery,business-continuity,infrastructure" \
    "Phase 3: Infrastructure Modernization" \
    "@assignee-placeholder" \
    "github-issues/issue-005-disaster-recovery-plan.md"

# Issue 013: Multi-Factor Authentication
create_issue "013" \
    "Multi-Factor Authentication Implementation" \
    "security,authentication,critical,mfa,compliance" \
    "Phase 1: Critical Security" \
    "@assignee-placeholder" \
    "github-issues/issue-013-multi-factor-authentication.md"

# Issue 014: Security Incident Response
create_issue "014" \
    "Security Incident Response Playbook" \
    "security,incident-response,critical,compliance,documentation" \
    "Phase 1: Critical Security" \
    "@assignee-placeholder" \
    "github-issues/issue-014-security-incident-response.md"

# HIGH PRIORITY ISSUES
echo -e "${YELLOW}HIGH PRIORITY ISSUES${NC}"
echo "===================="

# Issue 003: Application Performance Monitoring
create_issue "003" \
    "No Application Performance Monitoring" \
    "enhancement,monitoring,performance,high-priority,operations" \
    "Phase 2: Performance and Monitoring" \
    "@assignee-placeholder" \
    "github-issues/issue-003-application-performance-monitoring.md"

# Issue 006: Zero-Trust Security Architecture
create_issue "006" \
    "Implement Zero-Trust Security Architecture" \
    "security,architecture,enhancement,zero-trust,high-priority" \
    "Phase 3: Infrastructure Modernization" \
    "@assignee-placeholder" \
    "github-issues/issue-006-zero-trust-security-architecture.md"

# Issue 008: Performance Optimization - Caching
create_issue "008" \
    "Performance Optimization - Caching Layer" \
    "performance,caching,optimization,high-priority,infrastructure" \
    "Phase 2: Performance and Monitoring" \
    "@assignee-placeholder" \
    "github-issues/issue-008-performance-optimization-caching.md"

# Issue 009: CI/CD Security Scanning
create_issue "009" \
    "CI/CD Security Scanning Integration" \
    "security,cicd,automation,high-priority,devops" \
    "Phase 3: Infrastructure Modernization" \
    "@assignee-placeholder" \
    "github-issues/issue-009-cicd-security-scanning.md"

# Issue 010: Infrastructure as Code
create_issue "010" \
    "Infrastructure as Code Implementation" \
    "infrastructure,automation,devops,high-priority,iac" \
    "Phase 3: Infrastructure Modernization" \
    "@assignee-placeholder" \
    "github-issues/issue-010-infrastructure-as-code.md"

# Issue 012: Database Query Performance
create_issue "012" \
    "Database Query Performance Monitoring" \
    "database,performance,monitoring,high-priority,optimization" \
    "Phase 2: Performance and Monitoring" \
    "@assignee-placeholder" \
    "github-issues/issue-012-database-query-performance.md"

# Issue 015: Automated Security Testing
create_issue "015" \
    "Automated Security Testing Suite" \
    "security,testing,automation,high-priority,quality-assurance" \
    "Phase 2: Performance and Monitoring" \
    "@assignee-placeholder" \
    "github-issues/issue-015-automated-security-testing.md"

# MEDIUM PRIORITY ISSUES
echo -e "${BLUE}MEDIUM PRIORITY ISSUES${NC}"
echo "======================"

# Issue 007: API Versioning Strategy
create_issue "007" \
    "API Versioning Strategy Missing" \
    "api,versioning,architecture,medium-priority,backward-compatibility" \
    "Phase 4: Developer Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-007-api-versioning-strategy.md"

# Issue 011: Frontend Bundle Optimization
create_issue "011" \
    "Frontend Bundle Size Optimization" \
    "frontend,performance,optimization,bundle-size,medium-priority" \
    "Phase 4: Developer Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-011-frontend-bundle-optimization.md"

# Issue 016: Component Reusability
create_issue "016" \
    "Component Reusability Improvements" \
    "frontend,components,architecture,reusability,medium-priority" \
    "Phase 4: Developer Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-016-component-reusability.md"

# Issue 017: API Documentation Enhancement
create_issue "017" \
    "API Documentation Enhancement" \
    "documentation,api,developer-experience,medium-priority" \
    "Phase 4: Developer Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-017-api-documentation-enhancement.md"

# Issue 018: User Experience Monitoring
create_issue "018" \
    "User Experience Monitoring" \
    "monitoring,user-experience,analytics,medium-priority" \
    "Phase 5: User Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-018-user-experience-monitoring.md"

# Issue 019: Configuration Management
create_issue "019" \
    "Configuration Management Standardization" \
    "configuration,standardization,devops,medium-priority" \
    "Phase 5: User Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-019-configuration-management.md"

# Issue 020: Development Environment Consistency
create_issue "020" \
    "Development Environment Consistency" \
    "development,environment,consistency,docker,medium-priority" \
    "Phase 5: User Experience" \
    "@assignee-placeholder" \
    "github-issues/issue-020-development-environment-consistency.md"

echo ""
echo -e "${GREEN}GitHub Issues Creation Script Complete!${NC}"
echo "========================================"
echo ""
echo "📋 Summary:"
echo "- Total Issues: 20 (1 already resolved, 19 to create)"
echo "- Critical Issues: 5"
echo "- High Priority Issues: 7" 
echo "- Medium Priority Issues: 7"
echo ""
echo "🔧 To run this script:"
echo "1. Ensure you have gh CLI installed and authenticated"
echo "2. Navigate to your GitHub repository directory"
echo "3. Run: ./create-github-issues.sh"
echo ""
echo "📝 Notes:"
echo "- Replace '@assignee-placeholder' with actual GitHub usernames"
echo "- Create milestones in GitHub before running if they don't exist"
echo "- Ensure all markdown files are in the github-issues/ directory"
echo ""
echo "🏷️  Milestones to create:"
echo "- Phase 1: Critical Security"
echo "- Phase 2: Performance and Monitoring" 
echo "- Phase 3: Infrastructure Modernization"
echo "- Phase 4: Developer Experience"
echo "- Phase 5: User Experience"