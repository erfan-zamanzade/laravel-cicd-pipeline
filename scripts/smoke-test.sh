#!/usr/bin/env bash
# Smoke test against a running docker compose stack.
#
# 1. The app becomes healthy and ready, and reports the expected build.
# 2. Requests show up in /metrics.
# 3. Failure drill: with PostgreSQL stopped, /ready turns 503 while /health
#    stays 200 (so an orchestrator stops routing traffic instead of
#    restarting the container), then recovers once PostgreSQL is back.
#    The same for Redis, which also stores the request metrics.
#
# Usage: scripts/smoke-test.sh [base-url]   (default http://127.0.0.1:${APP_PORT:-8080})
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:${APP_PORT:-8080}}"
EXPECTED_VERSION="${APP_VERSION:-}"
TIMEOUT="${SMOKE_TIMEOUT:-60}"

pass() { printf '  \033[32mPASS\033[0m %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; exit 1; }

status_of() { curl -s -o /dev/null -w '%{http_code}' "$BASE_URL$1" || true; }

wait_for() { # path expected-status
    local deadline=$((SECONDS + TIMEOUT))
    until [ "$(status_of "$1")" = "$2" ]; do
        [ $SECONDS -lt $deadline ] || fail "$1 did not return $2 within ${TIMEOUT}s (last: $(status_of "$1"))"
        sleep 1
    done
}

echo "Smoke testing $BASE_URL"

wait_for /health 200 && pass "/health returns 200"
wait_for /ready 200 && pass "/ready returns 200 with database and cache reachable"

info="$(curl -fsS "$BASE_URL/")"
if [ -n "$EXPECTED_VERSION" ]; then
    grep -q "\"version\":\"$EXPECTED_VERSION\"" <<<"$info" || fail "expected version $EXPECTED_VERSION, got: $info"
    pass "/ reports version $EXPECTED_VERSION"
fi

curl -fsS "$BASE_URL/metrics" | grep -q '^http_requests_total{method="GET",route="health",status="200"} ' \
    || fail "/metrics does not count /health requests"
pass "/metrics exposes request counters"

echo "Failure drill: stopping PostgreSQL"
docker compose stop postgres >/dev/null 2>&1
wait_for /ready 503 && pass "/ready returns 503 while the database is down"
[ "$(status_of /health)" = 200 ] || fail "/health must stay 200 while the database is down"
pass "/health stays 200 (container is not restarted for a dependency outage)"

docker compose start postgres >/dev/null 2>&1
wait_for /ready 200 && pass "/ready recovers after PostgreSQL is back"

echo "Failure drill: stopping Redis (cache and metrics backend)"
docker compose stop redis >/dev/null 2>&1
wait_for /ready 503 && pass "/ready returns 503 while Redis is down"
[ "$(status_of /health)" = 200 ] || fail "/health must stay 200 while Redis is down"
pass "/health stays 200 (losing the metrics backend never fails a request)"

docker compose start redis >/dev/null 2>&1
wait_for /ready 200 && pass "/ready recovers after Redis is back"

echo "All smoke tests passed."
