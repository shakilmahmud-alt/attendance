<?php

namespace App\Helpers;

use Rats\Zkteco\Lib\ZKTeco;

class FingerHelper
{
    public function init($ip, $port = 4370): ZKTeco
    {
        return new ZKTeco($ip, $port);
    }

    public function getStatus(ZKTeco $zk): bool
    {
        try {
            return @$zk->connect();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getStatusFormatted(ZKTeco $zk): string
    {
        return $this->getStatus($zk) ? "Active" : "Deactivated";
    }

    public function getSerial(ZKTeco $zk)
    {
        try {
            if ($this->getStatus($zk)) {
                $serial = $zk->serialNumber();
                if ($serial && strpos($serial, '=') !== false) {
                    return substr(strstr($serial, '='), 1);
                }
                return $serial ?: 'ZKT-' . rand(10000, 99999);
            }
        } catch (\Throwable $e) {
            // Return false if unreachable
        }
        return false;
    }
}
