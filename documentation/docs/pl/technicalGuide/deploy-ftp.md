## Deploy na sewerze FTP cyberfolks
### krok po kroku

1. Wykup serwer cyber_up z dostępem do SSH
2. Zmień hasło do panelu direct_admin, naciskając ikonę kłódki w panelu w hostingu (będzie ono potrzebne do logowania się przez FTP)
3. Zaloguj się do panelu direct_admin
4. Utwórz bazę danych (Zarządzenia Mysql) [Bazy danych]
> UWAGA: Hasło do bazy danych nie może być utworzone ze znaków specjalnych, w przeciwnym razie nie będzie możliwości połączenia się z bazą danych

> Skorzystaj z generatora haseł dopisując 1 ! w jakimś miejscu, np na końcu (https://www.lastpass.com/features/password-generator)
5. Na stronie Zarządzanie MySQL, kliknij na nazwę bazy danych
6. Dodaj IP serwera takie samo jak IP serwera FTP (znajdziesz je w panelu direct_admin, po lewej stronie) [Hosting WWW, korzysta z tego samego adresu IP, a dopisujemy go do białej listy]
> Jeżeli chcesz dodaj również swój adres IP, aby połączyć się zdalnie z bazą danych za pomocą swojego klienta lokalnego
7. Utworz subdomenę o nazwie panel (zarządzanie subdomenami) [Serwer WWW i domeny]
8. Uruchom dostęp SSH (shell) [Pozostałe ustawienia]
9. Po lewej stronie będziesz miał dane do logowania się przez SSH (IP serwra, port SSH)
10. Pobierz klienta FTP, tutaj wykorzystywany był darmowy cyberduck Zaloguj się na serwer FTP, używając danych z punktu 5
```php
- IP serwera port 222
- login: login to nazwa serwera direct_admin (znajduje się np po lewej stronie w direct_admin > Serwer xx_)
- hasło: hasło do panelu direct_admin
```
11. Utwórz w public_html folder o nazwie panel, jeżeli nie został utworzony autamatycznie
12. Usuń wszystkie pliki z folderu panel
13. Dodaj pliki Symfony
> Uwaga: projekt symfony powinien mieć zainstolwany `composer require symfony/apache-pack`, oraz powinien działać na `AssetMapper`
Lista wymaganych folderów i plików:
```php
- vendor
- var
- translations
- templates
- symfony.lock
- src
- public
- migrations
- makefile
- importmap.php
- config
- composer.lock
- composer.json
- bin
- assets
```
14. Zaloguj się do SSH, korzystając terminala
```sh
ssh NAZWA_SERWERA@ADRES_IP -p 222
```
15. Przejdź do folderu panel
16. Utwórz plik .env na podstawie .env z projektu dostosuj go do swoich potrzeb
```sh
APP_ENV=prod
DB_HOST="s61.cyber-folks.pl"
DB_NAME="nazwa_bazy_danych"
DB_USER="nazwa_uzytkownika (taka sama ja nazwa_bazy_danych)"
DB_PASSWORD="hasło_do_bazy_danych"
CT_NAME="nazwa projektu"
CT_EMAIL="email klienta"
CORS_ALLOW_ORIGIN='^https?://(domena\.pl|www\.domena\.pl)$'
```
17. Utwórz plik .htaccess w folderze panel (jest to przekierowanie żądań do katalogu public)
```sh
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ /public/$1 [L]
</IfModule>
```
18. Wykonaj komendy:
```sh
php bin/console cache:clear
php bin/console asset-map:compile
php bin/console doctrine:schema:update --force
```
19. Utwórz certyfikat SSL (Certyfikaty SSL) [Bezpieczeństowo]
- Utwórz swój własny certyfikat
- Darmowy ceryfikat Let's Encrpt
- Zaznacz panel oraz nazwę projektu
20. Wsio, projekt powinien działać
