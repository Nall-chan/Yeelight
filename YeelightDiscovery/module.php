<?php

declare(strict_types=1);

/*
 * @addtogroup yeelight
 * @{
 *
 * @package       yeelight
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 *
 */
eval('declare(strict_types=1);namespace YeelightDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen

/**
 * YeelightDiscovery Klasse implementiert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2019 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.0
 *
 * @example <b>Ohne</b>
 *
 * @property array $Devices
 */
class YeelightDiscovery extends ipsmodule
{
    use \Yeelight\DebugHelper,
        \YeelightDiscovery\BufferHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->Devices = [];
        $this->RegisterTimer('Discovery', 0, 'YeeLight_Discover($_IPS[\'TARGET\']);');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->Devices = @$this->DiscoverDevices();
        $this->SetTimerInterval('Discovery', 300000);
    }

    /**
     * Interne Funktion des SDK.
     * Verarbeitet alle Nachrichten auf die wir uns registriert haben.
     *
     * @param int       $TimeStamp
     * @param int       $SenderID
     * @param int       $Message
     * @param array|int $Data
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->ApplyChanges();
                break;
        }
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

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm()
    {
        $Devices = $this->DiscoverDevices();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $IPSDevices = $this->GetIPSInstances();

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
//                'name'       => IPS_GetLocation($InstanceID),
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
        $DeviceData = [];
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return $DeviceData;
        }
        socket_bind($socket, '0.0.0.0', 0);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        $message = [
            'M-SEARCH * HTTP/1.1',
            'HOST: 239.255.255.250:1982',
            'MAN: "ssdp:discover"',
            'ST: wifi_bulb'
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";
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
            $Data = $this->parseHeader($buf);
            $Data['port'] = (int) explode(':', $Data['Location'])[2];
            $this->SendDebug($IPAddress, $Data, 0);
            if ($Data['name'] == '') {
                $Data['name'] = 'unnamed Yeelight Device';
            }
            $DeviceData[$IPAddress] = $Data;
        }
        socket_close($socket);
        return $DeviceData;
    }

    public function Discover()
    {
        $this->LogMessage($this->Translate('Background discovery of Yeelight devices'), KL_NOTIFY);
        $this->Devices = $this->DiscoverDevices();
        // Alt neu vergleich fehlt, sowie die Events an IPS senden wenn neues Gerät im Netz gefunden wurde.
    }
}

/* @} */
