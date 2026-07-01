#!/usr/bin/env sh
set -eu

BASE_URL="${APP_URL:-http://localhost}"
curl --fail --silent --show-error "$BASE_URL/health"
