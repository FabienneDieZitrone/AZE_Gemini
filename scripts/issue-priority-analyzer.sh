#!/bin/bash
# AZE Issue Priority Analyzer
# Automatische Priorisierung und Kategorisierung aller GitHub Issues

GH_TOKEN=${GH_TOKEN:-$1}
REPO="FabienneDieZitrone/AZE_Gemini"

echo "🎯 AZE Issue Priority Analyzer"
echo "=============================="
echo ""

# Fetch all issues
echo "📥 Fetching all open issues..."
gh issue list --repo $REPO --state open --limit 200 --json number,title,labels,body > /tmp/issues.json

# Count total
TOTAL=$(jq length /tmp/issues.json)
echo "📊 Total open issues: $TOTAL"
echo ""

# Categorize by keywords
echo "🔍 Analyzing issue priorities..."

# Critical Security Issues
echo -e "\n🔴 CRITICAL SECURITY ISSUES:"
jq -r '.[] | select(.title | test("(?i)(security|auth|password|credential|hack|vulnerability|csrf|xss|sql|injection)")) | "  #\(.number): \(.title)"' /tmp/issues.json

# Performance Issues  
echo -e "\n⚡ PERFORMANCE ISSUES:"
jq -r '.[] | select(.title | test("(?i)(performance|slow|optimization|n\\+1|query|bundle|size)")) | "  #\(.number): \(.title)"' /tmp/issues.json

# Compliance Issues
echo -e "\n⚖️  COMPLIANCE ISSUES:"
jq -r '.[] | select(.title | test("(?i)(dsgvo|gdpr|arbzg|compliance|legal|pause)")) | "  #\(.number): \(.title)"' /tmp/issues.json

# Test Coverage
echo -e "\n🧪 TESTING ISSUES:"
jq -r '.[] | select(.title | test("(?i)(test|coverage|e2e|unit|integration)")) | "  #\(.number): \(.title)"' /tmp/issues.json

# Find potential duplicates
echo -e "\n🔄 POTENTIAL DUPLICATES:"
# Group by similar titles
jq -r '.[] | .title' /tmp/issues.json | sort | uniq -d

# Find stale issues (not updated in 30+ days)
echo -e "\n📅 STALE ISSUES (30+ days):"
THIRTY_DAYS_AGO=$(date -d '30 days ago' +%Y-%m-%d)
jq -r --arg date "$THIRTY_DAYS_AGO" '.[] | select(.updatedAt < $date) | "  #\(.number): \(.title) (Last: \(.updatedAt | split("T")[0]))"' /tmp/issues.json

# Generate priority matrix
echo -e "\n📊 PRIORITY MATRIX:"
echo "==================="
CRITICAL=$(jq -r '[.[] | select(.labels[]?.name | test("(?i)(critical|security|bug)"))] | length' /tmp/issues.json)
HIGH=$(jq -r '[.[] | select(.labels[]?.name | test("(?i)(high|important)"))] | length' /tmp/issues.json) 
MEDIUM=$(jq -r '[.[] | select(.labels[]?.name | test("(?i)(medium|enhancement)"))] | length' /tmp/issues.json)
LOW=$(jq -r '[.[] | select(.labels[]?.name | test("(?i)(low|nice-to-have)"))] | length' /tmp/issues.json)

echo "🔴 Critical: $CRITICAL issues"
echo "🟠 High: $HIGH issues"
echo "🟡 Medium: $MEDIUM issues"
echo "🟢 Low: $LOW issues"

# Actionable recommendations
echo -e "\n💡 RECOMMENDATIONS:"
echo "=================="
echo "1. Focus on CRITICAL security issues first (est. 80h)"
echo "2. Set up automated testing (est. 40h)"
echo "3. Implement CI/CD pipeline (est. 24h)"
echo "4. Close stale/duplicate issues"
echo "5. Add priority labels to unlabeled issues"

# Export priority list
echo -e "\n📁 Exporting priority list to: priority-issues.csv"
echo "number,title,priority,category" > priority-issues.csv
jq -r '.[] | 
  (if (.title | test("(?i)(security|auth|password)")) then "CRITICAL" 
   elif (.title | test("(?i)(bug|error|fix)")) then "HIGH"
   elif (.title | test("(?i)(feature|enhancement)")) then "MEDIUM"
   else "LOW" end) as $priority |
  (if (.title | test("(?i)(security|auth)")) then "Security"
   elif (.title | test("(?i)(performance)")) then "Performance"
   elif (.title | test("(?i)(test)")) then "Testing"
   elif (.title | test("(?i)(dsgvo|legal)")) then "Compliance"
   else "Feature" end) as $category |
  "\(.number),\"\(.title)\",\($priority),\($category)"' /tmp/issues.json >> priority-issues.csv

echo -e "\n✅ Analysis complete!"