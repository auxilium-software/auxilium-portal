<?php

/**
 * Provides custom Twig filters for handling various application-specific functionalities.
 */

namespace Auxilium\TwigHandling\Extensions;

use Auxilium\Enumerators\CookieKey;
use Auxilium\MicroTemplate;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\Security;
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
            new TwigFilter('uiprop',            [$this, 'uiprop']),
            new TwigFilter('uitxt',             [$this, 'uitxt']),
            new TwigFilter('uitxtrt',           [$this, 'uitxtrt']),
            new TwigFilter('ndtitle',           [$this, 'ndtitle']),
            new TwigFilter('ndsentence',        [$this, 'ndsentence']),
            new TwigFilter('uihdg',             [$this, 'uihdg']),
            new TwigFilter('unpack_string',     [$this, 'unpack_string']),
            new TwigFilter('b64_url_safe',      [$this, 'b64_url_safe']),
            new TwigFilter('un_b64_url_safe',   [$this, 'un_b64_url_safe']),
            new TwigFilter('human_filesize',    [$this, 'human_filesize']),
            new TwigFilter('dnd',               [$this, 'dnd']),
        ];
    }

    /**
     *
     */
    public function uiprop($string): string
    {
        return MicroTemplate::data_type_to_human_name($string, CookieHandling::GetCookieValue(CookieKey::LANGUAGE));
    }

    public function uitxt($string): string
    {
        return MicroTemplate::ui_text($string, CookieHandling::GetCookieValue(CookieKey::LANGUAGE));
    }

    public function uitxtrt($string): string
    {
        // return MicroTemplate::ui_text_root($string, CookieHandling::GetCookieValue("lang"), $this->twigVariables);
        return MicroTemplate::ui_text_root($string, CookieHandling::GetCookieValue(CookieKey::LANGUAGE), [

            ]
        );
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

    public function uihdg($string): string
    {
        return MicroTemplate::ui_heading($string, CookieHandling::GetCookieValue(CookieKey::LANGUAGE));
    }

    public function unpack_string($string): string
    {
        return MicroTemplate::from_packed_template($string, CookieHandling::GetCookieValue(CookieKey::LANGUAGE));
    }

    public function b64_url_safe($string): string
    {
        return EncodingTools::Base64EncodeURLSafe($string);
    }

    public function un_b64_url_safe($string): string
    {
        return EncodingTools::Base64DecodeURLSafe($string);
    }

    /**
     * Converts a numeric string representing a file size into a human-readable format.
     *
     * @param string $string The input numeric string to be converted.
     *
     * @return string The formatted file size with appropriate units (B, KiB, MiB, GiB, or TiB).
     */
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

    // Macro to insert a dynamically loaded node view
    public function dnd($path): string
    {
        $rid = Security::GeneratePseudoRandomBytes(length: 16);
        $rid = bin2hex($rid);
        return "<span id=\"dynamic_inline_node_element_$rid\"></span><script>document.getElementById(\"dynamic_inline_node_element_$rid\").appendChild((new InlineNodeView(\"$path\")).render())</script>";
    }
}
