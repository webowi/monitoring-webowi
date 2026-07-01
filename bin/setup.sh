#!/usr/bin/env bash

set -e

echo "======================================="
echo "     Monitoring Webowi - Instalacja"
echo "======================================="
echo

read -p "Ta operacja przebuduje projekt od zera. Kontynuować? [y/N] " confirm

if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    exit 0
fi

echo
echo "Uruchamianie kontenerów..."
bash docker/run-app.sh

echo
echo "Konfiguracja administratora"

read -p "Adres e-mail: " EMAIL

while true; do
    read -s -p "Hasło: " PASSWORD
    echo

    read -s -p "Powtórz hasło: " PASSWORD_REPEAT
    echo

    if [[ "$PASSWORD" == "$PASSWORD_REPEAT" ]]; then
        break
    fi

    echo
    echo "❌ Hasła nie są identyczne. Spróbuj ponownie."
    echo
done

read -p "Nazwa organizacji [Default Organization]: " ORGANIZATION_NAME
ORGANIZATION_NAME=${ORGANIZATION_NAME:-Default Organization}

echo
echo "Tworzenie administratora..."

docker exec -u root "$CONTAINER_NAME" \
    bin/console mw:create:account \
    --email="$EMAIL" \
    --password="$PASSWORD" \
    --organization-name="$ORGANIZATION_NAME"

echo
echo "✅ Instalacja zakończona."
echo
echo "Panel: http://localhost:34500"
echo