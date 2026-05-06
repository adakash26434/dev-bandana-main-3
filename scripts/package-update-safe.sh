#!/usr/bin/env bash
# सुरक्षित अपडेट .zip — कोड मात्र; DB/admin user मेटाउने कुरा भित्र छैन।
# - includes/database.local.php, database.php, superadmin-config.local.php बाहेक
# - assets/uploads/ बाहेक (सर्भरको फाइल अपलोड नछोइन)
# - database/install.sql बाहेक (fresh install को लागि मात्र; अवस्थित DB मा import नगर्नु)
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

NAME="aakash-dms-update-safe"
DEST="$STAGE/$NAME"
mkdir -p "$DEST"

rsync -a \
  --exclude='.git' \
  --exclude='dist' \
  --exclude='*.zip' \
  --exclude='.cursor' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  --exclude='**/error_log' \
  --exclude='includes/superadmin-config.local.php' \
  --exclude='includes/database.local.php' \
  --exclude='includes/database.php' \
  --exclude='includes/.cred-master.key' \
  --exclude='includes/.cron-token' \
  --exclude='assets/uploads' \
  --exclude='database/install.sql' \
  --exclude='database/DEPLOY.md' \
  --exclude='database/README.md' \
  "$ROOT/" "$DEST/"

mkdir -p "$DEST/database"
# पुरानो DB मा DEPLOY.md को install.sql निर्देश नदेखियोस्
cat > "$DEST/database/README-UPDATE-NEPALI.txt" << 'EOF'
अपडेट प्याकेज — पुरानो डाटा जोगाउनुहोस्
========================================

1) यो zip मा install.sql छैन। अवस्थित साइटमा install.sql import नगर्नुहोस् — नत्र डाटा/युजर समस्या हुन सक्छ।

2) अपलोड अघि सर्भरमा Backup: डाटाबेस (phpMyAdmin Export) + includes/database.local.php +
   includes/superadmin-config.local.php + includes/.cred-master.key (छ भने)।

3) Zip unzip गर्दा आफ्नो सर्भरको यी फाइल नमेटाउनुहोस् / नयाँले नतानुहोस्:
   - includes/database.local.php  (DB user/password)
   - includes/database.php        (पुरानो शैली भए)
   - includes/superadmin-config.local.php
   - includes/.cred-master.key
   - assets/uploads/              (यो प्याकेजमा छैन — सर्भरको फोल्डर जोगाउनुहोस्)

4) सामान्य तरिका: FTP/cPanel File Manager मा अपडेट फाइलहरू मात्र माथि लेख्नुहोस् (merge)।
   नयाँ टेबल/कलम चाहिए Admin को PHP ensure-tables / migration ले हेर्छ।

5) Admin user / DB user यस zip ले बदल्दैन — तपाईंले install.sql चलाउनुभएन भने।
EOF

mkdir -p "$OUT_DIR"
STAMP="$(date +%Y%m%d-%H%M)"
ZIP="$OUT_DIR/${NAME}-${STAMP}.zip"
( cd "$STAGE" && rm -f "$ZIP" && zip -r -q "$ZIP" "$NAME" )
echo "OK: $ZIP"
ls -la "$ZIP"
