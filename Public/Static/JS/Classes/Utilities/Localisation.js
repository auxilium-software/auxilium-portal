class Localisation
{
    static async translate(text, substitutions = {})
    {
        const cacheKey = JSON.stringify({ text, substitutions });
        if (Localisation._cache && Localisation._cache[cacheKey])
        {
            return Localisation._cache[cacheKey];
        }

        try
        {
            const response = await fetch('/API/Translate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text, substitutions }),
            });

            if (!response.ok)
            {
                console.error('Translation request failed:', response.status);
                return text;
            }

            const data = await response.json();
            if (data.success)
            {
                if (!Localisation._cache) Localisation._cache = {};
                Localisation._cache[cacheKey] = data.translated;
                return data.translated;
            }

            console.error('Translation error:', data.error);
            return text;
        }
        catch (error)
        {
            console.error('Translation fetch error:', error);
            return text;
        }
    }

    static translateSync(text)
    {
        if (Localisation._cache && Localisation._cache[text]) {
            return Localisation._cache[text];
        }

        return text;
    }

    static async preloadTranslations(texts)
    {
        if (!Localisation._cache) {
            Localisation._cache = {};
        }

        const promises = texts.map(text =>
            Localisation.translate(text).then(translated => {
                Localisation._cache[text] = translated;
            })
        );

        await Promise.all(promises);
    }
}
