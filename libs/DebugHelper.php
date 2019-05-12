<?php

namespace Yeelight;

/**
 * DebugHelper ergänzt SendDebug um die Möglichkeit Array und Objekte auszugeben.
 * 
 */
trait DebugHelper
{
    /**
     * Ergänzt SendDebug um Möglichkeit Objekte und Array auszugeben.
     *
     * @access protected
     * @param string $Message Nachricht für Data.
     * @param TXB_API_Data|mixed $Data Daten für die Ausgabe.
     * @return int $Format Ausgabeformat für Strings.
     */
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, 'YeelightRPC_Data')) {
            /* @var $Data YeelightRPC_Data */

            if ($Data->Typ == YeelightRPC_Data::$isResult) {
                $Message .= ':' . $Data->Id;
                if (is_null($Data->Error)) {
                    //$DebugData = print_r($Data->Result, true);
                    $DebugData = $Data->Result;
                    $this->SendDebug($Message, $DebugData, 0);
                } else {
//                    $DebugData = print_r($Data->Error, true);
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
        } elseif (is_object($Data)) {
            foreach ($Data as $Key => $DebugData) {

                $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
            }
        } else if (is_array($Data)) {
            foreach ($Data as $Key => $DebugData) {
                $this->SendDebug($Message . ':' . $Key, $DebugData, 0);
            }
        } else if (is_bool($Data)) {
            parent::SendDebug($Message, ($Data ? 'TRUE' : 'FALSE'), 0);
        } else {
            if (IPS_GetKernelRunlevel() == KR_READY) {
                parent::SendDebug($Message, $Data, $Format);
            } else {
                $this->LogMessage($Message . ':' . (string) $Data, KL_DEBUG);
            }
        }
    }

}
