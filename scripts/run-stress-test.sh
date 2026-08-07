#!/bin/bash

# Stress test runner script
# Usage: ./scripts/run-stress-test.sh [options]
#
# Options:
#   --users NUM       Number of concurrent users (default: 10)
#   --duration SECS   Duration in seconds (default: 60)
#   --endpoint STR    Endpoint to test: all, menu, login, orders (default: all)
#   --baseline        Run baseline test (10 users, 30 seconds)
#   --help            Show help

set -e

# Default values
USERS=10
DURATION=60
ENDPOINT="all"
BASELINE=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --users)
            USERS="$2"
            shift 2
            ;;
        --duration)
            DURATION="$2"
            shift 2
            ;;
        --endpoint)
            ENDPOINT="$2"
            shift 2
            ;;
        --baseline)
            BASELINE=true
            USERS=10
            DURATION=30
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --users NUM       Number of concurrent users (default: 10)"
            echo "  --duration SECS   Duration in seconds (default: 60)"
            echo "  --endpoint STR    Endpoint to test: all, menu, login, orders (default: all)"
            echo "  --baseline        Run baseline test (10 users, 30 seconds)"
            echo "  --help            Show help"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=== La Frenona 3 Stress Test Runner ==="
echo ""
echo "Configuration:"
echo "  Users: ${USERS}"
echo "  Duration: ${DURATION}s"
echo "  Endpoint: ${ENDPOINT}"
echo ""

# Check if k6 is installed
if ! command -v k6 &> /dev/null; then
    echo -e "${YELLOW}k6 not found. Installing k6...${NC}"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install k6
    else
        sudo apt-get install -y apt-transport-https gnupg curl
        curl -s https://packagecloud.io/install/repositories/grafana/k6/script.deb.sh | sudo bash
        sudo apt-get install k6
    fi
fi

# Check if backend is running
echo "Checking backend availability..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:4005/up | grep -q "200"; then
    echo -e "${GREEN}✓ Backend is running${NC}"
else
    echo -e "${RED}✗ Backend is not running${NC}"
    echo "Start it with: docker compose up -d"
    exit 1
fi

# Run stress test
echo ""
echo "Starting stress test..."
echo ""

cd tests/stress-test/k6

# Run k6 stress test
k6 run --out json=stress-results.json stress-test.js \
    --user ${USERS} \
    --duration ${DURATION} \
    2>&1 | tee stress-test-output.log

EXIT_CODE=${PIPESTATUS[0]}

echo ""
echo "=== Stress Test Complete ==="

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ Stress test completed successfully${NC}"
else
    echo -e "${RED}✗ Stress test failed${NC}"
fi

# Show results summary
if [ -f "stress-results.json" ]; then
    echo ""
    echo "Results saved to: stress-results.json"
    echo ""
    
    # Extract key metrics
    echo "Key Metrics:"
    grep -o '"http_req_duration.*' stress-results.json | head -1 | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    print(f'  Average Response Time: {data.get(\"http_req_duration\", {}).get(\"avg\", 0):.2f}ms')
    print(f'  P95 Response Time: {data.get(\"http_req_duration\", {}).get(\"p(95)\", 0):.2f}ms')
    print(f'  P99 Response Time: {data.get(\"http_req_duration\", {}).get(\"p(99)\", 0):.2f}ms')
    print(f'  Requests/sec: {data.get(\"vus\", {}).get(\"max\", 0):.2f}')
except:
    pass
" 2>/dev/null || echo "  (Install jq for better results parsing)"
fi

exit $EXIT_CODE
