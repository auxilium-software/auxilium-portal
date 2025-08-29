class AuxiliumNode
{
    #uuid = null;
    #updatedAt = null;
    #deleteCallbacks = [];
    #changeCallbacks = [];
    #loadCallbacks = [];
    #dataLoading = false;
    #properties = null;
    #originalClient = null;
    #maxCacheTime = null;

    constructor(uuid = null, originalClient = null, maxCacheTime = 5000)
    {
        if ((typeof uuid !== 'string') && !(uuid instanceof String))
        {
            throw new Error("UUID must be supplied on node creation");
        }
        if (!originalClient instanceof AuxiliumClient)
        {
            throw new Error("Node must reference a creating Auxilium client");
        }
        this.#maxCacheTime = maxCacheTime;
        this.#originalClient = originalClient;
        this.#uuid = uuid;
    }

    fetchNodeInfo(onReady)
    {
        if (this.#dataLoading)
        {
            this.#changeCallbacks.push(onReady);
        }
        else
        {
            if (this.#updatedAt == null)
            {
                this.#updatedAt = 0;
            }
            if (this.#updatedAt < (Date.now() - this.#maxCacheTime))
            {
                this.#updatedAt = Date.now();
                this.#dataLoading = true;
                this.#changeCallbacks.push(onReady);

                let http = new XMLHttpRequest();
                let url = "/api/v2/nodes/" + this.#uuid;

                let data = new FormData();

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
                            let result = response["result"];
                            if (result.hasOwnProperty("@error"))
                            {
                                console.log(result);
                            }
                            else
                            {
                                this.#properties = result;
                                this.#dataLoading = false;
                                this.#changeCallbacks.forEach((cb) =>
                                {
                                    cb(this);
                                });
                                this.#loadCallbacks.forEach((cb) =>
                                {
                                    cb(this);
                                });
                                this.#loadCallbacks = [];
                            }
                        } catch (e)
                        {
                            console.log(e);
                            console.log(http.responseText);
                        }
                    }
                }

                http.send(data);

            }
            else
            {
                onReady(this);
            }
        }
    }

    destroy()
    {
        return new Promise((resolve, reject) =>
        {
            let http = new XMLHttpRequest();
            let url = "/api/v2/nodes/" + this.#uuid;

            http.open("DELETE", url, true);

            let responseHad = false;

            http.onreadystatechange = (e) =>
            {
                if (http.readyState == 4 && !responseHad)
                {
                    responseHad = true;

                    try
                    {
                        if (http.status == 200 || http.status == 202 || http.status == 204 || http.status == 404)
                        {
                            this.#deleteCallbacks.forEach((cb) =>
                            {
                                cb(this);
                            });
                            resolve();
                        }
                        else
                        {
                            reject("FAILED_TO_DELETE");
                        }
                    } catch (e)
                    {
                        console.log(e);
                        console.log(http.responseText);
                    }
                }
            }

            http.send();
        });
    }

    triggerUpdate()
    {
        this.#changeCallbacks.forEach((cb) =>
        {
            cb(this);
        });
    }

    addEventListener(eventName, callback)
    {
        if (!(callback instanceof Function))
        {
            throw new Error("Callback function is not defined");
        }
        switch (eventName)
        {
            case "change":
                this.#changeCallbacks.push(callback);
                break;
            case "load":
                this.#loadCallbacks.push(callback);
                break;
            case "delete":
                this.#deleteCallbacks.push(callback);
                break;
            default:
                throw new Error("Invalid event type \"" + eventName + "\"");
        }
    }

    getRawSchema()
    {
        return new Promise((resolve, reject) =>
        {
            this.fetchNodeInfo(() =>
            {
                if (this.#properties.hasOwnProperty("@schema"))
                {
                    resolve(this.#properties["@schema"]);
                }
                else
                {
                    resolve(null);
                }
            });
        });
    }

    getRawData()
    {
        return new Promise((resolve, reject) =>
        {
            this.fetchNodeInfo(() =>
            {
                if (this.#properties.hasOwnProperty("@data"))
                {
                    resolve(this.#properties["@data"]);
                }
                else
                {
                    resolve(null);
                }
            });
        });
    }

    getUuid()
    {
        return this.#uuid;
    }

    getData()
    {
        return new Promise((resolve, reject) =>
        {
            this.getRawData().then((data) =>
            {
                if (data == null)
                {
                    resolve(null);
                }
                else
                {
                    this.#originalClient.urlToData(data).then(resolve).catch(reject);
                }
            }).catch(reject);
        });
    }
}

