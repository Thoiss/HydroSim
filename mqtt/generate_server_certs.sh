#!/bin/bash
# =====================================================
# Script: generate_server_certs.sh
# Doel:  Generates a server private key and certificate for Mosquitto TLS
# =====================================================

# Stop in case of errors
set -e

# Ask for the domain of the server (e.g. in most cases the IP-address of the server)
read -p "Enter de domain of the server (e.g. The IP-adres, broker.mydomain.nl or localhost): " CN

# Check if CN isn't empty
if [ -z "$CN" ]; then
  echo "Error: Common Name can not be empty."
  exit 1
fi

# Subject Alternative Names: modern TLS clients ignore the CN and only check the SAN
if [[ "$CN" =~ ^[0-9.]+$ ]]; then SAN="IP:${CN}"; else SAN="DNS:${CN}"; fi
SAN="${SAN}, IP:127.0.0.1, DNS:localhost, DNS:mosquitto"
read -p "Extra SANs (optional, e.g. IP:10.0.0.5,DNS:broker.local): " EXTRA_SAN
[ -n "$EXTRA_SAN" ] && SAN="${SAN}, ${EXTRA_SAN}"

# Paths (change if necessary)
BASE_DIR="./certs"
SERVER_DIR="$BASE_DIR/server"
CA_DIR="$BASE_DIR/ca"

# Create the server directory if it doesn't exist
if [ ! -d "$SERVER_DIR" ]; then
  echo "Server directory doesn't exist yet. Creating $SERVER_DIR..."
  mkdir -p "$SERVER_DIR" || {
    echo "Error: Couldn't create directory $SERVER_DIR" >&2
    exit 1
  }
fi

# Filepaths
KEY_FILE="$SERVER_DIR/server.key"
CSR_FILE="$SERVER_DIR/server.csr"
CRT_FILE="$SERVER_DIR/server.crt"

echo "-------------------------------------------"
echo "Generating server private key..."
echo "-------------------------------------------"

openssl genrsa -out "$KEY_FILE" 4096

# Set the permissions of the server private key so the mosquitto container can read the file
chmod 644 "$KEY_FILE"

echo "-------------------------------------------"
echo "Generating the server CSR..."
echo "-------------------------------------------"

# Creating the server CSR (change -subj parameters if necessary)
openssl req -new -key "$KEY_FILE" -out "$CSR_FILE" -subj "/C=NL/O=HHS/CN=${CN}"

echo "-------------------------------------------"
echo "Signing with CA (valid for 1 year)..."
echo "-------------------------------------------"

openssl x509 -req -in "$CSR_FILE" \
  -CA "$CA_DIR/ca.crt" \
  -CAkey "$CA_DIR/ca.key" \
  -CAcreateserial \
  -out "$CRT_FILE" \
  -days 365 -sha256 \
  -extfile <(printf '%s\n' \
    "basicConstraints = critical, CA:FALSE" \
    "keyUsage = critical, digitalSignature, keyEncipherment" \
    "extendedKeyUsage = serverAuth" \
    "subjectAltName = $SAN")

echo "-------------------------------------------"
echo "Server certificate succesfully generated!"
echo "Files:"
echo "  Private key : $KEY_FILE"
echo "  Certificate : $CRT_FILE"
echo "  CSR         : $CSR_FILE"
echo "-------------------------------------------"
