#!/usr/bin/env bash
set -euo pipefail

IP="$1"
GLPI_SERVER_NAME="http://itassets.finstral.com/"
PLUGIN_NAME="shellcmd"
AGENT_EXE_PATH="$GLPI_SERVER_NAME/f/TCInventory/"

IP_TO_INV=$1
ROOT_PATH="/var/www/html/glpi/plugins/shellcmd"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"


#IP_RANGES_FILE_INV="./ip_ranges/ranges_ip_INV.txt"
OUTPUT_FILE_10ZiG_INV="$SCRIPT_DIR/results/10ZiG_INV.txt"
OUTPUT_FILE_Axel_INV="$SCRIPT_DIR/results/Axel_INV.txt"
#echo "$IP_TO_INV $IP_TO_INV" > $IP_RANGES_FILE_INV


# Variabili per il comando sshpass
pass=$(echo "1234" | gpg --batch -d -q --passphrase-fd 0 $SCRIPT_DIR/.spwd);                # Sostituisci con la password corretta
DESTPATH="/boot/inv"                                                            # Sostituisci con il percorso di destinazione remoto
GETPATH="http://itassets02.intranet.finstral.org:60001/f/TCInventory"                # Sostituisci con il percorso HTTP per il download dello script
INVURL="http://itassets02.intranet.finstral.org:60001/f/TCInventory"



# Pulizia dei file di output
 echo > $OUTPUT_FILE_10ZiG_INV
 echo > $OUTPUT_FILE_Axel_INV



## verifica host attivo o no

            if ping -c 1 -W 1 "$1" &> /dev/null; then
                echo "$1  attivo"

                # Lettura del contenuto della pagina index.html
                content=$(curl -s "http://$1/index.html")

                # Verifica della presenza delle stringhe specificate


    #################################################################
    # 10ZiG
    #################################################################



                if [[ $content == *"10ZiG"* ]]; then

		   # Esecuzione del comando SSH per gli IP nel file 10ZiG.txt

		    echo "Eseguendo comando SSH per $1"

		    ssh -T -F $ROOT_PATH/ssh_config root@$1 <<REMCODE
		    cd /
		    sleep 1
				[ -d "/boot/inv" ] || mkdir -p "/boot/inv"
				rm -f /boot/inv/agent.exe
				cd /boot/inv
			    wget -q -c $GETPATH/agent.exe
			    sleep 1
			    chmod +x agent.exe
			    sleep 1
		            echo "Esecuzione agent e invio informazioni al server";
			    ./agent.exe --server http://itassets.finstral.com:60001/plugins/glpiinventory/ --format FORMAT_GLPI --tag THINCLIENT_10ZIG --nosoftware  --verbose
REMCODE


		echo "$1" >> "$OUTPUT_FILE_10ZiG_INV"
        echo "Inventario 10ZiG eseguito su IP $1"

    #################################################################
    # AXEL
    #################################################################


                elif [[ $content == *"<AxelAdmin>"* ]]; then
					echo "Inventario Axel su IP $1"
			
					inventory_path="$SCRIPT_DIR/jsons"
					mkdir -p "$inventory_path"
			
					source="http://$1/index.html"
					Name_content=$(curl -s "$source")
			
					Axel_BVERSION=$(echo "$Name_content" | awk -F'[<>]' '/<Version>/{print $3}')
					Axel_MACADDR=$(echo "$Name_content" | awk -F'[<>]' '/<MacAddress>/{print $3}')
					Axel_UUID="${Axel_MACADDR//:/}"
					Axel_IPADDRESS=$(echo "$Name_content" | awk -F'[<>]' '/<IPAddress>/{print $3}')
					Axel_IPADDRESS="${Axel_IPADDRESS:-$1}"
			
					Axel_IPGATEWAY="$(echo "$Axel_IPADDRESS" | cut -d. -f1-3).254"
					Axel_IPSUBNET="$(echo "$Axel_IPADDRESS" | cut -d. -f1-3).0"
					Axel_boot="$(date '+%Y-%m-%d %T')"
			
					if echo "$Name_content" | grep -q '<Name>'; then
						Axel_NAME=$(echo "$Name_content" | grep -oP '(?<=<Name>).*?(?=</Name>)')
					else
						Axel_NAME=$(echo "$Name_content" | grep -oP '(?<=<FQDN>).*?(?=</FQDN>)' | cut -d. -f1)
					fi
			
					json_file="$inventory_path/$1-$Axel_NAME-$Axel_UUID.json"
			
					cat > "$json_file" <<EOF
{
"action": "inventory",
"content": {
	"bios": {
	"bmanufacturer": "Axel",
	"bversion": "$Axel_BVERSION"
	},
	"hardware": {
	"chassis_type": "thinclient",
	"defaultgateway": "$Axel_IPGATEWAY",
	"name": "$Axel_NAME",
	"uuid": "$Axel_UUID"
	},
	"networks": [{
	"description": "eth0",
	"ipaddress": "$Axel_IPADDRESS",
	"ipgateway": "$Axel_IPGATEWAY",
	"ipmask": "255.255.255.0",
	"ipsubnet": "$Axel_IPSUBNET",
	"mac": "$Axel_MACADDR",
	"status": "up"
	}],
	"operatingsystem": {
	"full_name": "Axel Embedded",
	"boot_time": "$Axel_boot"
	},
	"versionclient": "FinAxelAgent"
},
"deviceid": "$Axel_UUID-$Axel_UUID",
"itemtype": "Computer",
"tag": "THINCLIENT_AXEL"
}
EOF			


       /usr/bin/glpi-injector -v -r -f "$json_file" \
          --useragent Fin_TC_Agent \
          --url http://itassets.finstral.com:60001/plugins/glpiinventory

        echo "$IP" >> "$OUTPUT_FILE_Axel_INV"

    fi
else
    echo "$IP non attivo"
fi