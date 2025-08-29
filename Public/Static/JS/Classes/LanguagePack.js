class LanguagePack
{
    static definitionCache = {};
    static loadingDefinitions = [];
    #definition = null;
    #onLoad = [];
    #loaded = false;

    constructor(lang)
    {
        lang = lang.toLowerCase();
        if (LanguagePack.definitionCache.hasOwnProperty(lang))
        {
            this.#definition = LanguagePack.definitionCache[lang];
            this.#loaded = true;
        }
        else if (LanguagePack.loadingDefinitions.hasOwnProperty(lang))
        {
            let thislp = this;
            LanguagePack.loadingDefinitions[lang].#onLoad.push(() =>
            {
                this.#definition = LanguagePack.definitionCache[lang];
                this.#loaded = true;
                for (let i = 0; i < this.#onLoad.length; i++)
                {
                    this.#onLoad[i]();
                }
                this.#onLoad = [];
            });
        }
        else
        {
            let http = new XMLHttpRequest();
            http.open("GET", "/assets/language-packs/" + lang, true);
            http.send();

            let responseHad = false;
            LanguagePack.loadingDefinitions[lang] = this;

            http.onreadystatechange = (e) =>
            {
                if (http.readyState == 4 && !responseHad)
                {
                    let response = {};
                    responseHad = true;

                    try
                    {
                        response = JSON.parse(http.responseText);
                        this.#definition = response["pack"];
                        LanguagePack.definitionCache[lang] = this.#definition;

                        this.#loaded = true;
                        for (let i = 0; i < this.#onLoad.length; i++)
                        {
                            this.#onLoad[i]();
                        }
                        this.#onLoad = [];
                    } catch (e)
                    {
                        console.log(http.responseText);
                        console.log(e);
                    }
                }
            }
        }
    }

    static whenTemplateAvailable(key, callback)
    {
        let lang = document.documentElement.lang.split("-")[0];
        let lp = new LanguagePack(lang);
        if (lp.#loaded)
        {
            callback(lp.returnTemplateIfAvailable(key));
        }
        else
        {
            lp.#onLoad.push(() =>
            {
                callback(lp.returnTemplateIfAvailable(key));
            });
        }
    }

    static getPack(lang = null)
    {
        if (lang == null)
        {
            lang = document.documentElement.lang.split("-")[0];
        }
        lang = lang.toLowerCase();
        return new Promise((resolve, reject) =>
        {
            let lp = new LanguagePack(lang);
            if (lp.#loaded)
            {
                resolve(lp);
            }
            else
            {
                lp.#onLoad.push(() =>
                {
                    resolve(lp);
                });
            }
        });
    }

    returnTemplateIfAvailable(key)
    {
        let path = key.split("/");
        let current = this.#definition;
        while (path.length > 0)
        {
            let prop = path.shift();
            if (current.hasOwnProperty(prop))
            {
                current = current[prop];
            }
            else
            {
                //console.log("no prop " + prop);
                //console.log(current);
                break;
            }
        }
        return (typeof current === 'string') ? current : null;
    }

    returnDefinition()
    {
        return this.#definition;
    }
}
