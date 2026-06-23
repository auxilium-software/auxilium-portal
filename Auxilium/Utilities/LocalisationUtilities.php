<?php

namespace Auxilium\Utilities;

use Auxilium\Enumerators\CookieKey;
use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\SessionHandling\CookieHandling;
use Exception;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Yaml\Yaml;

/**
 * Utilities to help with Localisation.
 *
 * Everything that needs a locale (the |translate filter, and the |localise aux3form filter)
 * reads it from GetActiveLocale() so a page can never come out half in one language and half in another.
 *
 * Precedence (high to low):
 *   1. an explicit choice made earlier this request (SetLocale memoises it)
 *   2. the account preference, when logged in and set        (cross-device)
 *   3. the cookie, which only ever holds an *explicit* choice (this device)
 *   4. Accept-Language negotiated against the supported set
 *   5. the configured default
 */
final class LocalisationUtilities
{
    /**
     * Canonical supported locales.
     * The source language is first.
     */
    public const SUPPORTED_LOCALES = ['en-GB', 'cy-GB'];
    public const DEFAULT_LOCALE = 'en-GB';

    public static ?array $DictionaryCache = null;

    /**
     * Memoised active locale for this request.
     * Also the slot an explicit choice writes to.
     */
    private static ?string $resolvedLocale = null;





    // ==================================================
    // RESOLUTION

    /**
     * The active, canonical, supported locale for this request.
     */
    public static function GetActiveLocale(): string
    {
        // 1. if the locale is already set, use that
        if (self::$resolvedLocale !== null)
        {
            return self::$resolvedLocale;
        }

        // 2. account preference wins when logged in
        //  this is what makes the choice follow the user across devices.
        if (SecurityUtilities::IsLoggedIn())
        {
            $dbLocale = self::Canonicalise(SecurityUtilities::GetLanguagePreference());
            if ($dbLocale !== null)
            {
                // database wins - keep the cookie cache in step so logout has the right value ready.
                $cookieLocale = self::Canonicalise(
                    CookieHandling::GetCookieValue(targetCookie: CookieKey::LANGUAGE, default: '')
                );
                if ($cookieLocale !== $dbLocale)
                {
                    CookieHandling::SetCookie(CookieKey::LANGUAGE, $dbLocale);
                }
                self::$resolvedLocale = $dbLocale;
                return self::$resolvedLocale;
            }
        }

        // 3. Cookie.
        //  Because we only ever write the cookie on an explicit choice (never from Accept-Language),
        //  a value here always means "the user picked this",
        //  so it's safe to trust and later promote to the DB.
        $cookieLocale = self::Canonicalise(
            CookieHandling::GetCookieValue(targetCookie: CookieKey::LANGUAGE, default: '')
        );
        if ($cookieLocale !== null)
        {
            return self::$resolvedLocale = $cookieLocale;
        }

        // 4. Negotiate the browser's preference for *display only*
        //  note: we don't persist this anywhere.
        $negotiated = self::NegotiateFromAcceptLanguage();
        if ($negotiated !== null)
        {
            return self::$resolvedLocale = $negotiated;
        }

        // 5. Fall back to the configured default.
        return self::$resolvedLocale = self::DEFAULT_LOCALE;
    }





    // ==================================================
    // WRITES

    /**
     * Record an explicit language choice (e.g. from the language switcher).
     *
     * Logged out:  cookie only.
     * Logged in:   cookie + account, so it follows them.
     *
     * @throws Exception on a failed account write.
     */
    public static function SetLocale(string $locale, bool $persistForLoggedInUser = true): void
    {
        $canonical = self::Canonicalise($locale);
        if ($canonical === null)
        {
            throw new InvalidArgumentException("Unsupported locale: {$locale}");
        }

        // always update the cookie so logged-out continuity holds on this device.
        CookieHandling::SetCookie(CookieKey::LANGUAGE, $canonical);

        if ($persistForLoggedInUser && SecurityUtilities::IsLoggedIn())
        {
            self::persistLocaleToAccount($canonical);
        }

        // make the choice effective for the remainder of this request.
        self::$resolvedLocale = $canonical;
    }

    /**
     * Call immediately after a successful login, once the JWT is in place so `/api/v3/me` is fetchable.
     * Merges the logged-out cookie choice with the stored account preference WITHOUT letting a transient cookie clobber a real saved preference:
     *
     *  - account preference set    -> it wins; sync it down into the cookie.
     *  - account preference empty  -> adopt the cookie choice into the account.
     *  - neither                   -> leave it to negotiation/default.
     */
    public static function ReconcileLocaleOnLogin(): void
    {
        $dbLocale     = self::Canonicalise(SecurityUtilities::GetLanguagePreference());
        $cookieLocale = self::Canonicalise(
            CookieHandling::GetCookieValue(targetCookie: CookieKey::LANGUAGE, default: '')
        );

        if ($dbLocale !== null)
        {
            CookieHandling::SetCookie(CookieKey::LANGUAGE, $dbLocale);
            self::$resolvedLocale = $dbLocale;
            return;
        }

        if ($cookieLocale !== null)
        {
            self::persistLocaleToAccount($cookieLocale);
            self::$resolvedLocale = $cookieLocale;
            return;
        }

        self::$resolvedLocale = null; // recompute lazily on next GetActiveLocale()
    }

    private static function persistLocaleToAccount(string $canonicalLocale): void
    {
        APIInteractions::Patch('/api/v3/me', ['languagePreference' => $canonicalLocale]);
    }





    // ==================================================
    // NORMALISATION / NEGOTIATION

    /**
     * Map any raw tag to a supported canonical locale, or null if unsupported.
     * Case-insensitive, with a base-language fallback (
     *  `cy` -> `cy-GB`,
     *  `en-US` -> `en-GB`,
     *  `zh-CN` -> `zh`
     * ).
     * BCP 47 tags are case-insensitive, XML/cookie values are not, so we normalise before comparing.
     */
    public static function Canonicalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $needle = strtolower(trim($raw));
        if ($needle === '') {
            return null;
        }

        foreach (self::SUPPORTED_LOCALES as $supported) {
            if (strtolower($supported) === $needle) {
                return $supported;
            }
        }

        $needleBase = explode('-', $needle)[0];
        foreach (self::SUPPORTED_LOCALES as $supported) {
            if (strtolower(explode('-', $supported)[0]) === $needleBase) {
                return $supported;
            }
        }

        return null;
    }

    private static function NegotiateFromAcceptLanguage(): ?string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if ($header === '') {
            return null;
        }

        // parse `cy-GB,cy;q=0.9,en;q=0.8` into [tag, q] pairs, highest q first.
        $tags = [];
        foreach (explode(',', $header) as $part)
        {
            $bits = explode(';', trim($part));
            $tag = trim($bits[0]);
            if ($tag === '' || $tag === '*')
            {
                continue;
            }

            $q = 1.0;
            if (isset($bits[1]) && str_starts_with(trim($bits[1]), 'q='))
            {
                $q = (float) substr(trim($bits[1]), 2);
            }

            $tags[] = [$tag, $q];
        }

        usort($tags, static fn(array $a, array $b): int => $b[1] <=> $a[1]);

        foreach ($tags as [$tag])
        {
            $canonical = self::Canonicalise($tag);
            if ($canonical !== null)
            {
                return $canonical;
            }
        }

        return null;
    }





    // ==================================================
    // TRANSLATION

    /**
     * @param string $text The (English) source text to translate.
     * @param array  $subs Optional placeholder => replacement substitutions.
     * @return string The translated text.
     *
     * @throws JsonException
     * @throws Exception
     */
    public static function Translate(string $text, array $subs = []): string
    {
        $language = self::GetActiveLocale();

        $translatedText = $text;

        if ($language !== self::DEFAULT_LOCALE)
        {
            if (self::$DictionaryCache === null)
            {
                self::$DictionaryCache = Yaml::parseFile(
                    filename: __DIR__ . "/../../Configuration/Localisation/Dictionary.yaml"
                );
            }

            if (
                array_key_exists($text, self::$DictionaryCache)
                && array_key_exists($language, self::$DictionaryCache[$text])
            )
            {
                $translatedText = self::$DictionaryCache[$text][$language];
            }
            else
            {
                self::logMissingTranslation($text, $language);
            }
        }

        if (!empty($subs))
        {
            $translatedText = str_replace(
                array_keys($subs),
                array_values($subs),
                $translatedText
            );
        }

        return $translatedText;
    }

    private static function logMissingTranslation(string $text, string $language): void
    {
        $filePath = __DIR__ . "/../../LocalStorage/Cache/Development/MissingTranslations.json";

        $translations = file_exists($filePath)
            ? json_decode(file_get_contents($filePath), true, flags: JSON_THROW_ON_ERROR)
            : [];

        if (!in_array($text, $translations[$language] ?? [], true))
        {
            $translations[$language][] = $text;
            file_put_contents(
                $filePath,
                json_encode($translations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }
}
