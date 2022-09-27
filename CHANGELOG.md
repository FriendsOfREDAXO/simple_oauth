Changelog
=========

Version 1.0.2 - 14.03.2022
--------------------------

* Composer update


Version 1.0.1 - 14.03.2022
--------------------------

### Korrekturen

* Beim login_field aus ycom wurde nur die email beachtet. Nun geht das ausgewählte Feld
* Default Expire des AuthCodes war default nur auf 1min gesetzt, was in der Verwaltung nicht ersichtlich war
* Fehlende Texte und CS



Version 1.0 - 21.12.2021
--------------------------

### Anpassungen

* Encryptionkey wird nun auch "on the fly" generiert wenn nicht vorhanden
* Scopes sind nun erweiterbar und werden in Profilpage beachtet
* Doku nun sehr viel ausführlicher
* Verwaltung der Tabellen nun ohne falsche Darstellung



Version 0.9.2 – 24.08.2020
--------------------------

### Fehler

AuthCodeRepository speicherte den User falsch.



Version 0.9.1 – 24.08.2020
--------------------------

### Fehler

Import der YForm Tabellen funktionierte nicht.



Version 0.9 – 24.08.2020
--------------------------

#### Neu

* Anzeige ob public/private Key vorhanden ist und wie man diese erstellt
* Tabellen + Verwaltung für Client, Token und Authorization werden automatisch erstellt.
* Einstellung der Expirezeiten für Access/Refresh/Auth Tokens und Code sind einstellbar
