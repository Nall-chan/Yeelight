[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.00-blue.svg)](https://community.symcon.de/t/modul-xiaomi-yeelight-color-bulb/45887)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)
[![Check Style](https://github.com/Nall-chan/Yeelight/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/Yeelight/actions) [![Run Tests](https://github.com/Nall-chan/Yeelight/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/Yeelight/actions)  

# Yeelight Device <!-- omit in toc -->
Einbindung eines Yeelight-Gerätes in IPS.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
- [6. WebFront](#6-webfront)
- [7. Aktionen](#7-aktionen)
- [8. PHP-Befehlsreferenz](#8-php-befehlsreferenz)
- [9. Changelog](#9-changelog)
- [10. Lizenz](#10-lizenz)

## 1. Funktionsumfang

 - Empfangen und visualisieren der aktuellen Zustände in IPS.  
 - Steuerung über die Statusvariablen.  
 - Steuerung über [Symcon Aktionen](https://www.symcon.de/service/dokumentation/konzepte/automationen/ablaufplaene/aktionen/)
 - PHP-Funktionen für erweiterte Funktionen.  

## 2. Voraussetzungen

 - IPS 6.1 oder höher  
 - Yeelight Gerät ( '3th party local control' muss aktiviert werden, siehe [hier](../README.md#1-lan-steuerung-aktiveren)  

## 3. Software-Installation

 Dieses Modul ist Bestandteil der [IPSYeelight-Library](../README.md#3-software-installation).  

## 4. Einrichten der Instanzen in IP-Symcon

Das Anlegen von neuen Instanzen kann komfortabel über die Instanz [Yeelight Discovery Instanz](../YeelightDiscovery/) erfolgen.  
<span style="color:red">**Wird Symcon allerdings unter Docker mit aktivem NAT betrieben, so müssen die Geräte Instanze per Hand angelegt werden!**</span>  

Entsprechend ist das Modul im Dialog 'Instanz hinzufügen' unter dem Hersteller 'Xiaomi' oder dem Schnellfilter 'Yeelight' zu finden.  
![Instanz hinzufügen](imgs/add.png)  

Es wird automatisch eine 'Client Socket' Instanz erzeugt.  
In dem sich öffnenden Konfigurationsformular muss die IP-Adresse des Gerätes bei 'Host' eingetragen werden und der Haken 'Aktiv' gesetzt sein.
![Instanz hinzufügen](imgs/add1.png)  

Folgende Parameter sind in der 'Yeelight Device' Instanz zu konfigurieren:  
![Konfigurator](imgs/conf.png)  
**Konfigurationsseite:**  

| Eigenschaft |   Typ   | Standardwert |                                      Funktion                                       |
| :---------: | :-----: | :----------: | :---------------------------------------------------------------------------------: |
|  SetSmooth  |  bool   |    false     |      Bei true wird immer eine Transitionzeit von 500ms bei Ansteuerung gesetzt      |
|  HUESlider  |  bool   |     true     | Aktiviert zwei Variablen für das WebFront welche einen HUE und Sat Slider enthalten |
|    Mode     | integer |      0       |     Varianten für den Modus, 0=Farbe, 1=Farbe + Nachtlicht, 2=Weiß + Nachtlicht     |

## 5. Statusvariablen und Profile

**Statusvariablen:**  
Folgende Statusvariablen werden automatisch angelegt, je nach Gerät können es auch weniger sein.  

|         Name          |   Typ   |   Ident    |                 Beschreibung                 |
| :-------------------: | :-----: | :--------: | :------------------------------------------: |
|        Status         |  bool   |   power    |              Status des Gerätes              |
|      Helligkeit       | integer |   bright   |            Helligkeit in Prozent             |
|       RGB Farbe       | integer |    rgb     |                 RGB Farbwert                 |
|         Weiß          | integer |     ct     | Weißton im Modus 'Weiß'  von 1700K bis 6500K |
|    Aktueller Modus    | integer | color_mode |          1 = RGB, 2 = Weiß, 3 = HSV          |
|     HSV Sättigung     | integer |    sat     |         Sättigung in Prozent für HUE         |
|        HSV Hue        | string  |    hue     |  JavaScript für den HUE-Slider im WebFront   |
| Helligkeit Nachtlicht | integer |   nl_br    |  Vom Gerät gemeldete Helligkeit Nachtlicht   |
 
**Profile:**  

|           Name           |   Typ   | verwendet von Statusvariablen |
| :----------------------: | :-----: | :---------------------------: |
|    Yeelight.WhiteTemp    | integer |             Weiß              |
|      Yeelight.Mode       | integer |        Aktueller Modus        |
|   Yeelight.ModeWNight    | integer |        Aktueller Modus        |
| Yeelight.ModeColorWNight | integer |        Aktueller Modus        |

## 6. WebFront

Die direkte Darstellung und Steuerung im WebFront ist möglich.  
![WebFront Beispiel](imgs/wf.png)  

## 7. Aktionen

Es gibt diverse Ziel(Instanz)-Spezifische Aktionen für die 'Yeelight Device' Instanz.  
Diese Aktionen können sowohl in Ereignissen als auch Ablaufplänen verwendet werden.  
Ebenso können die Aktionen über die Schaltfläche 'Befehl einfügen' im Skript-Editor in ein Skript eingefügt werden.  
Abschließend stehen die Aktionen auch über den Eintrag 'Befehle testen' im Kontexmenü vom Objektbaum zur Verfügung.

Diese Aktionen sind speziell für die 'Yeelight Device' Instanz, weshalb als Ziel immer eine 'Yeelight Device' Instanz gewählt sein muss, damit die Aktionen zur Auswahl stehen.  

![Aktionen Beispiel 1](imgs/actions.png)  
![Aktionen Beispiel 2](imgs/action1.png)  

## 8. PHP-Befehlsreferenz

Für alle 'bool' Rückgabewerte gilt:  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false` zurück gegeben.  

Verfügt das Gerät über eine zweites 'Leuchtmittel' bzw. über eine Hintergrundfarbe,  
so stehen fast alle Befehle hierzu ebenfalls zur Verfügung.  
Da die Verwendung identisch ist, sind diese nicht weiter dokumentiert.  
Die Befehle lauten z.B.  
 ```php
  YEELIGHT_SetPower(...)       => YEELIGHT_SetBgPower(...)
  YEELIGHT_SetWhiteSmooth(...) => YEELIGHT_SetBgWhiteSmooth(...)
  YEELIGHT_SetHSV(...)         => YEELIGHT_SetBgHSV(...)
```
usw...  

```php
bool YEELIGHT_RequestState(integer $InstanzID)
```
Liest den Zustand des Gerätes und führt alle Statusvariablen nach.  

```php
bool YEELIGHT_SetWhite(integer $InstanzID, integer $Temperature)
```
Setzt den in '$Temperature' übergebenen Weißton.  
Erlaubter Wertebereich ist 1700 bis 6500.  

```php
bool YEELIGHT_SetWhiteSmooth(integer $InstanzID, integer $Temperature, integer $Duration)
```
Setzt den in '$Temperature' übergebenen Weißton mit der in '$Duration' übergebenen Transitionzeit in Millisekunden.  
Erlaubter Wertebereich ist 1700 bis 6500.  

```php
bool YEELIGHT_SetRGB(integer $InstanzID, integer $Red, integer $Green, integer $Blue)
```
Setzt die in '$Red', '$Green' und '$Blue' übergebenen Farben.  
Erlaubter Wertebereich ist 0 bis 255.  

```php
bool YEELIGHT_SetRGBSmooth(integer $InstanzID, integer $Red, integer $Green, integer $Blue, integer $Duration)
```
Setzt die in '$Red', '$Green' und '$Blue' übergebenen Farben mit der in '$Duration' übergebenen Transitionzeit in Millisekunden.  
Erlaubter Wertebereich ist 0 bis 255.  

```php
bool YEELIGHT_SetHSV(integer $InstanzID, integer $HUE, integer $Saturation)
```
Setzt die in '$HUE' und '$Saturation' übergebene Farbe.  
Erlaubter Wertebereich ist für '$HUE' von 0 bis 359 und für '$Saturation' 1 bis 100.  

```php
bool YEELIGHT_SetHSVSmooth(integer $InstanzID, integer $HUE, integer $Saturation, integer $Duration)
```
Setzt die in '$HUE' und '$Saturation' übergebene Farbe mit der in '$Duration' übergebenen Transitionzeit in Millisekunden.  
Erlaubter Wertebereich ist für '$HUE' von 0 bis 359 und für '$Saturation' 1 bis 100.  

```php
bool YEELIGHT_SetBrightness(integer $InstanzID, integer $Level)
```
Setzt die in '$Level' übergebene Helligkeit.  
Erlaubter Wertebereich ist 0 bis 100.  

```php
bool YEELIGHT_SetBrightnessSmooth(integer $InstanzID, integer $Level, integer $Duration)
```
Setzt die in '$Level' übergebene Helligkeit mit der in '$Duration' übergebenen Transitionzeit in Millisekunden.  
Erlaubter Wertebereich ist 0 bis 100.  

```php
bool YEELIGHT_SetMode(integer $InstanzID, integer $Mode)
```

```php
bool YEELIGHT_SetPower(integer $InstanzID, bool $Value)
```
Schaltet das Gerät ein oder aus.  
Erlaubte Werte für '$Value' sind 'true' zum ein- und 'false' zum ausschalten.  

```php
bool YEELIGHT_SetPowerSmooth(integer $InstanzID, bool $Value, integer $Duration)
```
Schaltet das Gerät ein oder aus, mit der in '$Duration' übergebenen Transitionzeit in Millisekunden.  
Erlaubte Werte für '$Value' sind 'true' zum ein- und 'false' zum ausschalten.  

```php
bool YEELIGHT_SetToogle(integer $InstanzID)
```

```php
bool YEELIGHT_SetToogleBoth(integer $InstanzID)
```

```php
bool YEELIGHT_SetDefault(integer $InstanzID)
```

```php
bool YEELIGHT_StartColorFlow(integer $InstanzID, integer $Loops, integer $RecoverState, string $Flow)
```

```php
bool YEELIGHT_StopColorFlow(integer $InstanzID)
```

```php
bool YEELIGHT_SetSleep(integer $InstanzID, integer $Minutes)
```

```php
integer YEELIGHT_GetSleep(integer $InstanzID)
```

```php
bool YEELIGHT_DelSleep(integer $InstanzID)
```

```php
bool YEELIGHT_IncreaseBright(integer $InstanzID)
```

```php
bool YEELIGHT_DecreaseBright(integer $InstanzID)
```

```php
bool YEELIGHT_IncreaseWhiteTemp(integer $InstanzID)
```

```php
bool YEELIGHT_DecreaseWhiteTemp(integer $InstanzID)
```

```php
bool YEELIGHT_RotateColor(integer $InstanzID)
```

```php
bool YEELIGHT_SetName(integer $InstanzID, string $Name)
```

## 9. Changelog

[Changelog der Library](../README.md#4-changelog)

## 10. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
