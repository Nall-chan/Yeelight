<?php

declare(strict_types=1);

namespace Yeelight;

/**
 * DebugHelper ergänzt SendDebug um die Möglichkeit Array und Objekte auszugeben.
 */
trait DebugHelper
{
    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @param string             $Message Nachricht für Data.
     * @param mixed $Data    Daten für die Ausgabe.
     *
     * @param int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug(string $Message, mixed $Data, int $Format): bool
    {
        if (is_a($Data, '\Yeelight\YeelightRPC_Data')) {
            /** @var \Yeelight\YeelightRPC_Data $Data */
            if ($Data->Typ == YeelightRPC_Data::$isResult) {
                $Message .= ':' . $Data->Id;
                if (is_null($Data->Error)) {
                    $DebugData = $Data->Result;
                    $this->SendDebug($Message, $DebugData, 0);
                } else {
                    $DebugData = $Data->Error;
                    $this->SendDebug($Message, $DebugData, 0);
                }
            } else {
                if (is_null($Data->Id)) {
                    $Message .= ':' . $Data->Id;
                }
                $this->SendDebug($Message . ':Method', $Data->Method, $Format);
                $Message .= ':Params';
                $DebugData = $Data->Params;
                $this->SendDebug($Message, $DebugData, 0);
            }
        } elseif (is_a($Data, '\Yeelight\YeelightRPCException')) {
            /** @var \Yeelight\YeelightRPCException $Data */
            $this->SendDebug('Error(' . $Data->getCode() . ')', $Data->getMessage(), 0);
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
            }
        } elseif (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
            }
        } elseif (is_bool($Data)) {
            parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                parent::SendDebug($Message, $Data, $Format);
            } else {
                $this->LogMessage($Message . ':' . (string) $Data, KL_DEBUG);
            }
        }
        return true;
    }
}
