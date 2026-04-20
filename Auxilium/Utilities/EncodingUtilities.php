<?php

/**
 * Utility class providing encoding tools for Base64, UUID, and strings.
 */

namespace Auxilium\Utilities;

/**
 * A utility class that provides encoding tools for various operations.
 */
final class EncodingUtilities
{
    /**
     * Encodes the given data to a URL-safe Base64 string.
     *
     * Replaces characters that are not URL-safe ('+' and '/') with safe alternatives ('-' and '_') and removes any padding '=' characters from the encoded string.
     *
     * @param string $data The data to be Base64 encoded.
     *
     * @return string The URL-safe Base64 encoded string.
     */
    public static function Base64EncodeURLSafe(string $data): string
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
    public static function Base64DecodeURLSafe(string $data): false|string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
