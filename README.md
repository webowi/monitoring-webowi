# Monitoring Webowi API

Backend aplikacji **Monitoring Webowi** oparty o Symfony oraz Docker.

Monitoring Webowi składa się z dwóch repozytoriów:

| Repozytorium          | Opis |
|-----------------------|------|
| `monitoring-webowi`   | Backend aplikacji oparty o Symfony i Docker |
| `monitoring-webowi-ui` | Panel administracyjny (SPA) napisany w React |

> To repozytorium zawiera wyłącznie backend aplikacji.
> Należy również pobrać repozytorium z panelem administracyjnym, aby móc w pełni korzystać z aplikacji.
> Panel administracyjny jest dostępny pod adresem: [monitoring-webowi-ui](https://github.com/webowi/monitoring-webowi-ui)

---

# Wymagania

Przed rozpoczęciem upewnij się, że masz zainstalowane:

- Docker
- Docker Compose
- Git

Możesz sprawdzić poprawność instalacji poleceniami:

```bash
docker --version
docker compose version
git --version
```

Jeżeli nie masz zainstalowanego Dockera, pobierz go z oficjalnej strony:

https://docs.docker.com/get-docker/

---

# Szybki start

## 1. Sklonuj repozytorium

```bash
git clone git@github.com:webowi/monitoring-webowi.git
cd monitoring-webowi
```

## 2. Uruchom instalator

```bash
make install
```

Instalator automatycznie:

- uruchomi kontenery Dockera,
- zainstaluje wymagane zależności,
- przygotuje środowisko,
- wykona migracje bazy danych,
- poprosi o utworzenie konta administratora,
- skonfiguruje aplikację.

Podczas instalacji zostaniesz poproszony o podanie:

- adresu e-mail administratora,
- hasła,
- potwierdzenia hasła.
- opcjonalnie nazwy organuizacji (jeżeli nie zostanie podana, zostanie użyta domyślna nazwa "Default organization").

Domyślnie aplikacja będzie dostępna pod adresem:

```text
http://localhost:34500
```

---

# Uruchomienie panelu administracyjnego (SPA)

Po poprawnym uruchomieniu backendu pobierz aplikację SPA:

```bash
git clone git@github.com:webowi/monitoring-webowi-ui.git
cd monitoring-webowi-ui
npm install
npm run dev
```

Domyślnie aplikacja będzie dostępna pod adresem:

```text
http://localhost:5173
```

Backend będzie dostępny zgodnie z konfiguracją Dockera (domyślny adres znajdziesz w pliku `docker-compose.yml` lub dokumentacji projektu).

---

# Kolejne uruchomienie projektu

Jeżeli aplikacja została już zainstalowana, wystarczy uruchomić:

```bash
make start
```


---

# Pomoc

Jeżeli podczas instalacji pojawią się problemy, upewnij się, że:

- Docker jest uruchomiony,
- wymagane porty nie są zajęte przez inne aplikacje,
- korzystasz z aktualnej wersji repozytorium.

Jeżeli problem nadal występuje, zgłoś go w zakładce **Issues** repozytorium GitHub.