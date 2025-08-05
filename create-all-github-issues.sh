#!/bin/bash

# GitHub Issues Creation Script for MP-AZE/mp-aze Repository
# This script creates all 20 GitHub issues using the GitHub API directly with curl
# 
# Usage: ./create-all-github-issues.sh [GITHUB_TOKEN]
# 
# The script will:
# 1. Read markdown content from github-issues directory
# 2. Extract title and labels from each issue file
# 3. Create issues using GitHub API with curl
# 4. Handle critical issues first as requested

set -e  # Exit on any error

# Configuration
REPO_OWNER="MP-AZE"
REPO_NAME="mp-aze"
GITHUB_API_URL="https://api.github.com"
ISSUES_DIR="/app/projects/aze-gemini/github-issues"
LOG_FILE="/app/projects/aze-gemini/github-issues-creation.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to log messages
log_message() {
    local level=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
}

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to check if GitHub token is provided
check_github_token() {
    if [ -z "$GITHUB_TOKEN" ]; then
        print_status "$RED" "❌ Error: GitHub token is required"
        print_status "$YELLOW" "Usage: $0 [GITHUB_TOKEN]"
        print_status "$YELLOW" "Or set GITHUB_TOKEN environment variable"
        print_status "$BLUE" "Get your token from: https://github.com/settings/tokens"
        print_status "$BLUE" "Required scopes: repo (for private repos) or public_repo (for public repos)"
        exit 1
    fi
}

# Function to extract title from markdown file
extract_title() {
    local file=$1
    # Extract title from first line, remove # and clean up
    local title=$(head -n 1 "$file" | sed 's/^# //' | sed 's/Issue #[0-9]*: //' | sed 's/ - SOLVED$//' | sed 's/ - .*$//')
    echo "$title"
}

# Function to extract labels from markdown file
extract_labels() {
    local file=$1
    local labels=""
    
    # Look for labels section in the file
    if grep -q "## Labels" "$file"; then
        labels=$(grep -A 1 "## Labels" "$file" | tail -n 1 | sed 's/`//g' | tr ',' '\n' | sed 's/^ *//' | sed 's/ *$//' | tr '\n' ',' | sed 's/,$//')
    fi
    
    # Determine priority based on content (avoid duplicates)
    if grep -q "Priority: CRITICAL" "$file"; then
        if [[ "$labels" != *"critical"* ]]; then
            if [ -n "$labels" ]; then
                labels="$labels,critical"
            else
                labels="critical"
            fi
        fi
    elif grep -q "Priority: HIGH" "$file"; then
        if [[ "$labels" != *"high-priority"* ]]; then
            if [ -n "$labels" ]; then
                labels="$labels,high-priority"
            else
                labels="high-priority"
            fi
        fi
    fi
    
    # Add resolved label if applicable (avoid duplicates)
    if grep -q "Status: RESOLVED" "$file" || grep -q "SOLVED" "$file"; then
        if [[ "$labels" != *"resolved"* ]]; then
            if [ -n "$labels" ]; then
                labels="$labels,resolved"
            else
                labels="resolved"
            fi
        fi
    fi
    
    echo "$labels"
}

# Function to create GitHub issue
create_github_issue() {
    local issue_file=$1
    local issue_number=$2
    
    if [ ! -f "$issue_file" ]; then
        log_message "ERROR" "Issue file not found: $issue_file"
        return 1
    fi
    
    print_status "$BLUE" "📝 Processing Issue #$(printf "%03d" $issue_number)..."
    
    # Extract title and labels
    local title=$(extract_title "$issue_file")
    local labels=$(extract_labels "$issue_file")
    local body=$(cat "$issue_file")
    
    # Escape JSON special characters in body
    local escaped_body=$(echo "$body" | sed 's/\\/\\\\/g' | sed 's/"/\\"/g' | sed ':a;N;$!ba;s/\n/\\n/g')
    
    # Create JSON payload
    local json_payload=""
    if [ -n "$labels" ]; then
        # Convert comma-separated labels to JSON array
        local labels_array=""
        IFS=',' read -ra LABEL_ARRAY <<< "$labels"
        for label in "${LABEL_ARRAY[@]}"; do
            label=$(echo "$label" | sed 's/^ *//' | sed 's/ *$//')  # trim whitespace
            if [ -n "$labels_array" ]; then
                labels_array="$labels_array,\"$label\""
            else
                labels_array="\"$label\""
            fi
        done
        
        json_payload=$(cat <<EOF
{
    "title": "$title",
    "body": "$escaped_body",
    "labels": [$labels_array]
}
EOF
)
    else
        json_payload=$(cat <<EOF
{
    "title": "$title",
    "body": "$escaped_body"
}
EOF
)
    fi
    
    # Make API request
    local response=$(curl -s -w "%{http_code}" -o /tmp/github_response.json \
        -X POST \
        -H "Accept: application/vnd.github.v3+json" \
        -H "Authorization: token $GITHUB_TOKEN" \
        -H "Content-Type: application/json" \
        -d "$json_payload" \
        "$GITHUB_API_URL/repos/$REPO_OWNER/$REPO_NAME/issues")
    
    local http_code="${response: -3}"
    
    if [ "$http_code" = "201" ]; then
        local issue_url=$(cat /tmp/github_response.json | grep -o '"html_url":"[^"]*"' | cut -d'"' -f4)
        print_status "$GREEN" "✅ Issue #$(printf "%03d" $issue_number) created successfully: $title"
        print_status "$GREEN" "   🔗 URL: $issue_url"
        log_message "SUCCESS" "Issue #$(printf "%03d" $issue_number) created: $title - $issue_url"
        return 0
    else
        print_status "$RED" "❌ Failed to create Issue #$(printf "%03d" $issue_number): $title"
        print_status "$RED" "   HTTP Code: $http_code"
        if [ -f /tmp/github_response.json ]; then
            local error_message=$(cat /tmp/github_response.json | grep -o '"message":"[^"]*"' | cut -d'"' -f4)
            if [ -n "$error_message" ]; then
                print_status "$RED" "   Error: $error_message"
                log_message "ERROR" "Issue #$(printf "%03d" $issue_number) failed: $error_message"
            fi
        fi
        return 1
    fi
}

# Function to test GitHub API connectivity
test_github_api() {
    print_status "$BLUE" "🔍 Testing GitHub API connectivity..."
    
    local response=$(curl -s -w "%{http_code}" -o /tmp/github_test.json \
        -H "Accept: application/vnd.github.v3+json" \
        -H "Authorization: token $GITHUB_TOKEN" \
        "$GITHUB_API_URL/repos/$REPO_OWNER/$REPO_NAME")
    
    local http_code="${response: -3}"
    
    if [ "$http_code" = "200" ]; then
        print_status "$GREEN" "✅ GitHub API connection successful"
        local repo_name=$(cat /tmp/github_test.json | grep -o '"full_name":"[^"]*"' | cut -d'"' -f4)
        print_status "$GREEN" "   📁 Repository: $repo_name"
        return 0
    else
        print_status "$RED" "❌ GitHub API connection failed"
        print_status "$RED" "   HTTP Code: $http_code"
        if [ -f /tmp/github_test.json ]; then
            local error_message=$(cat /tmp/github_test.json | grep -o '"message":"[^"]*"' | cut -d'"' -f4)
            if [ -n "$error_message" ]; then
                print_status "$RED" "   Error: $error_message"
            fi
        fi
        return 1
    fi
}

# Main execution function
main() {
    print_status "$BLUE" "🚀 GitHub Issues Creation Script for $REPO_OWNER/$REPO_NAME"
    print_status "$BLUE" "=================================================="
    
    # Initialize log file
    echo "GitHub Issues Creation Log - $(date)" > "$LOG_FILE"
    log_message "INFO" "Starting GitHub issues creation process"
    
    # Get GitHub token from parameter or environment
    if [ -n "$1" ]; then
        GITHUB_TOKEN="$1"
    fi
    
    # Check for GitHub token
    check_github_token
    
    # Test GitHub API connectivity
    if ! test_github_api; then
        print_status "$RED" "❌ Cannot proceed without GitHub API access"
        exit 1
    fi
    
    # Check if issues directory exists
    if [ ! -d "$ISSUES_DIR" ]; then
        print_status "$RED" "❌ Issues directory not found: $ISSUES_DIR"
        exit 1
    fi
    
    print_status "$BLUE" "📂 Issues directory: $ISSUES_DIR"
    
    # Define critical issues to process first (as requested)
    local critical_issues=(
        "issue-002-missing-test-coverage.md"
        "issue-003-application-performance-monitoring.md"
        "issue-004-database-backup-automation.md"
        "issue-005-disaster-recovery-plan.md"
        "issue-013-multi-factor-authentication.md"
    )
    
    # Define all other issues
    local other_issues=(
        "issue-001-ftp-deployment-authentication.md"
        "issue-006-zero-trust-security-architecture.md"
        "issue-007-api-versioning-strategy.md"
        "issue-008-performance-optimization-caching.md"
        "issue-009-cicd-security-scanning.md"
        "issue-010-infrastructure-as-code.md"
        "issue-011-frontend-bundle-optimization.md"
        "issue-012-database-query-performance.md"
        "issue-014-security-incident-response.md"
        "issue-015-automated-security-testing.md"
        "issue-016-component-reusability.md"
        "issue-017-api-documentation-enhancement.md"
        "issue-018-user-experience-monitoring.md"
        "issue-019-configuration-management.md"
        "issue-020-development-environment-consistency.md"
    )
    
    local success_count=0
    local failure_count=0
    local total_issues=$((${#critical_issues[@]} + ${#other_issues[@]}))
    
    print_status "$YELLOW" "🎯 Processing Critical Issues First (${#critical_issues[@]} issues)..."
    print_status "$YELLOW" "=============================================="
    
    # Process critical issues first
    for issue_file in "${critical_issues[@]}"; do
        local issue_number=$(echo "$issue_file" | grep -o '[0-9]\+' | head -n 1 | sed 's/^0*//')
        local full_path="$ISSUES_DIR/$issue_file"
        
        if create_github_issue "$full_path" "$issue_number"; then
            ((success_count++))
        else
            ((failure_count++))
        fi
        
        # Small delay to be respectful to GitHub API
        sleep 2
    done
    
    print_status "$YELLOW" ""
    print_status "$YELLOW" "📋 Processing Remaining Issues (${#other_issues[@]} issues)..."
    print_status "$YELLOW" "========================================="
    
    # Process remaining issues
    for issue_file in "${other_issues[@]}"; do
        local issue_number=$(echo "$issue_file" | grep -o '[0-9]\+' | head -n 1 | sed 's/^0*//')
        local full_path="$ISSUES_DIR/$issue_file"
        
        if create_github_issue "$full_path" "$issue_number"; then
            ((success_count++))
        else
            ((failure_count++))
        fi
        
        # Small delay to be respectful to GitHub API
        sleep 2
    done
    
    # Summary
    print_status "$BLUE" ""
    print_status "$BLUE" "📊 Summary Report"
    print_status "$BLUE" "================="
    print_status "$GREEN" "✅ Successfully created: $success_count issues"
    if [ $failure_count -gt 0 ]; then
        print_status "$RED" "❌ Failed to create: $failure_count issues"
    fi
    print_status "$BLUE" "📝 Total processed: $total_issues issues"
    print_status "$BLUE" "📋 Log file: $LOG_FILE"
    
    log_message "INFO" "GitHub issues creation completed: $success_count successful, $failure_count failed"
    
    if [ $failure_count -eq 0 ]; then
        print_status "$GREEN" ""
        print_status "$GREEN" "🎉 All GitHub issues created successfully!"
        print_status "$GREEN" "🔗 View them at: https://github.com/$REPO_OWNER/$REPO_NAME/issues"
        exit 0
    else
        print_status "$YELLOW" ""
        print_status "$YELLOW" "⚠️  Some issues failed to create. Check the log for details."
        exit 1
    fi
}

# Script usage information
show_usage() {
    print_status "$BLUE" "GitHub Issues Creation Script"
    print_status "$BLUE" "============================="
    print_status "$BLUE" ""
    print_status "$BLUE" "Usage: $0 [GITHUB_TOKEN]"
    print_status "$BLUE" ""
    print_status "$BLUE" "Parameters:"
    print_status "$BLUE" "  GITHUB_TOKEN    Your GitHub personal access token"
    print_status "$BLUE" ""
    print_status "$BLUE" "Environment Variables:"
    print_status "$BLUE" "  GITHUB_TOKEN    Alternative way to provide the token"
    print_status "$BLUE" ""
    print_status "$BLUE" "Examples:"
    print_status "$BLUE" "  $0 ghp_your_token_here"
    print_status "$BLUE" "  GITHUB_TOKEN=ghp_your_token_here $0"
    print_status "$BLUE" ""
    print_status "$BLUE" "Get your token from: https://github.com/settings/tokens"
    print_status "$BLUE" "Required scopes: 'repo' (private) or 'public_repo' (public)"
}

# Handle help parameter
if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    show_usage
    exit 0
fi

# Run main function
main "$@"