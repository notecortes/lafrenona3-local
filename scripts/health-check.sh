#!/bin/bash

# Health check script for staging environment
# Usage: ./scripts/health-check.sh

set -e

echo "=== La Frenona 3 Staging Health Check ==="
echo "Date: $(date)"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0
WARNINGS=0

check_pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASSED++))
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    ((FAILED++))
}

check_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARNINGS++))
}

# Check Docker services
echo "--- Docker Services ---"
for service in db redis backend nginx; do
    if docker ps --format '{{.Names}}' | grep -q "lafrenona3_staging_${service}"; then
        check_pass "${service} is running"
    else
        check_fail "${service} is NOT running"
    fi
done

echo ""
echo "--- Database Connection ---"
if docker exec lafrenona3_staging_backend php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; then
    check_pass "Database connection successful"
else
    check_fail "Database connection failed"
fi

echo ""
echo "--- Redis Connection ---"
if docker exec lafrenona3_staging_redis redis-cli ping 2>/dev/null | grep -q "PONG"; then
    check_pass "Redis connection successful"
else
    check_fail "Redis connection failed"
fi

echo ""
echo "--- API Health Check ---"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:5005/up 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    check_pass "API health endpoint returns 200"
else
    check_fail "API health endpoint returns ${HTTP_CODE}"
fi

echo ""
echo "--- Frontend Build ---"
if [ -d "frontend/dist" ] && [ -f "frontend/dist/index.html" ]; then
    check_pass "Frontend build exists"
else
    check_warn "Frontend build not found (may need npm run build)"
fi

echo ""
echo "--- Environment Files ---"
if [ -f ".env.staging" ]; then
    check_pass ".env.staging exists"
else
    check_fail ".env.staging not found"
fi

if grep -q "APP_ENV=staging" .env.staging 2>/dev/null; then
    check_pass "APP_ENV is set to staging"
else
    check_fail "APP_ENV is not set to staging"
fi

if grep -q "APP_DEBUG=false" .env.staging 2>/dev/null; then
    check_pass "APP_DEBUG is disabled"
else
    check_fail "APP_DEBUG should be false in staging"
fi

echo ""
echo "--- Security Headers ---"
HEADERS=$(curl -s -I http://localhost:5005/ 2>/dev/null || echo "")
if echo "$HEADERS" | grep -q "X-Frame-Options"; then
    check_pass "X-Frame-Options header present"
else
    check_warn "X-Frame-Options header missing"
fi

if echo "$HEADERS" | grep -q "X-Content-Type-Options"; then
    check_pass "X-Content-Type-Options header present"
else
    check_warn "X-Content-Type-Options header missing"
fi

echo ""
echo "=== Health Check Summary ==="
echo -e "Passed: ${GREEN}${PASSED}${NC}"
echo -e "Failed: ${RED}${FAILED}${NC}"
echo -e "Warnings: ${YELLOW}${WARNINGS}${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo -e "${RED}Health check FAILED${NC}"
    exit 1
else
    echo -e "${GREEN}Health check PASSED${NC}"
    exit 0
fi
