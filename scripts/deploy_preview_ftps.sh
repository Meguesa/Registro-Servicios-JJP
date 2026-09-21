#!/usr/bin/env bash
set -euo pipefail

if [ "${GITHUB_EVENT_NAME:-}" != "workflow_dispatch" ]; then
  echo "Despliegue omitido: solo se permite workflow_dispatch."
  exit 0
fi

: "${FTP_SERVER:?FTP_SERVER no configurado}"
: "${FTP_USERNAME:?FTP_USERNAME no configurado}"
: "${FTP_PASSWORD:?FTP_PASSWORD no configurado}"

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

cat > "$TMP" <<'LFTP'
set cmd:fail-exit true
set ftp:passive-mode true
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate true
set ssl:check-hostname false
set net:timeout 30
set net:max-retries 2
mkdir -p registro-servicios-preview
cd registro-servicios-preview
put index.php -o index.php
put styles.css -o styles.css
put capillas.js -o capillas.js
bye
LFTP

for attempt in 1 2 3; do
  echo "FTPS preview: intento ${attempt}/3"
  if timeout --kill-after=15s 180s lftp -u "$FTP_USERNAME","$FTP_PASSWORD" "ftp://$FTP_SERVER:21" < "$TMP"; then
    echo "Preview publicado en /registro-servicios-preview/"
    exit 0
  fi
  if [ "$attempt" -lt 3 ]; then
    sleep $((attempt * 10))
  fi
done

echo "No fue posible publicar el preview después de 3 intentos."
exit 1
