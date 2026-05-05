#!/bin/sh
# cPanel git pull — अब includes/database.php Git मा छैन; conflict भए includes/database.local.php वा modify/delete fix।
# Repo root मा:
#   chmod +x scripts/cpanel-pull-after-db-edit.sh && ./scripts/cpanel-pull-after-db-edit.sh

set -e
cd "$(git rev-parse --show-toplevel)"

LOCAL="includes/database.local.php"
LEGACY="includes/database.php"

stash_if_dirty () {
  f="$1"
  if [ -n "$(git status --porcelain -- "$f" 2>/dev/null || true)" ]; then
    echo "Stashing local changes: $f"
    git stash push -m "cpanel pre-pull $f" -- "$f"
  fi
}

stash_if_dirty "$LOCAL"
stash_if_dirty "$LEGACY"

echo "Running git pull..."
if [ "$#" -eq 0 ]; then git pull; else git pull "$@"; fi

echo ""
echo "Credentials: includes/database.local.php (see includes/database.local.php.example)"
echo "Legacy includes/database.php is still loaded if present — but gitignored, pull will not update it."
