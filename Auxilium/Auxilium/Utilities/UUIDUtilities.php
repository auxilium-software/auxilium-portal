<?php

namespace Auxilium\Utilities;

class UUIDUtilities
{
    public static string $Regex = '[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}';

    public static function IsValid(string $uuid): bool
    {
        return preg_match('/^' . self::$Regex . '$/', $uuid) === 1;
    }

    public static function CreateV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return sprintf('%08x-%04x-%04x-%04x-%012x',
                       unpack('N', substr($data, 0, 4))[1],
                       unpack('n', substr($data, 4, 2))[1],
                       unpack('n', substr($data, 6, 2))[1],
                       unpack('n', substr($data, 8, 2))[1],
                       unpack('N', substr($data, 10, 4))[1] << 16 | unpack('n', substr($data, 14, 2))[1]
        );
    }
}