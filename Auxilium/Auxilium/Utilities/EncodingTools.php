<?php

/**
 * Utility class providing encoding tools for Base64, UUID, and strings.
 */

namespace Auxilium\Utilities;

/**
 * A utility class that provides encoding tools for various operations.
 */
class EncodingTools
{
    /**
     * Encodes the given data to a URL-safe Base64 string.
     *
     * Replaces characters that are not URL-safe ('+' and '/') with safe alternatives
     * ('-' and '_') and removes any padding '=' characters from the encoded string.
     *
     * @param string $data The data to be Base64 encoded.
     *
     * @return string The URL-safe Base64 encoded string.
     */
    public static function Base64EncodeURLSafe($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodes a URL-safe base64-encoded string.
     *
     * Converts a base64-encoded string that uses URL-safe characters back to its original decoded form.
     * The method ensures proper padding and character replacement to handle URL compatibility.
     *
     * @param string $data The URL-safe base64-encoded string to decode.
     *
     * @return false|string The decoded string, or false on failure.
     */
    public static function Base64DecodeURLSafe($data): false|string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }


    /**
     * Packs a UUID string into its binary representation.
     *
     * Converts a standard UUID string into a 16-byte binary format by removing dashes
     * and transforming the hexadecimal sequence.
     *
     * @param string $uuid_string The UUID string to be packed into binary representation.
     *
     * @return false|string The binary representation of the UUID, or false on failure.
     */
    public static function PackUUID($uuid_string): false|string
    {
        return hex2bin(str_replace('-', '', $uuid_string));
    }

    /**
     * Converts a binary UUID to its string representation.
     *
     * Transforms a binary UUID into a human-readable string format by extracting
     * and organizing hexadecimal segments with standard UUID hyphen delimiters.
     *
     * @param string $uuid_bin The binary representation of the UUID.
     *
     * @return string The formatted UUID string representation.
     */
    public static function UnpackUUID($uuid_bin): string
    {
        $uuid_array = bin2hex($uuid_bin);
        $uuid_string = substr($uuid_array, 0, 8) . "-" . substr($uuid_array, 8, 4) . "-" . substr($uuid_array, 12, 4) . "-" . substr($uuid_array, 16, 4) . "-" . substr($uuid_array, 20, 32);
        return $uuid_string;
    }

    /**
     * Generates a new UUID (Universally Unique Identifier).
     *
     * Creates a version 4 UUID using random bytes. The random bytes are generated
     * using a cryptographically secure pseudo-random number generator. The method
     * ensures the UUID complies with the standard format by setting the appropriate
     * version and variant bits.
     *
     * @return string The generated UUID in the standard format (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).
     */
    public static function GenerateNewUUID(): string
    {
        $data = Security::GeneratePseudoRandomBytes(length: 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $uuid;
    }

    /**
     * Encodes a string using RFC 2047 encoding.
     *
     * Converts a given string to an RFC 2047 encoded format suitable for use in email headers,
     * escaping necessary characters to ensure compatibility and proper formatting.
     *
     * @param string $string The string to encode.
     *
     * @return string The RFC 2047 encoded string with escaped characters.
     */
    public static function RC2047Encode($string): string
    {
        return addcslashes(mb_encode_mimeheader($string, "UTF-8", "Q"), '"');
    }
}
