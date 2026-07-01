# Wymagania

## Wersja Docker:
Aby uruchomić ten projekt, musisz mieć zainstalowany Docker oraz Docker Compose. Poniżej znajdują się instrukcje, jak sprawdzić, czy te narzędzia są zainstalowane oraz jak je zainstalować, jeśli nie są.

### Sprawdzanie instalacji Dockera

Aby sprawdzić, czy Docker jest zainstalowany na Twoim systemie, wykonaj następujące polecenie w terminalu:

```sh
docker --version
```

Jeśli Docker nie jest zainstalowany, postępuj zgodnie z instrukcjami [na oficjalnej stronie Dockera](https://docs.docker.com/get-docker/), aby go zainstalować.

### Sprawdzanie instalacji Docker Compose

Aby sprawdzić, czy Docker Compose jest zainstalowany na Twoim systemie, wykonaj następujące polecenie w terminalu:

```sh
docker-compose --version
```

Jeśli Docker Compose nie jest zainstalowany, postępuj zgodnie z instrukcjami [na oficjalnej stronie Docker Compose](https://docs.docker.com/compose/install/), aby go zainstalować.

### Uruchamianie projektu

Aby uruchomić projekt, sklonuj repozytorium i wykonaj następujące polecenia w katalogu projektu:

1. Sklonuj repozytorium

```sh
git clone git@github.com:kkedzierski/monitoring-webowi.git
cd monitoring-webowi
```

2. Uruchom projekt

```sh
bash docker/run-app.sh
```

## Wersja na serwer wspódzielony z public_html:
Skopiuj foldery na serwer współdzielony w folderze public_html.

Następnie, wykonaj komendę:
```php
bash bin/deployWithoutDocker.sh
```

