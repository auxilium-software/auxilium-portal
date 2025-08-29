class Schema
{
    static definitionCache = {};
    static onLoad = {};
    static onFail = {};
    static loadingDefinitions = [];
    #definition = null;
    url = null;
    #extends = [];

    constructor(url, onLoadExec = null, onFailExec = null)
    {
        this.url = url;
        if (Schema.definitionCache.hasOwnProperty(url))
        {
            this.#definition = Schema.definitionCache[url];
            if (onLoadExec != null)
            {
                onLoadExec(this);
            }
        }
        else
        {
            if (!Schema.loadingDefinitions.includes(url))
            {
                Schema.loadingDefinitions.push(url);
                Schema.onLoad[url] = [];
                Schema.onFail[url] = [];
                if (onLoadExec != null)
                {
                    Schema.onLoad[url].push(onLoadExec);
                }
                if (onFailExec != null)
                {
                    Schema.onFail[url].push(onFailExec);
                }
                let http = new XMLHttpRequest();
                http.open("GET", url, true);

                let responseHad = false;

                http.onreadystatechange = (e) =>
                {
                    if (http.readyState == 4 && !responseHad)
                    {
                        let response = {};
                        responseHad = true;

                        try
                        {
                            response = JSON.parse(http.responseText);
                            this.#definition = response;
                            Schema.definitionCache[url] = this.#definition;
                            Schema.loadingDefinitions = Schema.loadingDefinitions.filter((elem) =>
                            {
                                return elem !== url
                            });
                            for (let i = 0; i < Schema.onLoad[url].length; i++)
                            {
                                Schema.onLoad[url][i](this);
                            }
                        } catch (e)
                        {
                            for (let i = 0; i < Schema.onFail[url].length; i++)
                            {
                                Schema.onFail[url][i](this);
                            }
                            //console.log(http.responseText);
                        }
                    }
                }

                http.send();
            }
            else
            {
                if (onLoadExec != null)
                {
                    Schema.onLoad[url].push(onLoadExec);
                }
                if (onFailExec != null)
                {
                    Schema.onFail[url].push(onFailExec);
                }
            }
        }

        //console.log(Schema.definitionCache[url]);
    }

    instanceOf(schemaUrl)
    {
        if (schemaUrl == this.url)
        {
            return true;
        }

        return false;
    }
}
