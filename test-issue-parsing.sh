#!/bin/bash

# Test script to demonstrate issue parsing functionality
# This shows how the main script extracts titles and labels

ISSUES_DIR="/app/projects/aze-gemini/github-issues"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to extract title from markdown file
extract_title() {
    local file=$1
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

print_status "$BLUE" "🧪 GitHub Issue Parsing Test"
print_status "$BLUE" "=============================="

# Test parsing for critical issues first
critical_issues=(
    "issue-002-missing-test-coverage.md"
    "issue-003-application-performance-monitoring.md"
    "issue-004-database-backup-automation.md"
    "issue-005-disaster-recovery-plan.md"
    "issue-013-multi-factor-authentication.md"
)

print_status "$YELLOW" ""
print_status "$YELLOW" "🎯 Critical Issues (will be processed first):"
print_status "$YELLOW" "=============================================="

for issue_file in "${critical_issues[@]}"; do
    full_path="$ISSUES_DIR/$issue_file"
    if [ -f "$full_path" ]; then
        issue_number=$(echo "$issue_file" | grep -o '[0-9]\+' | head -n 1 | sed 's/^0*//')
        title=$(extract_title "$full_path")
        labels=$(extract_labels "$full_path")
        
        print_status "$GREEN" "📝 Issue #$(printf "%d" $issue_number): $title"
        if [ -n "$labels" ]; then
            print_status "$BLUE" "   🏷️  Labels: $labels"
        else
            print_status "$BLUE" "   🏷️  Labels: (none detected)"
        fi
        echo
    else
        print_status "$RED" "❌ File not found: $issue_file"
    fi
done

# Test a few other issues
print_status "$YELLOW" "📋 Sample of Other Issues:"
print_status "$YELLOW" "=========================="

other_samples=(
    "issue-001-ftp-deployment-authentication.md"
    "issue-006-zero-trust-security-architecture.md"
    "issue-010-infrastructure-as-code.md"
)

for issue_file in "${other_samples[@]}"; do
    full_path="$ISSUES_DIR/$issue_file"
    if [ -f "$full_path" ]; then
        issue_number=$(echo "$issue_file" | grep -o '[0-9]\+' | head -n 1 | sed 's/^0*//')
        title=$(extract_title "$full_path")
        labels=$(extract_labels "$full_path")
        
        print_status "$GREEN" "📝 Issue #$(printf "%d" $issue_number): $title"
        if [ -n "$labels" ]; then
            print_status "$BLUE" "   🏷️  Labels: $labels"
        else
            print_status "$BLUE" "   🏷️  Labels: (none detected)"
        fi
        echo
    else
        print_status "$RED" "❌ File not found: $issue_file"
    fi
done

# Count total issues
total_issues=$(find "$ISSUES_DIR" -name "issue-*.md" | wc -l)
print_status "$BLUE" "📊 Total Issues Found: $total_issues"
print_status "$BLUE" "📂 Issues Directory: $ISSUES_DIR"

print_status "$GREEN" ""
print_status "$GREEN" "✅ Issue parsing test completed!"
print_status "$GREEN" "   The main script will use this same parsing logic to create GitHub issues."