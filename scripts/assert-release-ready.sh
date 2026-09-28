#!/usr/bin/env bash
# Fail closed before rsync --delete. Sourced by deploy.sh and guard tests.
set -euo pipefail

assert_release_ready() {
  local dir="${1:-}"
  local expected_sha="${2:-}"
  local min_files="${RELEASE_MIN_FILES:-500}"

  if [[ -z "$dir" || ! -d "$dir" ]]; then
    echo "Error: release directory missing${dir:+: $dir} — refusing rsync --delete"
    return 1
  fi

  if [[ ! -f "$dir/composer.json" ]]; then
    echo "Error: composer.json missing in $dir — refusing rsync --delete"
    return 1
  fi

  if [[ ! -f "$dir/artisan" ]]; then
    echo "Error: artisan missing in $dir — refusing rsync --delete"
    return 1
  fi

  local required
  for required in app config routes resources; do
    if [[ ! -d "$dir/$required" ]]; then
      echo "Error: $required/ missing in $dir — refusing rsync --delete"
      return 1
    fi
  done

  local count
  count="$(find "$dir" -type f | wc -l | tr -d ' ')"
  if [[ "$count" -lt "$min_files" ]]; then
    echo "Error: extracted file count $count is below minimum $min_files — refusing rsync --delete"
    return 1
  fi

  if [[ -n "$expected_sha" ]]; then
    local stamp="$dir/.release-sha"
    if [[ ! -f "$stamp" ]]; then
      echo "Error: release SHA stamp missing — refusing rsync --delete"
      return 1
    fi
    local got
    got="$(tr -d '[:space:]' < "$stamp")"
    if [[ "$got" != "$expected_sha" ]]; then
      echo "Error: extracted SHA $got does not match expected $expected_sha — refusing rsync --delete"
      return 1
    fi
  fi

  echo "Release tree OK ($count files) in $dir"
  return 0
}
