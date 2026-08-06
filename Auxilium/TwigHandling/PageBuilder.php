<?php

namespace Auxilium\TwigHandling;

use Auxilium\Enumerators\CookieKey;
use Auxilium\Enumerators\SessionKey;
use Auxilium\Exceptions\ApiException;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\Extensions\CommonFilters;
use Auxilium\TwigHandling\Extensions\CommonFunctions;
use Auxilium\TwigHandling\Extensions\FormLocalisationExtension;
use Auxilium\Utilities\LocalisationUtilities;
use Auxilium\Utilities\SecurityUtilities;
use Auxilium\Utilities\SessionUtilities;
use Auxilium\Utilities\SystemSettingsUtilities;
use JetBrains\PhpStorm\NoReturn;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class PageBuilder
{
    private Environment $twig;

    public function __construct(bool $useAuth)
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../Templates');
        $this->twig = new Environment($loader, [
            'debug' => true,
            'cache' => false,
        ]);


        $this->twig->addGlobal('style_options', []);
        $this->twig->addGlobal('_INLINE_NODE_EXPANDED_', false);
        $this->twig->addGlobal('_INLINE_NODE_NEW_TAB_', false);
        $this->twig->addGlobal('_SYSTEM_BULLETIN_', APIInteractions::Get('/api/v3/system-bulletin', [], false)->Payload);
        $this->twig->addGlobal('_INSTANCE_NAME_', SystemSettingsUtilities::GetInstanceName());
        $this->twig->addGlobal('_LOGO_PATH_', SystemSettingsUtilities::GetLogoPath());
        $this->twig->addGlobal('_LOGO_CONTRAST_PATH_', SystemSettingsUtilities::GetLogoContrastPath());
        $this->twig->addGlobal('_SELECTED_LANGUAGE_', LocalisationUtilities::GetActiveLocale());

        if ($useAuth)
        {
            SecurityUtilities::RequireLogin();

            $this->twig->addGlobal('_IS_LOGGED_IN_', true);
            $this->twig->addGlobal('_IS_ADMIN_', SecurityUtilities::IsAdmin());
            $this->twig->addGlobal('_CURRENTLY_LOGGED_IN_USER_USER_ID_', SecurityUtilities::GetUserId());
            $this->twig->addGlobal('_CURRENTLY_LOGGED_IN_USER_FULL_NAME_', SecurityUtilities::GetUserName());
        }
        else
        {
            $this->twig->addGlobal('_IS_LOGGED_IN_', false);
        }

        if (isset($_COOKIE['style']))
        {
            $this->twig->addGlobal('head_asset_options', explode(' ', $_COOKIE['style']));
        }


        $this->twig->addExtension(new CommonFilters());
        $this->twig->addExtension(new CommonFunctions());
        $this->twig->addExtension(new FormLocalisationExtension(
            fn() => LocalisationUtilities::GetActiveLocale(),
            LocalisationUtilities::DEFAULT_LOCALE
        ));


        $this->checkServerAccess();
    }

    private function checkServerAccess(): void
    {
        $ping = APIInteractions::Get('/api/v3/server/ping', [], false);

        if ($ping->StatusCode === 403 || $ping->StatusCode === 429)
        {
            echo $this->twig->render('/VirtualPages/IpBlockedErrorPage.html.twig', [
                'ErrorPayload' => $ping->Payload,
            ]);
            exit();
        }
    }

    #[NoReturn]
    public static function AutoRender(array $variables = []): void
    {
        self::Render(
            template: self::guessTargetTwigFile(),
            variables: $variables,
            useAuth: true
        );
    }

    #[NoReturn]
    public static function AutoRenderUnsafe(array $variables = []): void
    {
        self::Render(
            template: self::guessTargetTwigFile(),
            variables: $variables,
            useAuth: false
        );
    }

    #[NoReturn]
    public static function Render(string $template, array $variables = [], bool $useAuth = true): void
    {
        $builder = new self($useAuth);
        echo $builder->twig->render($template, $variables);
        exit();
    }

    #[NoReturn]
    public static function OfflineRender(string $template, array $variables = []): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../Templates');
        $twig = new Environment($loader, [
            'debug' => true,
            'cache' => false,
        ]);
        $twig->addGlobal('style_options', []);
        $twig->addGlobal('_INLINE_NODE_EXPANDED_', false);
        $twig->addGlobal('_INLINE_NODE_NEW_TAB_', false);
        $twig->addGlobal('_SELECTED_LANGUAGE_', LocalisationUtilities::GetActiveLocale());
        $twig->addGlobal('_SYSTEM_BULLETIN_',[]);
        $twig->addGlobal('_INSTANCE_NAME_', "");
        $twig->addGlobal('_LOGO_CONTRAST_PATH_', "");
        $twig->addExtension(new CommonFilters());
        // $twig->addExtension(new CommonFunctions());
        echo $twig->render($template, $variables);
        die();
    }

    #[NoReturn]
    public static function RenderInternalSystemError(Throwable $ex): void
    {
        http_response_code(500);

        if ($ex instanceof ApiException) {
            $technicalDetails = sprintf(
                "Exception Type:\n    %s\nMessage:\n    %s\nURI:\n    %s%s",
                get_class($ex),
                $ex->getMessage(),
                $_SERVER['HTTP_HOST'],
                $_SERVER['REQUEST_URI']
            );

            self::OfflineRender(
                template: '/ErrorPages/InternalSystemErrorErrorPage.html.twig',
                variables: ['technical_details' => $technicalDetails],
            );
        }

        throw $ex;
    }

    #[NoReturn]
    public static function Render404(): void
    {
        http_response_code(404);
        self::Render(
            template: '/ErrorPages/404.html.twig',
            variables: [],
            useAuth: false
        );
    }

    /**
     * Determines the Twig template path based on the current REQUEST_URI.
     *
     * @throws \RuntimeException If the template file does not exist.
     */
    private static function guessTargetTwigFile(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = preg_replace('/[^a-zA-Z0-9\/_\-.]/', '', $uri); // Sanitize

        $twigFile = str_replace('.php', '.html.twig', $uri);

        if (!str_ends_with($twigFile, '.html.twig')) {
            $twigFile .= '.html.twig';
        }

        $fullPath = __DIR__ . '/../../Templates/Pages' . $twigFile;

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Template not found: {$twigFile}");
        }

        return 'Pages' . $twigFile;
    }
}
