# Simple OAUTH: Einführung

## Features

OAuth2 Server für die YCom. Diverse Grantmöglichkeiten implementiert. Basiert auf der OAuth2 Server Biliothek von https://oauth2.thephpleague.com/
Dabei sind bisher folgende Grantmöglichkeiten implementiert die auf die YCom zugreifen. Dabei wird die YCom mit dem vorhandenen Login verwendet und darauf geleitet.

* authorization grant
* password grant

Dieser Pfad inkl Unterpfade muss verfügbar bleiben.

<code>/oauth2/</code>

Scopes sind bisher nicht eingebaut. Bisher ist Simple_Auth rein für die allgemeine Authentifizierung von YCom Usern nutzbar.


## Private und Public Key erstellen

Um OAuth nutzen zu können, müssen zunächst ein Private und ein Public Key erstellt werden, und dieser muss im Data Ordner von simple_oauth als private.key Datei und als public.key Datei abgelegt werden.

Erstellung eines Private Keys:

```openssl genrsa -out private.key 2048```

Erstellung eines Public Keys, aus dem vorhandenen Private Key

```openssl rsa -in private.key -pubout -out public.key```

Weitere Informationen gibt es hier.
https://oauth2.thephpleague.com/installation/


## Ablauf

### Settings

Einstellung der Zeitintervalle für die verschiedenen Codes.

### Client erstellen

In REDAXO muss zunächst ein Client erstellt werden, also eine Kennung für den Service, welcher OAUTH nutzen will.

```
Label: [Bezeichnung des Services]
Secret: [Einen Code eingeben, den man sich merken muss]
(Der Secret wird mit dem private.key verschlüsselt)
Beschreibung: 
Is Confidential: 
Redirect: (Url an welche bei einem grant_type=authorize der code) gesendet wird.
Scopes: Auswählen welche Daten beim Profil übergeben werden.
```

Es gibt ein paar vordefinierte Scopes, welche sicher nicht reichen werden. Man kann die Scopes erweitern indem man in der boot.php des Project-AddOns folgendes ergänzt.

```php
<?php

\REDAXO\Simple_OAuth\Simple_OAuth::addScope('my_scope', [
    'id',
    'login',
    'name',
    "firstname",
    "ycom_groups",
    "ein_weiteres_value",
    "ein_weiteres_value",
    "ein_weiteres_value",
]);

?>
```

Mit dem erstellten Client mit der ID (client_id) und dem sich gemerkten (secret) geht es weiter.


### Authorization Grant (Variante 1)

Hiermit wird eine Anfrage mit der client id erstellt mit welcher man auf die YCom Loginseite geleitet wird, mit einem Redirekt, welcher dann URL des beim Client eingetragenen URL leitet. Zu dieser URL wird ein code=[code] angefügt, mit welchem man dann weitere Abfragen macht. Scope ist bisher nicht implementiert. Ein State Parameter kann auch übergeben werden und wird entsprechend auch beim redirect weitergegeben. Dieser dient für den anfragenden zur Überprüfung und sollte auch eine Ablaufzeit haben

#### Hole den AuthCode (Step 1/2)

##### Request

```
[GET]
http://[domain]]/oauth2/authorize?

response_type=code&
grant_type=authorize&
client_id=[id]&
scope=&
state=[optional]&
```


#### LoginFormular für Authorization Grant

Dieses Formular über YForm in einem Artikel erstellen und über die Settings das Formular entsprechend zuweisen. 

```
objparams|form_method|get|

hidden|response_type|response_type|REQUEST
hidden|grant_type|grant_type|REQUEST
hidden|client_id|client_id|REQUEST
hidden|client_secret|client_secret|REQUEST
hidden|state|state|REQUEST

validate|ycom_auth|login|psw|auth|{{ login_info }}|{{ login_failed }}
text|login|{{ mitgliedsnummer }}: <span class="form-required-sign">*</span>|#attributes:{"autocomplete":"username"}
password|psw|{{ password }}: <span class="form-required-sign">*</span>|#attributes:{"autocomplete":"current-password"}

action|callback|\REDAXO\Simple_OAuth\Simple_OAuth::authorizeGrant|pre
```


##### Response

Weiterleitung auf

```
https://[url des clients]?code=[erzeugter Auth Code]&state=[wenn vorher vorhanden, dann hier auch]

```

#### Get Access Token (Step 2/2)

Mit diesem Code kann man nun den länger haltbaren Access Token abrufen. Nach dem erstmaligen Auruf, verfällt der Code.

##### Request

```
[POST]
http://[domain]/oauth2/token

code=[erzeugter Auth Code]
grant_type=authorization_code
client_id[id]&
client_secret[client_secret]&

```

##### Response

```
{
    "token_type": "Bearer",
    "expires_in": 2592000,
    "access_token": "hier kommt der access_token",
    "refresh_token": "hier kommt der refresh_token"
}
```

### Passwort Grant (Variante 2)

Diese, nicht empfohlene Variante, erlaubt es direkt mit Usernamen und Passwort einen Access-Code anzufragen.

##### Request

```
https://[domain]/oauth2/access_token

[POST]
grant_type      = password
client_id       = [id]
client_secret   = [client_secret]
username        = [ycom_user_login]
password        = [ycom_user_password]
```

##### Response

```
{
    "token_type": "Bearer",
    "expires_in": 2592000,
    "access_token": "hier kommt der access_token",
    "refresh_token": "hier kommt der refresh_token"
}
```

### Refresh Token Grant

Die verschiedenen Codes laufen unterschiedlich schnell ab oder werden verbraucht. Der Auth-Code verfällt nach einmaligem gebraucht und hat nur eine kurze Dauer gültig. Mit dem Access-Code hat man einen länger anhaltenden Schlüssel, mit welchem man weitere Anfragen stellen kann, ohne, dass ein Login erneut abgefragt werden muss. Mit dem Reresh-Code, kann dieser erneugt erstellt werden und damit wieder zum Laufen gebracht werden. Dazu ist der Refresh Token Granttype zuständig.

##### Request

```
https://[domain]/oauth2/access_token

[POST]
grant_type      = refresh_token
refresh_token   = [refresh_token]
client_id       = [id]
client_secret   = [client_secret]
```


##### Response

```
{
    "token_type": "Bearer",
    "expires_in": 2592000,
    "access_token": "hier kommt der access_token",
    "refresh_token": "hier kommt der refresh_token"
}
```


### Profil abfragen

Mit dem erzeugten Access-Code kann man die Profildaten des Users abfragen. Dabei werden, entsprechend der Scopes des Clients, die Attribute des Users ausgegeben

##### Request

```
https://[domain]/oauth2/profile

[HEADER]
Authorization = [access_token]
```

##### Response

```
{
    "id": "1",
    "firstname": "Jan",
    "name": "Kristinus",
    "email": "jan@kristinus.de",
    "ycom_groups": "1"
}
```
## Hilfereiche Links

* https://www.oauth.com/playground/
* https://oauth2.thephpleague.com/
