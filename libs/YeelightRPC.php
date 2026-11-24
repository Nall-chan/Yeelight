<?php

declare(strict_types=1);

/*
 * @addtogroup yeelight
 * @{
 *
 * @package       yeelight
 * @file          YeelightRPC.php
 * @author        Michael Tröger <nall4chan@gmail.com>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.16
 *
 */

namespace Yeelight;

/**
 * GUIDs der Module und Datenschnittstellen.
 */
class GUID
{
    /** Modul Yeelight Device. */
    public const Device = '{BF5D53BB-EB4E-45C0-8632-5DB4EF49FA9F}';
    /** Modul Client Socket. */
    public const ClientSocket = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
    /** Datenschnittstelle Client Socket (Senden an den IO). */
    public const SendToIO = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';
}

/**
 * Namen der Instanz-Eigenschaften.
 */
class Property
{
    public const HUESlider = 'HUESlider';
    public const SetSmooth = 'SetSmooth';
    public const Mode = 'Mode';
    /** Geräte-ID aus der SSDP-Antwort (z.B. 0x0000000004d2b2e4), wird von der Discovery gesetzt. */
    public const DeviceId = 'DeviceId';
}

/**
 * Namen der Semaphoren.
 */
class Locks
{
    public const IOChangeState = 'IOChangeState';
    public const Reconnect = 'Reconnect';
    public const ReplyJSONData = 'ReplyJSONData';
}

/**
 * Namen und Parameter des WebHook.
 */
class Hook
{
    /** Name für RegisterHook (Symcon setzt /hook/ davor), ergänzt um die InstanzID. */
    public const Name = 'Yeelight';
}

/**
 * Netzwerkparameter des Yeelight-Protokolls.
 */
class Network
{
    /** TCP-Port der Gerätesteuerung. */
    public const ControlPort = 55443;
    /** UDP-Port der SSDP-Anfragen an das Gerät. */
    public const SsdpPort = 1982;
    /** Lokaler UDP-Port für die SSDP-Antworten, nur bei NAT (muss weitergeleitet werden), sonst zufälliger Port. */
    public const SsdpLocalPort = 1983;
    /** Multicast-Adresse der SSDP-Suche. */
    public const SsdpMulticast = '239.255.255.250';
    /** Globale Semaphore für Port 1983 (nur bei NAT), damit die Instanzen ihn nacheinander nutzen. */
    public const SsdpLock = 'Yeelight.SSDP1983';
    /** Wartezeit auf die Semaphore in Millisekunden. */
    public const SsdpLockTimeout = 10000;
}

/**
 * Übergangszeiten in Millisekunden.
 */
class Duration
{
    /** Übergangszeit, wenn 'Licht sanft ändern' aktiv ist. */
    public const Smooth = 500;
    /** Kleinste Übergangszeit laut Spezifikation, darunter wird 'sudden' gesendet. */
    public const Minimum = 30;
}

/**
 * Idents der Statusvariablen (entsprechen den Property-Namen des Gerätes).
 */
class DataPoints
{
    public const Power = 'power';
    public const BgPower = 'bg_power';
    public const Bright = 'bright';
    public const BgBright = 'bg_bright';
    public const RGB = 'rgb';
    public const BgRGB = 'bg_rgb';
    public const SAT = 'sat';
    public const BgSAT = 'bg_sat';
    public const CT = 'ct';
    public const BgCT = 'bg_ct';
    public const ColorMode = 'color_mode';
    public const BgColorMode = 'bg_lmode';
    public const ActiveMode = 'active_mode';
    public const HUE = 'hue';
    public const HSV = 'hsv';
    public const BgHUE = 'bg_hue';
    public const BgHSV = 'bg_hsv';
    public const NightLightBright = 'nl_br';
    public const Flowing = 'flowing';
    public const BgFlowing = 'bg_flowing';
    public const DelayOff = 'delayoff';
    public const MusicOn = 'music_on';

    /**
     * Liefert alle Properties, welche per get_prop gelesen werden.
     *
     * @return string[]
     */
    public static function getReadableList(): array
    {
        return array_keys(array_filter(Variables::$List, function ($item)
        {
            return $item[Variables::Readable];
        }));
    }
}

/**
 * Definition der Statusvariablen.
 */
class Variables
{
    public const Readable = 'Readable';
    public const Name = 'Name';
    public const Type = 'Type';
    public const Presentation = 'Presentation';
    public const Presentation0 = 'Presentation0';
    public const Presentation1 = 'Presentation1';
    public const Presentation2 = 'Presentation2';
    public const enableAction = 'enableAction';
    public const Mapping = 'Mapping';

    private const PercentSlider = [
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'DIGITS'              => 0,
        'CUSTOM_GRADIENT'     => '[]',
        'ICON'                => 'brightness',
        'GRADIENT_TYPE'       => 0,
        'MAX'                 => 100,
        'INTERVALS'           => '[]',
        'INTERVALS_ACTIVE'    => false,
        'MIN'                 => 0,
        'PERCENTAGE'          => false,
        'PREFIX'              => '',
        'STEP_SIZE'           => 1,
        'SUFFIX'              => ' %',
        'THOUSANDS_SEPARATOR' => '',
        'USAGE_TYPE'          => 2,
    ];

    private const SaturationSlider = [
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'DIGITS'              => 0,
        'CUSTOM_GRADIENT'     => '[]',
        'ICON'                => 'dial-med-low',
        'GRADIENT_TYPE'       => 0,
        'MAX'                 => 100,
        'INTERVALS'           => '[]',
        'INTERVALS_ACTIVE'    => false,
        'MIN'                 => 0,
        'PERCENTAGE'          => false,
        'PREFIX'              => '',
        'STEP_SIZE'           => 1,
        'SUFFIX'              => ' %',
        'THOUSANDS_SEPARATOR' => '',
        'USAGE_TYPE'          => 5, // Keine hiervon, sonst bietet die Licht-Kachel die Sättigung als Helligkeit an
    ];

    private const WhiteSlider = [
        'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
        'TEMPLATE'     => VARIABLE_TEMPLATE_SLIDER_COLOR_TEMPERATURE,
        'MIN'          => 1700,
        'MAX'          => 6500,
    ];

    private const WhiteSliderNightlight = [
        'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
        'TEMPLATE'     => VARIABLE_TEMPLATE_SLIDER_COLOR_TEMPERATURE,
        'MIN'          => 2700,
        'MAX'          => 6500,
    ];

    private const HSVColor = [
        'COLOR_CURVE'        => 0,
        'COLOR_SPACE'        => 1,
        'SELECTION'          => 0,
        'CUSTOM_COLOR_CURVE' => '[]',
        'CUSTOM_COLOR_SPACE' => '[{"x":0.64,"y":0.33},{"x":0.3,"y":0.6},{"x":0.15,"y":0.06},{"x":0.3127,"y":0.329}]',
        'ENCODING'           => 2,
        'PRESENTATION'       => VARIABLE_PRESENTATION_COLOR,
        'PRESET_VALUES'      => '[{"Color":16007990},{"Color":16761095},{"Color":10233776},{"Color":48340},{"Color":2201331},{"Color":15277667}]'
    ];

    private const OptionRGB = ['Value' => 1, 'Caption' => 'RGB', 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
    private const OptionWhite = ['Value' => 2, 'Caption' => 'White', 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
    private const OptionHSV = ['Value' => 3, 'Caption' => 'HSV', 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
    private const OptionNightlight = ['Value' => 5, 'Caption' => 'Nightlight', 'IconActive' => false, 'IconValue' => '', 'Color' => -1];

    private const ModeColor = [
        'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
        'ICON'         => 'swatchbook',
        'OPTIONS'      => [self::OptionRGB, self::OptionWhite, self::OptionHSV]
    ];

    private const ModeColorNightlight = [
        'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
        'ICON'         => 'swatchbook',
        'OPTIONS'      => [self::OptionRGB, self::OptionWhite, self::OptionHSV, self::OptionNightlight]
    ];

    private const ModeWhiteNightlight = [
        'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
        'ICON'         => 'swatchbook',
        'OPTIONS'      => [self::OptionWhite, self::OptionNightlight]
    ];

    private const SleepSlider = [
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'DIGITS'              => 0,
        'CUSTOM_GRADIENT'     => '[]',
        'ICON'                => 'stopwatch',
        'GRADIENT_TYPE'       => 0,
        'MAX'                 => 120,
        'INTERVALS'           => [
            [
                'IntervalMinValue' => 0,
                'IntervalMaxValue' => 0,
                'ConstantActive'   => true,
                'ConstantValue'    => 'off',
                'ConversionFactor' => 1,
                'DigitsActive'     => false,
                'DigitsValue'      => 0,
                'IconActive'       => false,
                'IconValue'        => '',
                'PrefixActive'     => false,
                'PrefixValue'      => '',
                'SuffixActive'     => false,
                'SuffixValue'      => ''
            ]
        ],
        'INTERVALS_ACTIVE'    => true,
        'MIN'                 => 0,
        'PERCENTAGE'          => false,
        'PREFIX'              => '',
        'STEP_SIZE'           => 1,
        'SUFFIX'              => ' min',
        'THOUSANDS_SEPARATOR' => '',
        'USAGE_TYPE'          => 5,
    ];

    private const ActiveOptions = [
        ['Value' => false, 'Caption' => 'inactive', 'IconActive' => false, 'IconValue' => '', 'ColorActive' => false, 'ColorValue' => -1, 'ContentColorActive' => false, 'ContentColorValue' => -1],
        ['Value' => true, 'Caption' => 'active', 'IconActive' => false, 'IconValue' => '', 'ColorActive' => false, 'ColorValue' => -1, 'ContentColorActive' => false, 'ContentColorValue' => -1]
    ];

    private const FlowingState = [
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'ICON'         => 'wand-magic-sparkles',
        'OPTIONS'      => self::ActiveOptions
    ];

    private const MusicState = [
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'ICON'         => 'music',
        'OPTIONS'      => self::ActiveOptions
    ];

    /**
     * Alle Statusvariablen, Schlüssel ist der Ident.
     * Presentation0..2 werden passend zur Eigenschaft 'Mode' gewählt.
     *
     * @var array<string, array>
     */
    public static $List = [
        DataPoints::Power => [
            self::Readable     => true,
            self::Name         => 'State',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Presentation => ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            self::enableAction => true,
            self::Mapping      => ['on' => true, 'off' => false]
        ],
        DataPoints::BgPower => [
            self::Readable     => true,
            self::Name         => 'State Background',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Presentation => ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH],
            self::enableAction => true,
            self::Mapping      => ['on' => true, 'off' => false]
        ],
        DataPoints::Bright => [
            self::Readable     => true,
            self::Name         => 'Brightness',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => self::PercentSlider,
            self::enableAction => true
        ],
        DataPoints::BgBright => [
            self::Readable     => true,
            self::Name         => 'Brightness Background',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => self::PercentSlider,
            self::enableAction => true
        ],
        DataPoints::RGB => [
            self::Readable     => true,
            self::Name         => 'RGB Color',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => ['PRESENTATION' => VARIABLE_PRESENTATION_COLOR],
            self::enableAction => true
        ],
        DataPoints::BgRGB => [
            self::Readable     => true,
            self::Name         => 'RGB Color Background',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => ['PRESENTATION' => VARIABLE_PRESENTATION_COLOR],
            self::enableAction => true
        ],
        DataPoints::SAT => [
            self::Readable     => true,
            self::Name         => 'HSV Saturation',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => self::SaturationSlider,
            self::enableAction => true
        ],
        DataPoints::BgSAT => [
            self::Readable     => true,
            self::Name         => 'HSV Saturation Background',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => self::SaturationSlider,
            self::enableAction => true
        ],
        DataPoints::CT => [
            self::Readable      => true,
            self::Name          => 'White',
            self::Type          => VARIABLETYPE_INTEGER,
            self::Presentation0 => self::WhiteSlider,
            self::Presentation1 => self::WhiteSlider,
            self::Presentation2 => self::WhiteSliderNightlight,
            self::enableAction  => true
        ],
        DataPoints::BgCT => [
            self::Readable      => true,
            self::Name          => 'White Background',
            self::Type          => VARIABLETYPE_INTEGER,
            self::Presentation0 => self::WhiteSlider,
            self::Presentation1 => self::WhiteSlider,
            self::Presentation2 => self::WhiteSliderNightlight,
            self::enableAction  => true
        ],
        DataPoints::ColorMode => [
            self::Readable      => true,
            self::Name          => 'Current mode',
            self::Type          => VARIABLETYPE_INTEGER,
            self::Presentation0 => self::ModeColor,
            self::Presentation1 => self::ModeColorNightlight,
            self::Presentation2 => self::ModeWhiteNightlight,
            self::enableAction  => true
        ],
        DataPoints::BgColorMode => [
            self::Readable      => true,
            self::Name          => 'Current mode Background',
            self::Type          => VARIABLETYPE_INTEGER,
            self::Presentation0 => self::ModeColor,
            self::Presentation1 => self::ModeColorNightlight,
            self::Presentation2 => self::ModeWhiteNightlight,
            self::enableAction  => true
        ],
        DataPoints::HUE => [
            self::Readable     => true,
            self::Name         => 'HSV Hue',
            self::Type         => VARIABLETYPE_STRING,
            self::Presentation => ['PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT],
            self::enableAction => false
        ],
        DataPoints::HSV => [
            self::Readable     => false,
            self::Name         => 'HSV Color',
            self::Type         => VARIABLETYPE_STRING,
            self::Presentation => self::HSVColor,
            self::enableAction => true
        ],
        DataPoints::BgHUE => [
            self::Readable     => true,
            self::Name         => 'HSV Hue Background',
            self::Type         => VARIABLETYPE_STRING,
            self::Presentation => ['PRESENTATION' => VARIABLE_PRESENTATION_WEB_CONTENT],
            self::enableAction => false
        ],
        DataPoints::BgHSV => [
            self::Readable     => false,
            self::Name         => 'HSV Color Background',
            self::Type         => VARIABLETYPE_STRING,
            self::Presentation => self::HSVColor,
            self::enableAction => true
        ],
        DataPoints::NightLightBright => [
            self::Readable     => true,
            self::Name         => 'Brightness Nightlight',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => self::PercentSlider,
            self::enableAction => true
        ],
        DataPoints::Flowing => [
            self::Readable     => true,
            self::Name         => 'Sequence active',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Presentation => self::FlowingState,
            self::enableAction => false,
            self::Mapping      => [0 => false, 1 => true]
        ],
        DataPoints::BgFlowing => [
            self::Readable     => true,
            self::Name         => 'Sequence active Background',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Presentation => self::FlowingState,
            self::enableAction => false,
            self::Mapping      => [0 => false, 1 => true]
        ],
        DataPoints::DelayOff => [
            self::Readable     => true,
            self::Name         => 'Sleep timer',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Presentation => self::SleepSlider,
            self::enableAction => true
        ],
        DataPoints::MusicOn => [
            self::Readable     => true,
            self::Name         => 'Music mode active',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Presentation => self::MusicState,
            self::enableAction => false,
            self::Mapping      => [0 => false, 1 => true]
        ]
    ];

    /**
     * Liefert die Darstellung einer Statusvariable passend zum Modus der Instanz.
     *
     * @param string $Ident Ident der Statusvariable
     * @param int    $Mode  Wert der Eigenschaft 'Mode' (0 = Farbe, 1 = Farbe und Nachtlicht, 2 = Weiß und Nachtlicht)
     *
     * @return array Darstellung (Parameter wie bei MaintainVariable)
     */
    public static function getPresentation(string $Ident, int $Mode): array
    {
        $Variable = self::$List[$Ident];
        if (array_key_exists(self::Presentation . $Mode, $Variable)) {
            return $Variable[self::Presentation . $Mode];
        }
        return $Variable[self::Presentation];
    }
}

/**
 * Definiert eine RPCException.
 */
class RPCException extends \Exception
{
    public function __construct(string $message, int $code, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Enthält einen Yeelight-RPC Datensatz.
 *
 * @property-read int|null $Id Id des RPC-Objektes
 * @property-read string $Method Command des RPC-Objektes
 * @property-read array|object $Params Params des RPC-Objektes
 * @property-read int $Typ Typ der Daten (RPC_Data::$isCommand, RPC_Data::$isResult oder RPC_Data::$isEvent)
 * @property-read object|null $Error Fehler der Antwort
 * @property-read mixed $Result Ergebnis der Antwort
 *
 * @method void get_prop(array $Params) Liest Properties des Gerätes.
 *
 * @method void set_ct_abx(array $Params) Setzt die Farbtemperatur.
 * @method void bg_set_ct_abx(array $Params) Setzt die Farbtemperatur des Hintergrunds.
 *
 * @method void set_rgb(array $Params) Setzt die Farbe im RGB-Farbraum.
 * @method void bg_set_rgb(array $Params) Setzt die Farbe des Hintergrunds im RGB-Farbraum.
 *
 * @method void set_hsv(array $Params) Setzt die Farbe im HSV-Farbraum.
 * @method void bg_set_hsv(array $Params) Setzt die Farbe des Hintergrunds im HSV-Farbraum.
 *
 * @method void set_scene(array $Params) Setzt die Szene.
 * @method void bg_set_scene(array $Params) Setzt die Szene des Hintergrunds.
 *
 * @method void set_bright(array $Params) Setzt die Helligkeit.
 * @method void bg_set_bright(array $Params) Setzt die Helligkeit des Hintergrunds.
 *
 * @method void set_power(array $Params) Setzt den Ein-/Aus-Zustand.
 * @method void bg_set_power(array $Params) Setzt den Ein-/Aus-Zustand des Hintergrunds.
 *
 * @method void toggle() Schaltet den Ein-/Aus-Zustand um.
 * @method void bg_toggle() Schaltet den Ein-/Aus-Zustand des Hintergrunds um.
 * @method void dev_toggle() Schaltet beide Ein-/Aus-Zustände um.
 *
 * @method void set_default() Speichert den aktuellen Zustand als Standard.
 * @method void bg_set_default() Speichert den aktuellen Zustand des Hintergrunds als Standard.
 *
 * @method void start_cf(array $Params) Startet eine Farbsequenz.
 * @method void bg_start_cf(array $Params) Startet eine Farbsequenz im Hintergrund.
 *
 * @method void stop_cf() Stoppt eine Farbsequenz.
 * @method void bg_stop_cf() Stoppt eine Farbsequenz im Hintergrund.
 *
 * @method void cron_add(array $Params) Fügt einen Eintrag zum Zeitplan hinzu.
 * @method void cron_del(array $Params) Entfernt einen Eintrag aus dem Zeitplan.
 * @method void cron_get(array $Params) Liest den Zeitplan aus.
 *
 * @method void set_adjust(array $Params) Justiert die Helligkeit, Farbtemperatur oder Farbe.
 * @method void bg_set_adjust(array $Params) Justiert die Helligkeit, Farbtemperatur oder Farbe des Hintergrunds.
 *
 * @method void set_name(array $Params) Setzt den Gerätenamen.
 */
class RPC_Data
{
    public static $isEvent = 3;
    public static $isCommand = 1;
    public static $isResult = 2;

    /**
     * Typ der Daten.
     *
     * @var int|null
     */
    private $Typ;

    /**
     * Name der Methode.
     *
     * @var string
     */
    private $Method = '';

    /**
     * Enthält Fehlermeldungen der Methode.
     *
     * @var object|null
     */
    private $Error;

    /**
     * Parameter der Methode.
     *
     * @var array|object
     */
    private $Params;

    /**
     * Antwort der Methode.
     *
     * @var mixed
     */
    private $Result;

    /**
     * Id des RPC-Objektes.
     *
     * @var int|null
     */
    private $Id;

    /**
     * Erstellt ein RPC_Data Objekt.
     *
     * @param string $Method [optional] Name der RPC-Methode
     * @param array  $Params [optional] Parameter der Methode
     * @param int    $Id     [optional] Id des RPC-Objektes
     */
    public function __construct(?string $Method = null, ?array $Params = null, ?int $Id = null)
    {
        if (!is_null($Method)) {
            $this->Method = $Method;
            $this->Typ = self::$isCommand;
        }
        $this->Params = [];

        if (is_array($Params)) {
            $this->Params = $Params;
        }

        if (is_null($Id)) {
            $this->Id = self::NewId();
        } elseif ($Id > 0) {
            $this->Id = $Id;
        }
    }

    /**
     * @param string $name PropertyName
     *
     * @return mixed Value of Name
     */
    public function __get(string $name): mixed
    {
        return $this->{$name};
    }

    /**
     * Setzt eine RPC-Methode samt Parameter.
     *
     * @param string $name      Auszuführende RPC-Methode
     * @param array  $arguments Parameter der RPC-Methode.
     */
    public function __call(string $name, array $arguments): void
    {
        $this->Method = $name;
        if (count($arguments) == 0) {
            $this->Params = [];
        } else {
            $this->Params = $arguments[0];
        }
        $this->Id = self::NewId();
        $this->Typ = self::$isCommand;
    }

    /**
     * Gibt die RPC Antwort auf eine Anfrage zurück.
     *
     * @return mixed Enthält die Antwort des RPC-Server. Im Fehlerfall wird ein Objekt vom Typ RPCException zurückgegeben.
     */
    public function GetResult(): mixed
    {
        if (!is_null($this->Error)) {
            return $this->GetErrorObject();
        }
        if (!is_null($this->Result)) {
            return $this->Result;
        }
        return [];
    }

    /**
     * Schreibt die Daten aus $Data in das RPC_Data-Objekt.
     *
     * @param string $Data Ein JSON-kodierter RPC-String vom RPC-Server.
     *
     * @return bool true wenn $Data ein gültiges JSON-Objekt war.
     */
    public function CreateFromJSONString(string $Data): bool
    {
        $Json = json_decode($Data);
        if (!is_object($Json)) {
            return false;
        }

        if (property_exists($Json, 'method')) {
            $this->Method = (string) $Json->method;
        }

        if (property_exists($Json, 'params')) {
            $this->Params = $Json->params;
        }

        if (property_exists($Json, 'id')) {
            $this->Id = (int) $Json->id;
        } else {
            $this->Id = null;
            $this->Typ = self::$isEvent;
        }

        if (property_exists($Json, 'error')) {
            $this->Error = $Json->error;
            $this->Typ = self::$isResult;
        }

        if (property_exists($Json, 'result')) {
            $this->Result = $Json->result;
            $this->Typ = self::$isResult;
        }
        return true;
    }

    /**
     * Erzeugt einen JSON-kodierten String zum Versand an das Gerät.
     *
     * @return string JSON-kodierter String.
     */
    public function ToJSONString(): string
    {
        $RPC = new \stdClass();
        $RPC->id = $this->Id;
        $RPC->method = $this->Method;
        $RPC->params = $this->Params ?? [];
        return json_encode($RPC);
    }

    /**
     * Erzeugt eine neue Id für eine Anfrage.
     *
     * @return int
     */
    private static function NewId(): int
    {
        return random_int(1, 0x7FFFFFFF);
    }

    /**
     * Gibt ein Objekt RPCException mit den enthaltenen Fehlermeldung des RPC-Servers zurück.
     *
     * @return RPCException Enthält die Daten der Fehlermeldung des RPC-Server.
     */
    private function GetErrorObject(): RPCException
    {
        $code = property_exists($this->Error, 'code') ? (int) $this->Error->code : 0;
        $message = property_exists($this->Error, 'message') ? (string) $this->Error->message : '';
        return new RPCException($message, $code);
    }
}

/* @} */
