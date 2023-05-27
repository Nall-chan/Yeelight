<?php

declare(strict_types=1);

namespace Yeelight;

/*
 * @addtogroup yeelight
 * @{
 *
 * @package yeelight
 * @file          YeelightRPC.php
 * @author        Michael Tröger
 *
 */

/**
 * Definiert eine YeelightRPCException.
 */
class YeelightRPCException extends \Exception
{
    public function __construct($message, $code, \Exception $previous = null)
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
 * @property-read enum [ YeelightRPC_Data::isCommand, YeelightRPC_Data::isResult, YeelightRPC_Data::isEvent] $Typ Typ des RPC-Objektes
 */
class YeelightRPC_Data
{
    public static $isEvent = 3;
    public static $isCommand = 1;
    public static $isResult = 2;

    /**
     * Typ der Daten.
     *
     * @var enum [ YeelightRPC_Data::isCommand, YeelightRPC_Data::isResult, YeelightRPC_Data::isEvent]
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
     * Erstellt ein YeelightRPC_Data Objekt.
     *
     * @param string $Method [optional] Name der RPC-Methode
     * @param object $Params [optional] Parameter der Methode
     * @param int    $Id     [optional] Id des RPC-Objektes
     *
     * @return YeelightRPC_Data
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
        $this->Id = (int) round((explode(' ', microtime())[0] * 10000) + rand(0, 999));
        $this->Typ = self::$isCommand;
    }

    /**
     * Gibt die RPC Antwort auf eine Anfrage zurück.
     *
     * @return array|object|mixed|YeelightRPCException Enthält die Antwort des RPC-Server. Im Fehlerfall wird ein Objekt vom Typ YeelightRPCException zurückgegeben.
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
     * Schreibt die Daten aus $Data in das YeelightRPC_Data-Objekt.
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
     * Gibt ein Objekt YeelightRPCException mit den enthaltenen Fehlermeldung des RPC-Servers zurück.
     *
     * @return YeelightRPCException Enthält die Daten der Fehlermeldung des RPC-Server.
     */
    private function GetErrorObject(): YeelightRPCException
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
        return new YeelightRPCException($message, $code);
    }
}

/* @} */
