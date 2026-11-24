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
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
require_once __DIR__ . '/../libs/DebugHelper.php';  // SendDebug für RPC-Objekte
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace YeelightDevice {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
require_once __DIR__ . '/../libs/YeelightRPC.php';  // Konstanten, Variablen und RPC-Klassen

use Yeelight\DataPoints;
use Yeelight\Duration;
use Yeelight\GUID;
use Yeelight\Hook;
use Yeelight\Locks;
use Yeelight\Network;
use Yeelight\Property;
use Yeelight\RPC_Data;
use Yeelight\RPCException;
use Yeelight\Variables;

/**
 * YeelightDevice Klasse für die Anbindung eines Yeelight Gerätes über einen Client Socket.
 *
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       2.16
 *
 * @example <b>Ohne</b>
 * @property int $ParentID
 * @property array $Capabilities Vom Gerät per SSDP gemeldete Methoden
 * @property array $Propertys Vom Gerät unterstützte Properties
 * @property string $BufferIN Receive-Buffer
 * @property array $ReplyJSONData Send/Receive Buffer (Id => '' oder \Yeelight\RPC_Data)
 * @property string $Host IP-Adresse bzw. Hostname des Gerätes (vom Client Socket)
 * @property int $HUE Letzter Farbton
 * @property int $SAT Letzte Sättigung
 * @property int $BG_HUE Letzter Farbton Hintergrund
 * @property int $BG_SAT Letzte Sättigung Hintergrund
 * @property bool $BgPower Letzter Zustand Hintergrund
 * @property int $Bright Letzte Helligkeit (für v der HSV-Farbe)
 * @property int $BgBright Letzte Helligkeit Hintergrund (für v der HSV-Farbe)
 * @property string $ReportedDeviceId Vom Gerät per SSDP gemeldete Geräte-ID
 * @property int $ConnectionState
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method int IORegisterParent()
 * @method int FindIDForIdent(string $Ident)
 * @method void UnregisterProfile(string $Name)
 * @method bool lock(string $ident)
 * @method void unlock(string $ident)
 */
class YeelightDevice extends IPSModuleStrict
{
    use \YeelightDevice\BufferHelper,
        \YeelightDevice\VariableHelper,
        \YeelightDevice\VariableProfileHelper,
        \YeelightDevice\Semaphore,
        \Yeelight\DebugHelper,
        \YeelightDevice\InstanceStatus {
            \YeelightDevice\InstanceStatus::MessageSink as IOMessageSink;
            \YeelightDevice\InstanceStatus::RegisterParent as IORegisterParent;
            \YeelightDevice\InstanceStatus::RequestAction as IORequestAction;
        }

    public const isDisconnected = 0;
    public const isConnected = 1;
    public const isReconnecting = 2;

    /** Version, mit der die veralteten Funktionsnamen entfernt werden. */
    private const DeprecatedRemoveVersion = '2.20';

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean(Property::HUESlider, false);
        $this->RegisterPropertyBoolean(Property::SetSmooth, false);
        $this->RegisterPropertyInteger(Property::Mode, 0);
        $this->RegisterPropertyString(Property::DeviceId, '');
        $this->ReplyJSONData = [];
        $this->ReportedDeviceId = '';
        $this->BufferIN = '';
        $this->Capabilities = [];
        $this->Propertys = [];
        $this->Host = '';
        $this->HUE = 1;
        $this->SAT = 1;
        $this->BG_HUE = 1;
        $this->BG_SAT = 1;
        $this->BgPower = false;
        $this->Bright = 100;
        $this->BgBright = 100;
        $this->ParentID = 0;
        $this->ConnectionState = self::isDisconnected;
    }

    /**
     * GetCompatibleParents
     *
     * @return string
     */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'require', 'moduleIDs' => [GUID::ClientSocket]]);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        parent::ApplyChanges();
        $this->ReplyJSONData = [];
        $this->BufferIN = '';
        $this->Capabilities = [];
        $this->Propertys = [];
        $this->Host = '';
        $this->ReportedDeviceId = '';
        $this->ConnectionState = self::isDisconnected;
        // Profile älterer Versionen entfernen
        $this->UnregisterProfile('Yeelight.WhiteTemp');
        $this->UnregisterProfile('Yeelight.WhiteTemp2');
        $this->UnregisterProfile('Yeelight.ModeColor');
        $this->UnregisterProfile('Yeelight.ModeColorWNight');
        $this->UnregisterProfile('Yeelight.ModeWNight');
        if (!$this->ReadPropertyBoolean(Property::HUESlider)) {
            // Nur die Slider-Variablen (JavaScript) gehören zur Option, Sättigung bleibt erhalten.
            $this->UnregisterVariable(DataPoints::HUE);
            $this->UnregisterVariable(DataPoints::BgHUE);
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        $this->RegisterWebHook();
        $this->RegisterParent();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
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
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            case DataPoints::Power:
                $this->SetPower((bool) $Value);
                break;
            case DataPoints::Bright:
                $this->SetBrightness((int) $Value);
                break;
            case DataPoints::RGB:
                $this->SetColor((int) $Value, $this->DefaultDuration());
                break;
            case DataPoints::HSV:
                $HSV = $this->DecodeHSVValue($Value);
                if (($HSV !== false) && $this->SetHSV($HSV[0], $HSV[1]) && ($HSV[2] !== null) && ($HSV[2] != $this->Bright)) {
                    $this->SetBrightness($HSV[2]);
                }
                break;
            case DataPoints::DelayOff:
                if ((int) $Value > 0) {
                    $this->SetSleep((int) $Value);
                } else {
                    $this->DelSleep();
                }
                break;
            case DataPoints::SAT:
                $this->SetHSV($this->HUE, (int) $Value);
                break;
            case DataPoints::CT:
                $this->SetWhite((int) $Value);
                break;
            case DataPoints::ColorMode:
                $this->SetMode((int) $Value);
                break;
            case DataPoints::BgPower:
                $this->SetBgPower((bool) $Value);
                break;
            case DataPoints::BgBright:
                $this->SetBgBrightness((int) $Value);
                break;
            case DataPoints::BgRGB:
                $this->SetBgColor((int) $Value, $this->DefaultDuration());
                break;
            case DataPoints::BgHSV:
                $HSV = $this->DecodeHSVValue($Value);
                if (($HSV !== false) && $this->SetBgHSV($HSV[0], $HSV[1]) && ($HSV[2] !== null) && ($HSV[2] != $this->BgBright)) {
                    $this->SetBgBrightness($HSV[2]);
                }
                break;
            case DataPoints::BgSAT:
                $this->SetBgHSV($this->BG_HUE, (int) $Value);
                break;
            case DataPoints::BgCT:
                $this->SetBgWhite((int) $Value);
                break;
            case DataPoints::BgColorMode:
                $this->SetBgMode((int) $Value);
                break;
            default:
                echo sprintf($this->Translate('Invalid Ident: %s'), $Ident) . PHP_EOL;
        }
    }

    /**
     * Interne Funktion des SDK.
     * Bietet die vom Gerät gemeldete Geräte-ID zur Übernahme an, wenn sie von der Eigenschaft abweicht.
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $ReportedDeviceId = preg_replace('/[^0-9A-Za-z]/', '', $this->ReportedDeviceId);
        if (($ReportedDeviceId !== '') && (strcasecmp($ReportedDeviceId, $this->ReadPropertyString(Property::DeviceId)) != 0)) {
            $Form['elements'][1]['visible'] = true;
            $Form['elements'][1]['items'][0]['caption'] = sprintf($this->Translate('Device reports the ID %s'), $ReportedDeviceId);
            $Form['elements'][1]['items'][1]['onClick'] = "IPS_UpdateFormField('" . Property::DeviceId . "', 'value', '" . $ReportedDeviceId . "', \$id);";
        }
        return json_encode($Form);
    }

    //################# Instanz-Funktionen

    /**
     * Liest den Zustand des Gerätes und führt alle Statusvariablen nach.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function RequestState(): bool
    {
        $Propertys = $this->Propertys;
        if (count($Propertys) == 0) {
            $Propertys = DataPoints::getReadableList();
        }
        return $this->get_prop($Propertys);
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton.
     *
     * @param int $Temperature Weißton in Kelvin (1700 bis 6500)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetWhite(int $Temperature): bool
    {
        return $this->SetWhiteSmooth($Temperature, $this->DefaultDuration());
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton des Hintergrunds.
     *
     * @param int $Temperature Weißton in Kelvin (1700 bis 6500)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgWhite(int $Temperature): bool
    {
        return $this->SetBgWhiteSmooth($Temperature, $this->DefaultDuration());
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton mit der in '$Duration' übergebenen Übergangszeit.
     *
     * @param int $Temperature Weißton in Kelvin (1700 bis 6500)
     * @param int $Duration    Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetWhiteSmooth(int $Temperature, int $Duration): bool
    {
        if (!$this->CheckWhite($Temperature)) {
            return false;
        }
        return $this->SendCommand('set_ct_abx', array_merge([$Temperature], $this->Effect($Duration)));
    }

    /**
     * Setzt den in '$Temperature' übergebenen Weißton des Hintergrunds mit der in '$Duration' übergebenen Übergangszeit.
     *
     * @param int $Temperature Weißton in Kelvin (1700 bis 6500)
     * @param int $Duration    Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgWhiteSmooth(int $Temperature, int $Duration): bool
    {
        if (!$this->CheckWhite($Temperature)) {
            return false;
        }
        return $this->SendCommand('bg_set_ct_abx', array_merge([$Temperature], $this->Effect($Duration)));
    }

    /**
     * Setzt die Farbe aus den Anteilen Rot, Grün und Blau.
     *
     * @param int $Red   Rot (0 bis 255)
     * @param int $Green Grün (0 bis 255)
     * @param int $Blue  Blau (0 bis 255)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetRGB(int $Red, int $Green, int $Blue): bool
    {
        return $this->SetRGBSmooth($Red, $Green, $Blue, $this->DefaultDuration());
    }

    /**
     * Setzt die Farbe des Hintergrunds aus den Anteilen Rot, Grün und Blau.
     *
     * @param int $Red   Rot (0 bis 255)
     * @param int $Green Grün (0 bis 255)
     * @param int $Blue  Blau (0 bis 255)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgRGB(int $Red, int $Green, int $Blue): bool
    {
        return $this->SetBgRGBSmooth($Red, $Green, $Blue, $this->DefaultDuration());
    }

    /**
     * Setzt die Farbe aus den Anteilen Rot, Grün und Blau mit Übergangszeit.
     *
     * @param int $Red      Rot (0 bis 255)
     * @param int $Green    Grün (0 bis 255)
     * @param int $Blue     Blau (0 bis 255)
     * @param int $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetRGBSmooth(int $Red, int $Green, int $Blue, int $Duration): bool
    {
        $Color = $this->RGBToColor($Red, $Green, $Blue);
        if ($Color === false) {
            return false;
        }
        return $this->SetColor($Color, $Duration);
    }

    /**
     * Setzt die Farbe des Hintergrunds aus den Anteilen Rot, Grün und Blau mit Übergangszeit.
     *
     * @param int $Red      Rot (0 bis 255)
     * @param int $Green    Grün (0 bis 255)
     * @param int $Blue     Blau (0 bis 255)
     * @param int $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgRGBSmooth(int $Red, int $Green, int $Blue, int $Duration): bool
    {
        $Color = $this->RGBToColor($Red, $Green, $Blue);
        if ($Color === false) {
            return false;
        }
        return $this->SetBgColor($Color, $Duration);
    }

    /**
     * Setzt die Farbe im HSV-Farbraum.
     *
     * @param int $HUE        Farbton (0 bis 359)
     * @param int $Saturation Sättigung (0 bis 100)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetHSV(int $HUE, int $Saturation): bool
    {
        return $this->SetHSVSmooth($HUE, $Saturation, $this->DefaultDuration());
    }

    /**
     * Setzt die Farbe des Hintergrunds im HSV-Farbraum.
     *
     * @param int $HUE        Farbton (0 bis 359)
     * @param int $Saturation Sättigung (0 bis 100)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgHSV(int $HUE, int $Saturation): bool
    {
        return $this->SetBgHSVSmooth($HUE, $Saturation, $this->DefaultDuration());
    }

    /**
     * Setzt die Farbe im HSV-Farbraum mit Übergangszeit.
     *
     * @param int $HUE        Farbton (0 bis 359)
     * @param int $Saturation Sättigung (0 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetHSVSmooth(int $HUE, int $Saturation, int $Duration): bool
    {
        if (!$this->CheckHSV($HUE, $Saturation)) {
            return false;
        }
        if (!$this->SendCommand('set_hsv', array_merge([$HUE, $Saturation], $this->Effect($Duration)))) {
            return false;
        }
        $this->HUE = $HUE;
        $this->SAT = $Saturation;
        return true;
    }

    /**
     * Setzt die Farbe des Hintergrunds im HSV-Farbraum mit Übergangszeit.
     *
     * @param int $HUE        Farbton (0 bis 359)
     * @param int $Saturation Sättigung (0 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgHSVSmooth(int $HUE, int $Saturation, int $Duration): bool
    {
        if (!$this->CheckHSV($HUE, $Saturation)) {
            return false;
        }
        if (!$this->SendCommand('bg_set_hsv', array_merge([$HUE, $Saturation], $this->Effect($Duration)))) {
            return false;
        }
        $this->BG_HUE = $HUE;
        $this->BG_SAT = $Saturation;
        return true;
    }

    /**
     * Setzt die Helligkeit.
     *
     * @param int $Level Helligkeit in Prozent (0 bis 100, 0 wird als 1 gesendet)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBrightness(int $Level): bool
    {
        return $this->SetBrightnessSmooth($Level, $this->DefaultDuration());
    }

    /**
     * Setzt die Helligkeit des Hintergrunds.
     *
     * @param int $Level Helligkeit in Prozent (0 bis 100, 0 wird als 1 gesendet)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgBrightness(int $Level): bool
    {
        return $this->SetBgBrightnessSmooth($Level, $this->DefaultDuration());
    }

    /**
     * Setzt die Helligkeit mit Übergangszeit.
     *
     * @param int $Level    Helligkeit in Prozent (0 bis 100, 0 wird als 1 gesendet)
     * @param int $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBrightnessSmooth(int $Level, int $Duration): bool
    {
        $Level = $this->CheckBrightness($Level);
        if ($Level === false) {
            return false;
        }
        return $this->SendCommand('set_bright', array_merge([$Level], $this->Effect($Duration)));
    }

    /**
     * Setzt die Helligkeit des Hintergrunds mit Übergangszeit.
     *
     * @param int $Level    Helligkeit in Prozent (0 bis 100, 0 wird als 1 gesendet)
     * @param int $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgBrightnessSmooth(int $Level, int $Duration): bool
    {
        $Level = $this->CheckBrightness($Level);
        if ($Level === false) {
            return false;
        }
        return $this->SendCommand('bg_set_bright', array_merge([$Level], $this->Effect($Duration)));
    }

    /**
     * Schaltet das Gerät ein oder aus.
     *
     * @param bool $Value true = ein, false = aus
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetPower(bool $Value): bool
    {
        return $this->SetPowerSmooth($Value, $this->DefaultDuration());
    }

    /**
     * Schaltet den Hintergrund ein oder aus.
     *
     * @param bool $Value true = ein, false = aus
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgPower(bool $Value): bool
    {
        return $this->SetBgPowerSmooth($Value, $this->DefaultDuration());
    }

    /**
     * Schaltet das Gerät mit Übergangszeit ein oder aus.
     *
     * @param bool $Value    true = ein, false = aus
     * @param int  $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetPowerSmooth(bool $Value, int $Duration): bool
    {
        return $this->SendCommand('set_power', array_merge([$Value ? 'on' : 'off'], $this->Effect($Duration)));
    }

    /**
     * Schaltet den Hintergrund mit Übergangszeit ein oder aus.
     *
     * @param bool $Value    true = ein, false = aus
     * @param int  $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgPowerSmooth(bool $Value, int $Duration): bool
    {
        return $this->SendCommand('bg_set_power', array_merge([$Value ? 'on' : 'off'], $this->Effect($Duration)));
    }

    /**
     * Schaltet das Gerät ein und setzt den Modus.
     *
     * @param int $Mode 0 = Standard, 1 = RGB, 2 = Weiß, 3 = HSV, 5 = Nachtlicht
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetMode(int $Mode): bool
    {
        return $this->SetModeSmooth($Mode, $this->DefaultDuration());
    }

    /**
     * Schaltet das Gerät mit Übergangszeit ein und setzt den Modus.
     *
     * @param int $Mode     0 = Standard, 1 = RGB, 2 = Weiß, 3 = HSV, 5 = Nachtlicht
     * @param int $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetModeSmooth(int $Mode, int $Duration): bool
    {
        $PowerMode = $this->ModeToPowerMode($Mode);
        if ($PowerMode === false) {
            return false;
        }
        return $this->SendCommand('set_power', array_merge(['on'], $this->Effect($Duration), [$PowerMode]));
    }

    /**
     * Setzt den Modus des Hintergrunds, der Ein-/Aus-Zustand bleibt erhalten.
     *
     * @param int $Mode 0 = Standard, 1 = RGB, 2 = Weiß, 3 = HSV, 5 = Nachtlicht
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgMode(int $Mode): bool
    {
        return $this->SetBgModeSmooth($Mode, $this->DefaultDuration());
    }

    /**
     * Setzt den Modus des Hintergrunds mit Übergangszeit, der Ein-/Aus-Zustand bleibt erhalten.
     *
     * @param int $Mode     0 = Standard, 1 = RGB, 2 = Weiß, 3 = HSV, 5 = Nachtlicht
     * @param int $Duration Übergangszeit in Millisekunden (unter 30 = sofort)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgModeSmooth(int $Mode, int $Duration): bool
    {
        $PowerMode = $this->ModeToPowerMode($Mode);
        if ($PowerMode === false) {
            return false;
        }
        return $this->SendCommand('bg_set_power', array_merge([$this->BgPower ? 'on' : 'off'], $this->Effect($Duration), [$PowerMode]));
    }

    /**
     * Schaltet das Gerät um (ein/aus).
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetToggle(): bool
    {
        return $this->SendCommand('toggle');
    }

    /**
     * Schaltet den Hintergrund um (ein/aus).
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgToggle(): bool
    {
        return $this->SendCommand('bg_toggle');
    }

    /**
     * Schaltet Haupt- und Hintergrundlicht um (ein/aus).
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetToggleBoth(): bool
    {
        return $this->SendCommand('dev_toggle');
    }

    /**
     * Veraltet, siehe SetToggle.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetToogle(): bool
    {
        $this->DeprecatedFunction(__FUNCTION__, 'SetToggle');
        return $this->SetToggle();
    }

    /**
     * Veraltet, siehe SetBgToggle.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgToogle(): bool
    {
        $this->DeprecatedFunction(__FUNCTION__, 'SetBgToggle');
        return $this->SetBgToggle();
    }

    /**
     * Veraltet, siehe SetToggleBoth.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetToogleBoth(): bool
    {
        $this->DeprecatedFunction(__FUNCTION__, 'SetToggleBoth');
        return $this->SetToggleBoth();
    }

    /**
     * Speichert den aktuellen Zustand als Einschaltzustand im Gerät.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetDefault(): bool
    {
        return $this->SendCommand('set_default');
    }

    /**
     * Speichert den aktuellen Zustand des Hintergrunds als Einschaltzustand im Gerät.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgDefault(): bool
    {
        return $this->SendCommand('bg_set_default');
    }

    /**
     * Startet eine Farbsequenz.
     *
     * Jeder Schritt besteht aus vier Werten [Dauer, Modus, Wert, Helligkeit]:
     * Dauer in ms (mindestens 50), Modus 1 = Farbe, 2 = Farbtemperatur, 7 = Pause,
     * Wert = RGB-Farbe bzw. Farbtemperatur (bei Pause ignoriert),
     * Helligkeit -1 oder 1 bis 100 (bei Pause ignoriert, -1 = unverändert).
     *
     * @param int    $Loops        Anzahl der Durchläufe (0 = endlos)
     * @param int    $RecoverState 0 = vorherigen Zustand wiederherstellen, 1 = Endzustand bleibt, 2 = ausschalten
     * @param string $Flow         JSON-Array mit allen Werten der Schritte hintereinander
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function StartColorFlow(int $Loops, int $RecoverState, string $Flow): bool
    {
        $Params = $this->BuildColorFlow($Loops, $RecoverState, $Flow);
        if ($Params === false) {
            return false;
        }
        return $this->SendCommand('start_cf', $Params);
    }

    /**
     * Startet eine Farbsequenz im Hintergrund, Parameter siehe StartColorFlow.
     *
     * @param int    $Loops        Anzahl der Durchläufe (0 = endlos)
     * @param int    $RecoverState 0 = vorherigen Zustand wiederherstellen, 1 = Endzustand bleibt, 2 = ausschalten
     * @param string $Flow         JSON-Array mit allen Werten der Schritte hintereinander
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function StartBgColorFlow(int $Loops, int $RecoverState, string $Flow): bool
    {
        $Params = $this->BuildColorFlow($Loops, $RecoverState, $Flow);
        if ($Params === false) {
            return false;
        }
        return $this->SendCommand('bg_start_cf', $Params);
    }

    /**
     * Stoppt eine laufende Farbsequenz.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function StopColorFlow(): bool
    {
        return $this->SendCommand('stop_cf');
    }

    /**
     * Stoppt eine laufende Farbsequenz im Hintergrund.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function StopBgColorFlow(): bool
    {
        return $this->SendCommand('bg_stop_cf');
    }

    /**
     * Startet den Einschlaf-Timer, das Gerät schaltet nach Ablauf aus.
     *
     * @param int $Minutes Minuten bis zum Ausschalten (ab 1)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetSleep(int $Minutes): bool
    {
        if ($Minutes < 1) {
            echo sprintf($this->Translate('%s out of range.'), 'Minutes') . PHP_EOL;
            return false;
        }
        if (!$this->SendCommand('cron_add', [0, $Minutes])) {
            return false;
        }
        $this->UpdateSleepTimer($Minutes);
        return true;
    }

    /**
     * Liest die Restlaufzeit des Einschlaf-Timers.
     *
     * @return int Minuten bis zum Ausschalten, 0 wenn kein Timer läuft
     */
    public function GetSleep(): int
    {
        $Result = $this->Send(new RPC_Data('cron_get', [0]));
        if (($Result === false) || (count($Result) == 0)) {
            return 0;
        }
        $Entry = (array) $Result[0];
        return (int) ($Entry['delay'] ?? 0);
    }

    /**
     * Löscht den Einschlaf-Timer.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function DelSleep(): bool
    {
        if (!$this->SendCommand('cron_del', [0])) {
            return false;
        }
        $this->UpdateSleepTimer(0);
        return true;
    }

    /**
     * Erhöht die Helligkeit um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function IncreaseBright(): bool
    {
        return $this->SendCommand('set_adjust', ['increase', 'bright']);
    }

    /**
     * Verringert die Helligkeit um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function DecreaseBright(): bool
    {
        return $this->SendCommand('set_adjust', ['decrease', 'bright']);
    }

    /**
     * Erhöht die Farbtemperatur um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function IncreaseWhiteTemp(): bool
    {
        return $this->SendCommand('set_adjust', ['increase', 'ct']);
    }

    /**
     * Verringert die Farbtemperatur um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function DecreaseWhiteTemp(): bool
    {
        return $this->SendCommand('set_adjust', ['decrease', 'ct']);
    }

    /**
     * Wechselt zur nächsten Farbe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function RotateColor(): bool
    {
        return $this->SendCommand('set_adjust', ['circle', 'color']);
    }

    /**
     * Erhöht die Helligkeit des Hintergrunds um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function IncreaseBgBright(): bool
    {
        return $this->SendCommand('bg_set_adjust', ['increase', 'bright']);
    }

    /**
     * Verringert die Helligkeit des Hintergrunds um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function DecreaseBgBright(): bool
    {
        return $this->SendCommand('bg_set_adjust', ['decrease', 'bright']);
    }

    /**
     * Erhöht die Farbtemperatur des Hintergrunds um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function IncreaseBgWhiteTemp(): bool
    {
        return $this->SendCommand('bg_set_adjust', ['increase', 'ct']);
    }

    /**
     * Verringert die Farbtemperatur des Hintergrunds um eine Stufe.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function DecreaseBgWhiteTemp(): bool
    {
        return $this->SendCommand('bg_set_adjust', ['decrease', 'ct']);
    }

    /**
     * Wechselt zur nächsten Farbe des Hintergrunds.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function RotateBgColor(): bool
    {
        return $this->SendCommand('bg_set_adjust', ['circle', 'color']);
    }

    /**
     * Veraltet, siehe RotateBgColor.
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function CircleBgColor(): bool
    {
        $this->DeprecatedFunction(__FUNCTION__, 'RotateBgColor');
        return $this->RotateBgColor();
    }

    /**
     * Ändert die Helligkeit relativ um den angegebenen Prozentwert.
     *
     * @param int $Percentage Änderung in Prozent (-100 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function AdjustBrightness(int $Percentage, int $Duration): bool
    {
        return $this->SendAdjust('adjust_bright', $Percentage, $Duration);
    }

    /**
     * Ändert die Helligkeit des Hintergrunds relativ um den angegebenen Prozentwert.
     *
     * @param int $Percentage Änderung in Prozent (-100 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function AdjustBgBrightness(int $Percentage, int $Duration): bool
    {
        return $this->SendAdjust('bg_adjust_bright', $Percentage, $Duration);
    }

    /**
     * Ändert die Farbtemperatur relativ um den angegebenen Prozentwert.
     *
     * @param int $Percentage Änderung in Prozent (-100 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function AdjustWhiteTemp(int $Percentage, int $Duration): bool
    {
        return $this->SendAdjust('adjust_ct', $Percentage, $Duration);
    }

    /**
     * Ändert die Farbtemperatur des Hintergrunds relativ um den angegebenen Prozentwert.
     *
     * @param int $Percentage Änderung in Prozent (-100 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function AdjustBgWhiteTemp(int $Percentage, int $Duration): bool
    {
        return $this->SendAdjust('bg_adjust_ct', $Percentage, $Duration);
    }

    /**
     * Ändert die Farbe relativ um den angegebenen Prozentwert.
     *
     * @param int $Percentage Änderung in Prozent (-100 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function AdjustColor(int $Percentage, int $Duration): bool
    {
        return $this->SendAdjust('adjust_color', $Percentage, $Duration);
    }

    /**
     * Ändert die Farbe des Hintergrunds relativ um den angegebenen Prozentwert.
     *
     * @param int $Percentage Änderung in Prozent (-100 bis 100)
     * @param int $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function AdjustBgColor(int $Percentage, int $Duration): bool
    {
        return $this->SendAdjust('bg_adjust_color', $Percentage, $Duration);
    }

    /**
     * Setzt den im Gerät gespeicherten Namen.
     *
     * @param string $Name Neuer Name
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetName(string $Name): bool
    {
        return $this->SendCommand('set_name', [$Name]);
    }

    /**
     * Setzt eine Szene (Parameter laut Spezifikation von set_scene).
     *
     * @param string $Params JSON-Array, z. B. ["color", 65280, 70] oder ["ct", 5400, 100]
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetScene(string $Params): bool
    {
        $SceneParams = $this->DecodeSceneParams($Params);
        if ($SceneParams === false) {
            return false;
        }
        return $this->SendCommand('set_scene', $SceneParams);
    }

    /**
     * Setzt eine Szene im Hintergrund (Parameter laut Spezifikation von bg_set_scene).
     *
     * @param string $Params JSON-Array, z. B. ["color", 65280, 70] oder ["ct", 5400, 100]
     *
     * @return bool true bei Erfolg, sonst false
     */
    public function SetBgScene(string $Params): bool
    {
        $SceneParams = $this->DecodeSceneParams($Params);
        if ($SceneParams === false) {
            return false;
        }
        return $this->SendCommand('bg_set_scene', $SceneParams);
    }

    /**
     * Konfiguration für den Client Socket.
     *
     * @return string JSON mit dem Port der Gerätesteuerung
     */
    public function GetConfigurationForParent(): string
    {
        return json_encode(['Port' => Network::ControlPort]);
    }

    /**
     * Empfängt Daten vom Parent.
     *
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     *
     * @return string immer ein leerer String
     */
    public function ReceiveData(string $JSONString): string
    {
        $Packet = json_decode($JSONString);
        if (!is_object($Packet) || !property_exists($Packet, 'Buffer')) {
            return '';
        }
        $Data = $this->BufferIN . hex2bin($Packet->Buffer);
        $JSONLines = explode("\r\n", $Data);
        $this->BufferIN = array_pop($JSONLines);

        foreach ($JSONLines as $JSON) {
            if ($JSON === '') {
                continue;
            }
            $this->SendDebug('Receive', $JSON, 0);
            $YeelightData = new RPC_Data();
            if (!$YeelightData->CreateFromJSONString($JSON)) {
                $this->SendDebug('Receive', 'Invalid JSON', 0);
                continue;
            }
            if ($YeelightData->Typ == RPC_Data::$isResult) {
                $this->SendQueueUpdate((int) $YeelightData->Id, $YeelightData);
            } elseif ($YeelightData->Typ == RPC_Data::$isEvent) {
                $this->SendDebug('Event', $YeelightData, 0);
                $this->Decode($YeelightData);
            }
        }
        return '';
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterWebHook();
        $this->RegisterParent();
    }

    /**
     * Ermittelt den Parent und startet die Anmeldung am Gerät, wenn der Parent aktiv ist.
     */
    protected function RegisterParent(): void
    {
        $IOId = $this->IORegisterParent();
        if ($IOId > 0) {
            $this->Host = (string) IPS_GetProperty($IOId, 'Host');
            $this->SetSummary($this->Host);
            if ($this->HasActiveParent()) {
                $this->IOChangeState(IS_ACTIVE);
            }
            return;
        }
        $this->Host = '';
        $this->SetSummary($this->Translate('none'));
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     *
     * @param int $State Neuer Status des Parent
     */
    protected function IOChangeState(int $State): void
    {
        if (!$this->lock(Locks::IOChangeState)) {
            return;
        }
        if ($State != IS_ACTIVE) {
            $this->ConnectionState = self::isDisconnected;
            $this->unlock(Locks::IOChangeState);
            return;
        }
        if ($this->ConnectionState == self::isReconnecting) {
            $this->unlock(Locks::IOChangeState);
            return;
        }
        $this->ConnectionState = self::isReconnecting;
        if ($this->ParentID > 0) {
            // Host kann sich am Client Socket geändert haben
            $this->Host = (string) IPS_GetProperty($this->ParentID, 'Host');
        }
        if (!$this->GetCapabilities()) {
            $this->SetStatus(IS_EBASE + 1);
            $this->ConnectionState = self::isDisconnected;
            $this->unlock(Locks::IOChangeState);
            return;
        }
        $this->ConnectionState = self::isConnected;
        $this->get_prop(DataPoints::getReadableList(), true);
        $this->SetStatus(IS_ACTIVE);
        $this->SendDebug('Propertys read:', implode(' ', $this->Propertys), 0);
        $this->unlock(Locks::IOChangeState);
    }

    //################# Send / Receive

    /**
     * Versendet ein RPC-Objekt und empfängt die Antwort.
     * Fehler werden per echo an den Aufrufer gemeldet.
     *
     * @param RPC_Data $YeelightData Das Objekt welches versendet werden soll.
     * @param bool     $Retry        Bei 'Quota exceeded' neu verbinden und erneut senden.
     *
     * @return false|array Ergebnis-Array der Antwort oder false im Fehlerfall.
     */
    protected function Send(RPC_Data $YeelightData, bool $Retry = true): false|array
    {
        if (($this->ConnectionState == self::isReconnecting) && !$this->WaitForActive()) {
            return $this->ReportError($this->Translate('Instance has no active parent.'));
        }
        if (($this->ConnectionState == self::isDisconnected) || !$this->HasActiveParent()) {
            return $this->ReportError($this->Translate('Instance has no active parent.'));
        }
        if (count($this->Capabilities) == 0) {
            $this->SendDebug('Warning', 'Capabilities of device are unknown', 0);
        } elseif (!in_array($YeelightData->Method, $this->Capabilities)) {
            return $this->ReportError($this->Translate('Device not support this command.'));
        }

        $this->SendDebug('Send', $YeelightData, 0);
        if (!$this->SendQueuePush((int) $YeelightData->Id)) {
            return $this->ReportError($this->Translate('Send queue is locked.'));
        }
        $YeelightJSON = $YeelightData->ToJSONString();
        $this->SendDebug('Send', $YeelightJSON, 0);
        $this->SendDataToParent(json_encode([
            'DataID' => GUID::SendToIO,
            'Buffer' => bin2hex($YeelightJSON . "\r\n")
        ]));
        $ReplyYeelightData = $this->WaitForResponse((int) $YeelightData->Id);
        if ($ReplyYeelightData === false) {
            return $this->ReportError($this->Translate('No answer from device.'));
        }
        $Result = $ReplyYeelightData->GetResult();
        if ($Result instanceof RPCException) {
            $this->SendDebug('Result', $Result, 0);
            return $this->HandleRPCError($YeelightData, $Result, $Retry);
        }
        $this->SendDebug('Result', $ReplyYeelightData, 0);
        return (array) $Result;
    }

    //################# Webhook

    /**
     * Interne Funktion des SDK.
     */
    protected function ProcessHookData(): void
    {
        $Scripts = [
            'hueSliderEvents'           => ['SliderEvents.js', false],
            'bg_hueSliderEvents'        => ['SliderBgEvents.js', false],
            'hueSlider'                 => ['Slider.js', true],
            'bg_hueSlider'              => ['SliderBg.js', true],
            'hueSliderRequestAction'    => ['SliderRequestAction.js', false],
            'bg_hueSliderRequestAction' => ['SliderBgRequestAction.js', false]
        ];
        $Script = (string) ($_GET['script'] ?? '');
        if (array_key_exists($Script, $Scripts)) {
            [$File, $WithInstanceID] = $Scripts[$Script];
            $this->SendJSFile($File, $WithInstanceID ? 'var InstanceID =' . $this->InstanceID . ';' : '');
            return;
        }
        $Ident = (string) ($_GET['ident'] ?? '');
        $Action = (string) ($_GET['action'] ?? '');
        if (($Ident == DataPoints::HUE) || ($Ident == DataPoints::BgHUE)) {
            $this->SendDebug('HookData', $_GET, 0);
            $Background = ($Ident == DataPoints::BgHUE);
            if ($Action == 'GetValue') {
                $this->SendTextResponse('OK,' . ($Background ? 'Bg' : '') . $this->InstanceID . ',' . ($Background ? $this->BG_HUE : $this->HUE));
                return;
            }
            if (($Action == 'SetValue') && isset($_GET['value'])) {
                $Hue = (int) $_GET['value'];
                ob_start();
                $Result = $Background ? $this->SetBgHSV($Hue, $this->BG_SAT) : $this->SetHSV($Hue, $this->SAT);
                $Error = (string) ob_get_clean();
                $this->SendTextResponse($Result ? 'OK' : trim($Error));
                return;
            }
        }
        $this->SendFileNotFound();
    }

    //################# Helper

    /**
     * Meldet einen Fehler an den Aufrufer.
     *
     * @param string $Message Übersetzte Fehlermeldung
     *
     * @return false
     */
    private function ReportError(string $Message): false
    {
        $this->SendDebug('Error', $Message, 0);
        echo $Message . PHP_EOL;
        return false;
    }

    /**
     * Behandelt eine Fehlermeldung des Gerätes.
     * Bei 'Quota exceeded' (-1) wird die Verbindung neu aufgebaut und der Befehl erneut gesendet.
     * Ein anderer Weg zum Neuverbinden des Client Socket existiert nicht (vom Store genehmigte Ausnahme).
     *
     * @param RPC_Data     $YeelightData Gesendeter Befehl
     * @param RPCException $Exception    Fehler des Gerätes
     * @param bool         $Retry        Neu verbinden und erneut senden erlaubt
     *
     * @return false|array Ergebnis des erneuten Sendens oder false
     */
    private function HandleRPCError(RPC_Data $YeelightData, RPCException $Exception, bool $Retry): false|array
    {
        $Message = sprintf($this->Translate('Error (%d): %s'), $Exception->getCode(), $Exception->getMessage());
        if (($Exception->getCode() != -1) || !$Retry) {
            return $this->ReportError($Message);
        }
        if (!$this->lock(Locks::Reconnect)) {
            $this->SendDebug('Reconnect in progress', '', 0);
            return $this->ReportError($Message);
        }
        $this->ConnectionState = self::isReconnecting;
        $this->SendDebug('Quota exceeded', 'Force reconnect', 0);
        $this->SetStatus(IS_INACTIVE);
        IPS_RunScriptTextWait('IPS_SetProperty(' . $this->ParentID . ', "Open", false); IPS_ApplyChanges(' . $this->ParentID . ');');
        IPS_RunScriptText('IPS_SetProperty(' . $this->ParentID . ', "Open", true); IPS_ApplyChanges(' . $this->ParentID . ');');
        $Reconnected = $this->WaitForActive();
        $this->unlock(Locks::Reconnect);
        if (!$Reconnected) {
            $this->ConnectionState = self::isDisconnected;
            $this->SendDebug('Force reconnect', 'failed', 0);
            return $this->ReportError($Message);
        }
        $this->SendDebug('Force reconnect', 'successfully', 0);
        return $this->Send($YeelightData, false);
    }

    /**
     * Sendet einen Befehl und wertet nur Erfolg oder Fehler aus.
     *
     * @param string $Method Name der RPC-Methode
     * @param array  $Params Parameter der Methode
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SendCommand(string $Method, array $Params = []): bool
    {
        return $this->Send(new RPC_Data($Method, $Params)) !== false;
    }

    /**
     * Führt die Variable des Einschlaf-Timers nach einem bestätigten Befehl nach.
     * Das Gerät meldet beim Löschen des Timers kein Ereignis (live geprüft).
     *
     * @param int $Minutes Restlaufzeit in Minuten, 0 = kein Timer
     */
    private function UpdateSleepTimer(int $Minutes): void
    {
        if (in_array(DataPoints::DelayOff, $this->Propertys) || ($this->FindIDForIdent(DataPoints::DelayOff) > 0)) {
            $this->SetStatusVariable(DataPoints::DelayOff, $Minutes);
        }
    }

    /**
     * Sendet eine relative Änderung (adjust_bright, adjust_ct, adjust_color und bg_*).
     *
     * @param string $Method     Name der RPC-Methode
     * @param int    $Percentage Änderung in Prozent (-100 bis 100)
     * @param int    $Duration   Übergangszeit in Millisekunden (ab 30)
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SendAdjust(string $Method, int $Percentage, int $Duration): bool
    {
        if (($Percentage < -100) || ($Percentage > 100)) {
            echo sprintf($this->Translate('%s out of range.'), 'Percentage') . PHP_EOL;
            return false;
        }
        return $this->SendCommand($Method, [$Percentage, max(Duration::Minimum, $Duration)]);
    }

    /**
     * Liefert die Übergangszeit aus der Eigenschaft 'Licht sanft ändern'.
     *
     * @return int Übergangszeit in Millisekunden
     */
    private function DefaultDuration(): int
    {
        return $this->ReadPropertyBoolean(Property::SetSmooth) ? Duration::Smooth : 0;
    }

    /**
     * Liefert die Effekt-Parameter für eine Übergangszeit.
     *
     * @param int $Duration Übergangszeit in Millisekunden
     *
     * @return array ['sudden', 0] oder ['smooth', $Duration]
     */
    private function Effect(int $Duration): array
    {
        if ($Duration < Duration::Minimum) {
            return ['sudden', 0];
        }
        return ['smooth', $Duration];
    }

    /**
     * Meldet einen veralteten Funktionsnamen im Log.
     *
     * @param string $Old Alter Funktionsname
     * @param string $New Neuer Funktionsname
     */
    private function DeprecatedFunction(string $Old, string $New): void
    {
        $this->LogMessage(sprintf($this->Translate('%s is deprecated and will be removed in version %s, please use %s.'), 'YEELIGHT_' . $Old, self::DeprecatedRemoveVersion, 'YEELIGHT_' . $New), KL_WARNING);
    }

    /**
     * Registriert bzw. entfernt den WebHook für den HUE-Slider.
     */
    private function RegisterWebHook(): void
    {
        if ($this->ReadPropertyBoolean(Property::HUESlider)) {
            $this->RegisterHook(Hook::Name . $this->InstanceID);
        } else {
            $this->UnregisterHook(Hook::Name . $this->InstanceID);
        }
    }

    /**
     * Prüft den Weißton.
     *
     * @param int $Temperature Weißton in Kelvin
     *
     * @return bool true wenn gültig
     */
    private function CheckWhite(int $Temperature): bool
    {
        if (($Temperature < 1700) || ($Temperature > 6500)) {
            echo sprintf($this->Translate('%s out of range.'), 'Temperature') . PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * Prüft Farbton und Sättigung.
     *
     * @param int $HUE        Farbton
     * @param int $Saturation Sättigung
     *
     * @return bool true wenn gültig
     */
    private function CheckHSV(int $HUE, int $Saturation): bool
    {
        if (($HUE < 0) || ($HUE > 359)) {
            echo sprintf($this->Translate('%s out of range.'), 'HUE') . PHP_EOL;
            return false;
        }
        if (($Saturation < 0) || ($Saturation > 100)) {
            echo sprintf($this->Translate('%s out of range.'), 'Saturation') . PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * Prüft die Helligkeit, 0 wird zu 1 (kleinster Wert des Gerätes).
     *
     * @param int $Level Helligkeit in Prozent
     *
     * @return false|int Zu sendende Helligkeit oder false
     */
    private function CheckBrightness(int $Level): false|int
    {
        if (($Level < 0) || ($Level > 100)) {
            echo sprintf($this->Translate('%s out of range.'), 'Level') . PHP_EOL;
            return false;
        }
        return max(1, $Level);
    }

    /**
     * Wandelt RGB-Anteile in einen Farbwert.
     *
     * @param int $Red   Rot
     * @param int $Green Grün
     * @param int $Blue  Blau
     *
     * @return false|int Farbwert oder false
     */
    private function RGBToColor(int $Red, int $Green, int $Blue): false|int
    {
        foreach ([$Red, $Green, $Blue] as $Value) {
            if (($Value < 0) || ($Value > 255)) {
                echo $this->Translate('Invalid parameter.') . PHP_EOL;
                return false;
            }
        }
        return ($Red << 16) + ($Green << 8) + $Blue;
    }

    /**
     * Wandelt den Modus der Statusvariable in den Modus von set_power.
     *
     * @param int $Mode 0 = Standard, 1 = RGB, 2 = Weiß, 3 = HSV, 5 = Nachtlicht
     *
     * @return false|int Modus für set_power oder false
     */
    private function ModeToPowerMode(int $Mode): false|int
    {
        $Map = [0 => 0, 1 => 2, 2 => 1, 3 => 3, 5 => 5];
        if (!array_key_exists($Mode, $Map)) {
            echo sprintf($this->Translate('Mode %d is not supported.'), $Mode) . PHP_EOL;
            return false;
        }
        return $Map[$Mode];
    }

    /**
     * Prüft die Parameter einer Farbsequenz.
     *
     * @param int    $Loops        Anzahl der Durchläufe
     * @param int    $RecoverState Verhalten nach Ablauf
     * @param string $Flow         JSON-Array der Schritte
     *
     * @return false|array Parameter für start_cf oder false
     */
    private function BuildColorFlow(int $Loops, int $RecoverState, string $Flow): false|array
    {
        if ($Loops < 0) {
            echo sprintf($this->Translate('%s out of range.'), 'Loops') . PHP_EOL;
            return false;
        }
        if (($RecoverState < 0) || ($RecoverState > 2)) {
            echo sprintf($this->Translate('%s out of range.'), 'RecoverState') . PHP_EOL;
            return false;
        }
        $FlowData = json_decode($Flow, true);
        if (!is_array($FlowData) || (count($FlowData) == 0) || ((count($FlowData) % 4) != 0)) {
            echo sprintf($this->Translate('%s is invalid.'), 'Flow') . PHP_EOL;
            return false;
        }
        return [$Loops, $RecoverState, implode(',', array_values($FlowData))];
    }

    /**
     * Prüft die Parameter einer Szene.
     *
     * @param string $Params JSON-Array der Parameter
     *
     * @return false|array Parameter oder false
     */
    private function DecodeSceneParams(string $Params): false|array
    {
        $SceneParams = json_decode($Params, true);
        if (!is_array($SceneParams) || (count($SceneParams) == 0)) {
            echo sprintf($this->Translate('%s is invalid.'), 'Params') . PHP_EOL;
            return false;
        }
        return array_values($SceneParams);
    }

    /**
     * Wandelt den Wert einer HSV-Farbvariable in Farbton und Sättigung.
     * Die Kachel-Visualisierung sendet h von 0 bis 360, s und v von 0 bis 100 (Gleitkomma, live geprüft).
     *
     * @param mixed $Value JSON-String {"h":…, "s":…, "v":…}
     *
     * @return false|array [Farbton, Sättigung in Prozent, Helligkeit in Prozent oder null] oder false
     */
    private function DecodeHSVValue(mixed $Value): false|array
    {
        $HSV = json_decode((string) $Value, true);
        if (!is_array($HSV) || !isset($HSV['h'], $HSV['s'])) {
            echo $this->Translate('Invalid value.') . PHP_EOL;
            return false;
        }
        $Hue = ((int) round((float) $HSV['h'])) % 360;
        $Saturation = (int) round((float) $HSV['s']);
        $Brightness = isset($HSV['v']) ? min(100, max(0, (int) round((float) $HSV['v']))) : null;
        return [$Hue, min(100, max(0, $Saturation)), $Brightness];
    }

    /**
     * Wartet bis die Verbindung zum Gerät aufgebaut ist.
     *
     * @return bool true wenn verbunden
     */
    private function WaitForActive(): bool
    {
        for ($i = 0; $i < 1000; $i++) {
            if ($this->ConnectionState == self::isConnected) {
                return true;
            }
            usleep(5000);
        }
        return false;
    }

    /**
     * Liest Properties vom Gerät und führt die Statusvariablen nach.
     *
     * @param array $Propertys Zu lesende Properties
     * @param bool  $Init      true = unterstützte Properties merken
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function get_prop(array $Propertys, bool $Init = false): bool
    {
        $Propertys = array_values($Propertys);
        $Result = $this->Send(new RPC_Data('get_prop', $Propertys));
        if ($Result === false) {
            return false;
        }
        $Supported = $this->Propertys;
        foreach ($Propertys as $Index => $Property) {
            $Value = $Result[$Index] ?? '';
            if ($Value === '') {
                continue;
            }
            if ($Init && !in_array($Property, $Supported)) {
                $Supported[] = $Property;
            }
            $this->SetStatusVariable($Property, $Value);
        }
        if ($Init) {
            $this->Propertys = $Supported;
        }
        return true;
    }

    /**
     * Sendet set_rgb an das Gerät.
     *
     * @param int $Color    RGB Farbe
     * @param int $Duration Übergangszeit in Millisekunden
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetColor(int $Color, int $Duration = 0): bool
    {
        return $this->SendCommand('set_rgb', array_merge([$Color], $this->Effect($Duration)));
    }

    /**
     * Sendet bg_set_rgb an das Gerät.
     *
     * @param int $Color    RGB Farbe
     * @param int $Duration Übergangszeit in Millisekunden
     *
     * @return bool true bei Erfolg, sonst false
     */
    private function SetBgColor(int $Color, int $Duration = 0): bool
    {
        return $this->SendCommand('bg_set_rgb', array_merge([$Color], $this->Effect($Duration)));
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
            $Header[strtolower(trim(array_shift($line_array)))] = trim(implode(':', $line_array));
        }
        return $Header;
    }

    /**
     * Fragt alle Fähigkeiten und Eigenschaften des Gerätes per SSDP ab und speichert diese im Buffer.
     *
     * @return bool true wenn die Fähigkeiten ermittelt wurden
     */
    private function GetCapabilities(): bool
    {
        $this->Capabilities = [];
        $this->Propertys = [];
        if ($this->Host == '') {
            return false;
        }
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return false;
        }
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 100000]);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!@socket_bind($socket, '0.0.0.0', Network::SsdpLocalPort)) {
            $this->SendDebug('Ask capabilities', 'Bind to port ' . Network::SsdpLocalPort . ' failed', 0);
            socket_close($socket);
            return false;
        }
        $message = [
            'M-SEARCH * HTTP/1.1',
            'HOST: ' . $this->Host . ':' . Network::SsdpPort,
            'MAN: "ssdp:discover"',
            'ST: wifi_bulb'
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";
        $this->SendDebug('Ask capabilities', $SendData, 0);
        if (@socket_sendto($socket, $SendData, strlen($SendData), 0, $this->Host, Network::SsdpPort) === false) {
            socket_close($socket);
            return false;
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
            if ($IPAddress != $this->Host) {
                continue;
            }
            $Data = $this->parseHeader($buf);
            if (array_key_exists('id', $Data)) {
                $this->ReportedDeviceId = $Data['id'];
                $DeviceId = $this->ReadPropertyString(Property::DeviceId);
                if (($DeviceId !== '') && (strcasecmp($DeviceId, $Data['id']) != 0)) {
                    $this->SendDebug('Warning', 'Device reports ID ' . $Data['id'] . ', configured is ' . $DeviceId, 0);
                }
            }
            if (array_key_exists('support', $Data)) {
                $this->Capabilities = explode(' ', $Data['support']);
                $this->SendDebug('Got capabilities', $Data['support'], 0);
                $index = array_keys(array_keys($Data), 'support');
                $Propertys = array_keys(array_slice($Data, $index[0] + 1));
                $Propertys = array_values(array_diff($Propertys, ['name']));
                $this->Propertys = $Propertys;
                $this->SendDebug('Got propertys', implode(' ', $Propertys), 0);
                break;
            }
        }
        socket_close($socket);
        return count($this->Capabilities) > 0;
    }

    //################# Decode / StatusVariables

    /**
     * Decodiert ein Event und führt die Statusvariablen nach.
     *
     * @param RPC_Data $YeelightData
     */
    private function Decode(RPC_Data $YeelightData): void
    {
        if (($YeelightData->Method != 'props') || (!is_object($YeelightData->Params) && !is_array($YeelightData->Params))) {
            $this->SendDebug('Unknown event', $YeelightData->Method, 0);
            return;
        }
        foreach ($YeelightData->Params as $Ident => $Value) {
            $this->SetStatusVariable((string) $Ident, $Value);
        }
    }

    /**
     * Schreibt einen vom Gerät gemeldeten Wert in die Statusvariable.
     *
     * @param string $Ident Ident der Statusvariable (Property-Name des Gerätes)
     * @param mixed  $Value Neuer Wert der Statusvariable
     */
    private function SetStatusVariable(string $Ident, mixed $Value): void
    {
        if ($Ident == DataPoints::ActiveMode) {
            // active_mode: 0 = Tageslicht, 1 = Nachtlicht
            $Ident = DataPoints::ColorMode;
            $Value = ((int) $Value == 1) ? 5 : 2;
        }
        if (!array_key_exists($Ident, Variables::$List)) {
            $this->SendDebug('Unsupported property', $Ident . ': ' . json_encode($Value), 0);
            return;
        }
        switch ($Ident) {
            case DataPoints::HUE:
            case DataPoints::BgHUE:
                $this->UpdateHue($Ident, (int) $Value);
                return;
            case DataPoints::SAT:
                $this->SAT = (int) $Value;
                $this->UpdateHSV(DataPoints::HSV, null, (int) $Value);
                break;
            case DataPoints::BgSAT:
                $this->BG_SAT = (int) $Value;
                $this->UpdateHSV(DataPoints::BgHSV, null, (int) $Value);
                break;
            case DataPoints::Bright:
                $this->Bright = (int) $Value;
                $this->UpdateHSV(DataPoints::HSV, null, null);
                break;
            case DataPoints::BgBright:
                $this->BgBright = (int) $Value;
                $this->UpdateHSV(DataPoints::BgHSV, null, null);
                break;
        }
        $StatusVariable = Variables::$List[$Ident];
        if (array_key_exists(Variables::Mapping, $StatusVariable)) {
            if (!is_scalar($Value) || !array_key_exists($Value, $StatusVariable[Variables::Mapping])) {
                $this->SendDebug('Unknown value', $Ident . ': ' . json_encode($Value), 0);
                return;
            }
            $Value = $StatusVariable[Variables::Mapping][$Value];
        }
        if ($Ident == DataPoints::BgPower) {
            $this->BgPower = (bool) $Value;
        }
        $this->MaintainStatusVariable($Ident);
        $this->SetValue($Ident, $this->ConvertValue($Value, $StatusVariable[Variables::Type]));
    }

    /**
     * Legt eine Statusvariable an bzw. aktualisiert ihre Darstellung.
     *
     * @param string $Ident Ident der Statusvariable
     */
    private function MaintainStatusVariable(string $Ident): void
    {
        $StatusVariable = Variables::$List[$Ident];
        $Presentation = Variables::getPresentation($Ident, $this->ReadPropertyInteger(Property::Mode));
        $this->MaintainVariable($Ident, $this->Translate($StatusVariable[Variables::Name]), $StatusVariable[Variables::Type], $this->TranslatePresentation($Presentation), 0, true);
        if ($StatusVariable[Variables::enableAction]) {
            $this->EnableAction($Ident);
        }
    }

    /**
     * Verarbeitet einen neuen Farbton (hue bzw. bg_hue).
     *
     * @param string $Ident Ident hue oder bg_hue
     * @param int    $Hue   Farbton
     */
    private function UpdateHue(string $Ident, int $Hue): void
    {
        if ($Ident == DataPoints::HUE) {
            $this->HUE = $Hue;
            $HSVIdent = DataPoints::HSV;
        } else {
            $this->BG_HUE = $Hue;
            $HSVIdent = DataPoints::BgHSV;
        }
        if ($this->ReadPropertyBoolean(Property::HUESlider)) {
            $this->MaintainStatusVariable($Ident);
            if ($this->GetValue($Ident) == '') {
                $Hook = 'hook/' . Hook::Name . $this->InstanceID;
                $Script = '<script src="' . $Hook . '?script=' . $Ident . 'SliderRequestAction"></script>';
                $Script .= '<script src="' . $Hook . '?script=' . $Ident . 'SliderEvents"></script>';
                $Script .= '<script src="' . $Hook . '?script=' . $Ident . 'Slider"></script>';
                $this->SetValue($Ident, $Script);
            }
        }
        $this->UpdateHSV($HSVIdent, $Hue, null);
    }

    /**
     * Aktualisiert die HSV-Farbvariable (h von 0 bis 360, s und v von 0 bis 100 wie in der Kachel-Visualisierung).
     * v ist die Helligkeit. Eine neue Sättigung oder Helligkeit allein legt die Variable nicht an.
     *
     * @param string   $HSVIdent   Ident hsv oder bg_hsv
     * @param int|null $Hue        Neuer Farbton oder null
     * @param int|null $Saturation Neue Sättigung in Prozent oder null
     */
    private function UpdateHSV(string $HSVIdent, ?int $Hue, ?int $Saturation): void
    {
        $Exists = $this->FindIDForIdent($HSVIdent) > 0;
        if (!$Exists && ($Hue === null)) {
            return;
        }
        $HSV = $Exists ? json_decode((string) $this->GetValue($HSVIdent), true) : null;
        if (!is_array($HSV)) {
            $HSV = ['h' => 0, 's' => 100, 'v' => 100];
        }
        $HSV['s'] = $HSV['s'] ?? 100;
        // v entspricht der Helligkeit des Gerätes
        $HSV['v'] = ($HSVIdent == DataPoints::HSV) ? $this->Bright : $this->BgBright;
        if ($Hue !== null) {
            $HSV['h'] = $Hue;
        }
        if ($Saturation !== null) {
            $HSV['s'] = $Saturation;
        }
        $this->MaintainStatusVariable($HSVIdent);
        $this->SetValue($HSVIdent, json_encode($HSV));
    }

    /**
     * Wandelt einen Wert in den Typ der Statusvariable.
     *
     * @param mixed $Value Wert
     * @param int   $Type  Variablentyp
     *
     * @return mixed Gewandelter Wert
     */
    private function ConvertValue(mixed $Value, int $Type): mixed
    {
        switch ($Type) {
            case VARIABLETYPE_BOOLEAN:
                return (bool) $Value;
            case VARIABLETYPE_INTEGER:
                return (int) $Value;
            case VARIABLETYPE_FLOAT:
                return (float) $Value;
            default:
                return (string) $Value;
        }
    }

    /**
     * Übersetzt die Texte einer Darstellung und kodiert Listen als JSON.
     *
     * @param array $Presentation Darstellung
     *
     * @return array Darstellung für MaintainVariable
     */
    private function TranslatePresentation(array $Presentation): array
    {
        if (isset($Presentation['PREFIX'])) {
            $Presentation['PREFIX'] = $this->Translate($Presentation['PREFIX']);
        }
        if (isset($Presentation['SUFFIX'])) {
            $Presentation['SUFFIX'] = $this->Translate($Presentation['SUFFIX']);
        }
        if (isset($Presentation['OPTIONS']) && is_array($Presentation['OPTIONS'])) {
            $Options = $Presentation['OPTIONS'];
            foreach ($Options as &$Option) {
                $Option['Caption'] = $this->Translate($Option['Caption']);
            }
            $Presentation['OPTIONS'] = json_encode($Options);
        }
        if (isset($Presentation['INTERVALS']) && is_array($Presentation['INTERVALS'])) {
            $Intervals = $Presentation['INTERVALS'];
            foreach ($Intervals as &$Interval) {
                foreach (['ConstantValue', 'PrefixValue', 'SuffixValue'] as $Key) {
                    if (isset($Interval[$Key])) {
                        $Interval[$Key] = $this->Translate($Interval[$Key]);
                    }
                }
            }
            $Presentation['INTERVALS'] = json_encode($Intervals);
        }
        return $Presentation;
    }

    //################# SENDQUEUE

    /**
     * Wartet auf eine RPC-Antwort.
     *
     * @param int $Id Die RPC-ID auf die gewartet wird.
     *
     * @return false|RPC_Data Antwort oder false bei einem Timeout.
     */
    private function WaitForResponse(int $Id): false|RPC_Data
    {
        for ($i = 0; $i < 1000; $i++) {
            $ret = $this->ReplyJSONData;
            if (!array_key_exists($Id, $ret)) {
                return false;
            }
            if ($ret[$Id] instanceof RPC_Data) {
                return $this->SendQueuePop($Id);
            }
            usleep(1000);
        }
        $this->SendQueueRemove($Id);
        return false;
    }

    /**
     * Fügt eine Anfrage in die SendQueue ein.
     *
     * @param int $Id die RPC-ID des versendeten RPC-Objektes.
     *
     * @return bool false wenn die Queue gesperrt war
     */
    private function SendQueuePush(int $Id): bool
    {
        if (!$this->lock(Locks::ReplyJSONData)) {
            return false;
        }
        $data = $this->ReplyJSONData;
        $data[$Id] = '';
        $this->ReplyJSONData = $data;
        $this->unlock(Locks::ReplyJSONData);
        return true;
    }

    /**
     * Fügt eine RPC-Antwort in die SendQueue ein.
     *
     * @param int      $Id           die RPC-ID des empfangenen Objektes.
     * @param RPC_Data $YeelightData Das empfangene RPC-Result.
     */
    private function SendQueueUpdate(int $Id, RPC_Data $YeelightData): void
    {
        if (!$this->lock(Locks::ReplyJSONData)) {
            $this->SendDebug('SendQueue', 'locked, reply ' . $Id . ' dropped', 0);
            return;
        }
        $data = $this->ReplyJSONData;
        if (array_key_exists($Id, $data)) {
            $data[$Id] = $YeelightData;
        }
        $this->ReplyJSONData = $data;
        $this->unlock(Locks::ReplyJSONData);
    }

    /**
     * Holt eine RPC-Antwort aus der SendQueue.
     *
     * @param int $Id die RPC-ID des empfangenen Objektes.
     *
     * @return RPC_Data Das empfangene RPC-Result.
     */
    private function SendQueuePop(int $Id): RPC_Data
    {
        $Result = $this->ReplyJSONData[$Id];
        $this->SendQueueRemove($Id);
        return $Result;
    }

    /**
     * Löscht einen RPC-Eintrag aus der SendQueue.
     *
     * @param int $Id Die RPC-ID des zu löschenden Objektes.
     */
    private function SendQueueRemove(int $Id): void
    {
        if (!$this->lock(Locks::ReplyJSONData)) {
            return;
        }
        $data = $this->ReplyJSONData;
        unset($data[$Id]);
        $this->ReplyJSONData = $data;
        $this->unlock(Locks::ReplyJSONData);
    }

    /**
     * Sendet die Standard-Header einer HTTP-Antwort.
     *
     * @param int    $Code        HTTP-Statuscode
     * @param string $ContentType Content-Type
     */
    private function SendHTTPHeader(int $Code, string $ContentType): void
    {
        http_response_code($Code);
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: Hook Reverse Proxy');
        header('Cache-Control: no-cache');
        header('Content-Type: ' . $ContentType);
    }

    /**
     * Liefert einen Text als HTTP-Response.
     *
     * @param string $Text Inhalt
     */
    private function SendTextResponse(string $Text): void
    {
        $this->SendHTTPHeader(200, 'text/plain');
        echo $Text;
    }

    /**
     * Liefert ein JavaScript-File als HTTP-Response.
     *
     * @param string $File    Dateiname des Javascript-File
     * @param string $PreCode Dynamischer JS-Code welcher vor dem File ausgeliefert wird.
     */
    private function SendJSFile(string $File, string $PreCode = ''): void
    {
        $this->SendHTTPHeader(200, 'application/javascript');
        echo $PreCode;
        readfile(__DIR__ . '/../libs/' . $File);
    }

    /**
     * Sendet einen HTTP 404 File not Found als HTTP-Response.
     */
    private function SendFileNotFound(): void
    {
        $this->SendHTTPHeader(404, 'text/plain');
        header('Expires: 0');
        echo 'File not found!';
    }
}

/* @} */
