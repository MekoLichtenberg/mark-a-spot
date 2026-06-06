#!/usr/bin/env bash
# Delegate to the canonical script in the profile.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROFILE_SCRIPT="$SCRIPT_DIR/../web/profiles/contrib/markaspot/scripts/georeport-client.sh"

if [ ! -f "$PROFILE_SCRIPT" ]; then
  echo "ERROR: Profile script not found at $PROFILE_SCRIPT"
  echo "Run 'composer install' first."
  exit 1
fi

exec bash "$PROFILE_SCRIPT" "$@"
