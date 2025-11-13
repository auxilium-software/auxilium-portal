<?php

namespace Auxilium\TwigHandling;

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\CookieHandling;
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
        $this->twig->addGlobal('_INLINE_NODE_EXPANDED_', false);
        $this->twig->addGlobal('_INLINE_NODE_NEW_TAB_', false);
        $this->twig->addGlobal(name: "_SELECTED_LANGUAGE_",  value: CookieHandling::GetCookieValue(targetCookie: CookieKey::LANGUAGE));

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
