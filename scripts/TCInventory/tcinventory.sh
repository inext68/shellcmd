#!/bin/bash
set -euo pipefail

IP="$1"

GLPI_ROOT_DIR="${GLPI_ROOT_DIR:-}"
GLPI_PUBLIC_DIR="${GLPI_PUBLIC_DIR:-}"
GLPI_CONFIG_DIR="${GLPI_CONFIG_DIR:-}"
GLPI_VAR_DIR="${GLPI_VAR_DIR:-}"
GLPI_LOG_DIR="${GLPI_LOG_DIR:-}"
GLPI_PLUGIN_DIR="${GLPI_PLUGIN_DIR:-}"



## echo "GLPI_ROOT_DIR=$GLPI_ROOT_DIR"
## echo "GLPI_PUBLIC_DIR=$GLPI_PUBLIC_DIR"
## echo "GLPI_CONFIG_DIR=$GLPI_CONFIG_DIR"
## echo "GLPI_VAR_DIR=$GLPI_VAR_DIR"
## echo "GLPI_LOG_DIR=$GLPI_LOG_DIR"
echo "GLPI_PLUGIN_DIR=$GLPI_PLUGIN_DIR"


if [ $# -eq 0 ]; then
    echo "inserisci un indirizzo IP"
    exit 1
fi

IP_TO_INV=$1

## ROOT_PATH="/var/www/html/glpi/plugins/tcinvtools/scripts/TCInventory/"
ROOT_PATH=$(pwd)
cd $ROOT_PATH

#CHECK if .ssh exist

if [ ! -d $ROOT_PATH/.ssh ]; then
  # Crea la directory
  mkdir -p $ROOT_PATH/.ssh"
  chmod 700 $ROOT_PATH/.ssh"
  echo "directory .ssh creata";
else 
  echo "directory .ssh non creata" ;  
fi
if [ ! -d $ROOT_PATH/.gnupg ]; then
  # Crea la directory
  mkdir -p $ROOT_PATH/.gnupg"
  chmod 700 $ROOT_PATH/.gnupg"
fi
if [ ! $ROOT_PATH/.ssh/known_hosts ]; then
  # Crea la directory
  touch $ROOT_PATH/.ssh/known_hosts
  chmod 700 $ROOT_PATH/.ssh/known_hosts
  echo "file known_hosts ok";
else 
  echo "directory .ssh non creata" ;  
fi

IP_RANGES_FILE_INV="./ip_ranges/ranges_ip_INV.txt"
OUTPUT_FILE_10ZiG_INV="./results/10ZiG_INV.txt"
OUTPUT_FILE_Axel_INV="./results/Axel_INV.txt"
echo "$IP_TO_INV $IP_TO_INV" > $IP_RANGES_FILE_INV


# Variabili per il comando sshpass
pass=$(echo "1234" | gpg --batch -d -q --passphrase-fd 0 .spwd);		# Sostituisci con la password corretta
DESTPATH="/boot/inv"								# Sostituisci con il percorso di destinazione remoto
GETPATH="http://itassets.finstral.com:60001/glpi/plugins/tcinvtools/scripts/TCInventory"		# Sostituisci con il percorso HTTP per il download dello script
INVURL="http://itassets.finstral.com:60001/glpi/plugins/tcinvtools/scripts/TCInventory"

# Funzione per verificare se un IP è valido
is_valid_ip() {
    local ip=$1
    if [[ $ip =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
        IFS='.' read -r -a octets <<< "$ip"
        for octet in "${octets[@]}"; do
            if ((octet < 0 || octet > 255)); then
                return 1
            fi
        done
        return 0
    else
        return 1
    fi
}

# Funzione per convertire un indirizzo IP in un numero a 32 bit
ip_to_num() {
    local ip=$1
    local a b c d
    IFS=. read -r a b c d <<< "$ip"
    echo $(((a << 24) + (b << 16) + (c << 8) + d))
}

# Funzione per convertire un numero a 32 bit in un indirizzo IP
num_to_ip() {
    local num=$1
    echo "$((num >> 24 & 255)).$((num >> 16 & 255)).$((num >> 8 & 255)).$((num & 255))"
}



# Pulizia dei file di output
> "$OUTPUT_FILE_10ZiG_INV"
> "$OUTPUT_FILE_Axel_INV"

# Lettura dei range IP dal file
while IFS= read -r line; do
    # Estrarre gli IP di inizio e fine del range
    IFS=' ' read -r start_ip end_ip <<< "$line"

    # Verifica se gli IP sono validi
    if is_valid_ip "$start_ip" && is_valid_ip "$end_ip"; then
        start_num=$(ip_to_num "$start_ip")
        end_num=$(ip_to_num "$end_ip")

        # Loop attraverso gli IP nel range
        for ((num=start_num; num<=end_num; num++)); do
            current_ip_INV=$(num_to_ip $num)
            echo "Verifica IP: $current_ip_INV"

            # Verifica se l'host è attivo
            if ping -c 1 -W 1 "$current_ip_INV" &> /dev/null; then
                echo "$current_ip_INV  attivo"

                # Lettura del contenuto della pagina index.html
                content=$(curl -s "http://$current_ip_INV/index.html")

                # Verifica della presenza delle stringhe specificate
                if [[ $content == *"10ZiG"* ]]; then
                    echo "$current_ip_INV" >> "$OUTPUT_FILE_10ZiG_INV"
                elif [[ $content == *"<AxelAdmin>"* ]]; then
                    echo "$current_ip_INV" >> "$OUTPUT_FILE_Axel_INV"
                fi
            else
                echo "$current_ip_INV non  attivo"
            fi
        done
    else
        echo "Range IP non valido: $line"
    fi
done < "$IP_RANGES_FILE_INV"

# Esecuzione del comando SSH per gli IP nel file 10ZiG.txt


while IFS= read -r ip; do
    echo "Eseguendo comando SSH per $ip"
    sshpass -p"$pass" ssh -T -o UserKnownHostsFile=$(pwd)/.ssh/known_hosts -o StrictHostKeyChecking=no root@"$ip"  << REMCODE

##      ssh -T -F $ROOT_PATH/ssh_config root@$ip <<REMCODE


    cd /
    sleep 1
if [ ! -d "$DESTPATH" ]; then 
	mkdir -p "$DESTPATH"
    fi
    if [ -f "$DESTPATH/agent.exe" ]; then
        rm $DESTPATH/agent.exe
    fi
	cd $DESTPATH 
	wget -c $GETPATH/agent.exe
    sleep 1 
    chmod +x $DESTPATH/agent.exe
    sleep 1

    $DESTPATH/agent.exe --server http://itassets.finstral.com:60001/glpi/plugins/glpiinventory/ --format FORMAT_GLPI --tag THINCLIENT_10ZIG --nosoftware  --verbose
REMCODE
echo "eseguito Inventario $ip" 
done < "$OUTPUT_FILE_10ZiG_INV"


#inventario Thicleint Axel
while IFS= read -r ip; do

        source="http://$ip/index.html"
        inventory_path="./jsons/"
        if [ ! -d "$inventory_path" ]; then
                mkdir -p $inventory_path
        fi

        Axel_BVERSION=$(curl -s "$source" | awk -F'[<>]' '/<Version>/{print $3}');
        Axel_MACADDR=$(curl -s "$source" | awk -F'[<>]' '/<MacAddress>/{print $3}');
        Axel_UUID=$(echo "$Axel_MACADDR" | tr -d ':');

	Axel_IPADDRESS=$(curl -s "$source" | awk -F'[<>]' '/<IPAddress>/{print $3}');
	if [ -z "$Axel_IPADDRESS" ] ; then
	Axel_IPADDRESS=$ip
	fi


        Name_content=$(curl -s $source);
echo $Name_content;
	Axel_IPGATEWAY=$(echo $Axel_IPADDRESS | cut -d'.' -f1-3)."254";
        Axel_IPSUBNET=$(echo $Axel_IPADDRESS | cut -d'.' -f1-3)."0";
        Axel_FQDN=$(echo "$Name_content" | grep -oP '(?<=<FQDN>).*(?=</FQDN>)');
	Axel_boot=$(date +%Y-%M-%d" "%T);
#        Name_content=$(curl -s $source);

        # Verifica se esiste il pattern <name>
        if $(echo "$Name_content" | grep -q '<Name>'); then
        # Se esiste <name>, estrai il contenuto tra <name> e </name>
          Axel_Nome=$(echo "$Name_content" | grep -oP '(?<=<Name>).*(?=</Name>)')
        echo " <name> $Axel_Nome"
	fi
        # Se <name> non esiste, verifica per <fqdn> e estrai il contenuto tra <fqdn> e </fqdn>
        if $(echo "$Name_content" | grep -q '<FQDN>'); then
          Axel_Nome=$(echo "$Name_content" | grep -oP '(?<=<FQDN>).*(?=</FQDN>)'| cut -d'.' -f1-1)
        echo "<fqdn> $Axel_Nome"
        
fi

      Axel_NAME="$Axel_Nome" 
echo "$Axel_NAME";
echo "$Axel_NAME-bjo";





echo "{                                                 " > "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "   \"action\": \"inventory\",                     " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "   \"content\": {                                 " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      \"bios\": {                                 " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"bdate\": \"1999-12-31\",                " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"bmanufacturer\": \"Axel\",              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"bversion\": \"$Axel_BVERSION\",         " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"mmanufacturer\": \"Axel\",              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"mmodel\": \"AX3000/M80G\",              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"msn\": \"\",                            " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"skunumber\": \"\",                      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"smanufacturer\": \"Axel\",              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"smodel\": \"\",                         " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "        \"ssn\": \"\"                             " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      },                                          " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      \"hardware\": {                             " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"chassis_type\": \"thinclient\",        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"defaultgateway\": \"$Axel_IPGATEWAY\", " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"dns\": \"\",                           " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"memory\": 16,                          " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"name\": \"$Axel_NAME\",                " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"uuid\": \"$Axel_UUID\",                " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"vmsystem\": \"Physical\",              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"winlang\": \"\",                       " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"winowner\": \"\",                      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"winprodid\": \"\",                     " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"winprodkey\": \"\",                    " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"workgroup\": \"\"                      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      },                                          " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      \"networks\": [                             " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         {                                        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"description\": \"eth0\",            " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"ipaddress\": \"$Axel_IPADDRESS\",   " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"ipgateway\": \"$Axel_IPGATEWAY\",   " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"ipmask\": \"255.255.255.0\",        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"ipsubnet\": \"$Axel_IPSUBNET\",     " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"mac\": \"$Axel_MACADDR\",           " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"pciid\": \"\",                      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"pnpdeviceid\": \"\",                " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"speed\": \"100\",                   " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"status\": \"up\",                   " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"type\": \"ethernet\",               " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"virtualdev\": false                 " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         }                                        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      ],                                          " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      \"operatingsystem\": {                      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"arch\": \"x86_64\",                    " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"boot_time\": \"$Axel_boot\",           " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"dns_domain\": \".\",                   " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"fqdn\": \"$Axel_NAME\",                " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"full_name\": \"Axel Embedded\",        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"hostid\": \"$Axel_UUID\",              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"install_date\": \"$Axel_boot\",        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"kernel_name\": \"Axel Embedded\",      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"kernel_version\": \"\",                " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"name\": \"Axel\",                      " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"ssh_key\": \"\",                       " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"timezone\": {                          " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"name\": \"Europe/Rome\",            " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "            \"offset\": \"+0200\"                 " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         },                                       " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "         \"version\": \"\"                        " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      },                                          " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "      \"versionclient\": \"FAAgent\"              " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "   },                                             " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "   \"deviceid\": \"$Axel_UUID-$Axel_UUID\",       " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "   \"itemtype\": \"Computer\",                    " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "   \"tag\": \"THINCLIENT_AXEL\"                   " >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json
echo "}" >> "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json


echo "$inventory_path$ip-$Axel_NAME-$Axel_UUID".json

sleep 60
glpi-injector -v -r -f $inventory_path$ip-$Axel_NAME-$Axel_UUID.json --useragent Fin_TC_Agent --url http://itassets.finstral.com/plugins/glpiinventory
sleep 1
done <  "$OUTPUT_FILE_Axel_INV"
echo >  $IP_RANGES_FILE_INV
