#!/bin/bash
set -e

HOST="54.37.205.68"
PORT="4022"
USER="dilizm56"
PASS="T0M1WVKVJ7bpWjAovztX"
REMOTE="/home/dilizm56/htdocs/tfr.gure.party/wp-content/plugins/reforger-milsim-management/"

cd "$(dirname "$0")/.."
PLUGIN_DIR="$(pwd)"

echo "Testing connection to $HOST:$PORT..."

# Test with a single file
echo "Uploading reforger-milsim-management.php..."
curl -k --connect-timeout 10 -u "${USER}:${PASS}" \
  -T "${PLUGIN_DIR}/reforger-milsim-management.php" \
  "sftp://${HOST}:${PORT}${REMOTE}reforger-milsim-management.php"

echo "Test upload complete!"
