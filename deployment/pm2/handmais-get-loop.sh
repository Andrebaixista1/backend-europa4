#!/usr/bin/env bash
set -u

URL="http://127.0.0.1:8011/api/start/consultashandmais"

while true; do
  TS=$(date "+%Y-%m-%d %H:%M:%S")
  METRICS=$(curl -sS --max-time 180 -o /dev/null -w "%{http_code} %{time_total}" "$URL" || echo "000 0")
  HTTP_CODE=$(echo "$METRICS" | awk '{print $1}')
  TIME_TOTAL=$(echo "$METRICS" | awk '{print $2}')

  echo "[$TS] GET /api/start/consultashandmais -> $HTTP_CODE (${TIME_TOTAL}s)"
  sleep 5
done
