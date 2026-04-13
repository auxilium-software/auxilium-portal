<?php

namespace Auxilium\TwigHandling\Extensions;

use Auxilium\Enumerators\CookieKey;
use Auxilium\MicroTemplate;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\Security;
use Auxilium\Utilities\SecurityUtilities;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CommonFunctions extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('GeneratePseudoRandomBytes',       [$this, 'generatePseudoRandomBytes'],       ['is_safe' => ['html']]),
            new TwigFunction('GeneratePseudoRandomCharacters',  [$this, 'generatePseudoRandomCharacters'],  ['is_safe' => ['html']]),
            new TwigFunction('GetSystemConfiguration',          [$this, 'getSystemConfiguration'],          ['is_safe' => ['html']]),
            new TwigFunction('GetUserConfiguration',            [$this, 'getUserConfiguration'],            ['is_safe' => ['html']]),
        ];
    }

    public function generatePseudoRandomBytes(int $length): string
    {
        return SecurityUtilities::GeneratePseudoRandomBytes(length: $length);
    }

    public function generatePseudoRandomCharacters(int $length): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $stringBuilder = '';

        for ($i = 0; $i < $length; $i++)
        {
            $stringBuilder .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $stringBuilder;
    }
    public function getSystemConfiguration(string ...$path)
    {
        $temp = ConfigurationUtilities::GetSystemConfiguration();
        foreach($path as $p)
        {
            if(array_key_exists($p, $temp))
            {
                $temp = $temp[$p];
            }
            else
            {
                return null;
            }
        }
        return $temp;
    }
    public function getUserConfiguration(string ...$path)
    {
        $temp = ConfigurationUtilities::GetUserConfiguration();
        foreach($path as $p)
        {
            if(array_key_exists($p, $temp))
            {
                $temp = $temp[$p];
            }
            else
            {
                return null;
            }
        }
        return $temp;
    }
}
