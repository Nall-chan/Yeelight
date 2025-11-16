<?php

declare(strict_types=1);

/*
 * @addtogroup yeelight
 * @{
 *
 * @package       yeelight
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.13
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen

/**
 * YeelightDiscovery Klasse implementiert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.13
 *
 * @example <b>Ohne</b>
 *
 */
class YeelightDiscovery extends IPSModuleStrict
{
    use \Yeelight\DebugHelper;

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

        if (IPS_GetOption('NATSupport') && strpos(IPS_GetKernelPlatform(), 'Docker')) {
            // not supported. Docker cannot forward Multicast :(
            $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate("The combination of Docker and NAT is not supported because Docker does not support multicast.\r\nPlease run the container in the host network.");
            $Form['actions'][1]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }

        $Devices = $this->DiscoverDevices();
        $DevicesAddress = $this->GetIPSInstances();
        if (count($Devices) == 0) {
            $Form['actions'][1]['visible'] = true;
        }
        $DeviceValues = [];

        foreach ($Devices as $id => $Data) {
            $Data['Hosts'] = array_unique($Data['Hosts']);
            ksort($Data['Hosts']);
            $AddDevice = [
                'host'       => $Data['Hosts'][array_key_last($Data['Hosts'])],
                'id'         => $id,
                'model'      => $Data['Model'],
                'name'       => $Data['Name'],
                'location'   => '',
                'instanceID' => 0
            ];
            foreach ($Data['Hosts'] as $Host) {
                $InstanceID = array_search($Host, $DevicesAddress);
                if ($InstanceID !== false) {
                    $AddDevice['name'] = IPS_GetName($InstanceID);
                    $AddDevice['instanceID'] = $InstanceID;
                    $AddDevice['host'] = $Host;
                    $AddDevice['location'] = stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true);
                    unset($DevicesAddress[$InstanceID]);
                }

                $AddDevice['create'] = [
                    [
                        'moduleID'      => '{BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F}',
                        'location'      => [$this->Translate('Yeelight Devices')],
                        'configuration' => new stdClass()
                    ],
                    [
                        'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',
                        'configuration' => [
                            'Host' => $Host,
                            'Port' => $Data['Port'],
                            'Open' => true
                        ]
                    ]
                ];
            }
            $DeviceValues[] = $AddDevice;
        }

        foreach ($DevicesAddress as $InstanceID => $Host) {
            $DeviceValues[] = [
                'host'       => $Host,
                'id'         => '',
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

    private function GetIPSInstances(): array
    {
        $InstanceIDList = IPS_GetInstanceListByModuleID('{BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F}');
        $Devices = [];
        foreach ($InstanceIDList as $InstanceID) {
            $IO = IPS_GetInstance($InstanceID)['ConnectionID'];
            if ($IO > 0) {
                $Devices[$InstanceID] = IPS_GetProperty($IO, 'Host');
            }
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

    private function DiscoverDevices(): array
    {

        $Interfaces = $this->getIPAdresses();
        $DevicesData = [];

        $Index = 0;
        foreach ($Interfaces['ipv4'] as $IP => $Interface) {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
                socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
                socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 4);
                socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_IF, $Interface);
                socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
                if (@socket_bind($socket, $IP, 1983) == false) {
                    continue;
                }
                $discoveryTimeout = time() + 3;
                $message = [
                    'M-SEARCH * HTTP/1.1',
                    'HOST: 239.255.255.250:1982',
                    'MAN: "ssdp:discover"',
                    'ST: wifi_bulb',
                    '',
                    ''
                ];
                $SendData = implode("\r\n", $message) . "\r\n\r\n";
                $this->SendDebug('Start Discovery(' . $Interface . ')', $IP, 0);
                $this->SendDebug('Search', $SendData, 0);
                if (@socket_sendto($socket, $SendData, strlen($SendData), 0, '239.255.255.250', 1982) === false) {
                    $this->SendDebug('Error on send discovery message', $IP, 0);
                    @socket_close($socket);
                    continue;
                }
                $response = '';
                $IPAddress = '';
                $Port = 0;
                do {
                    if (0 == @socket_recvfrom($socket, $response, 2048, 0, $IPAddress, $Port)) {
                        continue;
                    }
                    $this->SendDebug('Receive (' . $IPAddress . ')', $response, 0);
                    $Data = $this->parseHeader($response);

                    if (!array_key_exists('location', $Data)) {
                        continue;
                    }
                    $Location = explode(':', $Data['location']);
                    if (!isset($Location[2]) || $Location[0] != 'yeelight') {
                        continue;
                    }
                    $Data['port'] = (int) $Location[2];
                    $IPAddress = parse_url($Data['location'], PHP_URL_HOST);
                    if ($Data['name'] == '') {
                        $Data['name'] = 'Yeelight Device - ' . $Data['id'];
                    }

                    $this->AddDiscoveryEntry($DevicesData, $Data['name'], $Data['model'], $Data['id'], $IPAddress, $Data['port'], 60 + $Index);
                    $Host = gethostbyaddr($IPAddress);
                    if ($Host != $IPAddress) {
                        $this->AddDiscoveryEntry($DevicesData, $Data['name'], $Data['model'], $Data['id'], $Host, $Data['port'], 40 + $Index);
                    }
                    $Index++;
                } while (time() < $discoveryTimeout);
                socket_close($socket);
            } else {
                $this->SendDebug('Error on create Socket ipv4', $IP, 0);
            }
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
            if (!in_array($Host, $DevicesData[$id]['Hosts'])) {
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
