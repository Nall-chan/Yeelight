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
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
require_once __DIR__ . '/../libs/DebugHelper.php';  // diverse Klassen
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
require_once __DIR__ . '/../libs/YeelightRPC.php';  // diverse Klassen

/**
 * YeelightDevice Klasse implementiert.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.80
 *
 * @example <b>Ohne</b>
 *
 * @property array $Capabilities
 * @property array $Propertys
 * @property string $BufferIN Receive-Buffer
 * @property array $ReplyJSONData Send/Receive Buffer
 * @property string $Host
 * @property int $HUE
 * @property int $SAT
 * @property int $BG_HUE
 * @property int $BG_SAT
 */
class YeelightDevice extends IPSModule
{
    use \YeelightDevice\BufferHelper,
        \YeelightDevice\VariableProfileHelper,
        \YeelightDevice\Semaphore,
        \Yeelight\DebugHelper,
        \YeelightDevice\WebhookHelper,
        \YeelightDevice\InstanceStatus {
        \YeelightDevice\InstanceStatus::MessageSink as IOMessageSink;
        \YeelightDevice\InstanceStatus::RegisterParent as IORegisterParent;
        \YeelightDevice\InstanceStatus::RequestAction as IORequestAction;
    }
    protected static $DataPoints = [
        'power'      => [
            'Name'         => 'State',
            'Type'         => VARIABLETYPE_BOOLEAN,
            'Profile'      => '~Switch',
            'enableAction' => true,
            'Mapping'      => ['on' => true, 'off' => false]
        ],
        'bg_power'   => [
            'Name'         => 'State Background',
            'Type'         => VARIABLETYPE_BOOLEAN,
            'Profile'      => '~Switch',
            'enableAction' => true,
            'Mapping'      => ['on' => true, 'off' => false]
        ],
        'bright'     => [
            'Name'         => 'Brightness',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~Intensity.100',
            'enableAction' => true
        ],
        'bg_bright'  => [
            'Name'         => 'Brightness Background',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~Intensity.100',
            'enableAction' => true
        ],
        'rgb'        => [
            'Name'         => 'RGB Color',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~HexColor',
            'enableAction' => true
        ],
        'bg_rgb'     => [
            'Name'         => 'RGB Color Background',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~HexColor',
            'enableAction' => true
        ],
        'sat'        => [
            'Name'         => 'HSV Saturation',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~Intensity.100',
            'enableAction' => true
        ],
        'bg_sat'     => [
            'Name'         => 'HSV Saturation Background',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~Intensity.100',
            'enableAction' => true
        ],
        'ct'         => [
            'Name'         => 'White',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile0'     => 'Yeelight.WhiteTemp',
            'Profile1'     => 'Yeelight.WhiteTemp',
            'Profile2'     => 'Yeelight.WhiteTemp2',
            'enableAction' => true
        ],
        'bg_ct'      => [
            'Name'         => 'White Background',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile0'     => 'Yeelight.WhiteTemp',
            'Profile1'     => 'Yeelight.WhiteTemp',
            'Profile2'     => 'Yeelight.WhiteTemp2',
            'enableAction' => true
        ],
        'color_mode' => [
            'Name'         => 'Current mode',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile0'     => 'Yeelight.ModeColor',
            'Profile1'     => 'Yeelight.ModeColorWNight',
            'Profile2'     => 'Yeelight.ModeWNight',
            'enableAction' => true
        ],
        'bg_lmode'   => [
            'Name'         => 'Current mode Background',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile0'     => 'Yeelight.ModeColor',
            'Profile1'     => 'Yeelight.ModeColorWNight',
            'Profile2'     => 'Yeelight.ModeWNight',
            'enableAction' => true
        ],
        'hue'        => [
            'Name'    => 'HSV Hue',
            'Type'    => VARIABLETYPE_STRING,
            'Profile' => '~HTMLBox'
        ],
        'bg_hue'     => [
            'Name'    => 'HSV Hue Background',
            'Type'    => VARIABLETYPE_STRING,
            'Profile' => '~HTMLBox'
        ],
        'nl_br'      => [
            'Name'         => 'Brightness Nightlight',
            'Type'         => VARIABLETYPE_INTEGER,
            'Profile'      => '~Intensity.100',
            'enableAction' => false
        ]
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}');
        $this->RegisterPropertyBoolean('HUESlider', true);
        $this->RegisterPropertyBoolean('SetSmooth', false);
        $this->RegisterPropertyInteger('Mode', 0);

        $this->ReplyJSONData = [];
        $this->BufferIN = '';
        $this->Capabilities = [];
        $this->Propertys = [];
        $this->HUE = 0;
        $this->SAT = 0;
        $this->BG_HUE = 0;
        $this->BG_SAT = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/Yeelight' . $this->InstanceID);
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        parent::ApplyChanges();
        $this->ReplyJSONData = [];
        $this->BufferIN = '';
        $this->Capabilities = [];
        $this->Propertys = [];
        $this->RegisterProfileInteger('Yeelight.WhiteTemp', 'Intensity', '', ' %', 1700, 6500, 1, 0);
        $this->RegisterProfileInteger('Yeelight.WhiteTemp2', 'Intensity', '', ' %', 2700, 6500, 1, 0);
        $this->RegisterProfileIntegerEx('Yeelight.ModeColor', '', '', '', [
            [1, 'RGB', '', -1],
            [2, $this->Translate('White'), '', -1],
            [3, 'HSV', '', -1],
        ]);

        $this->RegisterProfileIntegerEx('Yeelight.ModeColorWNight', '', '', '', [
            [1, 'RGB', '', -1],
            [2, $this->Translate('White'), '', -1],
            [3, 'HSV', '', -1],
            [5, $this->Translate('Nightlight'), '', -1],
        ]);
        $this->RegisterProfileIntegerEx('Yeelight.ModeWNight', '', '', '', [
            [1, $this->Translate('White'), '', -1],
            [5, $this->Translate('Nightlight'), '', -1],
        ]);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean('HUESlider')) {
            $this->RegisterHook('/hook/Yeelight' . $this->InstanceID);
        } else {
            $this->UnregisterVariable('hue');
            $this->UnregisterVariable('sat');
            $this->UnregisterVariable('bg_hue');
            $this->UnregisterVariable('bg_sat');
        }
        $this->RegisterParent();

        // Wenn Parent aktiv, dann Anmeldung an der Hardware bzw. Datenabgleich starten
        if ($this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'power':
                $this->SetPower((bool) $Value);
                break;
            case 'bright':
                $this->SetBrightness((int) $Value);
                break;
            case 'rgb':
                $this->SetColor((int) $Value);
                break;
            case 'sat':
                $this->SetSaturation((int) $Value);
                break;
            case 'ct':
                $this->SetWhite((int) $Value);
                break;
            case 'bg_power':
                $this->SetBgPower((bool) $Value);
                break;
            case 'bg_bright':
                $this->SetBgBrightness((int) $Value);
                break;
            case 'bg_rgb':
                $this->SetBgColor((int) $Value);
                break;
            case 'bg_sat':
                $this->SetBgSaturation((int) $Value);
                break;
            case 'bg_ct':
                $this->SetBgWhite((int) $Value);
                break;
            case 'color_mode':
                $this->SetMode((int) $Value);
                break;
            case 'bg_lmode':
                $this->SetBgMode((int) $Value);
                break;
            default:
                echo sprintf($this->Translate('Invalid Ident: %s'), $Ident);
        }
    }

    //################# Instanz-Funktionen

    /**
     * Liest den Zustand des Gerätes und führt alle Statusvariablen nach.
     *
     * @return bool
     */
    public function RequestState(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->get_prop($this->Propertys);
        $Result = $this->Send($YeelightData);
        if ($Result === null) {
            return false;
        }

        foreach ($this->Propertys as $Index => $Property) {
            if ($Result[$Index] == '') {
                continue;
            }
            $this->SetStatusVariable($Property, $Result[$Index]);
        }
        return true;
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton.
     *
     * @param int $Temperature Weißton
     *
     * @return bool
     */
    public function SetWhite(int $Temperature): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetWhiteSmooth($Temperature, $Duration);
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton.
     *
     * @param int $Temperature Weißton
     *
     * @return bool
     */
    public function SetBgWhite(int $Temperature): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBgWhiteSmooth($Temperature, $Duration);
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton mit der in '$Duration' übergebenen Transitionzeit.
     *
     * @param int $Temperature Weißton
     * @param int $Duration    Transitionzeit
     *
     * @return bool
     */
    public function SetWhiteSmooth(int $Temperature, int $Duration): bool
    {
        if ($Duration <= 30) {
            $Params = [$Temperature, 'sudden', 0];
        } else {
            $Params = [$Temperature, 'smooth', $Duration];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_ct_abx($Params);
        return $this->Send($YeelightData);
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton mit der in '$Duration' übergebenen Transitionzeit.
     *
     * @param int $Temperature Weißton
     * @param int $Duration    Transitionzeit
     *
     * @return bool
     */
    public function SetBgWhiteSmooth(int $Temperature, int $Duration): bool
    {
        if ($Duration <= 30) {
            $Params = [$Temperature, 'sudden', 0];
        } else {
            $Params = [$Temperature, 'smooth', $Duration];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_ct_abx($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @param int $Red
     * @param int $Green
     * @param int $Blue
     *
     * @return bool
     */
    public function SetRGB(int $Red, int $Green, int $Blue): bool
    {
        if (($Red < 0) || ($Red > 255) || ($Green < 0) || ($Green > 255) || ($Blue < 0) || ($Blue > 255)) {
            trigger_error($this->Translate('Invalid parameter.'), E_USER_WARNING);
            return false;
        }
        $Color = ($Red << 16) + ($Green << 8) + $Blue;
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetColor($Color, $Duration);
    }

    /**
     * @param int $Red
     * @param int $Green
     * @param int $Blue
     *
     * @return bool
     */
    public function SetBgRGB(int $Red, int $Green, int $Blue): bool
    {
        if (($Red < 0) || ($Red > 255) || ($Green < 0) || ($Green > 255) || ($Blue < 0) || ($Blue > 255)) {
            trigger_error($this->Translate('Invalid parameter.'), E_USER_WARNING);
            return false;
        }
        $Color = ($Red << 16) + ($Green << 8) + $Blue;
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBgColor($Color, $Duration);
    }

    /**
     * @param int $Red
     * @param int $Green
     * @param int $Blue
     * @param int $Duration
     *
     * @return bool
     */
    public function SetRGBSmooth(int $Red, int $Green, int $Blue, int $Duration): bool
    {
        if (($Red < 0) || ($Red > 255) || ($Green < 0) || ($Green > 255) || ($Blue < 0) || ($Blue > 255)) {
            trigger_error($this->Translate('Invalid parameter.'), E_USER_WARNING);
            return false;
        }
        $Color = ($Red << 16) + ($Green << 8) + $Blue;
        return $this->SetColor($Color, $Duration);
    }

    /**
     * @param int $Red
     * @param int $Green
     * @param int $Blue
     * @param int $Duration
     *
     * @return bool
     */
    public function SetBgRGBSmooth(int $Red, int $Green, int $Blue, int $Duration): bool
    {
        if (($Red < 0) || ($Red > 255) || ($Green < 0) || ($Green > 255) || ($Blue < 0) || ($Blue > 255)) {
            trigger_error($this->Translate('Invalid parameter.'), E_USER_WARNING);
            return false;
        }
        $Color = ($Red << 16) + ($Green << 8) + $Blue;
        return $this->SetBgColor($Color, $Duration);
    }

    /**
     * @param int $HUE
     * @param int $Saturation
     *
     * @return bool
     */
    public function SetHSV(int $HUE, int $Saturation): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetHSVSmooth($HUE, $Saturation, $Duration);
    }

    /**
     * @param int $HUE
     * @param int $Saturation
     *
     * @return bool
     */
    public function SetBgHSV(int $HUE, int $Saturation): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBgHSVSmooth($HUE, $Saturation, $Duration);
    }

    /**
     * @param int $HUE
     * @param int $Saturation
     * @param int $Duration
     *
     * @return bool
     */
    public function SetHSVSmooth(int $HUE, int $Saturation, int $Duration): bool
    {
        if (!$this->ReadPropertyBoolean('HUESlider')) {
            trigger_error($this->Translate('Device not support this command.'), E_USER_WARNING);
            return false;
        }
        if (($HUE < 0) || ($HUE > 359)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'HUE'), E_USER_WARNING);
            return false;
        }
        if (($Saturation < 1) || ($Saturation > 100)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_WARNING);
            return false;
        }
        if ($Duration < 30) {
            $Params = [$HUE, $Saturation, 'sudden', 0];
        } else {
            $Params = [$HUE, $Saturation, 'smooth', $Duration];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_hsv($Params);
        $Result = $this->Send($YeelightData);
        if ($Result) {
            $this->HUE = $HUE;
            $this->SAT = $Saturation;
        }
        return $Result;
    }

    /**
     * @param int $HUE
     * @param int $Saturation
     * @param int $Duration
     *
     * @return bool
     */
    public function SetBgHSVSmooth(int $HUE, int $Saturation, int $Duration): bool
    {
        if (!$this->ReadPropertyBoolean('HUESlider')) {
            trigger_error($this->Translate('Device not support this command.'), E_USER_WARNING);
            return false;
        }
        if (($HUE < 0) || ($HUE > 359)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'HUE'), E_USER_WARNING);
            return false;
        }
        if (($Saturation < 1) || ($Saturation > 100)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Value'), E_USER_WARNING);
            return false;
        }
        if ($Duration < 30) {
            $Params = [$HUE, $Saturation, 'sudden', 0];
        } else {
            $Params = [$HUE, $Saturation, 'smooth', $Duration];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_hsv($Params);
        $Result = $this->Send($YeelightData);
        if ($Result) {
            $this->BG_HUE = $HUE;
            $this->BG_SAT = $Saturation;
        }
        return $Result;
    }

    /**
     * @param int $Level
     *
     * @return bool
     */
    public function SetBrightness(int $Level): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBrightnessSmooth($Level, $Duration);
    }

    /**
     * @param int $Level
     *
     * @return bool
     */
    public function SetBgBrightness(int $Level): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBgBrightnessSmooth($Level, $Duration);
    }

    /**
     * @param int $Level
     * @param int $Duration
     *
     * @return bool
     */
    public function SetBrightnessSmooth(int $Level, int $Duration): bool
    {
        if (($Level < 0) || ($Level > 100)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Level'), E_USER_WARNING);
            return false;
        }
        if ($Level == 0) {
            $Level = 1;
        }
        if ($Level > 100) {
            $Level = 100;
        }
        if ($Duration < 30) {
            $Params = [$Level, 'sudden', 0];
        } else {
            $Params = [$Level, 'smooth', $Duration];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_bright($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @param int $Level
     * @param int $Duration
     *
     * @return bool
     */
    public function SetBgBrightnessSmooth(int $Level, int $Duration): bool
    {
        if (($Level < 0) || ($Level > 100)) {
            trigger_error(sprintf($this->Translate('%s out of range.'), 'Level'), E_USER_WARNING);
            return false;
        }
        if ($Level == 0) {
            $Level = 1;
        }
        if ($Level > 100) {
            $Level = 100;
        }
        if ($Duration < 30) {
            $Params = [$Level, 'sudden', 0];
        } else {
            $Params = [$Level, 'smooth', $Duration];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_bright($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @param bool $Value
     *
     * @return bool
     */
    public function SetPower(bool $Value): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetPowerSmooth($Value, $Duration);
    }

    /**
     * @param bool $Value
     *
     * @return bool
     */
    public function SetBgPower(bool $Value): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBgPowerSmooth($Value, $Duration);
    }

    /**
     * @param bool $Value
     * @param int  $Duration
     *
     * @return bool
     */
    public function SetPowerSmooth(bool $Value, int $Duration): bool
    {
        if ($Duration < 30) {
            $Params = [$Value ? 'on' : 'off', 'sudden', 0];
        } else {
            $Params = [$Value ? 'on' : 'off', 'smooth', $Duration];
        }

        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_power($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @param bool $Value
     * @param int  $Duration
     *
     * @return bool
     */
    public function SetBgPowerSmooth(bool $Value, int $Duration): bool
    {
        if ($Duration < 30) {
            $Params = [$Value ? 'on' : 'off', 'sudden', 0];
        } else {
            $Params = [$Value ? 'on' : 'off', 'smooth', $Duration];
        }

        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_power($Params);
        return $this->Send($YeelightData);
    }

    public function SetMode(int $Mode): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetModeSmooth($Mode, $Duration);
    }

    public function SetModeSmooth(int $Mode, int $Duration): bool
    {
        if ($Duration < 30) {
            $Params = ['on', 'sudden', 0, $Mode];
        } else {
            $Params = ['on', 'smooth', $Duration, $Mode];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_power($Params);
        return $this->Send($YeelightData);
    }

    public function SetBgMode(int $Mode): bool
    {
        $Duration = ($this->ReadPropertyBoolean('SetSmooth') ? 500 : 0);
        return $this->SetBgModeSmooth($Mode, $Duration);
    }

    public function SetBgModeSmooth(int $Mode, int $Duration): bool
    {
        $Power = $this->GetValue('bg_power');
        if ($Duration < 30) {
            $Params = [$Power ? 'on' : 'off', 'sudden', 0, $Mode];
        } else {
            $Params = [$Power ? 'on' : 'off', 'smooth', $Duration, $Mode];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_power($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function SetToogle(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->toogle();
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function SetBgToogle(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_toogle();
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function SetToogleBoth(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->dev_toogle();
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function SetDefault(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_default();
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function SetBgDefault(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_default();
        return $this->Send($YeelightData);
    }

    /*
     * [duration, mode, value, brightness]:
      Duration: Gradual change time or sleep time, in milliseconds,
      minimum value 50.
      Mode: 1 – color, 2 – color temperature, 7 – sleep.
      Value: RGB value when mode is 1, CT value when mode is 2,
      Ignored when mode is 7.
      Brightness: Brightness value, -1 or 1 ~ 100. Ignored when mode is 7.
      When this value is -1, brightness in this tuple is ignored (only color or CT change takes
      effect).
     */

    /**
     * @param int    $Loops
     * @param int    $RecoverState
     * @param string $Flow
     *
     * @return bool
     */
    public function StartColorFlow(int $Loops, int $RecoverState, string $Flow): bool
    {
        $FlowArray = json_decode($Flow);
        if ($FlowArray === null) {
            trigger_error(sprintf($this->Translate('%s is invalid.'), 'Flow'), E_USER_WARNING);
            return false;
        }
        $FlowData = array_values($FlowArray);
        if ((count($FlowData) % 4) != 0) {
            trigger_error(sprintf($this->Translate('%s is invalid.'), 'Flow'), E_USER_WARNING);
            return false;
        }
        $FlowString = implode(',', $FlowData);
        $Params = [$Loops, $RecoverState, $FlowString];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->start_cf($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @param int    $Loops
     * @param int    $RecoverState
     * @param string $Flow
     *
     * @return bool
     */
    public function StartBgColorFlow(int $Loops, int $RecoverState, string $Flow): bool
    {
        $FlowArray = json_decode($Flow);
        if ($FlowArray === null) {
            trigger_error(sprintf($this->Translate('%s is invalid.'), 'Flow'), E_USER_WARNING);
            return false;
        }
        $FlowData = array_values($FlowArray);
        if ((count($FlowData) % 4) != 0) {
            trigger_error(sprintf($this->Translate('%s is invalid.'), 'Flow'), E_USER_WARNING);
            return false;
        }
        $FlowString = implode(',', $FlowData);
        $Params = [$Loops, $RecoverState, $FlowString];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_start_cf($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function StopColorFlow(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->stop_cf();
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function StopBgColorFlow(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_stop_cf();
        return $this->Send($YeelightData);
    }

    /**
     * @param int $Minutes
     *
     * @return bool
     */
    public function SetSleep(int $Minutes): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->cron_add([0, $Minutes]);
        return $this->Send($YeelightData);
    }

    /**
     * @return mixed
     */
    public function GetSleep()
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->cron_get([0]);
        $Result = $this->Send($YeelightData);
        if ($Result !== false) {
            return $Result['delay'];
        }
        return false;
    }

    /**
     * @return bool
     */
    public function DelSleep(): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->cron_del([0]);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function IncreaseBright(): bool
    {
        $Params = ['increase', 'bright'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function DecreaseBright(): bool
    {
        $Params = ['decrease', 'bright'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function IncreaseWhiteTemp(): bool
    {
        $Params = ['increase', 'ct'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function DecreaseWhiteTemp(): bool
    {
        $Params = ['decrease', 'ct'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function CircleColor(): bool
    {
        $Params = ['circle', 'color'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function IncreaseBgBright(): bool
    {
        $Params = ['increase', 'bright'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function DecreaseBgBright(): bool
    {
        $Params = ['decrease', 'bright'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function IncreaseBgWhiteTemp(): bool
    {
        $Params = ['increase', 'ct'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function DecreaseBgWhiteTemp(): bool
    {
        $Params = ['decrease', 'ct'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @return bool
     */
    public function CircleBgColor(): bool
    {
        $Params = ['circle', 'color'];
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_adjust($Params);
        return $this->Send($YeelightData);
    }

    /**
     * @param string $Name
     *
     * @return bool
     */
    public function SetName(string $Name): bool
    {
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_name([$Name]);
        return $this->Send($YeelightData);
    }

    /**
     * Empfängt Daten vom Parent.
     *
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     * @result boolean True wenn Daten verarbeitet wurden, sonst false.
     */
    public function ReceiveData($JSONString)
    {
        $data = utf8_decode(json_decode($JSONString)->Buffer);
        $head = $this->BufferIN;
        $Data = $head . $data;
        $JSONLines = explode("\r\n", $Data);
        $this->BufferIN = array_pop($JSONLines);

        foreach ($JSONLines as $JSON) {
            $this->SendDebug('Receive', $JSON, 0);
            $YeelightData = new \Yeelight\YeelightRPC_Data();
            if (!$YeelightData->CreateFromJSONString($JSON)) {
                $this->SendDebug('Receive', $YeelightData, 0);
                continue;
            }
            if ($YeelightData->Typ == \Yeelight\YeelightRPC_Data::$isResult) { //Reply
                $this->SendQueueUpdate($YeelightData->Id, $YeelightData);
            } elseif ($YeelightData->Typ == \Yeelight\YeelightRPC_Data::$isEvent) { //Event
                $this->SendDebug('Event', $YeelightData, 0);
                $this->Decode($YeelightData);
            }
        }
        return true;
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->RegisterParent();
    }

    protected function RegisterParent()
    {
        $IOId = $this->IORegisterParent();
        if ($IOId > 0) {
            $this->Host = IPS_GetProperty($this->ParentID, 'Host');
            $this->SetSummary(IPS_GetProperty($IOId, 'Host'));
            return;
        }
        $this->Host = '';
        $this->SetSummary(('none'));
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        if ($State == IS_ACTIVE) {
            $this->GetCapabilities();
            $this->LogMessage('Propertys read:' . implode(' ', $this->Propertys), KL_NOTIFY);
            $this->RequestState();
            return;
        }
    }

    //################# Send / Receive

    /**
     * Error-Handler für die Send-Routine. Gibt die Fehlermeldung an den Aufrufer als Klartext zurück.
     *
     * @param type $errno
     * @param type $errstr
     */
    protected function ModulErrorHandler($errno, $errstr)
    {
        $this->SendDebug('ERROR', $errstr, 0);
        echo $errstr . PHP_EOL;
    }

    /**
     * Versendet ein RPC-Objekt und empfängt die Antwort.
     *
     * @param \Yeelight\YeelightRPC_Data $YeelightData Das Objekt welches versendet werden soll.
     * @result mixed Enthält die Antwort auf das Versendete Objekt oder NULL im Fehlerfall.
     */
    protected function Send(\Yeelight\YeelightRPC_Data $YeelightData)
    {
        set_error_handler([$this, 'ModulErrorHandler']);

        try {
            if (!$this->HasActiveParent()) {
                throw new Exception('Instance has no active parent.', E_USER_WARNING);
            }
            if (count($this->Capabilities) == 0) {
                $this->LogMessage($this->Translate('Capabilities of device are unknown. Please check your Firewall.'), KL_WARNING);
            } else {
                if (!in_array($YeelightData->Method, $this->Capabilities)) {
                    throw new Exception('Device not support this command.', E_USER_WARNING);
                }
            }

            $this->SendDebug('Send', $YeelightData, 0);
            $this->SendQueuePush($YeelightData->Id);
            $SendData = new stdClass();
            $SendData->DataID = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
            $YeelightJSON = $YeelightData->ToJSONString();
            $SendData->Buffer = utf8_encode($YeelightJSON . "\r\n");
            $this->SendDebug('Send', $YeelightJSON, 0);
            $this->SendDataToParent(json_encode($SendData));
            $ReplyYeelightData = $this->WaitForResponse($YeelightData->Id);

            if ($ReplyYeelightData === false) {
                throw new Exception('No answer from Device', E_USER_WARNING);
            }

            $ret = $ReplyYeelightData->GetResult();
            if (is_a($ret, 'YeelightRPCException')) {
                throw $ret;
            }
            $this->SendDebug('Result', $ReplyYeelightData, 0);
            restore_error_handler();
            if (count($ret) == 1) {
                return $ret[0] == 'ok';
            }
            return $ret;
        } catch (\Yeelight\YeelightRPCException $ex) {
            $this->SendDebug('Result', $ex, 0);
            trigger_error('Error (' . $ex->getCode() . '): ' . $ex->getMessage(), E_USER_WARNING);
        } catch (Exception $ex) {
            $this->SendDebug('Result', $ex->getMessage(), 0);
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        restore_error_handler();
        return false;
    }

    //################# Webhook

    /**
     * Interne Funktion des SDK.
     */
    protected function ProcessHookdata()
    {
        if (isset($_GET['script'])) {
            switch ($_GET['script']) {
                case 'HueSliderEvents':
                    $this->SendJSFile('SliderEvents.js');
                    return;
                case 'HueSlider':
                    $this->SendJSFile('Slider.js', 'var InstanceID =' . $this->InstanceID . ';');
                    return;
                case 'HueSliderRequestAction':
                    $this->SendJSFile('SliderRequestAction.js');
                    return;
                case 'HueSliderEvents':
                    $this->SendJSFile('SliderBgEvents.js');
                    return;
                case 'HueSlider':
                    $this->SendJSFile('SliderBg.js', 'var InstanceID =' . $this->InstanceID . ';');
                    return;
                case 'HueSliderRequestAction':
                    $this->SendJSFile('SliderBgRequestAction.js');
                    return;
            }
        }
        if (isset($_GET['ident'])) {
            switch ($_GET['ident']) {
                case 'hue':
                    if ($_GET['action'] == 'GetValue') {
                        http_response_code(200);
                        header('Connection: close');
                        header('Server: Symcon ' . IPS_GetKernelVersion());
                        header('X-Powered-By: Hook Reverse Proxy');
                        header('Cache-Control: no-cache');
                        header('Content-Type: text/plain');
                        echo 'OK,' . $this->InstanceID . ',' . $this->HUE;
                        return;
                    }
                    if ($_GET['action'] == 'SetValue') {
                        http_response_code(200);
                        header('Connection: close');
                        header('Server: Symcon ' . IPS_GetKernelVersion());
                        header('X-Powered-By: Hook Reverse Proxy');
                        header('Cache-Control: no-cache');
                        header('Content-Type: text/plain');
                        if ($this->SetHUE((int) $_GET['value'])) {
                            echo 'OK';
                        }
                        return;
                    }
                    break;
                case 'bg_hue':
                    if ($_GET['action'] == 'GetValue') {
                        http_response_code(200);
                        header('Connection: close');
                        header('Server: Symcon ' . IPS_GetKernelVersion());
                        header('X-Powered-By: Hook Reverse Proxy');
                        header('Cache-Control: no-cache');
                        header('Content-Type: text/plain');
                        echo 'OK,Bg' . $this->InstanceID . ',' . $this->BG_HUE;
                        return;
                    }
                    if ($_GET['action'] == 'SetValue') {
                        http_response_code(200);
                        header('Connection: close');
                        header('Server: Symcon ' . IPS_GetKernelVersion());
                        header('X-Powered-By: Hook Reverse Proxy');
                        header('Cache-Control: no-cache');
                        header('Content-Type: text/plain');
                        if ($this->SetBgHUE((int) $_GET['value'])) {
                            echo 'OK';
                        }
                        return;
                    }
            }
        }
        $this->SendFileNotFound();
        return;
    }

    //################# Helper

    /**
     * Sendet set_rgb an das Gerät.
     *
     * @param int $Color    RGB Farbe
     * @param int $Duration Transitionzeit
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetColor(int $Color, int $Duration = 0): bool
    {
        if ($Duration < 30) {
            $Params = [$Color, 'sudden', 0];
        } else {
            $Params = [$Color, 'smooth', 500];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->set_rgb($Params);
        $Result = $this->Send($YeelightData);
        if ($Result === null) {
            return false;
        }
        return $Result[0] === 'ok';
    }

    /**
     * Sendet bg_set_rgb an das Gerät.
     *
     * @param int $Color    RGB Farbe
     * @param int $Duration Transitionzeit
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetBgColor(int $Color, int $Duration = 0): bool
    {
        if ($Duration < 30) {
            $Params = [$Color, 'sudden', 0];
        } else {
            $Params = [$Color, 'smooth', 500];
        }
        $YeelightData = new \Yeelight\YeelightRPC_Data();
        $YeelightData->bg_set_rgb($Params);
        $Result = $this->Send($YeelightData);
        if ($Result === null) {
            return false;
        }
        return $Result[0] === 'ok';
    }

    /**
     * Wenn nur neuer Sättigungswert übertragen werden soll.
     *
     * @param int $Saturation
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetSaturation(int $Saturation): bool
    {
        $Hue = $this->HUE;
        return $this->SetHSV($Hue, $Saturation);
    }

    /**
     * Wenn nur neuer Sättigungswert übertragen werden soll.
     *
     * @param int $Saturation
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetBgSaturation(int $Saturation): bool
    {
        $Hue = $this->BG_HUE;
        return $this->SetBgHSV($Hue, $Saturation);
    }

    /**
     * Wenn nur neuer HUE Wert übertragen werden soll.
     *
     * @param int $HUE
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetHUE(int $HUE): bool
    {
        $Saturation = $this->SAT;
        return $this->SetHSV($HUE, $Saturation);
    }

    /**
     * Wenn nur neuer HUE Wert übertragen werden soll.
     *
     * @param int $HUE
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetBgHUE(int $HUE): bool
    {
        $Saturation = $this->BG_SAT;
        return $this->SetBgHSV($HUE, $Saturation);
    }

    //################# GetCapabilities

    /**
     * Parse HTTP-Header.
     *
     * @param string $Data HTTP-Header
     *
     * @return array Assoziiertes Array alle Header-Felder
     */
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

    /**
     * Fragt alle Fähigkeiten und Eigenschaften des Gerätes ab und speichert diese im Buffer.
     */
    private function GetCapabilities()
    {
        $this->Capabilities = [];
        $this->Propertys = [];
        if ($this->Host == '') {
            return;
        }
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return;
        }
        socket_bind($socket, '0', 1983);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        $message = [
            'M-SEARCH * HTTP/1.1',
            'HOST: ' . $this->Host . ':1982',
            'MAN: "ssdp:discover"',
            'ST: wifi_bulb'
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";
        $this->SendDebug('Ask capabilities', $SendData, 0);
        if (@socket_sendto($socket, $SendData, strlen($SendData), 0, $this->Host , 1982) === false) {
            return;
        }
        usleep(100000);
        $i = 10;
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
            if (array_key_exists('support', $Data)) {
                $this->Capabilities = explode(' ', $Data['support']);
                $this->SendDebug('Got capabilities', $Data['support'], 0);
                $index = array_keys(array_keys($Data), 'support');
                $Propertys = array_keys(array_slice($Data, $index[0] + 1));
                if (in_array('name', $Propertys)) {
                    unset($Propertys[array_keys($Propertys, 'name')[0]]);
                }
                $this->Propertys = $Propertys;
                $this->SendDebug('Got propertys', implode(' ', $Propertys), 0);
                break;
            }
        }
        socket_close($socket);
        return;
    }

    //################# Decode / StatusVariables

    /**
     * Decodiert ein Event und führt die Statusvariablen nach.
     *
     * @param \Yeelight\YeelightRPC_Data $YeelightData
     */
    private function Decode(\Yeelight\YeelightRPC_Data $YeelightData)
    {
        if ($YeelightData->Method != 'props') {
            $this->LogMessage($this->Translate('Invalid event method received.'), KL_WARNING);
            return;
        }
        foreach ($YeelightData->Params as $Ident => $Value) {
            $this->SetStatusVariable($Ident, $Value);
        }
    }

    /**
     * @param string $Ident Ident der Statusvariable
     * @param mixed  $Value Neuer Wert der Statusvariable
     */
    private function SetStatusVariable(string $Ident, $Value)
    {
        if (!array_key_exists($Ident, self::$DataPoints)) {
            $this->LogMessage(sprintf($this->Translate('Property %s actually not supported.'), $Ident), KL_MESSAGE);
            return;
        }
        $StatusVariable = self::$DataPoints[$Ident];
        if ($Ident == 'hue') {
            $this->HUE = (int) $Value;
            if ($this->ReadPropertyBoolean('HUESlider')) {
                $HueSlider = @$this->GetIDForIdent($Ident);
                if ($HueSlider == false) {
                    $this->MaintainVariable($Ident, $this->Translate($StatusVariable['Name']), $StatusVariable['Type'], $StatusVariable['Profile'], 0, true);
                    $Value = '<script src="hook/Yeelight' . $this->InstanceID . '?script=HueSliderRequestAction"></script>';
                    $Value .= '<script src="hook/Yeelight' . $this->InstanceID . '?script=HueSliderEvents"></script>';
                    $Value .= '<script src="hook/Yeelight' . $this->InstanceID . '?script=HueSlider"></script>';
                    $this->SetValue('hue', $Value);
                }
            }
            return;
        }
        if ($Ident == 'bg_hue') {
            $this->HUE = (int) $Value;
            if ($this->ReadPropertyBoolean('HUESlider')) {
                $HueSlider = @$this->GetIDForIdent($Ident);
                if ($HueSlider == false) {
                    $this->MaintainVariable($Ident, $this->Translate($StatusVariable['Name']), $StatusVariable['Type'], $StatusVariable['Profile'], 0, true);
                    $Value = '<script src="hook/Yeelight' . $this->InstanceID . '?script=HueSliderBgRequestAction"></script>';
                    $Value .= '<script src="hook/Yeelight' . $this->InstanceID . '?script=HueSliderBgEvents"></script>';
                    $Value .= '<script src="hook/Yeelight' . $this->InstanceID . '?script=HueSliderBg"></script>';
                    $this->SetValue('bg_hue', $Value);
                }
            }
            return;
        }
        if ($Ident == 'sat') {
            if (!$this->ReadPropertyBoolean('HUESlider')) {
                return;
            }
            $this->SAT = (int) $Value;
        }
        if ($Ident == 'bg_sat') {
            if (!$this->ReadPropertyBoolean('HUESlider')) {
                return;
            }
            $this->BG_SAT = (int) $Value;
        }

        if ($Ident == 'active_mode') {
            $Ident = 'color_mode';
            if ((int) $Value == 1) {
                $Value = 5;
            } else {
                $Value = 2;
            }
        }

        if (($Ident == 'color_mode') || ($Ident == 'bg_lmode') || ($Ident == 'ct') || ($Ident == 'bg_ct')) {
            $StatusVariable['Profile'] = $StatusVariable['Profile' . $this->ReadPropertyInteger('Mode')];
        }

        $this->MaintainVariable($Ident, $this->Translate($StatusVariable['Name']), $StatusVariable['Type'], $StatusVariable['Profile'], 0, true);
        if ($StatusVariable['enableAction']) {
            $this->EnableAction($Ident);
        }
        if (array_key_exists('Mapping', $StatusVariable)) {
            $Value = $StatusVariable['Mapping'][$Value];
        }
        $this->SetValue($Ident, $Value);
    }

    //################# SENDQUEUE

    /**
     * Wartet auf eine RPC-Antwort.
     *
     * @param int $Id Die RPC-ID auf die gewartet wird.
     * @result mixed Enthält ein Kodi_RPC_Data-Objekt mit der Antwort, oder false bei einem Timeout.
     */
    private function WaitForResponse($Id)
    {
        for ($i = 0; $i < 1000; $i++) {
            $ret = $this->ReplyJSONData;
            if (!array_key_exists(intval($Id), $ret)) {
                return false;
            }
            if ($ret[$Id] != '') {
                return $this->SendQueuePop($Id);
            }
            IPS_Sleep(1);
        }
        $this->SendQueueRemove($Id);
        return false;
    }

    /**
     * Fügt eine Anfrage in die SendQueue ein.
     *
     * @param int $Id die RPC-ID des versendeten RPC-Objektes.
     */
    private function SendQueuePush(int $Id)
    {
        if (!$this->lock('ReplyJSONData')) {
            throw new Exception('ReplyJSONData is locked', E_USER_WARNING);
        }
        $data = $this->ReplyJSONData;
        $data[$Id] = '';
        $this->ReplyJSONData = $data;
        $this->unlock('ReplyJSONData');
    }

    /**
     * Fügt eine RPC-Antwort in die SendQueue ein.
     *
     * @param int                        $Id           die RPC-ID des empfangenen Objektes.
     * @param \Yeelight\YeelightRPC_Data $YeelightData Das empfangene RPC-Result.
     */
    private function SendQueueUpdate(int $Id, \Yeelight\YeelightRPC_Data $YeelightData)
    {
        if (!$this->lock('ReplyJSONData')) {
            throw new Exception('ReplyJSONData is locked', E_USER_WARNING);
        }
        $data = $this->ReplyJSONData;
        if (array_key_exists(intval($Id), $data)) {
            $data[$Id] = $YeelightData;
        }
        $this->ReplyJSONData = $data;
        $this->unlock('ReplyJSONData');
    }

    /**
     * Holt eine RPC-Antwort aus der SendQueue.
     *
     * @param int $Id die RPC-ID des empfangenen Objektes.
     *
     * @return \Yeelight\YeelightRPC_Data Das empfangene RPC-Result.
     */
    private function SendQueuePop(int $Id)
    {
        $data = $this->ReplyJSONData;
        $Result = $data[$Id];
        $this->SendQueueRemove($Id);
        return $Result;
    }

    /**
     * Löscht einen RPC-Eintrag aus der SendQueue.
     *
     * @param int $Id Die RPC-ID des zu löschenden Objektes.
     */
    private function SendQueueRemove(int $Id)
    {
        if (!$this->lock('ReplyJSONData')) {
            throw new Exception('ReplyJSONData is locked', E_USER_WARNING);
        }
        $data = $this->ReplyJSONData;
        unset($data[$Id]);
        $this->ReplyJSONData = $data;
        $this->unlock('ReplyJSONData');
    }

    /**
     * Liefert ein JavaScript-File als HTTP-Response.
     *
     * @param string $File    Dateiname des Javascript-File
     * @param string $PreCode Dynamischer JS-Code welcher vor dem File ausgeliefert wird.
     */
    private function SendJSFile(string $File, string $PreCode = '')
    {
        http_response_code(200);
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: Hook Reverse Proxy');

        header('Content-Type: application/javascript');
        if ($File != '') {
            header('Cache-Control: no-cache');
            echo $PreCode;
        } else {
            header('Cache-Control: max-age=3600');
        }
        readfile(__DIR__ . '/../libs/' . $File);
    }

    /**
     * Sendet einen HTTP 404 File not Found als HTTP-Response.
     */
    private function SendFileNotFound()
    {
        http_response_code(404);
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: Hook Reverse Proxy');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Content-Type: text/plain');
        echo 'File not found!';
    }
}
