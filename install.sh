#!/bin/bash

FONT_RESET='\033[0m'
FONT__HELP_HEADER='\033[1;34m'
FONT__HELP_WARNING='\033[5;31m'
FONT__ERROR='\033[0;33m'
FONT__HELP_ARG='\033[1;34m'
FONT__HELP_PARAM='\033[1;32m'


####################################################################################################
#  _____  _____  ______ ______ _      _____ _____ _    _ _______ 
# |  __ \|  __ \|  ____|  ____| |    |_   _/ ____| |  | |__   __|
# | |__) | |__) | |__  | |__  | |      | || |  __| |__| |  | |   
# |  ___/|  _  /|  __| |  __| | |      | || | |_ |  __  |  | |   
# | |    | | \ \| |____| |    | |____ _| || |__| | |  | |  | |   
# |_|    |_|  \_\______|_|    |______|_____\_____|_|  |_|  |_|   
####################################################################################################
#########################
# Ensure that the script is NOT run as root.
if [ "$EUID" -eq 0 ]
    then
    echo -e "${FONT__ERROR}This script must not be run as root.${FONT_RESET}"
    exit
fi


if [ ! -d bin ]; then
    mkdir bin
fi

#########################
# ASCII AUXILIUM(TM) LOGO
echo ""
tput setaf 4
tput bold
echo -e "  /AAAAAA                      /II /LL /II                             "
echo -e " /AA__  AA                    |__/| LL|__/                         (TM)"
echo -e "| AA  \ AA /UU   /UU /XX   /XX /II| LL /II /UU   /UU /MMMMMM/MMMM      "
echo -e "| AAAAAAAA| UU  | UU|  XX /XX/| II| LL| II| UU  | UU| MM_  MM_  MM     "
echo -e "| AA__  AA| UU  | UU \  XXXX/ | II| LL| II| UU  | UU| MM \ MM \ MM     "
echo -e "| AA  | AA| UU  | UU  >XX  XX | II| LL| II| UU  | UU| MM | MM | MM     "
echo -e "| AA  | AA|  UUUUUU/ /XX/\  XX| II| LL| II|  UUUUUU/| MM | MM | MM     "
echo -e "|__/  |__/ \______/ |__/  \__/|__/|__/|__/ \______/ |__/ |__/ |__/     "
tput sgr0

echo ""
echo "$(tput bold)Welcome to the Auxilium docker package build tool$(tput sgr0)"
echo ""

#echo "Writing default config files"
#########################
# PORTS
HTTP_PORT=8080
HTTPS_PORT=8081
DEEGRAPH_PORT=8880

#########################
# VERSIONS
DEEGRAPH_VERSION=0.8

#########################
# MISC.
HOSTNAME=$(hostname --fqdn)
INSTANCE_IDENITIFIER=$(echo $HOSTNAME | cut -d"." -f1)
INSTALL_ID=$(cat /dev/urandom | base32 | cut -c-16 | head -n 1)
CERT_LOC=$(pwd)/certs
ACCEPT_SELF_SIGNED_CERTIFICATES=true
####################################################################################################





####################################################################################################
#  ______ _    _ _   _  _____ _______ _____ ____  _   _  _____ 
# |  ____| |  | | \ | |/ ____|__   __|_   _/ __ \| \ | |/ ____|
# | |__  | |  | |  \| | |       | |    | || |  | |  \| | (___  
# |  __| | |  | | . ` | |       | |    | || |  | | . ` |\___ \ 
# | |    | |__| | |\  | |____   | |   _| || |__| | |\  |____) |
# |_|     \____/|_| \_|\_____|  |_|  |_____\____/|_| \_|_____/ 
####################################################################################################
function fatalErrorMessage {
    echo -e "${FONT__ERROR}Error: ${1}${FONT_RESET}"
    exit
}
function checkUserIsInGroup {
    if groups $USER | grep -q "\b${1}\b"; then
        # No action, just continue
        :
    else
        fatalErrorMessage "User is not in the ${1} group"
    fi
}
function checkPackageIsInstalled {
        # No action, just continue
    if dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q "install ok installed"; then
        :
    else
        fatalErrorMessage "Package '${1}' is not installed"
    fi
}
function dockerVolumeExists {
    checkPackageIsInstalled docker-ce
    checkUserIsInGroup 'docker'

    if [ "$(docker volume ls -f name=$1 | awk '{print $NF}' | grep -E '^'$1'$')" ]; then
        return 0
    else
        return 1
    fi
}
function showHelp {
    echo -e "${FONT__HELP_HEADER}NAME${FONT_RESET}"
    echo -e "\tAuxilium installer script"
    echo -e ""
    echo -e "${FONT__HELP_HEADER}SYNOPSIS${FONT_RESET}"
    echo -e "\t./install.sh ${FONT__HELP_ARG}--help${FONT_RESET}"
    echo -e "\t./install.sh [${FONT__HELP_ARG}OPTION${FONT_RESET}]..."
    echo -e ""
    echo -e "${FONT__HELP_HEADER}DESCRIPTION${FONT_RESET}"
    echo -e "\tInstalls and builds Auxilium with given options for testing and deployment."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-h${FONT_RESET}, ${FONT__HELP_ARG}--help${FONT_RESET}"
    echo -e "\t\tPrint this Help."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-l${FONT_RESET}, ${FONT__HELP_ARG}--local${FONT_RESET}"
    echo -e "\t\tInstall locally, ${FONT__HELP_WARNING}THIS WILL MODIFY YOUR HOST${FONT_RESET}."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-b${FONT_RESET}, ${FONT__HELP_ARG}--build-only${FONT_RESET}"
    echo -e "\t\tBuild only, do not test."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-n ${FONT__HELP_PARAM}<hostname>${FONT_RESET}, ${FONT__HELP_ARG}--hostname ${FONT__HELP_PARAM}<hostname>${FONT_RESET}"
    echo -e "\t\tSet the fully qualified domain name the server will self-identify as."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-i ${FONT__HELP_PARAM}<name>${FONT_RESET}, ${FONT__HELP_ARG}--name ${FONT__HELP_PARAM}<name>${FONT_RESET}"
    echo -e "\t\tSet the identifier name of the container."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-c ${FONT__HELP_PARAM}<dir>${FONT_RESET}, ${FONT__HELP_ARG}--certs ${FONT__HELP_PARAM}<dir>${FONT_RESET}"
    echo -e "\t\tPoint to real certificates, and don't create self-signed certs."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-a ${FONT__HELP_PARAM}<dir>${FONT_RESET}, ${FONT__HELP_ARG}--allow-self-signed-certs ${FONT__HELP_PARAM}<dir>${FONT_RESET}"
    echo -e "\t\tConfigures Auxilium to allow self signed certs for Deegraph."
    echo -e ""
    echo -e "\t${FONT__HELP_ARG}-y${FONT_RESET}"
    echo -e "\t\tSkip questions."
}
####################################################################################################





####################################################################################################
#           _____   _____ _    _ __  __ ______ _   _ _______       _    _          _   _ _____  _      _____ _   _  _____ 
#     /\   |  __ \ / ____| |  | |  \/  |  ____| \ | |__   __|     | |  | |   /\   | \ | |  __ \| |    |_   _| \ | |/ ____|
#    /  \  | |__) | |  __| |  | | \  / | |__  |  \| |  | |        | |__| |  /  \  |  \| | |  | | |      | | |  \| | |  __ 
#   / /\ \ |  _  /| | |_ | |  | | |\/| |  __| | . ` |  | |        |  __  | / /\ \ | . ` | |  | | |      | | | . ` | | |_ |
#  / ____ \| | \ \| |__| | |__| | |  | | |____| |\  |  | |        | |  | |/ ____ \| |\  | |__| | |____ _| |_| |\  | |__| |
# /_/    \_\_|  \_\\_____|\____/|_|  |_|______|_| \_|  |_|        |_|  |_/_/    \_\_| \_|_____/|______|_____|_| \_|\_____|
####################################################################################################
_MODE__CREATE_SELF_SIGNED_CERTS=1
_MODE__INSTALL=1
_MODE__LOCAL_INSTALL=0
_MODE__SKIP_QUESTIONS=0
_MODE__PREEXISTING_VOLUME=0

#########################
# TRANSFORM LONG OPTIONS TO SHORT ONES
for arg in "$@"; do
    shift
    case "$arg" in
        '--help')                       set -- "$@" '-h'   ;;
        '--hostname')                   set -- "$@" '-n'   ;;
        '--identifier')                 set -- "$@" '-i'   ;;
        '--build-only')                 set -- "$@" '-b'   ;;
        '--local')                      set -- "$@" '-l'   ;;
        '--certs')                      set -- "$@" '-c'   ;;
        '--allow-self-signed-certs')    set -- "$@" '-a'   ;;
        *)                              set -- "$@" "$arg" ;;
    esac
done

OPTIND=1

# optstring starts with a colon so getopts lets the case block handle errors
while getopts ":hylbi:n:c:" opt; do
    case $opt in
        h) # display Help
            showHelp
            exit;;
        b) # Enter a name
            _MODE__LOCAL_INSTALL=0
            _MODE__INSTALL=0;;
        y) # Enter a name
            _MODE__SKIP_QUESTIONS=1;;
        l) # Enter a name
            _MODE__LOCAL_INSTALL=1;;
        i) # Enter a name
            INSTANCE_IDENITIFIER=$OPTARG;;
        n) # Enter a name
            HOSTNAME=$OPTARG;;
        c)
            CERT_LOC=$OPTARG
            _MODE__CREATE_SELF_SIGNED_CERTS=0;;
        a) # Enter a name
            ACCEPT_SELF_SIGNED_CERTIFICATES=true;;
        \?) # Invalid option
            fatalErrorMessage "Invalid option $OPTARG";;
    esac
done

#########################
# REMOVE OPTIONS FROM POSITIONAL PARAMETERS
shift $(expr $OPTIND - 1)

if [ "$_MODE__INSTALL" -eq 1 ]; then
    if [ "$_MODE__LOCAL_INSTALL" -eq 0 ]; then
        if dockerVolumeExists auxilium-volume-$INSTANCE_IDENITIFIER; then
            _MODE__PREEXISTING_VOLUME=1
        fi
    fi
fi
####################################################################################################




####################################################################################################
#  __  __  ____  _____  ______           _____ _   _  _____ _______       _      _      
# |  \/  |/ __ \|  __ \|  ____|  _      |_   _| \ | |/ ____|__   __|/\   | |    | |     
# | \  / | |  | | |  | | |__    (_)       | | |  \| | (___    | |  /  \  | |    | |     
# | |\/| | |  | | |  | |  __|             | | | . ` |\___ \   | | / /\ \ | |    | |     
# | |  | | |__| | |__| | |____   _       _| |_| |\  |____) |  | |/ ____ \| |____| |____ 
# |_|  |_|\____/|_____/|______| (_)     |_____|_| \_|_____/   |_/_/    \_\______|______|
####################################################################################################
tput bold
tput rev
if [ "$_MODE__INSTALL" -eq 1 ]; then
    echo "About to build and install Auxilium with the following options:"
    tput sgr0
    echo ""
    echo "External hostname is $HOSTNAME"
    echo "Instance identifier is $INSTANCE_IDENITIFIER"
    echo "HTTP port on $HTTP_PORT"
    echo "HTTPS port on $HTTPS_PORT"
    echo "Deegraph server on port $DEEGRAPH_PORT"
    if [ "$_MODE__PREEXISTING_VOLUME" -eq 1 ]; then
        echo "Reinstalling with existing user data"
    else
        echo "Creating a new instance"
    fi
    if [ "$_MODE__CREATE_SELF_SIGNED_CERTS" -eq 1 ]; then
        echo "Creating new self-signed certs for development purposes"
    else
        echo "Using SSL certificates at $CERT_LOC"
    fi
    if [ "$_MODE__LOCAL_INSTALL" -eq 1 ]; then
        echo ""
        tput bold
        tput setaf 1
        tput rev
        echo "ABOUT TO INSTALL TO HOST MACHINE"
        echo "THIS MAY BREAK YOUR APACHE/MYSQL CONFIGURATION"
        tput sgr0
    fi
else
    echo "About to build Auxilium"
    tput sgr0
    _MODE__CREATE_SELF_SIGNED_CERTS=0
    _MODE__SKIP_QUESTIONS=1
fi

if [ "$_MODE__SKIP_QUESTIONS" -eq 0 ]; then
    echo ""
    tput bold
    read -p "Do you want to continue with the installation? [Y/n] " CONFIRM_VALUES
    tput sgr0

    if [[ $CONFIRM_VALUES = [Nn] ]]; then
        echo "Aborting installation"
        exit
    fi
fi
####################################################################################################





####################################################################################################
#  __  __  ____  _____  ______           _      ____   _____          _            _____ _   _  _____ _______       _      _      
# |  \/  |/ __ \|  __ \|  ____|  _      | |    / __ \ / ____|   /\   | |          |_   _| \ | |/ ____|__   __|/\   | |    | |     
# | \  / | |  | | |  | | |__    (_)     | |   | |  | | |       /  \  | |            | | |  \| | (___    | |  /  \  | |    | |     
# | |\/| | |  | | |  | |  __|           | |   | |  | | |      / /\ \ | |            | | | . ` |\___ \   | | / /\ \ | |    | |     
# | |  | | |__| | |__| | |____   _      | |___| |__| | |____ / ____ \| |____       _| |_| |\  |____) |  | |/ ____ \| |____| |____ 
# |_|  |_|\____/|_____/|______| (_)     |______\____/ \_____/_/    \_\______|     |_____|_| \_|_____/   |_/_/    \_\______|______|
####################################################################################################
if [ "$_MODE__LOCAL_INSTALL" -eq 1 ]; then
    echo ""
    tput bold
    echo "Installing dependencies"
    tput sgr0


    checkUserIsInGroup 'sudo'

    
    sudo apt-get update
    sudo apt-get -y install supervisor wget grep curl openjdk-17-jre-headless
    sudo apt-get -y install apache2 apache2-utils libapache2-mod-php php-gd php-mysql mariadb-server mariadb-client php-simplexml php-mysql php-curl php-bcmath php-json php-imap php-mbstring
    sudo apt-get -y install composer ssl-cert git jq
    
    export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`
    sudo a2enmod php$PHP_VER
    sudo a2enmod headers
    sudo a2enmod rewrite
    sudo a2enmod ssl
    sudo a2enmod mime
    sudo mkdir /etc/deegraph
    sudo mkdir /etc/deegraph/store
    sudo mkdir /opt/deegraph
    
    sudo useradd deegraph -d /etc/deegraph/store
    sudo chown deegraph -R /etc/deegraph
    
    sudo rm /etc/apache2/sites-enabled/*
    sudo cp -r config/apache2/* /etc/apache2/

    sudo cp config/php.ini /etc/php/php.ini.tmp
    export PHP_VER=`dpkg -l 'php*' | grep ^ii | grep -oP "php[0-9]+\\.[0-9]*" | cut -c 4- | head -1 | tr -d $'\n'`
    sudo mv /etc/php/php.ini.tmp /etc/php/$PHP_VER/apache2/php.ini;

    sudo cp Auxilium/composer.json /srv/Auxilium/composer.json
    
    cd Auxilium
    composer config allow-plugins.endroid/installer true
    composer install
    cd ..
    
    cp templates/EnvironmentLocal.php Auxilium/Configuration/Configuration/Environment.php
    
    sudo chown www-data:www-data Auxilium/ -R
    f=$(pwd)/Auxilium
    while [[ $f != / ]]; do sudo chmod +rx "$f"; f=$(dirname "$f"); done;
    sudo ln -s $(pwd)/Auxilium/ /srv/Auxilium/auxilium2 
    
    #cp Scripts/new-keys.php /srv/Auxilium/new-keys.php

    sudo mkdir /var/auxilium
    
    sudo mkdir /var/auxilium/ecs
    sudo chown www-data:www-data /var/auxilium/ecs -R

    sudo mkdir /var/auxilium/dgdata
    sudo chown deegraph:deegraph /var/auxilium/dgdata -R
    
    sudo mkdir /var/auxilium/www-data
    sudo chown www-data:www-data /var/auxilium/www-data -R

fi
####################################################################################################





####################################################################################################
#   _____ ______ _____ _______ _____ ______ _____ _____       _______ ______  _____ 
#  / ____|  ____|  __ \__   __|_   _|  ____|_   _/ ____|   /\|__   __|  ____|/ ____|
# | |    | |__  | |__) | | |    | | | |__    | || |       /  \  | |  | |__  | (___  
# | |    |  __| |  _  /  | |    | | |  __|   | || |      / /\ \ | |  |  __|  \___ \ 
# | |____| |____| | \ \  | |   _| |_| |     _| || |____ / ____ \| |  | |____ ____) |
#  \_____|______|_|  \_\ |_|  |_____|_|    |_____\_____/_/    \_\_|  |______|_____/ 
####################################################################################################
if [ "$_MODE__CREATE_SELF_SIGNED_CERTS" -eq 1 ]; then

CERT_LOC="$(pwd)/certs/$INSTALL_ID"

INSTALL_DIR=$(pwd)
if [ ! -d $CERT_LOC ]; then
    mkdir -p $CERT_LOC
fi
cd $CERT_LOC

cat > ssc-ca-csr.conf << EOF

[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = GB
ST = Ceredigion
L = Aberystwyth
O = Aberystwyth University
OU = Auxilium
CN = Development CA

[v3_req]
keyUsage = keyCertSign, cRLSign

EOF

openssl req -x509 -sha256 -days 1825 -config ssc-ca-csr.conf -newkey rsa:2048 -passout pass:auxilium -keyout rootCA.key -out rootCA.crt
# openssl genrsa -out privkey.pem 2048
openssl genpkey -algorithm RSA -out privkey.pem -pkeyopt rsa_keygen_bits:2048

cat > ssc-csr.conf << EOF

[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = GB
ST = Ceredigion
L = Aberystwyth
O = Aberystwyth University
OU = Auxilium
CN = $HOSTNAME

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = $INSTANCE_IDENITIFIER

EOF

cat > csr.ext << EOF

authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
subjectAltName = @alt_names
[alt_names]
DNS.1 = $HOSTNAME
DNS.2 = $INSTANCE_IDENITIFIER

EOF


openssl req -new -key privkey.pem -out ssc.csr -config ssc-csr.conf
openssl x509 -req -days 365 -passin pass:auxilium -CA rootCA.crt -CAkey rootCA.key -in ssc.csr -out fullchain.pem -CAcreateserial -extfile csr.ext

cd $INSTALL_DIR

fi
####################################################################################################





####################################################################################################
#  _____  ______ ______ _____ _____            _____  _    _ 
# |  __ \|  ____|  ____/ ____|  __ \     /\   |  __ \| |  | |
# | |  | | |__  | |__ | |  __| |__) |   /  \  | |__) | |__| |
# | |  | |  __| |  __|| | |_ |  _  /   / /\ \ |  ___/|  __  |
# | |__| | |____| |___| |__| | | \ \  / ____ \| |    | |  | |
# |_____/|______|______\_____|_|  \_\/_/    \_\_|    |_|  |_|
####################################################################################################
if [ ! -f bin/deegraph-v${DEEGRAPH_VERSION}.jar ]; then
    if [ ! -f bin/deegraph.jar ]; then
        rm bin/deegraph.jar
    fi
    echo "Downloading Deegraph"
    wget -O bin/deegraph-v${DEEGRAPH_VERSION}.jar https://github.com/owoalex/deegraph/releases/download/v${DEEGRAPH_VERSION}/deegraph.jar
    cp bin/deegraph-v${DEEGRAPH_VERSION}.jar bin/deegraph.jar 
fi


if [ $? -ne 0 ]; then
    echo ""
    tput bold
    tput setaf 1
    tput rev
    echo "FAILED TO DOWNLOAD DEEGRAPH"
    tput sgr0
    echo ""
    exit 2
fi
####################################################################################################





####################################################################################################
#  _      ____   _____          _            _____ _   _  _____ _______       _      _      
# | |    / __ \ / ____|   /\   | |          |_   _| \ | |/ ____|__   __|/\   | |    | |     
# | |   | |  | | |       /  \  | |            | | |  \| | (___    | |  /  \  | |    | |     
# | |   | |  | | |      / /\ \ | |            | | | . ` |\___ \   | | / /\ \ | |    | |     
# | |___| |__| | |____ / ____ \| |____       _| |_| |\  |____) |  | |/ ____ \| |____| |____ 
# |______\____/ \_____/_/    \_\______|     |_____|_| \_|_____/   |_/_/    \_\______|______|
####################################################################################################
if [ "$_MODE__LOCAL_INSTALL" -eq 1 ]; then

    sudo su - root -c "echo \"127.0.0.1     $HOSTNAME\" >> /etc/hosts"

    pwd
    ls -lah bin
    sudo cp bin/deegraph.jar /opt/deegraph/deegraph.jar
    sudo chmod +x /opt/deegraph/deegraph.jar

    JSON_KEYS=$(php Scripts/new-keys.php --user=nobody)

    MYSQL_PASSWORD=$(echo $JSON_KEYS | jq -r '.mysqlPassword')
    DEEGRAPH_ROOT_AUTH_TOKEN=$(echo $JSON_KEYS | jq -r '.deegraphRootToken')
    LOCAL_ONLY_API_KEY=$(echo $JSON_KEYS | jq -r '.localOnlyApiKey')
    URL_METADATA_JWT_SECRET=$(echo $JSON_KEYS | jq -r '.urlMetadataJwtSecret')
    AUTH_JWT_SECRET=$(echo $JSON_KEYS | jq -r '.jwtSecret')
    AUTH_JWT_PUBLIC=$(echo $JSON_KEYS | jq -r '.jwtPublic')
    
    #########################
    # HANDLE SQL
    cat > pre-init-db.sql << EOF
DROP USER IF EXISTS auxilium2@localhost;
CREATE USER auxilium2@localhost IDENTIFIED BY '$MYSQL_PASSWORD';
DROP DATABASE IF EXISTS auxilium2;
CREATE DATABASE auxilium2 DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
GRANT ALL PRIVILEGES ON auxilium2.* TO auxilium2@localhost;
EOF
    sudo mysql < pre-init-db.sql
    rm pre-init-db.sql
    
    if [ -f $CERT_LOC/rootCA.crt ]; then
        sudo su - root -c "cat $CERT_LOC/rootCA.crt >> /etc/ssl/certs/ca-certificates.crt"
        sudo su - root -c "cp $CERT_LOC/rootCA.crt /usr/local/share/ca-certificates/auxroot.crt"
        sudo update-ca-certificates
    fi
    
    #########################
    # SETUP APACHE FILES
cat > apache-auxilium.conf << EOF

<VirtualHost *:80>
        DocumentRoot /srv/Auxilium/Auxilium/Public

        ServerName $HOSTNAME

        ErrorLog \${APACHE_LOG_DIR}/www.error.log
        CustomLog \${APACHE_LOG_DIR}/www.access.log combined

        RewriteEngine On
        RewriteCond %{REQUEST_URI} !^/.well-known/
        RewriteRule ^(.*) https://$HOSTNAME\$1 [R=301,L]
</VirtualHost>


<VirtualHost *:443>
        DocumentRoot /srv/Auxilium/Auxilium/Public

        ServerName $HOSTNAME

        ErrorLog \${APACHE_LOG_DIR}/www.error.log
        CustomLog \${APACHE_LOG_DIR}/www.access.log combined

        SSLEngine On
        SSLCertificateFile      /var/auxilium/ecs/certs/apache/fullchain.pem
        SSLCertificateKeyFile   /var/auxilium/ecs/certs/apache/privkey.pem

        RewriteEngine On

        Include auxilium-custom.conf
</VirtualHost>

EOF
    sudo cp apache-auxilium.conf /etc/apache2/sites-enabled/default.conf
    
    #########################
    # SETUP DEEGRAPH CONFIG FILES
    cat > deegraph-config.json << EOF
{
    "fqdn": "$HOSTNAME",
    "data_directory": "/var/auxilium/dgdata/",
    "ssl_certs": {
        "private_key": "/var/auxilium/ecs/certs/deegraph/privkey.pem",
        "full_chain": "/var/auxilium/ecs/certs/deegraph/fullchain.pem"
    },
    "port": 8880,
    "root_auth_tokens": ["$DEEGRAPH_ROOT_AUTH_TOKEN"],
    "journal_lifetime": 60
}
EOF
    sudo mv deegraph-config.json /etc/deegraph/deegraph-config.json

    cat > deegraph.service << EOF
[Unit]
Description=Deegraph Graph Database
After=network.target auditd.service

[Service]
Type=simple
User=deegraph
Group=deegraph
ExecStart=/usr/bin/java -jar /opt/deegraph/deegraph.jar /etc/deegraph/deegraph-config.json
TimeoutStartSec=0
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF
    
    #########################
    # SYSTEMD
    sudo systemctl stop deegraph.service
    sudo mv deegraph.service /etc/systemd/system/deegraph.service
    sudo systemctl daemon-reload
    sudo systemctl enable deegraph.service
    sudo systemctl start deegraph.service
    sudo systemctl enable apache2
    sudo systemctl start apache2
    
    QUICK_RETURN=$(pwd)
    cd /var/auxilium/dgdata
    lines=0
    while [ $lines -eq 0 ]; do
            echo "Waiting for Deegraph server to come online"
            sleep 0.25
            lines=$(find . -maxdepth 1 -name "*.private.jwk" | wc -l)
    done
    DDS_ROOT_NODE=$(find . -maxdepth 1 -name "*.private.jwk" | cut -d '/' -f2 | cut -d '.' -f1)
    cd $QUICK_RETURN
    
    #########################
    # AUXILIUM CREDENTIALS FILE
    cat > credentials.php << EOF
<?php
const INSTANCE_DOMAIN_NAME = "$HOSTNAME";
const INSTANCE_UUID = "$DDS_ROOT_NODE";

const INSTANCE_CREDENTIAL_SQL_HOST = "localhost";
const INSTANCE_CREDENTIAL_SQL_USERNAME = "auxilium2";
const INSTANCE_CREDENTIAL_SQL_PASSWORD = '$MYSQL_PASSWORD';
const INSTANCE_CREDENTIAL_SQL_DATABASE = "auxilium2";

const INSTANCE_CREDENTIAL_DDS_PORT = 8880;
const INSTANCE_CREDENTIAL_DDS_HOST = "$HOSTNAME";
const INSTANCE_CREDENTIAL_DDS_LOGIN_NODE = "$DDS_ROOT_NODE";
const INSTANCE_CREDENTIAL_DDS_TOKEN = '$DEEGRAPH_ROOT_AUTH_TOKEN';

const INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY = "$LOCAL_ONLY_API_KEY"; // b64 random data > 32 chars

const INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET = "$URL_METADATA_JWT_SECRET"; // b64 random data > 64 chars
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY = "$AUTH_JWT_SECRET";
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY = "$AUTH_JWT_PUBLIC";

const INSTANCE_CREDENTIAL_LOCAL_IP_RANGES = [
    "192.168.0.0/24",
    "127.0.0.0/8"
];

const INSTANCE_CREDENTIAL_OPENID_SOURCES = [];
const INSTANCE_CREDENTIAL_OPENID_CACHE_TIME = 60; // Cache trusted JWKs for this time in seconds.

const INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS = [
    "primary" => [
        "type" => "MS_APP_GRAPH",
        "username" => "REDACTED",
        "external_smtp_address" => "REDACTED",
        "password" => 'REDACTED',
        "user_guid" => "REDACTED",
        "tenant_guid" => "REDACTED",
        "client_guid" => "REDACTED",
        "client_secret" => 'REDACTED'
    ]
];

const ACCEPT_SELF_SIGNED_CERTIFICATES = $ACCEPT_SELF_SIGNED_CERTIFICATES
?>
EOF
    sudo mv credentials.php /var/auxilium/credentials.php
    sudo chown www-data:www-data /var/auxilium/credentials.php

    sudo chown www-data:www-data /var/auxilium/ecs -R

    sudo mkdir -p /var/auxilium/ecs/certs/deegraph/
    sudo cp $CERT_LOC/* /var/auxilium/ecs/certs/deegraph/
    sudo chown deegraph:deegraph /var/auxilium/ecs/certs/deegraph -R
    
    sudo mkdir -p /var/auxilium/ecs/certs/apache/
    sudo cp $CERT_LOC/* /var/auxilium/ecs/certs/apache/
    sudo chown www-data:www-data /var/auxilium/ecs/certs/apache -R
    
    sudo systemctl restart apache2.service
####################################################################################################
#  _____   ____   _____ _  ________ _____        _____ _   _  _____ _______       _      _      
# |  __ \ / __ \ / ____| |/ /  ____|  __ \      |_   _| \ | |/ ____|__   __|/\   | |    | |     
# | |  | | |  | | |    | ' /| |__  | |__) |       | | |  \| | (___    | |  /  \  | |    | |     
# | |  | | |  | | |    |  < |  __| |  _  /        | | | . ` |\___ \   | | / /\ \ | |    | |     
# | |__| | |__| | |____| . \| |____| | \ \       _| |_| |\  |____) |  | |/ ____ \| |____| |____ 
# |_____/ \____/ \_____|_|\_\______|_|  \_\     |_____|_| \_|_____/   |_/_/    \_\______|______|
####################################################################################################
else
    checkUserIsInGroup 'docker'

    echo "Building docker image"
    docker build -t auxilium .
    if [ $? -eq 0 ]; then
        if [ "$_MODE__INSTALL" -eq 1 ]; then
            echo "Running new image"
            docker stop auxilium-$INSTANCE_IDENITIFIER
            docker rm auxilium-$INSTANCE_IDENITIFIER

            #docker run -d -p 5097:8085 --name auxilium-dev auxilium
            docker run -dit -p $HTTP_PORT:80 -p $HTTPS_PORT:443 -p $DEEGRAPH_PORT:8880 -v $CERT_LOC:/etc/ssl/ext-certs --mount source=auxilium-volume-$INSTANCE_IDENITIFIER,target=/store -e CONTAINER_FQDN="$HOSTNAME" -e HTTPS_PORT="$HTTPS_PORT" --name auxilium-$INSTANCE_IDENITIFIER auxilium
            #docker run -it -p 8080:80 --name auxilium-dev auxilium
            #docker exec -it auxilium-dev /bin/bash
            #docker exec -it auxilium-dev watch cat /var/log/apache2/error.log
            #docker exec -it auxilium-dev watch -n 0.1 ps -A
            echo ""
            tput bold
            tput rev
            echo "Successfully installed Auxilium!"
            tput sgr0
            echo ""

            if [ "$_MODE__PREEXISTING_VOLUME" -eq 1 ]; then
                echo "Data restored from disk"
                echo "Go to <https://$HOSTNAME:$HTTPS_PORT/login> to examine this instance"
                echo ""
                #echo "https://$HOSTNAME:$HTTPS_PORT/login" | qrencode -o - -t ANSI256
                #echo ""
            else
                echo "Created blank auxilium instance"
                echo "Go to <https://$HOSTNAME:$HTTPS_PORT/system/init> to setup this new instance"
                echo ""
                #echo "https://$HOSTNAME:$HTTPS_PORT/system/init" | qrencode -o - -t ANSI256
                #echo ""
            fi
            echo "Run './Scripts/reset.sh $INSTANCE_IDENITIFIER' to reset to a new instance"
            echo "Run './Scripts/shutdown.sh $INSTANCE_IDENITIFIER' to close the instance cleanly"
        else
            tput bold
            tput rev
            echo "Successfully built Auxilium!"
            tput sgr0
            echo ""
        fi
    else
        echo ""
        tput bold
        tput setaf 1
        tput rev
        echo "BUILD FAILED :("
        tput sgr0
        echo ""
        exit 1
    fi
fi
####################################################################################################
