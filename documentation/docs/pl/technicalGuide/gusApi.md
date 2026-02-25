Gus Api

Użycie API GUS do pobierania danych o firmach
Dane pobierane są za pomocą narzędzia: https://github.com/johnzuk/GusApi

Dane są cache'owane przez 24h, aby przyspieszyć kolejne zapytania o te same dane.
Zapytania są blokowane po 5 próbach, aby uniknąć nadmiernego obciążenia API GUS.


Wyjaśnienie działania:
1. Użytkownik wysyła zapytanie o dane firmy, podając NIP
2. System sprawdza, czy dane dla tego NIP są już w cache'u i czy są aktualne (nie starsze niż 24h)
3. Jeśli dane są w cache'u i aktualne, zwraca je użytkownikowi
4. Jeśli danych nie ma w cache'u lub są nieaktualne, system wysyła zapytanie do API GUS, pobiera dane, zapisuje je w cache'u i zwraca użytkownikowi
5. Jeśli zapytanie do API GUS zakończy się użytkownik dostaje informację o błędzie
6. System zlicza liczbę prób zapytań do API GUS dla danego NIP i blokuje dalsze zapytania po 5 próbach, zwracając informację o blokadzie
