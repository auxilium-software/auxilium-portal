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
            new TwigFilter('translate',             [$this, 'translate']),
            new TwigFilter('b64_url_safe',          [$this, 'b64_url_safe']),
            new TwigFilter('un_b64_url_safe',       [$this, 'un_b64_url_safe']),
            new TwigFilter('format_as_sentence',    [$this, 'format_as_sentence']),
            new TwigFilter('human_filesize',        [$this, 'human_filesize']),
            new TwigFilter('is_uuid',               [$this, 'is_uuid']),
            new TwigFilter('ndtitle',               [$this, 'ndtitle']),
            new TwigFilter('ndsentence',            [$this, 'ndsentence']),
        ];
    }


    public function translate($string): string
    {
        return LocalisationUtilities::translate($string);
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

    public function is_uuid(string|array $string): string
    {
        if(gettype($string) === "string")
        {
            return UUIDUtilities::IsValid($string);
        }
        return false;
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
}
