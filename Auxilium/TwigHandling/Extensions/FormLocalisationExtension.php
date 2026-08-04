<?php

namespace Auxilium\TwigHandling\Extensions;

use SimpleXMLElement;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FormLocalisationExtension extends AbstractExtension
{
    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @var callable():string
     */
    private $localeResolver;

    private string $defaultLocale;

    /**
     * @param callable():string $localeResolver Returns the current request locale, e.g. 'cy' or 'en-GB'.
     * @param string            $defaultLocale  Used when nothing in the fallback chain matches.
     */
    public function __construct(callable $localeResolver, string $defaultLocale = 'en-GB')
    {
        $this->localeResolver = $localeResolver;
        $this->defaultLocale  = $defaultLocale;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('localise', [$this, 'localise']),
        ];
    }

    /**
     * @param mixed       $node   A .aux3form field (SimpleXMLElement or array) holding <translation> children, or a plain string.
     * @param string|null $locale Override the active locale for this call.
     */
    public function localise(mixed $node, ?string $locale = null): string
    {
        // plain text: an un-migrated field, or already-resolved text. pass through.
        if (is_scalar($node))
        {
            return (string) $node;
        }
        if ($node === null)
        {
            return '';
        }

        $translations = $this->extractTranslations($node);

        // no <translation> children found.
        // treat the node's own text as the value, so the filter is safe to apply to specs that haven't been migrated yet.
        if ($translations === [])
        {
            return is_object($node) ? trim((string) $node) : '';
        }

        $locale = $locale ?? ($this->localeResolver)();

        return $this->pick($translations, (string) $locale);
    }

    /**
     * @return array<string,string> normalised-langtag => text
     */
    private function extractTranslations($node): array
    {
        $out = [];

        if ($node instanceof SimpleXMLElement)
        {
            foreach ($node->translation as $translation)
            {
                $attrs = $translation->attributes(self::XML_NS);
                $lang = $attrs !== null ? trim((string) $attrs->lang) : '';

                if ($lang === '')
                {
                    $lang = $this->defaultLocale;
                }

                $out[$this->normalise($lang)] = trim((string) $translation);
            }

            return $out;
        }

        if (is_array($node))
        {
            $items = $node['translation'] ?? $node;

            // a single <translation> may have decoded to one assoc array rather than a list of them - wrap it so the loop below is uniform
            if (isset($items['@attributes']) || isset($items['#text']) || isset($items['value']))
            {
                $items = [$items];
            }

            foreach ((array) $items as $item)
            {
                if (is_array($item))
                {
                    // being a tad unnecessary here, but just checking for possibilities
                    $lang = $item['@attributes']['lang']
                        ?? $item['@xml:lang']
                        ?? $item['xml:lang']
                        ?? $item['lang']
                        ?? $this->defaultLocale;
                    $text = $item['#text'] ?? $item['value'] ?? '';
                }
                else
                {
                    $lang = $this->defaultLocale;
                    $text = (string) $item;
                }

                $out[$this->normalise((string) $lang)] = trim((string) $text);
            }
        }

        return $out;
    }

    private function pick(array $translations, string $locale): string
    {
        foreach ($this->candidates($locale) as $candidate)
        {
            if (isset($translations[$candidate]))
            {
                return $translations[$candidate];
            }
        }

        // configured default, then whatever is first as a last resort
        // just so text is never blank just because the active locale is missing
        $default = $this->normalise($this->defaultLocale);
        if (isset($translations[$default]))
        {
            return $translations[$default];
        }

        return $translations === [] ? '' : (string) reset($translations);
    }

    /**
     * Build the lookup order for a locale, e.g. 'cy-GB' -> ['cy-gb', 'cy'].
     *
     * @return list<string>
     */
    private function candidates(string $locale): array
    {
        $locale = $this->normalise($locale);
        if ($locale === '')
        {
            return [];
        }

        $candidates = [$locale];
        if (str_contains($locale, '-'))
        {
            $candidates[] = explode('-', $locale)[0];
        }

        return $candidates;
    }

    /**
     * lowercase + hyphenate so "en_GB", "en-GB" and "en-gb" all match.
     * BCP 47 tags are case-insensitive whereas xml element/attribute values are not
     */
    private function normalise(string $langtag): string
    {
        return strtolower(str_replace('_', '-', trim($langtag)));
    }
}
