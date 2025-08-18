<?php

namespace Auxilium\TwigHandling;

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\Extensions\CommonFilters;
use Auxilium\TwigHandling\Extensions\CommonFunctions;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class PageBuilder2
{
    private static array $AdditionalVariables = [];
    public FilesystemLoader $loader;
    public Environment $twig;

    public function __construct()
    {
        $this->loader = new FilesystemLoader(__DIR__ . "/../../Templates/");
        $this->twig = new Environment($this->loader, [
                "debug" => true,
                "cache" => false,
            ]
        );


        $this->twig->addGlobal('style_options', []);

        $this->twig->addGlobal('INSTANCE_BRANDING_LOGO', INSTANCE_BRANDING_LOGO);
        $this->twig->addGlobal('INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR', INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR);
        $this->twig->addGlobal('INSTANCE_BRANDING_NAME', INSTANCE_BRANDING_NAME);
        $this->twig->addGlobal('INSTANCE_BRANDING_DOMAIN_NAME', INSTANCE_BRANDING_DOMAIN_NAME);
        $this->twig->addGlobal('INSTANCE_DOMAIN_NAME', INSTANCE_DOMAIN_NAME);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_EMAIL', INSTANCE_INFO_MAIN_EMAIL);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_PHONE', INSTANCE_INFO_MAIN_PHONE);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS', INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_TEXT', INSTANCE_INFO_MAIN_TEXT);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS', INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS);
        $this->twig->addGlobal('INSTANCE_INFO_MAINTAINER_NAME', INSTANCE_INFO_MAINTAINER_NAME);
        $this->twig->addGlobal('INSTANCE_INFO_MAINTAINER_EMAIL', INSTANCE_INFO_MAINTAINER_EMAIL);
        $this->twig->addGlobal('INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME', INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME);
        $this->twig->addGlobal('INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL', INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL);
        $this->twig->addGlobal('INSTANCE_UUID', INSTANCE_UUID);


        $this->twig->addExtension(new CommonFilters());
        $this->twig->addExtension(new CommonFunctions());


        // Serve the correct language *if* the cookie is set
        if(isset($_COOKIE["lang"]))
        {
            switch($_COOKIE["lang"])
            {
                case "cy":
                    $this->twig->addGlobal('selected_lang', "cy");
                    break;
                case "zh": // For testing only, this language pack is shoddy at best
                    $this->twig->addGlobal('selected_lang', "zh");
                    break;
                case "ar": // For testing only, this language pack is shoddy at best
                    $this->twig->addGlobal('selected_lang', "ar");
                    break;
                case "en":
                default:
                    $this->twig->addGlobal('selected_lang', "en");
                    break;
            }
        }

        // Grab style options if present
        if(isset($_COOKIE["style"]))
        {
            $this->twig->addGlobal('head_asset_options', explode(" ", $_COOKIE["style"]));
        }

        try
        {
            $this->twig->addGlobal('current_user', Session::get_current()->getUser());
        }
        catch(Exception $e)
        {
            $this->twig->addGlobal('current_user', null);
        }


    }

    /**
     * Will figure out which twig template to render, and render it.
     * You can also pass through variables.
     *
     * @param array $variables Any variables you want to pass through to the template.
     * @return void
     */
    #[NoReturn] public static function AutoRender(array $variables = []): void
    {
        PageBuilder2::Render(
            template : PageBuilder2::GuessTargetTwigFile(),
            variables: $variables,
        );
    }

    #[NoReturn] public static function Render(string $template, array $variables = []): void
    {

        foreach(self::$AdditionalVariables as $key => $value)
        {
            $variables[$key] = $value;
        }

        try
        {
            echo (new PageBuilder2())->twig->render($template, $variables);
            exit();
        }
        catch(RuntimeError $e)
        {
            throw $e;
            die();
            $e = $e->getPrevious();
            PageBuilder2::RenderInternalSystemError($e);
        }
        catch(LoaderError $e)
        {
            throw $e;
            die();
        }
        catch(SyntaxError $e)
        {
            throw $e;
            die();
        }
        catch(Exception $e)
        {
            throw $e;
            die();
        }
    }

    #[NoReturn] public static function RenderInternalSystemError(Throwable $ex): void
    {
        http_response_code(500);

        if($ex instanceof DatabaseConnectionException)
        {
            $technicalDetails = "Exception Type:\n    " . get_class($ex);
            $technicalDetails .= "\nMessage:\n    " . $ex->getMessage();
            $technicalDetails .= "\nURI:\n    " . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

            self::Render(
                template : "ErrorPages/InternalSystemError.html.twig",
                variables: [
                    "technical_details" => $technicalDetails,
                ],
            );
        }
        else
        {
            throw $ex;
            die();


            echo "<pre>";
            echo get_class($ex) . "\n";
            echo htmlentities($ex->getMessage()) . "\n";
            echo htmlentities(json_encode($ex->getTrace(), JSON_PRETTY_PRINT)) . "\n";
            echo htmlentities(json_encode(get_class_methods($ex), JSON_PRETTY_PRINT));
            echo "</pre>";
        }
        die();
    }

    /**
     * Uses the $_SERVER['REQUEST_URI'] variable to figure out which twig file to target.
     * Means that you don't have to specify the twig file every time, small QoL feature.
     *
     * @return string The relative path of the template to load.
     */
    private static function GuessTargetTwigFile(): string
    {
        $phpPage = $_SERVER["REQUEST_URI"];

        $twigFile = str_replace(search: ".php", replace: ".html.twig", subject: $phpPage);
        if(!str_ends_with(haystack: $twigFile, needle: ".html.twig")) $twigFile .= ".html.twig";

        $pageTemplateDirectory = __DIR__ . "/../../Templates/Pages";

        if(!file_exists($pageTemplateDirectory . $twigFile))
        {
            echo "template not found";
            die();
        }

        return "Pages" . $twigFile;
    }

    #[NoReturn] public static function Render404(): void
    {
        http_response_code(404);
        self::Render(
            template : "Pages/node-views/404.html.twig",
            variables: [
            ],
        );
    }


    public static function AddVariable(string $variableName, mixed $variableValue): void
    {
        self::$AdditionalVariables[$variableName] = $variableValue;
    }

    public static function GetVariable(string $variableName, ?string $default = null): mixed
    {
        if(array_key_exists($variableName, self::$AdditionalVariables))
        {
            return self::$AdditionalVariables[$variableName];
        }
        if($default !== null)
        {
            return $default;
        }
        echo "variable \"" . $variableName . "\" does not exist";
        die();
    }
}
