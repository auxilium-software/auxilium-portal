#!/bin/bash

# rm /store/* -R

NEW_INSTALL=0

if [ -f /store/docker-config ]; then
    echo "Loading config files from store"

    source /store/docker-config

    /etc/init.d/mariadb start
else
    echo "New install"
    NEW_INSTALL=1

    mkdir /store/local-assets/
    mkdir /store/mysql-data/
    chown mysql:mysql /store/mysql-data/
    mv /var/lib/mysql/* /store/mysql-data/

    JSON_KEYS=$(php /app/new-keys.php --user=nobody)

    MYSQL_PASSWORD=$(echo $JSON_KEYS | jq -r '.mysqlPassword')
    echo "MYSQL_PASSWORD=\"$MYSQL_PASSWORD\"" >> /store/docker-config
    DEEGRAPH_ROOT_AUTH_TOKEN=$(echo $JSON_KEYS | jq -r '.deegraphRootToken')
    echo "DEEGRAPH_ROOT_AUTH_TOKEN=\"$DEEGRAPH_ROOT_AUTH_TOKEN\"" >> /store/docker-config
    LOCAL_ONLY_API_KEY=$(echo $JSON_KEYS | jq -r '.localOnlyApiKey')
    echo "LOCAL_ONLY_API_KEY=\"$LOCAL_ONLY_API_KEY\"" >> /store/docker-config
    URL_METADATA_JWT_SECRET=$(echo $JSON_KEYS | jq -r '.urlMetadataJwtSecret')
    echo "URL_METADATA_JWT_SECRET=\"$URL_METADATA_JWT_SECRET\"" >> /store/docker-config
    AUTH_JWT_SECRET=$(echo $JSON_KEYS | jq -r '.jwtSecret')
    echo "AUTH_JWT_SECRET=\"$AUTH_JWT_SECRET\"" >> /store/docker-config
    AUTH_JWT_PUBLIC=$(echo $JSON_KEYS | jq -r '.jwtPublic')
    echo "AUTH_JWT_PUBLIC=\"$AUTH_JWT_PUBLIC\"" >> /store/docker-config

    cat > pre-init-db.sql << EOF
DROP USER IF EXISTS auxilium2@localhost;
CREATE USER auxilium2@localhost IDENTIFIED BY '$MYSQL_PASSWORD';
DROP DATABASE IF EXISTS auxilium2;
CREATE DATABASE auxilium2 DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
GRANT ALL PRIVILEGES ON auxilium2.* TO auxilium2@localhost;
EOF

    /etc/init.d/mariadb start
    mysql < pre-init-db.sql

    mkdir -p /store/deegraph/dgdata/
    chown deegraph:deegraph /store/deegraph -R
fi

#openssl x509 -outform der -in /etc/ssl/ext-certs/rootCA.crt -out /usr/local/share/ca-certificates/development-ca.crt
if [ -f /etc/ssl/ext-certs/rootCA.crt ]; then
    cat /etc/ssl/ext-certs/rootCA.crt >> /etc/ssl/certs/ca-certificates.crt
#update-ca-certificates
fi

mkdir -p /store/Auxilium/{LFS,Messages,Misc}

mkdir -p /var/EphemeralCredentialsStore/FormsInProgress
mkdir -p /var/EphemeralCredentialsStore/Indexes
mkdir -p /var/EphemeralCredentialsStore/Jobs/{Completed,Failed,Queue}
mkdir -p /var/EphemeralCredentialsStore/MessageDrafts
mkdir -p /var/EphemeralCredentialsStore/Certificates/{Deegraph,Nginx}/
cp /etc/ssl/ext-certs/* /var/EphemeralCredentialsStore/Certificates/Nginx/

mkdir -p /srv/Auxilium/Public/Static/LocalAssets
ln -s /store/local-assets /srv/Auxilium/Public/Static/LocalAssets

chown -R www-data:www-data /var/EphemeralCredentialsStore
chown -R www-data:www-data /store/Auxilium

cp /etc/ssl/ext-certs/* /var/EphemeralCredentialsStore/Certificates/Deegraph/
chown deegraph:deegraph /var/EphemeralCredentialsStore/Certificates/Deegraph -R

echo "127.0.0.1     $CONTAINER_FQDN" >> /etc/hosts

cat > /app/config.json << EOF
{
    "fqdn": "$CONTAINER_FQDN",
    "data_directory": "/store/deegraph/dgdata/",
    "ssl_certs": {
        "private_key": "/var/EphemeralCredentialsStore/Certificates/Deegraph/privkey.pem",
        "full_chain": "/var/EphemeralCredentialsStore/Certificates/Deegraph/fullchain.pem"
    },
    "port": 8880,
    "root_auth_tokens": ["$DEEGRAPH_ROOT_AUTH_TOKEN"],
    "journal_lifetime": 60
}
EOF

chown deegraph:deegraph /app/config.json

# runuser -l deegraph -c "cd /app/; java -jar /app/deegraph.jar /app/config.json"
#runuser -l deegraph -c "cd /app/; nohup java -jar /app/deegraph.jar /app/config.json >/dev/null 2>&1"

echo "Launching deegraph"

nohup su - deegraph -c "/usr/bin/java -jar /app/deegraph.jar /app/config.json & echo \$! > /store/deegraph/deegraph.pid.tmp" &

QUICK_RETURN=$(pwd)
cd /store/deegraph/dgdata/
lines=0
while [ $lines -eq 0 ]; do
        echo "Waiting for Deegraph server to come online"
        sleep 0.25
        lines=$(find . -maxdepth 1 -name "*.private.jwk" | wc -l)
done
DDS_ROOT_NODE=$(find . -maxdepth 1 -name "*.private.jwk" | cut -d '/' -f2 | cut -d '.' -f1)
cd $QUICK_RETURN

cat > /app/credentials.php << EOF
<?php
const INSTANCE_DOMAIN_NAME = "$CONTAINER_FQDN:$HTTPS_PORT";
const INSTANCE_UUID = "$DDS_ROOT_NODE";

const INSTANCE_CREDENTIAL_SQL_HOST = "localhost";
const INSTANCE_CREDENTIAL_SQL_USERNAME = "auxilium2";
const INSTANCE_CREDENTIAL_SQL_PASSWORD = '$MYSQL_PASSWORD';
const INSTANCE_CREDENTIAL_SQL_DATABASE = "auxilium2";

const INSTANCE_CREDENTIAL_DDS_PORT = 8880;
const INSTANCE_CREDENTIAL_DDS_HOST = "$CONTAINER_FQDN";
const INSTANCE_CREDENTIAL_DDS_LOGIN_NODE = "$DDS_ROOT_NODE";
const INSTANCE_CREDENTIAL_DDS_TOKEN = '$DEEGRAPH_ROOT_AUTH_TOKEN';

const INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY = "$LOCAL_ONLY_API_KEY"; // b64 random data > 32 chars

const INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET = "$URL_METADATA_JWT_SECRET"; // b64 random data > 64 chars
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY = "$AUTH_JWT_SECRET";
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY = "$AUTH_JWT_PUBLIC";

const INSTANCE_CREDENTIAL_LOCAL_IP_RANGES = [
    "172.17.0.0/16",
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

const ACCEPT_SELF_SIGNED_CERTIFICATES = FALSE;
EOF

chown www-data:www-data /app/credentials.php

#export NGINX_USER=www-data
#export NGINX_GROUP=www-data
#export NGINX_PID_FILE=/var/run/nginx.pid
#export NGINX_LOG_DIR=/var/log/nginx
service nginx start
service php8.2-fpm start

trap "/etc/init.d/mariadb stop; kill -s SIGTERM \$(cat /store/deegraph/deegraph.pid.tmp); echo \$(date +%s) > /store/last-shutdown.txt" EXIT

while true
do
    sleep 1
done
