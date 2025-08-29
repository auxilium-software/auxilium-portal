<?php

namespace Auxilium\TwigHandling\Extensions;

use Auxilium\Enumerators\CookieKey;
use Auxilium\MicroTemplate;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CommonFunctions extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('proplist', [$this, 'proplist'], ['is_safe' => ['html']]),
            new TwigFunction('ui_template', [$this, 'ui_template'], ['is_safe' => ['html']]),
        ];
    }

    // Macro to insert a dynamically loaded property list
    public function proplist(
        $path,
        $hidden_props = [],
        $compact = false,
        $sort = null,
        $recursive = false
    ): string
    {
        $rid = Security::GeneratePseudoRandomBytes(length: 16);
        $rid = bin2hex($rid);
        return "<span id=\"dynamic_property_list_element_$rid\"></span><script>document.getElementById(\"dynamic_property_list_element_$rid\").appendChild((new PropertyList(\"$path\", " . ($compact ? "true" : "false") . ", " . json_encode($hidden_props) . ", " . json_encode($sort) . ", " . ($recursive ? "true" : "false") . ")).render())</script>";
    }

    public function ui_template(
        $path,
        $template_variables = []
    ): string
    {
        return (string)new MicroTemplate(
            "ui_templates/" . $path,
            // $this->twigVariables["selected_lang"],
            CookieHandling::GetCookieValue(CookieKey::LANGUAGE),
            $template_variables,
            false
        );
    }
}
