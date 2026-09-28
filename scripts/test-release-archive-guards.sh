#!/usr/bin/env bash
# Local tests for deploy archive guards. Does not touch production.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/assert-release-ready.sh"

fail() { echo "FAIL: $1"; exit 1; }
pass() { echo "PASS: $1"; }

WORKDIR="$(mktemp -d /tmp/kf-release-guard.XXXXXX)"
trap 'rm -rf "$WORKDIR"' EXIT

empty="$WORKDIR/empty"
mkdir -p "$empty"
if assert_release_ready "$empty" >/dev/null 2>&1; then
  fail "empty archive → abort"
fi
pass "empty archive → abort/no production mutation"

missing="$WORKDIR/missing-composer"
mkdir -p "$missing/app" "$missing/config" "$missing/routes" "$missing/resources"
touch "$missing/artisan"
# pad file count
for i in $(seq 1 510); do echo x > "$missing/pad-$i"; done
if assert_release_ready "$missing" >/dev/null 2>&1; then
  fail "missing composer.json → abort"
fi
pass "missing composer.json → abort/no production mutation"

truncated="$WORKDIR/truncated"
mkdir -p "$truncated/app" "$truncated/config"
echo '{}' > "$truncated/composer.json"
touch "$truncated/artisan"
if assert_release_ready "$truncated" >/dev/null 2>&1; then
  fail "truncated archive → abort"
fi
pass "truncated archive → abort/no production mutation"

wrong="$WORKDIR/wrong-sha"
mkdir -p "$wrong/app" "$wrong/config" "$wrong/routes" "$wrong/resources"
echo '{}' > "$wrong/composer.json"
touch "$wrong/artisan"
for i in $(seq 1 510); do echo x > "$wrong/f-$i"; done
echo 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' > "$wrong/.release-sha"
expected='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
if assert_release_ready "$wrong" "$expected" >/dev/null 2>&1; then
  fail "wrong SHA → abort"
fi
pass "wrong SHA → abort/no production mutation"

valid="$WORKDIR/valid"
mkdir -p "$valid/app" "$valid/config" "$valid/routes" "$valid/resources"
echo '{}' > "$valid/composer.json"
touch "$valid/artisan"
for i in $(seq 1 510); do echo x > "$valid/f-$i"; done
echo "$expected" > "$valid/.release-sha"
if ! assert_release_ready "$valid" "$expected" >/dev/null; then
  fail "valid archive → passes guards"
fi
pass "valid archive → passes guards"

echo "All release-archive guards passed."
