#!/bin/bash
if [ ! -d certs ]; then
    mkdir certs
fi
cd certs

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

HOSTNAME_ALT=$(hostname)
HOSTNAME=$(hostname --fqdn)

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
DNS.1 = $HOSTNAME_ALT

EOF

cat > csr.ext << EOF

authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
subjectAltName = @alt_names
[alt_names]
DNS.1 = $HOSTNAME
DNS.2 = $HOSTNAME_ALT

EOF


openssl req -new -key privkey.pem -out ssc.csr -config ssc-csr.conf
openssl x509 -req -days 365 -passin pass:auxilium -CA rootCA.crt -CAkey rootCA.key -in ssc.csr -out fullchain.pem -CAcreateserial -extfile csr.ext

