<?php

namespace Auxilium\Utilities;

use DateTime;

class Logging
{
    private static string $LogDirectory = __DIR__ . "/../../LocalStorage/DevCache";
    public static function LogAPIRequest(): void
    {
        $filePath = self::$LogDirectory . "/Requests.log";

        $data = (new DateTime())->format('c') . ': ';
        $data .= '(' . $_SERVER['REMOTE_ADDR'] . ') ';
        $data .= '[' . $_SERVER['REQUEST_METHOD'] . '] ';
        $data .= $_SERVER['REQUEST_URI'];

        file_put_contents(
            $filePath,
            $data . "\n",
            FILE_APPEND
        );
    }

}