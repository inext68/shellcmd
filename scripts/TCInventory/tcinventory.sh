#!/usr/bin/env bash

# DEBUG esplicito (essenziale da web)
set -x
set -o pipefail

IP="$1"
[ -z "$IP" ] && { echo "IP mancante"; exit 1; }

################################
# Percorsi assoluti
################################
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_DIR="$SCRIPT_DIR"
RESULTS_DIR="$BASE_DIR/results"
JSON_DIR="$BASE_DIR/jsons"
LOG_DIR="$BASE_DIR/logs"

mkdir -p "$RESULTS_DIR" "$JSON_DIR" "$LOG_DIR"

LOGFILE="$LOG_DIR/${IP}_$(date +%Y%m%d_%H%M%S).log"
exec > >(tee -a "$LOGFILE") 2>&1

################################
# Binari
################################
PING="/bin/ping"
CURL="/usr/bin/curl"
WGET="/usr/bin/wget"
GLPI_INJECTOR="/usr/bin/glpi-injector"

GLPI_URL="http://serverx:60001/plugins/glpiinventory"
AGENT_URL="https://serverx:60001/f/TCInventory/agent.exe"
DEST="/tmp/tc_inventory"

mkdir -p "$DEST"

echo "== Inventario TC su $IP =="
echo "User: $(id)"
echo "Start: $(date)"

################################
# Ping
################################
if ! $PING -c 1 -W 1 "$IP" >/dev/null 2>&1; then
   echo "HOST NON RAGGIUNGIBILE"
   exit 0
fi
echo "Host attivo"

################################
# Identificazione
################################
INDEX="$($CURL -sf http://$IP/index.html || true)"

if [[ "$INDEX" == *"10ZiG"* ]]; then
   TYPE="10ZIG"
elif [[ "$INDEX" == *"<AxelAdmin>"* ]]; then
   TYPE="AXEL"
else
   echo "Tipo dispositivo non riconosciuto"
   exit 0
fi

echo "Tipo: $TYPE"

################################
# 10ZiG
################################
if [ "$TYPE" = "10ZIG" ]; then
   echo "Download agent"
   if ! $WGET -q -O "$DEST/agent.exe" "$AGENT_URL"; then
      echo "DOWNLOAD FALLITO"
      exit 1
   fi

   chmod +x "$DEST/agent.exe"

   echo "Esecuzione agent"
   "$DEST/agent.exe" \
      --server "$GLPI_URL" \
      --format FORMAT_GLPI \
      --tag THINCLIENT_10ZIG \
      --nosoftware \
      --verbose

   echo "Inventario 10ZiG completato"
   exit 0
fi

################################
# AXEL
################################
echo "Inventario AXEL"

MAC=$(echo "$INDEX" | awk -F'[<>]' '/<MacAddress>/{print $3}')
UUID=$(echo "$MAC" | tr -d ':')
NAME=$(echo "$INDEX" | awk -F'[<>]' '/<Name>/{print $3}')
BOOT=$(date +"%Y-%m-%d %H:%M:%S")

JSON="$JSON_DIR/${IP}-${NAME}-${UUID}.json"

cat > "$JSON" <<EOF
{
  "action": "inventory",
  "deviceid": "$UUID-$UUID",
  "itemtype": "Computer",
  "tag": "THINCLIENT_AXEL",
  "content": {
    "hardware": {
      "name": "$NAME",
      "uuid": "$UUID",
      "chassis_type": "thinclient"
    },
    "networks": [{
      "ipaddress": "$IP",
      "mac": "$MAC"
    }],
    "operatingsystem": {
      "boot_time": "$BOOT"
    }
  }
}
EOF

echo "Invio inventario a GLPI"
$GLPI_INJECTOR -v -r -f "$JSON" --useragent Fin_TC_Agent --url "$GLPI_URL"

echo "Inventario AXEL completato"
exit 0
