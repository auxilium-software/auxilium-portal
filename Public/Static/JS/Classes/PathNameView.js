class PathNameView
{
    #loadingIndicator;
    #target;
    #schema = null;
    #path = null;
    #data = null;
    #nodeId = null;

    constructor(nodePath, linkable = false, hideNavigationalCue = false)
    {
        this.#target = linkable ? document.createElement("a") : document.createElement("span");

        if (linkable)
        {
            if (!hideNavigationalCue)
            {
                this.#target.classList.add("navigational-link");
            }
            this.#target.href = "/graph/" + deegraph_path_to_link(nodePath);
        }

        //this.#target.classList.add("logical-box");
        this.#target.classList.add("node-name-container");
        //this.#targetSecondaryActions.classList.add("logical-box");

        this.#loadingIndicator = document.createElement("span");
        //this.#loadingIndicator.classList.add("logical-box");

        let indicatorBox = document.createElement("span");
        indicatorBox.classList.add("loading-placeholder");
        indicatorBox.classList.add("large-spacer");
        indicatorBox.innerText = random_length_placeholder_text([4, 16]);
        this.#loadingIndicator.appendChild(indicatorBox);

        this.#target.appendChild(this.#loadingIndicator);

        this.#path = nodePath;


        let pathComponent = this.#path.split("/").slice(-1);
        let preferContextNaming = true;
        if (guidRegex.test(pathComponent))
        {
            preferContextNaming = false;
        }
        if (numberRegex.test(pathComponent))
        {
            preferContextNaming = false;
        }
        if (metaPropRegex.test(pathComponent))
        {
            preferContextNaming = false;
        }

        let getPropertyBasedName = (prop, onFail = null, icon = null) =>
        {
            LanguagePack.whenTemplateAvailable("data_types/" + prop, (templ) =>
            {
                //console.log("data_types/" + prop + " => " + templ);
                if (templ != null)
                {
                    this.#target.innerHTML = "";
                    let text = document.createElement("span")
                    text.innerText = capitalize(templ);
                    if (icon != null)
                    {
                        let iconElem = document.createElement("span")
                        iconElem.classList.add("inline-icon-shift-left");
                        iconElem.classList.add("inline-icon-low-margin")
                        iconElem.classList.add(icon);
                        this.#target.appendChild(iconElem);
                    }
                    this.#target.appendChild(text);
                }
                else
                {
                    if (onFail != null)
                    {
                        onFail();
                    }
                    else
                    {
                        this.#target.innerHTML = "";
                        let text = document.createElement("span")
                        text.innerText = capitalize(prop);
                        if (icon != null)
                        {
                            let iconElem = document.createElement("span")
                            iconElem.classList.add("inline-icon-shift-left");
                            iconElem.classList.add("inline-icon-low-margin")
                            iconElem.classList.add(icon);
                            this.#target.appendChild(iconElem);
                        }
                        this.#target.appendChild(text);
                    }
                }
            });
        }

        let getSchemaBasedName = (onFail = null) =>
        {

            this.#fetchInfo("SELECT @schema FROM " + this.#path + "", (result) =>
            {

                let schemaUri = null;
                if (result.hasOwnProperty("@schema"))
                {
                    let vals = Object.keys(result["@schema"]);
                    if (vals.length > 0)
                    {
                        schemaUri = result["@schema"][vals[0]];
                    }
                }
                if (schemaUri == null)
                {
                    if (onFail != null)
                    {
                        onFail();
                    }
                }
                else
                {
                    new Schema(schemaUri, (schema) =>
                    {

                        if (schema != null)
                        {
                            this.#schema = schema;
                            if (this.#schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/case.json"))
                            {
                                let finalDisplay = (title, name) =>
                                {
                                    this.#target.innerHTML = "";
                                    let icon = document.createElement("span")
                                    icon.classList.add("inline-icon-shift-left");
                                    icon.classList.add("inline-icon-low-margin")
                                    icon.classList.add("work-icon");
                                    this.#target.appendChild(icon);

                                    let finishWithName = (name) =>
                                    {
                                        if (name != null)
                                        {
                                            let sp = document.createElement("span")
                                            sp.innerText = " - ";
                                            this.#target.appendChild(sp);
                                            sp = document.createElement("span")
                                            sp.innerText = name;
                                            this.#target.appendChild(sp);
                                        }
                                    }

                                    if (title == null)
                                    {
                                        let emTag = document.createElement("em")
                                        emTag.innerText = "Untitled Case";
                                        this.#target.appendChild(emTag);
                                        finishWithName(name);
                                    }
                                    else
                                    {
                                        MicroTemplate.from_packed_template(title).then((str) =>
                                        {
                                            let sp = document.createElement("span")
                                            sp.innerText = capitalize(str);
                                            this.#target.appendChild(sp);
                                            finishWithName(name);
                                        }).catch((err) =>
                                        {
                                            let sp = document.createElement("span")
                                            sp.innerText = err.path;
                                            this.#target.appendChild(sp);
                                            finishWithName(name);
                                        });
                                    }


                                }

                                extract_text_property("title", this.#path).then((title) =>
                                {
                                    extract_text_property("clients/0/name", this.#path).then((name) =>
                                    {
                                        finalDisplay(title, name);
                                    }).catch((e) =>
                                    {
                                        finalDisplay(title, null);
                                    });
                                }).catch((e) =>
                                {
                                    extract_text_property("clients/0/name", this.#path).then((name) =>
                                    {
                                        finalDisplay(null, name);
                                    }).catch((e) =>
                                    {
                                        finalDisplay(null, null);
                                    });
                                });
                            }
                            else if (this.#schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/collection.json"))
                            {
                                if (onFail != null)
                                {
                                    onFail();
                                }
                            }
                            else if (this.#schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/message.json"))
                            {
                                this.#target.innerHTML = "";
                                let icon = document.createElement("span")
                                icon.classList.add("inline-icon-shift-left");
                                icon.classList.add("inline-icon-low-margin")
                                icon.classList.add("email-icon");
                                this.#target.appendChild(icon);
                                let text = document.createElement("span")
                                LanguagePack.whenTemplateAvailable("data_types/message", (templ) =>
                                {
                                    if (templ != null)
                                    {
                                        text.innerText = capitalize(templ);
                                    }
                                });
                                this.#target.appendChild(text);
                            }
                            else if (this.#schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/user.json"))
                            {
                                extract_text_property("name", this.#path).then((data) =>
                                {
                                    //this.#target.innerText = data;
                                    this.#target.innerHTML = "";
                                    let icon = document.createElement("span")
                                    icon.classList.add("inline-icon-shift-left");
                                    icon.classList.add("inline-icon-low-margin")
                                    icon.classList.add("account-box-icon");
                                    this.#target.appendChild(icon);
                                    let text = document.createElement("span")
                                    text.innerText = data;
                                    this.#target.appendChild(text);
                                });
                            }
                            else if (this.#schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/organisation.json"))
                            {
                                extract_text_property("name", this.#path).then((data) =>
                                {
                                    //this.#target.innerText = data;
                                    this.#target.innerHTML = "";
                                    let icon = document.createElement("span")
                                    //icon.classList.add("inline-icon");
                                    icon.classList.add("inline-icon-low-margin")
                                    icon.classList.add("groups-icon");
                                    this.#target.appendChild(icon);
                                    let text = document.createElement("span")
                                    MicroTemplate.from_packed_template(data).then((str) =>
                                    {

                                        text.innerText = " " + capitalize(str);
                                    });

                                    this.#target.appendChild(text);
                                });
                            }
                            else
                            {
                                if (onFail != null)
                                {
                                    onFail();
                                }
                            }
                        }
                        else
                        {
                            if (onFail != null)
                            {
                                onFail();
                            }
                        }
                    });
                }
            }, () =>
            {
                if (onFail != null)
                {
                    onFail();
                }
            });
        }

        if (preferContextNaming)
        {
            getPropertyBasedName(pathComponent);
        }
        else
        {
            getSchemaBasedName((icon = null) =>
            {
                getPropertyBasedName(pathComponent, null, icon)
            });
        }
    }

    render()
    {
        return this.#target;
    }

    #query(query, callback, failCallback = null)
    {
        let http = new XMLHttpRequest();
        let url = "/api/v2/query";

        let data = new FormData();
        data.append("query", query);
        data.append("paginate", false)

        http.open("POST", url, true);

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
                    let result = response["result"];

                    if (result.hasOwnProperty("@error"))
                    {
                        if (failCallback != null)
                        {
                            failCallback();
                        }
                    }
                    else
                    {
                        callback(result);
                    }
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                    if (failCallback != null)
                    {
                        failCallback();
                    }
                }
            }
        }

        http.send(data);
    }

    #fetchInfo(query, callback, failCallback = null)
    {
        let http = new XMLHttpRequest();
        let url = "/api/v2/query";

        let data = new FormData();
        data.append("query", query);
        data.append("paginate", false)

        http.open("POST", url, true);

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
                    let result = response["result"];

                    if (result.hasOwnProperty("@rows"))
                    {
                        let vals = Object.values(result["@rows"]);
                        let val = (vals.length > 0) ? vals[0] : null;
                        if (val == null)
                        {
                            if (failCallback != null)
                            {
                                failCallback();
                            }
                        }
                        else
                        {
                            callback(val);
                        }
                    }
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                    if (failCallback != null)
                    {
                        failCallback();
                    }
                }
            }
        }

        http.send(data);
    }
}
