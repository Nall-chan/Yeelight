<?php

declare(strict_types=1);

/*
 * @addtogroup yeelight
 * @{
 *
 * @package       yeelight
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.80
 *
 */
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen

/**
 * YeelightDiscovery Klasse implementiert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.80
 *
 * @example <b>Ohne</b>
 *
 */
class YeelightDiscovery extends ipsmodule
{
    use \Yeelight\DebugHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $DisplayNATWarning = false;
        if (IPS_GetOption('NATSupport') && strpos(IPS_GetKernelPlatform(), 'Docker')) {
            // not supported. Docker cannot forward Multicast :(
            $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate("The combination of Docker and NAT is not supported because Docker does not support multicast.\r\nPlease run the container in the host network.");
            $Form['actions'][1]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }

        $Devices = $this->DiscoverDevices();
        $IPSDevices = $this->GetIPSInstances();
        if (count($Devices) == 0) {
            $Form['actions'][1]['visible'] = true;
        }
        $Values = [];

        foreach ($Devices as $IPAddress => $Device) {
            $InstanceID = array_search($IPAddress, $IPSDevices);
            $AddValue = [
                'IPAddress'  => $IPAddress,
                'id'         => $Device['id'],
                'model'      => $Device['model'],
                'name'       => $Device['name'],
                'location'   => '',
                'instanceID' => 0
            ];
            if ($InstanceID !== false) {
                unset($IPSDevices[$InstanceID]);
                $AddValue['name'] = IPS_GetName($InstanceID);
                $Device['location'] = stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true);
                $AddValue['instanceID'] = $InstanceID;
            }
            $AddValue['create'] = [
                [
                    'moduleID'      => '{BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F}',
                    'location'      => [$this->Translate('Yeelight Devices')],
                    'configuration' => new stdClass()
                ],
                [
                    'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',
                    'configuration' => [
                        'Host' => $IPAddress,
                        'Port' => $Device['port'],
                        'Open' => true
                    ]
                ]
            ];
            $Values[] = $AddValue;
        }

        foreach ($IPSDevices as $InstanceID => $IPAddress) {
            $Values[] = [
                'IPAddress'  => $IPAddress,
                'id'         => '',
                'model'      => '',
                'name'       => IPS_GetName($InstanceID),
                'location'   => stristr(IPS_GetLocation($InstanceID), IPS_GetName($InstanceID), true),
                'instanceID' => $InstanceID
            ];
        }
        $Form['actions'][0]['values'] = $Values;
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
            $Header[trim(array_shift($line_array))] = trim(implode(':', $line_array));
        }
        return $Header;
    }

    private function DiscoverDevices(): array
    {
        $this->LogMessage($this->Translate('Background discovery of Yeelight devices'), KL_NOTIFY);
        $DeviceData = [];
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return $DeviceData;
        }

        socket_bind($socket, '0', 0);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 4);
        $message = [
            'M-SEARCH * HTTP/1.1',
            'HOST: 239.255.255.250:1982',
            'MAN: "ssdp:discover"',
            'ST: wifi_bulb',
            '',
            ''
        ];
        $SendData = implode("\r\n", $message);
        $this->SendDebug('Search', $SendData, 0);
        if (@socket_sendto($socket, $SendData, strlen($SendData), 0, '239.255.255.250', 1982) === false) {
            return $DeviceData;
        }
        usleep(100000);
        $i = 50;
        $buf = '';
        $IPAddress = '';
        $Port = 0;
        while ($i) {
            $ret = @socket_recvfrom($socket, $buf, 2048, 0, $IPAddress, $Port);
            if ($ret === false) {
                break;
            }
            if ($ret === 0) {
                $i--;
                continue;
            }
            $this->SendDebug($IPAddress, $buf, 0);
            $Data = $this->parseHeader($buf);
            $Data['port'] = (int) explode(':', $Data['Location'])[2];
            if ($Data['name'] == '') {
                $Data['name'] = 'unnamed Yeelight Device';
            }
            $DeviceData[$IPAddress] = $Data;
        }
        socket_close($socket);
        return $DeviceData;
    }
}

/* @} */
