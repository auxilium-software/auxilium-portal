class InfiniteScroller
{
    // Private properties
    #loadingIndicator; // DOM element used to show loading status
    #target; // Target container for appending new content
    #currentPage = 0; // Tracks the current page number for pagination
    #queryResponseId = null; // Stores the unique query ID for API requests

    // Constructor: Initializes the scroller with a query, target, and compact mode
    constructor(query, target, compact = false)
    {
        this.#target = target; // Set the target container
        this.#spawnLoadingIndicator(); // Create the initial loading indicator

        let http = new XMLHttpRequest();
        let url = "/api/v2/query";

        // Create the initial API request payload
        let data = new FormData();
        data.append("query", query);
        data.append("paginate", true);
        data.append("page_size", 8);

        http.open("POST", url, true);
        http.send(data);

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
                    let result = response["result_slice"];
                    this.#queryResponseId = result["@generated_query"];
                    let nodes = [];

                    // Extract initial nodes from the response
                    if (result.hasOwnProperty("@rows"))
                    {
                        for (let rowId = 0; rowId < result["@rows"].length; rowId++)
                        {
                            for (let key in result["@rows"][rowId])
                            {
                                if (key == "@path")
                                {
                                    for (let propId in result["@rows"][rowId]["@path"])
                                    {
                                        if (result["@rows"][rowId]["@path"].hasOwnProperty(propId))
                                        {
                                            nodes.push(result["@rows"][rowId]["@path"][propId]);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Render and insert initial nodes
                    for (let i = 0; i < nodes.length; i++)
                    {
                        let nodeView = compact ? new PathNameView(nodes[i], true) : new InlineNodeView(nodes[i]);
                        let nodeViewRender = nodeView.render();
                        this.#target.insertBefore(nodeViewRender, this.#loadingIndicator);
                    }

                    this.#loadingIndicator.style.display = "none";

                    this.#loadMore(); // Start loading additional content
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                }
            }
        };

        // Add change listeners for the nodes derived from the query path
        let path = query.slice(query.indexOf("FROM ") + 6);
        path = path.split(" ");
        path = path[0];
        path = path.split("/");
        path.pop();
        path = path.join("/");

        default_auxilium_client.getNodes(path.endsWith("/") ? path.slice(0, -1) : path).then((nodes) =>
        {
            for (const [path, node] of Object.entries(nodes))
            {
                node.addEventListener("change", () =>
                {
                    console.log("Reflowing infinite scroller! " + path);
                    this.#target.innerHTML = ""; // Clear the container
                    this.#spawnLoadingIndicator(); // Reset the loading indicator
                    this.#currentPage = -1; // Reset the page counter
                    this.#loadMore(true); // Force a reload
                });
            }
        });
    }

    // Private method: Creates and appends a loading indicator to the target container
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

    // Private method: Fetches more content based on scrolling or forced triggers
    #loadMore(forced = false)
    {
        let bounds = this.#target.getBoundingClientRect();
        let tryLoadMore = false;

        // Check if the user has scrolled near the bottom of the target or if forced loading is required
        if (bounds.bottom < window.innerHeight * 2)
        {
            tryLoadMore = true;
        }
        if (forced)
        {
            tryLoadMore = true;
        }
        //console.log("Load more? " + (tryLoadMore ? "YES" : "NO"));
        if (tryLoadMore)
        {
            let http = new XMLHttpRequest();
            let url = "/api/v2/query";

            this.#currentPage++; // Increment the page counter

            // Create the POST data payload
            let data = new FormData();
            data.append("query", this.#queryResponseId);
            data.append("paginate", true);
            data.append("page_size", 8);
            data.append("page", this.#currentPage);

            // Show the loading indicator
            this.#loadingIndicator.style.display = null;

            http.open("POST", url, true);
            http.send(data);

            let responseHad = false; // Flag to prevent multiple responses

            http.onreadystatechange = (e) =>
            {
                if (http.readyState == 4 && !responseHad)
                {
                    let response = {};
                    responseHad = true;

                    try
                    {
                        response = JSON.parse(http.responseText);
                        let result = response["result_slice"];
                        let nodes = [];

                        // Extract the node paths from the API response
                        if (result.hasOwnProperty("@rows"))
                        {
                            for (let rowId in result["@rows"])
                            {
                                //console.log(rowId);
                                if (result["@rows"].hasOwnProperty(rowId))
                                {
                                    for (let key in result["@rows"][rowId])
                                    {
                                        if (key == "@path")
                                        {
                                            for (let propId in result["@rows"][rowId]["@path"])
                                            {
                                                if (result["@rows"][rowId]["@path"].hasOwnProperty(propId))
                                                {
                                                    nodes.push(result["@rows"][rowId]["@path"][propId]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Render and insert new nodes into the target container
                        for (let i = 0; i < nodes.length; i++)
                        {
                            //console.log(nodes[i]);
                            let nodeView = new InlineNodeView(nodes[i]);
                            this.#target.insertBefore(nodeView.render(), this.#loadingIndicator);
                        }

                        // Hide the loading indicator
                        this.#loadingIndicator.style.display = "none";

                        // If no new nodes are added, end the infinite scrolling
                        if (nodes.length > 0)
                        {
                            console.log("End of list");
                            this.#loadMore();
                        }
                    } catch (e)
                    {
                        console.log(e);
                        console.log(http.responseText);
                    }
                }
            };
        }
        else
        {
            // Retry loading after a short delay if not triggered immediately
            setTimeout(() =>
            {
                this.#loadMore();
            }, 500);
        }
    }
}
