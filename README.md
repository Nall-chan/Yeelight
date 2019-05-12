[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.5-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-5.1%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-1-%28Stable%29-Changelog)
[![StyleCI](https://styleci.io/repos/186269467/shield?style=flat)](https://styleci.io/repos/186269467)  

# Symcon-Modul: Yeelight
Einbinden von Yeelight Geräten in IPS.  

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Anhang](#5-anhang)  
    1. [GUID der Module](#1-guid-der-module)
    2. [Hinweise](#2-hinweise)
    3. [Changlog](#3-changlog)
    4. [Spenden](#4-spenden)
6. [Anhang](#6-anhang)  

## 1. Funktionsumfang


### [Yeelight Discovery:](YeelightDiscovery/)  

 - Auflisten alle im Netzwerk vorhandenen Yeelight Geräte.  
 - Erstellen von neuen Yeelight Device-Instanzen in IPS.  

### [Yeelight Gerät:](YeelightDevice/)  

 - Empfangen und visualisieren der aktuellen Zustände in IPS.  
 - Steuerung per WebFront und per PHP-Funktionen.  

## 2. Voraussetzungen

 - IPS 5.1 oder höher  
 - Yeelight Gerät ( '3th party local control' muss aktiviert werden, siehe [hier](#2-hinweise)  

## 3. Software-Installation

**IPS 5.1:**  
   Bei privater Nutzung:
     Über den 'Module-Store' in IPS.  
   **Bei kommerzieller Nutzung (z.B. als Errichter oder Integrator) wenden Sie sich bitte an den Autor.**  

## 4. Einrichten der Instanzen in IP-Symcon

Details sind in der Dokumentation der jeweiligen Module beschrieben.  

Die [Discovery](YeelightDiscovery/) Instanz ermöglich das einfache einbinden aller Geräte in IPS.  


## 5. Anhang

###  1. GUID der Module

 
| Modul              | Typ          |Prefix    | GUID                                   |
| :----------------: | :----------: | :------: | :------------------------------------: |
| Yeelight Discovery | Discovery    | YeeLight | {7AABB3D2-3D24-4F2C-86CE-A56FB09D188A} |
| Yeelight Device    | Device       | YeeLight | {BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F} |


### 2. Hinweise  

Nachdem die Geräte mit der Hersteller APP (Yeelight, nicht Xiaomi !) in das Netzwerk integriert wurden, muss die lokale Steuerung für jedes Gerät einzeln aktiviert werden.  

![App1](imgs/app1.png)![App2](imgs/app2.png)![App3](imgs/app3.png)  

### 3. Changlog

Version 1.5:  
 - Release für IPS 5.1 und den Module-Store  

Version 1.0:  
 - Erstes offizielles Release  

### 4. Spenden  
  
  Die Library ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## 6. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
 
