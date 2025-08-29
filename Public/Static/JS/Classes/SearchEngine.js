class SearchEngine
{
    #loadingIndicator;
    #target;
    #indexData = null;
    #awaitingSearch = null;
    #indexName = "global";
    #showTop = 3;
    #surface = null;
    #inputTarget = null;
    #onTyping = null;
    #onTypingEnd = null;
    #selectionCallback = null;
    #cachedElements = {};
    #focused = null;

    constructor(indexName = "global", showTop = 3)
    {
        this.#indexName = indexName;
        this.#showTop = showTop;
        this.#initialize(() =>
        {
            this.#indexName = "global"; // Fallback to global index if malformed index is provided
            this.#initialize();
        });
    }

    #initialize(failCallback = null)
    {
        let http = new XMLHttpRequest();
        let url = "/api/v2/indexes/" + this.#indexName + ".json";

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
                    if (response.hasOwnProperty("@error"))
                    {
                        if (failCallback != null)
                        {
                            failCallback();
                        }
                    }
                    else
                    {
                        this.#indexData = response;
                        if (this.#awaitingSearch != null)
                        {
                            this.searchFor(this.#awaitingSearch);
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

        http.send();
    }

    searchFor(prompt)
    {
        //console.log(prompt);
        if (this.#indexData == null)
        {
            this.#awaitingSearch = prompt;
        }
        else
        {
            prompt = prompt.toLowerCase();
            let lookupTable = this.#indexData["index"]["lookup_table"];
            const iterator = Object.keys(lookupTable);

            let indexedTable = [];

            for (const key of iterator)
            {
                let ld = levenshtein_distance(key, prompt);
                if (typeof indexedTable[ld] === "undefined")
                {
                    indexedTable[ld] = [];
                }
                indexedTable[ld] = indexedTable[ld].concat(lookupTable[key]);
            }

            let orderedResults = [];

            for (let i = 0; i < indexedTable.length; i++)
            {
                if (!(typeof indexedTable[i] === "undefined"))
                {
                    orderedResults = orderedResults.concat(indexedTable[i]);
                }
            }

            if (this.#surface != null)
            {
                let newChildren = [];
                const absolutePathGuidExtract = /^\{[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}\}/i;
                for (let i = 0; i < orderedResults.length; i++)
                {
                    let uuid = absolutePathGuidExtract.exec(orderedResults[i])[0].toLowerCase();
                    if (typeof this.#cachedElements[uuid] === "undefined")
                    {
                        this.#cachedElements[uuid] = new PathNameView(uuid);
                    }
                    let searchResult = document.createElement("a");
                    searchResult.classList.add("search-result");

                    searchResult.appendChild(this.#cachedElements[uuid].render());
                    searchResult.addEventListener("click", (e) =>
                    {
                        //console.log("Selected " + uuid);
                        if (this.#selectionCallback != null)
                        {
                            this.#selectionCallback(uuid);
                        }
                        if (this.#onTypingEnd != null)
                        {
                            this.#onTypingEnd();
                        }
                        if (this.#inputTarget != null)
                        {
                            this.#inputTarget.value = "";
                        }
                        setTimeout(() =>
                        {
                            // Wait a bit, then clear results so as to not confuse tab navigation with hidden elements
                            this.#surface.replaceChildren();

                        }, 100);
                    });
                    newChildren.push(searchResult);
                    searchResult.href = "#";
                }
                //console.log(newChildren);
                this.#surface.replaceChildren(...newChildren);
            }

            //console.log(orderedResults);
        }
    }

    fullPage()
    {
        let rootContainer = document.createElement("dialog");
        let innerContainer = document.createElement("div");
        innerContainer.classList.add("inner-content");
        let closeButton = document.createElement("a");
        closeButton.classList.add("action-link");
        closeButton.classList.add("action-link-close");
        closeButton.innerText = "Close search";
        LanguagePack.whenTemplateAvailable("ui_text/close_search", (templ) =>
        {
            if (templ != null)
            {
                closeButton.innerText = capitalize(templ);
            }
        });
        closeButton.addEventListener("click", (e) =>
        {
            e.preventDefault();
            rootContainer.remove();
        })
        innerContainer.appendChild(closeButton);
        let spacer = document.createElement("div");
        spacer.classList.add("small-spacer")
        innerContainer.appendChild(spacer);


        rootContainer.appendChild(innerContainer);
        rootContainer.classList.add("full-page-panel");

        let inputBox = document.createElement("input");
        inputBox.type = "text";
        inputBox.placeholder = "Start typing...";
        inputBox.classList.add("fullwidth-text-input");
        LanguagePack.whenTemplateAvailable("ui_text/start_typing", (templ) =>
        {
            if (templ != null)
            {
                inputBox.placeholder = capitalize(templ);
            }
        });
        innerContainer.appendChild(inputBox);

        let resultsBox = document.createElement("div");
        resultsBox.classList.add("logical-box")
        innerContainer.appendChild(resultsBox);

        let resultsHintText = document.createElement("span");
        resultsHintText.innerText = "Results will appear as you type";
        LanguagePack.whenTemplateAvailable("ui_text/results_will_appear_as_you_type", (templ) =>
        {
            if (templ != null)
            {
                resultsHintText.innerText = capitalize(templ);
            }
        });
        resultsBox.appendChild(resultsHintText);

        this.bindTo(inputBox);
        this.showResultsOn(resultsBox);

        return rootContainer;
    }

    focus()
    {
        if (this.#inputTarget != null)
        {
            this.#inputTarget.focus();
        }
    }

    bindTo(inputTarget)
    {
        this.#inputTarget = inputTarget;
        inputTarget.addEventListener("input", (e) =>
        {
            if (inputTarget.value.length > 0)
            {
                this.searchFor(inputTarget.value);
                this.#focused = true;
                if (this.#onTyping != null)
                {
                    this.#onTyping();
                }
            }
            else
            {
                this.#focused = false;
                if (this.#onTypingEnd != null)
                {
                    this.#onTypingEnd();
                }
            }
        });

        inputTarget.addEventListener("focus", (e) =>
        {
            if (inputTarget.value.length > 0)
            {
                this.searchFor(inputTarget.value);
                this.#focused = true;
                if (this.#onTyping != null)
                {
                    this.#onTyping();
                }
            }
        });

        inputTarget.addEventListener("blur", (e) =>
        {

            this.#focused = false;
            setTimeout(() =>
            {
                // Make sure not to close the drop down if someone is using keyboard navigation
                if (!document.activeElement.classList.contains("search-result"))
                {
                    if (!this.#focused)
                    {
                        if (this.#onTypingEnd != null)
                        {
                            this.#onTypingEnd();
                        }
                    }
                }
            }, 500); // Just a small delay to allow selection to take place
        });
    }

    showResultsOn(surface = null)
    {
        this.#surface = surface;
    }

    onTyping(callback = null)
    {
        this.#onTyping = callback;
    }

    onTypingEnd(callback = null)
    {
        this.#onTypingEnd = callback;
    }

    onSelect(callback = null)
    {
        this.#selectionCallback = callback;
    }
}
