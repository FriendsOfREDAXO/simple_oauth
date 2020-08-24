Simple_OAuth für REDAXO 5.11+
=============

Features
--------

OAuth2 Server für die YCom. Diverse Grantmöglichkeiten implementiert. Basiert auf der OAuth2 Server Biliothek von https://oauth2.thephpleague.com/
Dabei sind bisher folgende Grantmöglichkeiten implementiert die auf die YCom zugreifen. Dabei wird die YCom mit dem vorhandenen Login verwendet und darauf geleitet.

* authorization grant
* password grant

Pfade:
https://domain.de/oauth2/authorize
https://domain.de/oauth2/token
https://domain.de/oauth2/profile


#### Achtung

Dieser Pfad inkl Unterpfade muss verfügbar bleiben.

<code>/oauth2/</code>

Scopes sind bisher nicht eingebaut. Bisher ist Simple_Auth rein für die allgemeine Authentifizierung von YCom Usern nutzbar.
