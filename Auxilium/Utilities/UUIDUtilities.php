<?php

namespace Auxilium\Utilities;

use Random\RandomException;

/**
 * Utilities to help with the usage of UUIDs.
 */
class UUIDUtilities
{
    /**
     * @var string Regex to pattern match a UUID.
     */
    public static string $Regex = '[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}';

    /**
     * Checks whether a given string is a valid UUID or not.
     *
     * @param string $uuid The string to check.
     * @return bool Whether it's a valid UUID.
     */
    public static function IsValid(string $uuid): bool
    {
        return preg_match('/^' . self::$Regex . '$/', $uuid) === 1;
    }

    /**
     * Creates a V4 UUID (the random one).
     *
     * @return string The generated V4 UUID.
     *
     * @throws RandomException Thrown if an "appropriate source of randomness can't be found"
     */
    public static function CreateV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            unpack('N', substr($data, 0, 4))[1],
                    unpack('n', substr($data, 4, 2))[1],
                    unpack('n', substr($data, 6, 2))[1],
                    unpack('n', substr($data, 8, 2))[1],
                    unpack('N', substr($data, 10, 4))[1] << 16 | unpack('n', substr($data, 14, 2))[1]
        );
    }
}
