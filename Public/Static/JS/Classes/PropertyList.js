class PropertyList
{
    #loadingIndicator;
    #target;
    #path = null;

    constructor(path, compact = false, except = [], sort = null, recursive = false, allowEdit = true, invertExcept = false)
    {


        //console.log(except);
        if (except == null)
        {
            except = [];
        }
        this.#path = path;
        if (compact)
        {
            this.#target = document.createElement("ul");
            this.#target.classList.add("collection-list");
        }
        else
        {
            this.#target = document.createElement("dl");
        }
        this.#spawnLoadingIndicator();

        let renderObject = (tuples) =>
        {
            if (compact)
            {
                for (let i = 0; i < tuples.length; i++)
                {
                    if ((except.indexOf(tuples[i][2]) == -1) ^ invertExcept)
                    {
                        let li = document.createElement("li");
                        let nodeView = new InlineNodeView(tuples[i][0], tuples[i][1], !recursive);
                        //console.log(tuples[i][0]);
                        li.appendChild(nodeView.render());
                        this.#target.appendChild(li);
                    }
                }
            }
            else
            {
                for (let i = 0; i < tuples.length; i++)
                {
                    if ((except.indexOf(tuples[i][2]) == -1) ^ invertExcept)
                    {
                        let dt = document.createElement("dt");
                        let dd = document.createElement("dd");
                        dt.innerText = capitalize(tuples[i][2].replaceAll("_", " ")) + " ";
                        LanguagePack.whenTemplateAvailable("data_types/" + tuples[i][2], (templ) =>
                        {
                            //console.log("data_types/" + tuples[i][2] + " => " + templ);
                            if (templ != null)
                            {
                                dt.innerText = capitalize(templ) + " ";
                            }
                        });
                        let nodeView = new InlineNodeView(tuples[i][0], tuples[i][1], !recursive, allowEdit);
                        dd.appendChild(nodeView.render());
                        dt.appendChild(nodeView.renderSecondaryActions());
                        this.#target.appendChild(dt);
                        this.#target.appendChild(dd);
                    }
                }
            }
        }

        let reRender = () =>
        {
            default_auxilium_client.getNodes(path + (path.endsWith("/") ? "*" : "/*")).then((nodes) =>
            {
                let tuples = [];
                for (const [path, node] of Object.entries(nodes))
                {
                    let lprop = path.split("/");
                    lprop = lprop[lprop.length - 1];
                    let tuple = [path, node, lprop];
                    tuples.push(tuple);
                }

                renderObject(tuples);
                this.#loadingIndicator.style.display = "none";
            });
        }


        default_auxilium_client.getNodes(path.endsWith("/") ? path.slice(0, -1) : path).then((nodes) =>
        {
            for (const [path, node] of Object.entries(nodes))
            {
                //console.log("Adding listener on " + path)
                node.addEventListener("change", () =>
                {
                    this.#target.innerHTML = "";
                    this.#spawnLoadingIndicator();
                    reRender();
                });
            }
        });

        reRender();

        /*
         let http = new XMLHttpRequest();
         let url = "/api/v2/query";

         let data = new FormData();
         let query = "SELECT . FROM " + path + (path.endsWith("/") ? "*" : "/*") + ((sort == null) ? "" : (" ORDERBY " + sort));
         //console.log(query);
         data.append("query", query);
         data.append("paginate", false);

         http.open("POST", url, true);
         http.send(data);

         let responseHad = false;


         http.onreadystatechange = (e) => {
         if (http.readyState == 4 && !responseHad) {
         let response = {};
         responseHad = true;

         try {
         response = JSON.parse(http.responseText);
         let result = response["result"];
         //console.log(result);

         let tuples = [];

         if (result.hasOwnProperty("@rows")) {
         for (let rowId in result["@rows"]) {
         //console.log(rowId);
         if (result["@rows"].hasOwnProperty(rowId)) {
         for (let key in result["@rows"][rowId]) {
         if (result["@rows"][rowId].hasOwnProperty(key)) {
         let map = result["@rows"][rowId][key]
         for (let propId in map) {
         if (map.hasOwnProperty(propId)) {
         //console.log(propId)
         //console.log(map[propId])
         let lprop = propId.split("/");
         lprop = lprop[lprop.length - 1];
         let tuple = [propId, map[propId], lprop];
         tuples.push(tuple);
         }
         }
         }
         }
         }
         }
         }

         //console.log(pairs)

         this.#loadingIndicator.style.display = "none";


         } catch(e) {
         console.log(e);
         console.log(http.responseText);
         }
         }
         }
         */

    }

    render()
    {
        return this.#target;
    }

    #spawnLoadingIndicator()
    {
        this.#loadingIndicator = document.createElement("div");
        this.#loadingIndicator.classList.add("logical-box");

        let indicatorBox = document.createElement("div");
        indicatorBox.classList.add("loading-placeholder");
        indicatorBox.classList.add("large-spacer");
        this.#loadingIndicator.appendChild(indicatorBox);

        this.#target.appendChild(this.#loadingIndicator);
    }

    reload()
    {
        console.log("Reloading property list!");
        //TODO: Replace with better functionality, this is a bodge so the demo just works
        location.reload();
    }
}
