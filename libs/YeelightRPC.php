<?php

declare(strict_types=1);

namespace Yeelight;

/*
 * @addtogroup yeelight
 * @{
 *
 * @package yeelight
 * @file YeelightRPC.php
 * @author Michael Tröger
 *
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
    public const HUE = 'hue';
    public const HSV = 'hsv';
    public const BgHUE = 'bg_hue';
    public const BgHSV = 'bg_hsv';
    public const NightLightBright = 'nl_br';
    public const Flowing = 'flowing';
    public const BgFlowing = 'bg_flowing';

    public static $List = [
        self::BgBright,
        self::BgColorMode,
        self::BgCT,
        self::BgFlowing,
        self::BgHUE,
        self::BgPower,
        self::BgRGB,
        self::BgSAT,
        self::Bright,
        self::ColorMode,
        self::CT,
        self::Flowing,
        self::HUE,
        self::NightLightBright,
        self::Power,
        self::RGB,
        self::SAT
    ];
    public static function getReadableList(): array
    {
        return array_keys(array_filter(Variables::$List, function ($item)
        {
            return $item[Variables::Readable];
        }));
    }
}
class Variables
{
    public const Readable = 'Readable';
    public const Name = 'Name';
    public const Type = 'Type';
    public const Profile = 'Profile';
    public const Profile0 = 'Profile0';
    public const Profile1 = 'Profile1';
    public const Profile2 = 'Profile2';
    public const enableAction = 'enableAction';
    public const Mapping = 'Mapping';
    public static $List = [
        DataPoints::Power => [
            self::Readable     => true,
            self::Name         => 'State',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Profile      => '~Switch',
            self::enableAction => true,
            self::Mapping      => ['on' => true, 'off' => false]
        ],
        DataPoints::BgPower => [
            self::Readable     => true,
            self::Name         => 'State Background',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Profile      => '~Switch',
            self::enableAction => true,
            self::Mapping      => ['on' => true, 'off' => false]
        ],
        DataPoints::Bright => [
            self::Readable     => true,
            self::Name         => 'Brightness',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~Intensity.100',
            self::enableAction => true
        ],
        DataPoints::BgBright => [
            self::Readable     => true,
            self::Name         => 'Brightness Background',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~Intensity.100',
            self::enableAction => true
        ],
        DataPoints::RGB => [
            self::Readable     => true,
            self::Name         => 'RGB Color',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~HexColor',
            self::enableAction => true
        ],
        DataPoints::BgRGB => [
            self::Readable     => true,
            self::Name         => 'RGB Color Background',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~HexColor',
            self::enableAction => true
        ],
        DataPoints::SAT => [
            self::Readable     => true,
            self::Name         => 'HSV Saturation',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~Intensity.100',
            self::enableAction => true
        ],
        DataPoints::BgSAT => [
            self::Readable     => true,
            self::Name         => 'HSV Saturation Background',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~Intensity.100',
            self::enableAction => true
        ],
        DataPoints::CT => [
            self::Readable         => true,
            self::Name             => 'White',
            self::Type             => VARIABLETYPE_INTEGER,
            self::Profile0         => 'Yeelight.WhiteTemp',
            self::Profile1         => 'Yeelight.WhiteTemp',
            self::Profile2         => 'Yeelight.WhiteTemp2',
            self::enableAction     => true
        ],
        DataPoints::BgCT => [
            self::Readable         => true,
            self::Name             => 'White Background',
            self::Type             => VARIABLETYPE_INTEGER,
            self::Profile0         => 'Yeelight.WhiteTemp',
            self::Profile1         => 'Yeelight.WhiteTemp',
            self::Profile2         => 'Yeelight.WhiteTemp2',
            self::enableAction     => true
        ],
        DataPoints::ColorMode => [
            self::Readable         => true,
            self::Name             => 'Current mode',
            self::Type             => VARIABLETYPE_INTEGER,
            self::Profile0         => 'Yeelight.ModeColor',
            self::Profile1         => 'Yeelight.ModeColorWNight',
            self::Profile2         => 'Yeelight.ModeWNight',
            self::enableAction     => true
        ],
        DataPoints::BgColorMode => [
            self::Readable         => true,
            self::Name             => 'Current mode Background',
            self::Type             => VARIABLETYPE_INTEGER,
            self::Profile0         => 'Yeelight.ModeColor',
            self::Profile1         => 'Yeelight.ModeColorWNight',
            self::Profile2         => 'Yeelight.ModeWNight',
            self::enableAction     => true
        ],
        DataPoints::HUE => [
            self::Readable => true,
            self::Name     => 'HSV Hue',
            self::Type     => VARIABLETYPE_STRING,
            self::Profile  => '~HTMLBox'
        ],
        DataPoints::HSV => [
            self::Readable     => false,
            self::Name         => 'HSV Color',
            self::Type         => VARIABLETYPE_STRING,
            self::enableAction => true,
            self::Profile      => [
                'COLOR_CURVE'        => 0,
                'COLOR_SPACE'        => 1,
                'SELECTION'          => 0,
                'CUSTOM_COLOR_CURVE' => '[]',
                'CUSTOM_COLOR_SPACE' => '[{"x":0.64,"y":0.33},{"x":0.3,"y":0.6},{"x":0.15,"y":0.06},{"x":0.3127,"y":0.329}]',
                'ENCODING'           => 2,
                'PRESENTATION'       => '{05CC3CC2-A0B2-5837-A4A7-A07EA0B9DDFB}',
                'PRESET_VALUES'      => '[{"Color":16007990},{"Color":16761095},{"Color":10233776},{"Color":48340},{"Color":2201331},{"Color":15277667}]'
            ]
        ],
        DataPoints::BgHUE => [
            self::Readable => true,
            self::Name     => 'HSV Hue Background',
            self::Type     => VARIABLETYPE_STRING,
            self::Profile  => '~HTMLBox'
        ],
        DataPoints::BgHSV => [
            self::Readable     => false,
            self::Name         => 'HSV Color',
            self::Type         => VARIABLETYPE_STRING,
            self::enableAction => true,
            self::Profile      => [
                'COLOR_CURVE'        => 0,
                'COLOR_SPACE'        => 1,
                'SELECTION'          => 0,
                'CUSTOM_COLOR_CURVE' => '[]',
                'CUSTOM_COLOR_SPACE' => '[{"x":0.64,"y":0.33},{"x":0.3,"y":0.6},{"x":0.15,"y":0.06},{"x":0.3127,"y":0.329}]',
                'ENCODING'           => 2,
                'PRESENTATION'       => '{05CC3CC2-A0B2-5837-A4A7-A07EA0B9DDFB}',
                'PRESET_VALUES'      => '[{"Color":16007990},{"Color":16761095},{"Color":10233776},{"Color":48340},{"Color":2201331},{"Color":15277667}]'
            ]
        ],
        DataPoints::NightLightBright => [
            self::Readable     => true,
            self::Name         => 'Brightness Nightlight',
            self::Type         => VARIABLETYPE_INTEGER,
            self::Profile      => '~Intensity.100',
            self::enableAction => false
        ],
        DataPoints::Flowing => [
            self::Readable     => true,
            self::Name         => 'Sequenz active',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Profile      => '',
            self::enableAction => false
        ],
        DataPoints::BgFlowing => [
            self::Readable     => true,
            self::Name         => 'Sequenz active Background',
            self::Type         => VARIABLETYPE_BOOLEAN,
            self::Profile      => '',
            self::enableAction => false
        ]
    ];

}
/**
 * Definiert eine RPCException.
 */
class RPCException extends \Exception
{
    public function __construct($message, $code, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Enthält einen Yeelight-RPC Datensatz.
 *
 * @method void send(array $Params (mixed)) Sendet einen Request.
 *
 * @property-read int $Id Id des RPC-Objektes
 * @property-read string $Method Command des RPC-Objektes
 * @property-read array $Params Params des RPC-Objektes
 * @phpstan-type RPC_Data_Type {RPC_Data::isCommand, RPC_Data::isResult, RPC_Data::isEvent}
 * @property-read RPC_Data_Type $Typ
 *
 * @method void set_ct_abx(array $Params) Setzt die Farbtemperatur mit Smooth-Übergang.
 * @method void bg_set_ct_abx(array $Params) Setzt die Farbtemperatur mit Smooth-Übergang.
 *
 * @method void set_hsv(array $Params) Setzt die Farbe im HSV-Farbraum.
 * @method void bg_set_hsv(array $Params) Setzt die Farbe im HSV-Farbraum.
 *
 * @method void set_scene(array $Params) Setzt die Szene.
 * @method void bg_set_scene(array $Params) Setzt die Szene.
 *
 * @method void set_bright(array $Params) Setzt die Helligkeit.
 * @method void bg_set_bright(array $Params) Setzt die Helligkeit.
 *
 * @method void set_power(array $Params) Setzt den Ein-/Aus-Zustand.
 * @method void bg_set_power(array $Params) Setzt den Ein-/Aus-Zustand.
 *
 * @method void toogle() Schaltet den Ein-/Aus-Zustand um.
 * @method void bg_toogle() Schaltet den Ein-/Aus-Zustand um.
 * @method void dev_toogle() Schaltet beide Ein-/Aus-Zustand um.
 *
 * @method void set_default() Setzt die Werkseinstellungen.
 * @method void bg_set_default() Setzt die Werkseinstellungen.
 *
 * @method void start_cf(array $Params) Startet eine Farbsequenz.
 * @method void bg_start_cf(array $Params) Startet eine Farbsequenz.
 *
 * @method void stop_cf() Stoppt eine Farbsequenz.
 * @method void bg_stop_cf() Stoppt eine Farbsequenz.
 *
 * @method void cron_add(array $Params) Fügt einen Eintrag zum Zeitplan hinzu.
 * @method void cron_del(array $Params) Entfernt einen Eintrag aus dem Zeitplan.
 * @method void cron_get(array $Params) Liest den Zeitplan aus.
 *
 * @method void set_adjust(array $Params) Justiert die Helligkeit, Farbtemperatur oder Farbe.
 * @method void bg_set_adjust(array $Params) Justiert die Helligkeit, Farbtemperatur oder Farbe.
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
     * @phpstan-type RPC_Data_Type {RPC_Data::isCommand, RPC_Data::isResult, RPC_Data::isEvent}
     * @var RPC_Data_Type
     */
    private $Typ;

    /**
     * Name der Methode.
     *
     * @var string
     */
    private $Method;

    /**
     * Enthält Fehlermeldungen der Methode.
     *
     * @var object
     */
    private $Error;

    /**
     * Parameter der Methode.
     *
     * @var object
     */
    private $Params;

    /**
     * Antwort der Methode.
     *
     * @var object
     */
    private $Result;

    /**
     * Enthält den Typ eines Event.
     *
     * @var object
     */
    private $Event;

    /**
     * Id des RPC-Objektes.
     *
     * @var int
     */
    private $Id;

    /**
     * Erstellt ein RPC_Data Objekt.
     *
     * @param string $Method [optional] Name der RPC-Methode
     * @param object $Params [optional] Parameter der Methode
     * @param int $Id [optional] Id des RPC-Objektes
     *
     * @return RPC_Data
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
            $this->Id = (int) round((explode(' ', microtime())[0] * 10000) + rand(0, 999));
        } else {
            if ($Id > 0) {
                $this->Id = $Id;
            }
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
     * Führt eine RPC-Methode aus.
     *
     * @param string $name Auszuführende RPC-Methode
     * @param array $arguments Parameter der RPC-Methode.
     */
    public function __call(string $name, array $arguments): void
    {
        $this->Method = $name;
        if (count($arguments) == 0) {
            $this->Params = [];
        } else {
            $this->Params = $arguments[0];
        }
        $this->Id = (int) round((explode(' ', microtime())[0] * 10000) + rand(0, 999));
        $this->Typ = self::$isCommand;
    }

    /**
     * Gibt die RPC Antwort auf eine Anfrage zurück.
     *
     * @return array|object|mixed|RPCException Enthält die Antwort des RPC-Server. Im Fehlerfall wird ein Objekt vom Typ RPCException zurückgegeben.
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
     * Gibt die Daten eines RPC-Event zurück.
     *
     * @return object|mixed Enthält die Daten eines RPC-Event des RPC-Server.
     */
    public function GetEvent(): mixed
    {
        if (property_exists($this, 'Event')) {
            return $this->Event;
        } else {
            return null;
        }
    }

    /**
     * Schreibt die Daten aus $Data in das RPC_Data-Objekt.
     *
     * @param string $Data Ein JSON-kodierter RPC-String vom RPC-Server.
     */
    public function CreateFromJSONString(string $Data): bool
    {
        $Json = json_decode($Data);
        if (is_null($Json)) {
            return false;
        }

        if (property_exists($Json, 'method')) {
            $this->Method = $Json->method;
        }

        if (property_exists($Json, 'params')) {
            $this->Params = $Json->params;
        }

        if (property_exists($Json, 'id')) {
            $this->Id = $Json->id;
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
     * Erzeugt einen, mit der GUID versehenen, JSON-kodierten String zum versand an den RPC-Server.
     *
     * @return string JSON-kodierter String für IPS-Dateninterface.
     */
    public function ToJSONString(): string
    {
        $RPC = new \stdClass();
        $RPC->id = $this->Id;
        $RPC->method = $this->Method;
        if (!is_null($this->Params)) {
            $RPC->params = $this->Params;
        } else {
            $RPC->params = [];
        }
        return json_encode($RPC);
    }

    /**
     * Gibt ein Objekt RPCException mit den enthaltenen Fehlermeldung des RPC-Servers zurück.
     *
     * @return RPCException Enthält die Daten der Fehlermeldung des RPC-Server.
     */
    private function GetErrorObject(): RPCException
    {
        if (property_exists($this->Error, 'code')) {
            $code = (int) $this->Error->code;
        } else {
            $code = 0;
        }
        if (property_exists($this->Error, 'message')) {
            $message = (string) $this->Error->message;
        } else {
            $message = '';
        }
        return new RPCException($message, $code);
    }
}

/* @} */
