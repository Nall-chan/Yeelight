<?php

declare(strict_types=1);

/*
 * @addtogroup yeelight
 * @{
 *
 * @package       yeelight
 * @file          module.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.16
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // SendDebug für Arrays und Objekte
require_once __DIR__ . '/../libs/YeelightRPC.php';  // Konstanten

use Yeelight\GUID;
use Yeelight\Network;
use Yeelight\Property;

/**
 * YeelightDiscovery Klasse implementiert.
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       2.16
 *
 * @example <b>Ohne</b>
 *
 */
class YeelightDiscovery extends IPSModuleStrict
{
    use \Yeelight\DebugHelper;

    /** Suchzeit in Sekunden für alle Interfaces gemeinsam. */
    private const DiscoveryTime = 3;

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        if (IPS_GetOption('NATSupport') && str_contains(IPS_GetKernelPlatform(), 'Docker')) {
            // not supported. Docker cannot forward Multicast :(
            $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate("The combination of Docker and NAT is not supported because Docker does not support multicast.\r\nPlease run the container in the host network.");
            $Form['actions'][1]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }

        $Devices = $this->DiscoverDevices();
        $Instances = $this->GetIPSInstances();
        if (count($Devices) == 0) {
            $Form['actions'][1]['visible'] = true;
        }
        $DeviceValues = [];

        foreach ($Devices as $DeviceId => $Data) {
            $Hosts = array_unique($Data['Hosts']);
            ksort($Hosts);
            $Host = $Hosts[array_key_last($Hosts)];
            $AddDevice = [
                'host'       => $Host,
                'id'         => $DeviceId,
                'model'      => $Data['Model'],
                'name'       => $Data['Name'],
                'location'   => '',
                'instanceID' => 0
            ];
            $InstanceID = $this->FindInstance($Instances, (string) $DeviceId, $Hosts);
            if ($InstanceID > 0) {
                // Vom Nutzer gewählte Schreibweise (IP oder Hostname) beibehalten, sofern das Gerät darunter noch erreichbar ist.
                if (in_array(strtolower($Instances[$InstanceID]['Host']), $Hosts)) {
                    $Host = strtolower($Instances[$InstanceID]['Host']);
                }
                $AddDevice['name'] = IPS_GetName($InstanceID);
                $AddDevice['instanceID'] = $InstanceID;
                $AddDevice['host'] = $Host;
                $AddDevice['location'] = stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true);
                unset($Instances[$InstanceID]);
            }
            // Weicht die Instanz davon ab (z.B. neue IP-Adresse oder fehlende Geräte-ID), zeigt der Konfigurator die Zeile grau.
            $AddDevice['create'] = [
                [
                    'moduleID'      => GUID::Device,
                    'configuration' => [
                        Property::DeviceId => (string) $DeviceId
                    ]
                ],
                [
                    'moduleID'      => GUID::ClientSocket,
                    'configuration' => [
                        'Host' => $Host,
                        'Port' => $Data['Port'],
                        'Open' => true
                    ]
                ]
            ];
            $DeviceValues[] = $AddDevice;
        }

        foreach ($Instances as $InstanceID => $Instance) {
            $DeviceValues[] = [
                'host'       => $Instance['Host'],
                'id'         => $Instance['DeviceId'],
                'model'      => '',
                'name'       => IPS_GetName($InstanceID),
                'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true),
                'instanceID' => $InstanceID
            ];
        }
        $Form['actions'][0]['values'] = $DeviceValues;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * Sucht die Instanz zu einem gefundenen Gerät.
     * Zuerst über die Geräte-ID, Instanzen ohne Geräte-ID (ältere Versionen) über den Host des Client Socket.
     *
     * @param array  $Instances Vorhandene Instanzen (InstanzID => ['Host' => …, 'DeviceId' => …])
     * @param string $DeviceId  Geräte-ID aus der SSDP-Antwort
     * @param array  $Hosts     IP-Adressen und Hostnamen des Gerätes (kleingeschrieben)
     *
     * @return int InstanzID oder 0
     */
    private function FindInstance(array $Instances, string $DeviceId, array $Hosts): int
    {
        foreach ($Instances as $InstanceID => $Instance) {
            if (($Instance['DeviceId'] !== '') && (strcasecmp($Instance['DeviceId'], $DeviceId) == 0)) {
                return $InstanceID;
            }
        }
        foreach ($Instances as $InstanceID => $Instance) {
            if (($Instance['DeviceId'] === '') && in_array(strtolower($Instance['Host']), $Hosts)) {
                return $InstanceID;
            }
        }
        return 0;
    }

    /**
     * Liefert alle Yeelight Device Instanzen mit Host des Client Socket und Geräte-ID.
     *
     * @return array InstanzID => ['Host' => …, 'DeviceId' => …]
     */
    private function GetIPSInstances(): array
    {
        $InstanceIDList = IPS_GetInstanceListByModuleID(GUID::Device);
        $Devices = [];
        foreach ($InstanceIDList as $InstanceID) {
            $IO = IPS_GetInstance($InstanceID)['ConnectionID'];
            $Devices[$InstanceID] = [
                'Host'     => ($IO > 0) ? (string) IPS_GetProperty($IO, 'Host') : '',
                'DeviceId' => (string) @IPS_GetProperty($InstanceID, Property::DeviceId)
            ];
        }
        $this->SendDebug('IPS Devices', $Devices, 0);
        return $Devices;
    }

    private function parseHeader(string $Data): array
    {
        $Lines = explode("\r\n", $Data);
        array_shift($Lines);
        array_pop($Lines);
        $Header = [];
        foreach ($Lines as $Line) {
            $line_array = explode(':', $Line);
            $Header[strtolower(trim(array_shift($line_array)))] = trim(implode(':', $line_array));
        }
        return $Header;
    }

    /**
     * Sucht Geräte per SSDP auf allen IPv4-Interfaces gleichzeitig.
     * Je Interface wird ein Socket geöffnet und die Suche gesendet, danach werden alle Sockets
     * gemeinsam per socket_select bis zum Ablauf der Suchzeit ausgewertet.
     *
     * @return array Gefundene Geräte, Schlüssel ist die Geräte-ID
     */
    private function DiscoverDevices(): array
    {
        $Interfaces = $this->getIPAdresses();
        $message = [
            'M-SEARCH * HTTP/1.1',
            'HOST: ' . Network::SsdpMulticast . ':' . Network::SsdpPort,
            'MAN: "ssdp:discover"',
            'ST: wifi_bulb'
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";
        $this->SendDebug('Search', $SendData, 0);

        // Alle Sockets öffnen und die Suche senden
        $Sockets = [];
        foreach ($Interfaces['ipv4'] as $IP => $Interface) {
            $socket = $this->OpenSearchSocket((string) $IP, $Interface);
            if ($socket === false) {
                continue;
            }
            if (@socket_sendto($socket, $SendData, strlen($SendData), 0, Network::SsdpMulticast, Network::SsdpPort) === false) {
                $this->SendDebug('Error on send discovery message', $IP, 0);
                socket_close($socket);
                continue;
            }
            $this->SendDebug('Start Discovery(' . $Interface . ')', $IP, 0);
            $Sockets[] = $socket;
        }

        // Antworten aller Interfaces gemeinsam empfangen
        $Responses = [];
        $DiscoveryTimeout = microtime(true) + self::DiscoveryTime;
        while (count($Sockets) > 0) {
            $Remaining = $DiscoveryTimeout - microtime(true);
            if ($Remaining <= 0) {
                break;
            }
            $Read = $Sockets;
            $Write = null;
            $Except = null;
            $Seconds = (int) $Remaining;
            $Changed = @socket_select($Read, $Write, $Except, $Seconds, (int) (($Remaining - $Seconds) * 1000000));
            if (!$Changed) {
                break; // Fehler oder Suchzeit abgelaufen
            }
            foreach ($Read as $socket) {
                $Response = '';
                $IPAddress = '';
                $Port = 0;
                if (!@socket_recvfrom($socket, $Response, 2048, 0, $IPAddress, $Port)) {
                    continue;
                }
                $this->SendDebug('Receive (' . $IPAddress . ')', $Response, 0);
                $Responses[] = $Response;
            }
        }
        foreach ($Sockets as $socket) {
            socket_close($socket);
        }
        return $this->ParseResponses($Responses);
    }

    /**
     * Öffnet einen UDP-Socket für die Suche auf einem Interface.
     *
     * @param string     $IP        Lokale IPv4-Adresse
     * @param mixed      $Interface Interface für Multicast (Index oder Name)
     *
     * @return false|Socket Socket oder false
     */
    private function OpenSearchSocket(string $IP, mixed $Interface): false|Socket
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            $this->SendDebug('Error on create Socket ipv4', $IP, 0);
            return false;
        }
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 4);
        @socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_IF, $Interface);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        if (!@socket_bind($socket, $IP, Network::SsdpLocalPort)) {
            $this->SendDebug('Error on bind socket', $IP, 0);
            socket_close($socket);
            return false;
        }
        return $socket;
    }

    /**
     * Wertet die SSDP-Antworten aus. Der Hostname wird je Geräte-IP nur einmal per Reverse-DNS ermittelt.
     *
     * @param string[] $Responses Empfangene SSDP-Antworten
     *
     * @return array Gefundene Geräte, Schlüssel ist die Geräte-ID
     */
    private function ParseResponses(array $Responses): array
    {
        $DevicesData = [];
        $HostNames = [];
        $Index = 0;
        foreach ($Responses as $Response) {
            $Data = $this->parseHeader($Response);
            if (!array_key_exists('location', $Data) || !array_key_exists('id', $Data)) {
                continue;
            }
            $Location = explode(':', $Data['location']);
            if (!isset($Location[2]) || $Location[0] != 'yeelight') {
                continue;
            }
            $IPAddress = (string) parse_url($Data['location'], PHP_URL_HOST);
            if ($IPAddress === '') {
                continue;
            }
            $Name = (($Data['name'] ?? '') != '') ? $Data['name'] : 'Yeelight Device - ' . $Data['id'];
            $Model = $Data['model'] ?? '';
            $Port = (int) $Location[2];
            $this->AddDiscoveryEntry($DevicesData, $Name, $Model, $Data['id'], $IPAddress, $Port, 60 + $Index);
            if (!array_key_exists($IPAddress, $HostNames)) {
                $HostNames[$IPAddress] = (string) gethostbyaddr($IPAddress);
            }
            if (($HostNames[$IPAddress] !== '') && ($HostNames[$IPAddress] != $IPAddress)) {
                $this->AddDiscoveryEntry($DevicesData, $Name, $Model, $Data['id'], $HostNames[$IPAddress], $Port, 40 + $Index);
            }
            $Index++;
        }
        return $DevicesData;
    }

    private function getIPAdresses(): array
    {
        $Interfaces = SYS_GetNetworkInfo();
        $InterfaceDescriptions = array_column($Interfaces, 'Description', 'InterfaceIndex');
        $Networks = net_get_interfaces();
        $Addresses = [];
        $Addresses['ipv6'] = [];
        $Addresses['ipv4'] = [];
        foreach ($Networks as $InterfaceDescription => $Interface) {
            if (!$Interface['up']) {
                continue;
            }
            if (array_key_exists('description', $Interface)) {
                $InterfaceDescription = array_search($Interface['description'], $InterfaceDescriptions);
            }
            foreach ($Interface['unicast'] as $Address) {
                switch ($Address['family']) {
                    case AF_INET6:
                        if ($Address['address'] == '::1') {
                            continue 2;
                        }
                        $Address['address'] = '[' . $Address['address'] . ']';
                        $family = 'ipv6';
                        break;
                    case AF_INET:
                        if ($Address['address'] == '127.0.0.1') {
                            continue 2;
                        }
                        $family = 'ipv4';
                        break;
                    default:
                        continue 2;
                }
                $Addresses[$family][$Address['address']] = $InterfaceDescription;
            }
        }
        return $Addresses;
    }
    private function AddDiscoveryEntry(array &$DevicesData, string $name, string $model, string $id, string $Host, int $Port, int $Index): void
    {
        if (array_key_exists($id, $DevicesData)) {
            if (!in_array(strtolower($Host), $DevicesData[$id]['Hosts'])) {
                $DevicesData[$id]['Hosts'][$Index] = strtolower($Host);
            }
        } else {
            $DevicesData[$id]['Name'] = $name;
            $DevicesData[$id]['Model'] = $model;
            $DevicesData[$id]['Port'] = $Port;
            $DevicesData[$id]['Hosts'][$Index] = strtolower($Host);
        }
    }
}

/* @} */
