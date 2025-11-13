<?php

/**
 * Provides custom Twig filters for handling various application-specific functionalities.
 */

namespace Auxilium\TwigHandling\Extensions;

use Auxilium\Enumerators\CookieKey;
use Auxilium\MicroTemplate;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\EncodingUtilities;
use Auxilium\Utilities\LocalisationUtilities;
use Auxilium\Utilities\Security;
use Auxilium\Utilities\UUIDUtilities;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides a set of custom Twig filters for use in templates.
 */
class CommonFilters extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('translate',                         [$this, 'translate'],                           ['is_safe' => ['html']]),
            new TwigFilter('b64_url_safe',                      [$this, 'b64_url_safe']),
            new TwigFilter('un_b64_url_safe',                   [$this, 'un_b64_url_safe']),
            new TwigFilter('format_as_sentence',                [$this, 'format_as_sentence']),
            new TwigFilter('human_filesize',                    [$this, 'human_filesize']),
            new TwigFilter('is_uuid',                           [$this, 'is_uuid']),
            new TwigFilter('is_auxlfs_url',                     [$this, 'is_auxlfs_url']),
            new TwigFilter('is_auxmsg_url',                     [$this, 'is_auxmsg_url']),
            new TwigFilter('extract_file_id_from_auxlfs_url',   [$this, 'extract_file_id_from_auxlfs_url']),
            new TwigFilter('ndtitle',                           [$this, 'ndtitle']),
            new TwigFilter('ndsentence',                        [$this, 'ndsentence']),
            new TwigFilter('base64_encode',                     [$this, 'base64_encode']),
            new TwigFilter('base64_decode',                     [$this, 'base64_decode']),
            new TwigFilter('hex2bin',                           [$this, 'hex2bin']),
        ];
    }


    public function translate(string $string, array $substitutions = []): string
    {
        return LocalisationUtilities::Translate($string, $substitutions);
    }

    public function b64_url_safe($string): string
    {
        return EncodingUtilities::Base64EncodeURLSafe($string);
    }

    public function un_b64_url_safe($string): string
    {
        return EncodingUtilities::Base64DecodeURLSafe($string);
    }

    public function format_as_sentence($string): string
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    public function human_filesize(string $string): string
    {
        $size = (int)$string;
        if($size <= 256)
        {
            return $size . " B";
        }
        elseif($size <= 256 * (1024 ** 1))
        {
            return substr($size / (1024 ** 1), 0, 3) . " KiB";
        }
        elseif($size <= 256 * (1024 ** 2))
        {
            return substr($size / (1024 ** 2), 0, 3) . " MiB";
        }
        elseif($size <= 256 * (1024 ** 3))
        {
            return substr($size / (1024 ** 3), 0, 3) . " GiB";
        }
        else
        {
            return substr($size / (1024 ** 4), 0, 3) . " TiB";
        }
    }

    public function is_uuid(string|array|null $string): string
    {
        if(is_string($string))
        {
            return UUIDUtilities::IsValid($string);
        }
        return false;
    }

    public function is_auxlfs_url(string $string): string
    {
        // auxlfs://%%couchdb%%/ - literal prefix
        // [0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12} - UUID format
        // \?size=\d+ - size parameter with digits
        // &hash=[0-9a-f]{40} - hash parameter with 40 hex characters (SHA-1)

        $pattern = '/^auxlfs:\/\/%%couchdb%%\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\?size=\d+&hash=[0-9a-f]{40}$/';

        return preg_match($pattern, $string) === 1;
    }

    public function is_auxmsg_url(string $string): string
    {
        // auxmsg://%%couchdb%%/ - literal prefix
        // [0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12} - UUID format

        $pattern = '/^auxmsg:\/\/%%couchdb%%\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

        return preg_match($pattern, $string) === 1;
    }

    public function extract_file_id_from_auxlfs_url(string $string): string
    {
        $pattern = '/^auxlfs:\/\/%%couchdb%%\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\?size=\d+&hash=[0-9a-f]{40}$/';
        preg_match($pattern, $string, $matches);
        return $matches[1] ?? '';
    }

    public function ndtitle($string): string
    {
        $pcs = mb_split(" ", $string);
        foreach($pcs as &$pc)
        {
            $pc = mb_strtoupper(mb_substr($pc, 0, 1)) . mb_substr($pc, 1);
        }
        return implode(" ", $pcs);
    }

    public function ndsentence($string): string
    {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    public function base64_encode($string): string
    {
        return base64_encode($string);
    }
    public function base64_decode($string): string
    {
        return base64_decode($string);
    }
    public function hex2bin($string): string
    {
        return hex2bin($string);
    }
}
