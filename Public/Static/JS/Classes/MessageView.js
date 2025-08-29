class MessageView
{
    #loadingIndicator;
    #target;
    #path = null;
    #data = null;
    #nodeId = null;

    constructor(nodePath)
    {
        this.#target = document.createElement("span");

        this.#target.classList.add("chat-bubble");

        this.#loadingIndicator = document.createElement("span");
        //this.#loadingIndicator.classList.add("logical-box");

        let indicatorBox = document.createElement("span");
        indicatorBox.classList.add("loading-placeholder");
        indicatorBox.classList.add("large-spacer");
        indicatorBox.innerText = randomLengthPlaceholderText([4, 16]);
        this.#loadingIndicator.appendChild(indicatorBox);

        this.#target.appendChild(this.#loadingIndicator);

        this.#path = nodePath;
    }

    render()
    {
        return this.#target;
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

    #extractProperty(property, onSuccess, onFail)
    {
        this.#fetchInfo("SELECT " + property + " FROM " + this.#path + "", (result2) =>
        {
            let dataUri = null;
            let vals = Object.keys(result2);
            if (vals.length > 0)
            {
                let result3 = result2[vals[0]];
                vals = Object.keys(result3);
                if (vals.length > 0)
                {
                    dataUri = result3[vals[0]];
                }
            }
            if (dataUri != null)
            {
                urlToData(dataUri, onSuccess);
            }
            else
            {
                onFail();
            }
        }, () =>
        {
            if (onFail != null)
            {
                onFail();
            }
        });
    }
}
