class AuxiliumClient
{
    #loginNodeId = null;
    #workingNodeId = null;
    #nodeCache = {};
    #pathCache = {};
    #pendingDbJobs = [];
    #loginNode = null;
    #maxCacheTime = 5000; // In Msec
    #sessionToken = null;

    constructor(sessionToken = null, loginNodeId = null, workingNodeId = null)
    {
        this.#sessionToken = sessionToken;
        this.#loginNodeId = loginNodeId;
        this.#workingNodeId = workingNodeId;
        if (workingNodeId == null)
        {
            this.#workingNodeId = this.#loginNodeId;
        }
    }

    matchPath(path = null, originalCandidates = [])
    {
        let candidates = originalCandidates;
        let pathComponents = path.split("/");
        let level = 0;
        while (true)
        {
            if (level < pathComponents.length)
            {
                let evalPart = pathComponents[level];
                if (evalPart == "")
                {
                    level++;
                    continue;
                }
                if (evalPart == "*")
                {
                    let newCandidates = [];
                    for (let i = 0; i < candidates.length; i++)
                    {
                        newCandidates = newCandidates
                    }
                    candidates = newCandidates;
                    level++;
                    continue;
                }
                if (evalPart == "*")
                {
                    let newCandidates = [];
                    for (let i = 0; i < candidates.length; i++)
                    {
                        newCandidates = newCandidates
                    }
                    candidates = newCandidates;
                    level++;
                    continue;
                }
                if (common_regex.guid.test(evalPart))
                {
                    candidates = [evalPart];
                    level++;
                    continue;
                }
                if (common_regex.guid.test(evalPart))
                {
                    let newCandidates = [];
                    for (let i = 0; i < candidates.length; i++)
                    {
                        newCandidates = newCandidates
                    }
                    candidates = newCandidates;
                    level++;

                }
            }
            else
            {
                break;
            }
        }
    }

    query(query)
    {
        return new Promise((resolve, reject) =>
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
                            reject(result);
                        }
                        else
                        {
                            resolve(result);
                        }
                    } catch (e)
                    {
                        console.log(e);
                        console.log(http.responseText);
                        reject(null);
                    }
                }
            }

            http.send(data);
        });
    }

    /*
     default_auxilium_client.getNodes("/name").then((nodes) => {
     //console.log(nodes)
     for (const [path, node] of Object.entries(nodes)) {
     node.getData().then((data) => {
     console.log(data)
     });
     };
     });
     */

    getNodeById(uuid)
    {
        if (!this.#nodeCache.hasOwnProperty(uuid))
        {
            this.#nodeCache[uuid] = new AuxiliumNode(uuid, this, this.#maxCacheTime);
        }
        return this.#nodeCache[uuid];
    }

    getNodes(path)
    {
        return new Promise((resolve, reject) =>
        {
            if (path.startsWith("/"))
            {
                path = "{" + this.#loginNodeId + "}" + path;
            }
            if (path.endsWith("/"))
            {
                path = path.substring(0, path.length - 1);
            }
            //console.log(path)
            if (!common_regex.path.test(path))
            {
                console.error("Invalid path! => " + path);
                reject("INVALID_PATH");
            }
            let doNodeLookup = (result) =>
            {
                //console.log(result);
                if (result.hasOwnProperty("@rows"))
                {
                    let vals = Object.values(result["@rows"]);
                    let nodeIds = {};
                    for (let i = 0; i < vals.length; i++)
                    {
                        let keys = Object.keys(vals[i]["@id"]);
                        if (keys.length > 0)
                        {
                            let guid = vals[i]["@id"][keys[0]];
                            nodeIds[keys[0].substring(0, keys[0].length - 4)] = this.getNodeById(guid.substring(1, guid.length - 1));
                        }
                    }
                    //console.log("Resolving!")
                    resolve(nodeIds);
                }
                else
                {
                    reject("ERROR_FROM_DATABASE");
                }
            };
            let fromCache = false;
            if (this.#pathCache.hasOwnProperty(path))
            {
                if (this.#pathCache[path]["at"] > (Date.now() - this.#maxCacheTime))
                {
                    fromCache = true;
                    if (this.#pathCache[path]["data"] == null)
                    {
                        //console.log("Double request");
                        this.#pathCache[path]["then"].push(doNodeLookup);
                        fromCache = false;
                    }
                    else
                    {
                        //console.log("Serving from cache");
                        doNodeLookup(this.#pathCache[path]["data"]);
                    }
                }
                else
                {
                    //console.log("Stale cache");

                }
            }
            if (!fromCache)
            {
                this.#pathCache[path] = {
                    "at": Date.now(),
                    "then": [],
                    "data": null
                };
                this.query("SELECT @id FROM " + path).then((result) =>
                {
                    this.#pathCache[path]["data"] = result;
                    this.#pathCache[path]["at"] = Date.now();
                    doNodeLookup(result);
                    if (this.#pathCache[path].hasOwnProperty("then"))
                    {
                        this.#pathCache[path]["then"].forEach((f) =>
                        {
                            f(result);
                        });
                    }
                    delete this.#pathCache[path]["then"];
                });
            }
        });
    }

    getPathCache()
    {
        return this.#pathCache;
    }

    updatePath(path = null)
    {
        console.log("Updating " + path)
        this.getNodes(path.endsWith("/") ? path.slice(0, -1) : path).then((nodes) =>
        {
            for (const [path, node] of Object.entries(nodes))
            {
                console.log("Updating " + path)
                node.triggerUpdate();
            }
        });
    }

    urlToData(url)
    {
        return new Promise((resolve, reject) =>
        {
            if (url.startsWith("data:"))
            {
                url = url.substring(5);
                let header = url.substring(0, url.indexOf(","));
                let body = url.substring(header.length + 1);
                let b64 = false;
                //console.log(header);
                if (header.endsWith(";base64"))
                {
                    b64 = true;
                    header = header.substring(0, header.length - 7);
                }
                if (b64)
                {
                    resolve(new Blob([atob(body)], {type: header}));
                }
                else
                {
                    resolve(new Blob([decodeURIComponent(body.replaceAll("+", " "))], {type: header}));
                }
            }
            else if (url.startsWith("auxlfs://"))
            {
                let http = new XMLHttpRequest();
                let lfsUrl = aux_lfs_uri_to_https(url);

                http.open("GET", lfsUrl, true);
                http.responseType = "arraybuffer";

                let responseHad = false;

                http.onreadystatechange = (e) =>
                {
                    if (http.readyState == 4 && !responseHad)
                    {
                        responseHad = true;

                        try
                        {
                            // console.log(http.getAllResponseHeaders());
                            // console.log(http.getResponseHeader("content-type"));
                            resolve(new Blob([http.response], {type: http.getResponseHeader("content-type")}));
                        } catch (e)
                        {
                            reject(e);
                        }
                    }
                }

                http.send();
            }
            else
            {
                reject(new Error("Invalid URI schema"));
            }
        });
    }
}

