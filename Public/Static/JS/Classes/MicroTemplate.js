class MicroTemplate
{
    path = null;
    templateVariables = [];
    nullOnUnknown = false;

    constructor(path, templateVariables = [], nullOnUnknown = false)
    {
        this.path = path;
        this.templateVariables = templateVariables;
        this.nullOnUnknown = nullOnUnknown;
    }


    static from_packed_template(str, templateVariables = [], nullOnUnknown = false)
    {
        if (str.indexOf("::auxpckstr:") !== -1)
        {
            if (str.endsWith("::"))
            {
                let strcomponents = str.substring(12, str.length - 2).split(":");
                if (strcomponents.length > 0)
                {
                    let path = strcomponents.shift();
                    let elem = strcomponents.shift();
                    let key = null;
                    let template_variables = [];
                    while (elem != null)
                    {
                        if (key == null)
                        {
                            key = elem;
                        }
                        else
                        {
                            template_variables[key] = $elem;
                            key = null;
                        }
                        elem = strcomponents.shift();
                    }
                    return new MicroTemplate(path, template_variables).asString();
                }
            }
        }

        return new Promise((resolve, reject) =>
        {
            resolve(str);
        });

    }

    asString()
    {
        return new Promise((resolve, reject) =>
        {
            LanguagePack.getPack().then((lp) =>
            {
                let cmps = this.path.split("/");
                let cdir = lp.returnDefinition();
                //console.log(cdir);
                for (let i = 0; i < cmps.length; i++)
                {
                    if (cdir.hasOwnProperty(cmps[i]))
                    {
                        cdir = cdir[cmps[i]];
                    }
                    else
                    {
                        cdir = null;
                        break;
                    }
                }
                if (cdir != null)
                {
                    let templateString = cdir;
                    for (let [tvkey, tvval] of Object.entries(this.templateVariables))
                    {
                        if (/^\{[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\}$/.test(tvval))
                        {
                            // Might be a node uuid
                            tvval = tvval.substring(1, 37);
                        }
                        else if (/^\{(?:[A-Za-z0-9_-]{4})*(?:[A-Za-z0-9_-]{2}|[A-Za-z0-9_-]{3})?\}$/.test(tvval))
                        {
                            // Might be an inner encoded template
                            tvval = MicroTemplate.from_packed_template(base64_decode_url_safe(tvval.substring(1, tvval.length - 1)), this.templateVariables);
                        }
                        templateString = templateString.replace(new RegExp(`{{${tvkey}}}`, 'g'), tvval);
                        templateString = templateString.replace(new RegExp(`{{ ${tvkey} }}`, 'g'), tvval);
                    }
                    resolve(templateString);
                }
                else
                {
                    if (this.nullOnUnknown)
                    {
                        resolve(null);
                    }
                    else
                    {
                        let replaceRegex = /_+/gi;
                        reject({
                            "error": "MISSING_TEMPLATE",
                            "path": cmps.join("/"),
                            "substitute": cmps[cmps.length - 1].replaceAll(replaceRegex, " "),
                            "message": "Missing Template: " + cmps.join("/")
                        });
                    }
                }
            });
        });
    }
}
