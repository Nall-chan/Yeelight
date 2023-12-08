[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.13-blue.svg)](https://community.symcon.de/t/modul-xiaomi-yeelight-color-bulb/45887)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://www.symcon.de/service/dokumentation/installation/migrationen/v60-v61-q1-2022/)
[![Check Style](https://github.com/Nall-chan/Yeelight/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/Yeelight/actions) [![Run Tests](https://github.com/Nall-chan/Yeelight/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/Yeelight/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#2-spenden)[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# Yeelight Discovery <!-- omit in toc -->  
Sucht Yeelight Geräte im LAN und vereinfacht das Anlegen von Geräten in IPS.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Statusvariablen und Profile](#5-statusvariablen-und-profile)
- [6. WebFront](#6-webfront)
- [7. PHP-Befehlsreferenz](#7-php-befehlsreferenz)
- [8. Anhang](#8-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [9. Lizenz](#9-lizenz)

## 1. Funktionsumfang

 - Auflisten alle im Netzwerk vorhandenen Yeelight Geräte.  
 - Erstellen von neuen Yeelight Device-Instanzen in IPS.  

## 2. Voraussetzungen

 - IPS 6.1 oder höher  
 - Yeelight Gerät ( '3th party local control' muss aktiviert werden, siehe [hier](../README.md#1-lan-steuerung-aktiveren) 

## 3. Software-Installation

 Dieses Modul ist Bestandteil der [Yeelight-Library](../README.md#3-software-installation).  

## 4. Einrichten der Instanzen in IP-Symcon

Die Yeelight Discovery Instanz ist direkt im Objektbaum unter den Discovery Instanzen zu finden, sofern sie bei der Installation des Modules mit angelegt wurde.  

Beim öffnen der Yeelight Discovery Instanz oder nach dem betätigen der Schaltfläche 'Aktualisieren', dauert es wenige Sekunden bis alle im Netzwerk gefunden Geräte angezeigt werden.  

<span style="color:red">**Wird Symcon in einen Docker Container welcher per NAT angebunden ist betrieben, so wird eine Fehlermeldung ausgegeben. Diese Konstellation wird aufgrund der fehlenden Multicast Fähigkeiten von Docker nicht unterstützt.**</span>  

Über das selektieren eines Eintrages in der Tabelle und betätigen des dazugehörigen 'Erstellen' Button können einzelne Instanzen in IPS angelegt werden.  

Alternativ können auch alle fehlenden Instanzen auf einmal erstellt werden.  

Erstellte Instanzen werden in der Kategorie 'Yeelight Geräte' des Objektbaum erstellt. Die Instanzen können anschließend frei verschoben werden.  

![Discovery](imgs/conf.png)  

## 5. Statusvariablen und Profile

Die Instanz besitzt keine Statusvariablen und Variablenprofile.  

## 6. WebFront

Die Instanz besitzt keine im WebFront darstellbaren Elemente.  

## 7. PHP-Befehlsreferenz

Die Instanz besitzt keine Instanz-Funktionen.  

## 8. Anhang

### 1. Changelog

[Changelog der Library](../README.md#4-changelog)

### 2. Spenden

Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)


## 9. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
