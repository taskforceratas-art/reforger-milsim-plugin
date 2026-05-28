#!/bin/bash
set -e

# Load credentials
SFTP_HOST="54.37.205.68"
SFTP_PORT="4022"
SFTP_USER="dilizm56"
SFTP_PASS="T0M1WVKVJ7bpWjAovztX"
SFTP_REMOTE_PATH="/home/dilizm56/htdocs/tfr.gure.party/wp-content/plugins/reforger-milsim-management/"

cd "$(dirname "$0")"
PLUGIN_DIR="$(pwd)"

echo "🚀 Deploying to tfr.gure.party..."

# Files to exclude
EXCLUDES=(
  ".git"
  ".credentials"
  ".gitignore"
  "._"
  "deploy.sh"
  "download-tiles.sh"
  "DAGRProyect.md"
  "_dl_cain.sh"
)

# Function to check if path should be excluded
should_exclude() {
  local rel_path="$1"
  for excl in "${EXCLUDES[@]}"; do
    if [[ "$rel_path" == *"$excl"* ]]; then
      return 0
    fi
  done
  return 1
}

CURL_OPTS="-k --ftp-pasv -u ${SFTP_USER}:${SFTP_PASS} sftp://${SFTP_HOST}:${SFTP_PORT}"

# Create remote directory structure
echo "📁 Creating remote directories..."
find . -type d | while read -r dir; do
  rel_dir="${dir#./}"
  [[ -z "$rel_dir" ]] && continue
  if should_exclude "$rel_dir"; then
    continue
  fi
  remote_dir="${SFTP_REMOTE_PATH}${rel_dir}/"
  curl $CURL_OPTS --ftp-create-dirs -T /dev/null "$remote_dir" 2>/dev/null || true
  echo "  ✓ $rel_dir"
done

# Upload files
echo "📤 Uploading files..."
find . -type f | while read -r file; do
  rel_path="${file#./}"
  if should_exclude "$rel_path"; then
    continue
  fi
  remote_file="${SFTP_REMOTE_PATH}${rel_path}"
  curl $CURL_OPTS --ftp-create-dirs -T "$file" "$remote_file" 2>/dev/null
  echo "  ✓ $rel_path"
done

echo "✅ Deploy complete!"
