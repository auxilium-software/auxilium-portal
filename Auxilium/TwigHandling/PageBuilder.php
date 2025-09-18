<?php

namespace Auxilium\TwigHandling;

use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\Session;
use Auxilium\TwigHandling\Extensions\CommonFilters;
use Auxilium\TwigHandling\Extensions\CommonFunctions;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\JWTUtilities;
use Auxilium\Utilities\SecurityUtilities;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class PageBuilder
{
    private static array $AdditionalVariables = [];
    public FilesystemLoader $loader;
    public Environment $twig;

    public function __construct(bool $useAuth)
    {
        $this->loader = new FilesystemLoader(__DIR__ . "/../../Templates/");
        $this->twig = new Environment($this->loader, [
                "debug" => true,
                "cache" => false,
            ]
        );


        $this->twig->addGlobal('style_options', []);

        $this->twig->addGlobal('INSTANCE_BRANDING_LOGO',                        ConfigurationUtilities::GetConfiguration()["Instance"]['Branding']['Logo']);
        $this->twig->addGlobal('INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR',   ConfigurationUtilities::GetConfiguration()["Instance"]['Branding']['LogoContrast']);
        $this->twig->addGlobal('INSTANCE_BRANDING_NAME',                        ConfigurationUtilities::GetConfiguration()["Instance"]['Branding']['Name']);
        //$this->twig->addGlobal('INSTANCE_BRANDING_DOMAIN_NAME',                 ConfigurationUtilities::GetConfiguration()["Instance"]);
        //$this->twig->addGlobal('INSTANCE_DOMAIN_NAME',                          ConfigurationUtilities::GetConfiguration()["Instance"]);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_EMAIL',                      ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Primary']['EmailAddress']);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_PHONE',                      ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Primary']['Phone']['Number']);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS',        ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Primary']['Phone']['OpeningHours']);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_TEXT',                       ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Primary']['Text']['Number']);
        $this->twig->addGlobal('INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS',         ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Primary']['Text']['OpeningHours']);
        $this->twig->addGlobal('INSTANCE_INFO_MAINTAINER_NAME',                 ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Maintainer']['Name']);
        $this->twig->addGlobal('INSTANCE_INFO_MAINTAINER_EMAIL',                ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['Maintainer']['EmailAddress']);
        $this->twig->addGlobal('INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME',  ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['GeneralEnquiries']['Name']);
        $this->twig->addGlobal('INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL', ConfigurationUtilities::GetConfiguration()["Instance"]['Contacts']['GeneralEnquiries']['EmailAddress']);
        //$this->twig->addGlobal('INSTANCE_UUID',                                 ConfigurationUtilities::GetConfiguration()["Instance"]);
        $this->twig->addGlobal('INSTANCE_RECAPTCHA_SITE_KEY',                   ConfigurationUtilities::GetConfiguration()["ReCAPTCHA"]['SiteKey']);
        $this->twig->addGlobal('INSTANCE_RECAPTCHA_SECRET_KEY',                 ConfigurationUtilities::GetConfiguration()["ReCAPTCHA"]['SecretKey']);

        if($useAuth)
        {
            SecurityUtilities::RequireLogin();
            $this->twig->addGlobal(name: "_IS_LOGGED_IN_",  value: true);
            $this->twig->addGlobal(name: "_IS_ADMIN_",      value: SecurityUtilities::IsAdmin());
        }
        else
        {
            $this->twig->addGlobal(name: "_IS_LOGGED_IN_",  value: false);
        }


        $this->twig->addExtension(new CommonFilters());
        $this->twig->addExtension(new CommonFunctions());


        // Serve the correct language *if* the cookie is set
        if(isset($_COOKIE["lang"]))
        {
            switch($_COOKIE["lang"])
            {
                case "cy":
                    $this->twig->addGlobal('_SELECTED_LANGUAGE_', "cy-GB");
                    break;
                case "zh": // For testing only, this language pack is shoddy at best
                    $this->twig->addGlobal('_SELECTED_LANGUAGE_', "zh");
                    break;
                case "ar": // For testing only, this language pack is shoddy at best
                    $this->twig->addGlobal('_SELECTED_LANGUAGE_', "ar");
                    break;
                case "en":
                default:
                $this->twig->addGlobal('_SELECTED_LANGUAGE_', "en-GB");
                    break;
            }
        }

        // Grab style options if present
        if(isset($_COOKIE["style"]))
        {
            $this->twig->addGlobal('head_asset_options', explode(" ", $_COOKIE["style"]));
        }
    }

    #[NoReturn] public static function AutoRender(array $variables = []): void
    {
        PageBuilder::Render(
            template : PageBuilder::GuessTargetTwigFile(),
            variables: $variables,
            useAuth: true,
        );
    }

    #[NoReturn] public static function AutoRenderUnsafe(array $variables = []): void
    {
        PageBuilder::Render(
            template : PageBuilder::GuessTargetTwigFile(),
            variables: $variables,
            useAuth: false,
        );
    }

    #[NoReturn] public static function Render(string $template, array $variables = [], bool $useAuth = true): void
    {

        foreach(self::$AdditionalVariables as $key => $value)
        {
            $variables[$key] = $value;
        }

        try
        {
            echo (new PageBuilder($useAuth))->twig->render($template, $variables);
            exit();
        }
        catch(RuntimeError $e)
        {
            throw $e;
            die();
            $e = $e->getPrevious();
            PageBuilder::RenderInternalSystemError($e);
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
