class MessageDraft
{
    #rootContainer = null;
    #innerContainer = null;
    #draftId = null;
    #data = {};
    #currentFocus = null;
    #recipientsBox = null;
    #recipientsContainerBox = null;
    #textArea = null;
    #sendButton = null;
    #autosave = false;

    constructor(draftId, floating = false)
    {
        this.#rootContainer = document.createElement("div");


        if (floating)
        {
            this.#innerContainer = document.createElement("div");
            this.#innerContainer.classList.add("inner-content");
            this.#innerContainer.classList.add("message-draft");
            let closeButton = document.createElement("a");
            closeButton.classList.add("action-link");
            closeButton.classList.add("action-link-delete");
            closeButton.innerText = "Discard draft";
            closeButton.addEventListener("click", (e) =>
            {
                e.preventDefault();
                this.#rootContainer.remove();
            })
            this.#innerContainer.appendChild(closeButton);
            let spacer = document.createElement("div");
            spacer.classList.add("small-spacer")
            this.#innerContainer.appendChild(spacer);
            this.#rootContainer.appendChild(this.#innerContainer);
            this.#rootContainer.classList.add("floating-panel");
        }
        else
        {
            this.#innerContainer = this.#rootContainer;
            this.#rootContainer.classList.add("message-draft");
            this.#rootContainer.classList.add("logical-box");
        }

        this.#recipientsContainerBox = document.createElement("div");
        this.#recipientsContainerBox.classList.add("zero-height-growable-container");
        this.#innerContainer.appendChild(this.#recipientsContainerBox);

        this.#recipientsBox = document.createElement("input");
        this.#recipientsBox.type = "text";
        this.#recipientsBox.placeholder = "Start typing a name...";
        this.#recipientsBox.classList.add("fullwidth-text-input");
        this.#recipientsBox.classList.add("zero-margin-input");
        LanguagePack.whenTemplateAvailable("ui_text/start_typing_a_name", (templ) =>
        {
            if (templ != null)
            {
                this.#recipientsBox.placeholder = capitalize(templ);
            }
        });
        this.#innerContainer.appendChild(this.#recipientsBox);

        let resultsBoxContainer = document.createElement("div");
        resultsBoxContainer.classList.add("zero-height-container");
        let resultsBox = document.createElement("div");
        resultsBox.classList.add("search-drop-down");
        resultsBox.classList.add("hidden");
        resultsBoxContainer.appendChild(resultsBox)
        this.#innerContainer.appendChild(resultsBoxContainer);

        this.#textArea = document.createElement("textarea");

        let recipientSearch = new SearchEngine("users");
        recipientSearch.showResultsOn(resultsBox);
        recipientSearch.bindTo(this.#recipientsBox);
        recipientSearch.onTyping(() =>
        {
            resultsBox.classList.add("visible");
            resultsBox.classList.remove("hidden");
        });
        recipientSearch.onTypingEnd(() =>
        {
            resultsBox.classList.remove("visible");
            resultsBox.classList.add("hidden");
        });
        recipientSearch.onSelect((nodeId) =>
        {
            console.log("SELECTED" + nodeId);
            if (typeof this.#data["recipients"] === "undefined")
            {
                this.#data["recipients"] = [];
            }
            this.#data["recipients"].push(nodeId);
            this.#showRecipients();
            this.#textArea.focus();
        });

        this.#currentFocus = this.#recipientsBox;


        let spacer = document.createElement("div");
        spacer.classList.add("margin-spacer")
        this.#innerContainer.appendChild(spacer);


        this.#textArea.classList.add("fullwidth-text-input");
        this.#textArea.rows = 8;
        LanguagePack.whenTemplateAvailable("ui_text/start_typing_your_message", (templ) =>
        {
            if (templ != null)
            {
                this.#textArea.placeholder = capitalize(templ);
            }
        });
        this.#innerContainer.appendChild(this.#textArea);

        this.#sendButton = document.createElement("input");
        this.#sendButton.type = "submit"
        this.#sendButton.value = "Send message";

        LanguagePack.whenTemplateAvailable("ui_text/send_message", (templ) =>
        {
            if (templ != null)
            {
                this.#sendButton.value = capitalize(templ);
            }
        });

        this.#sendButton.addEventListener("click", (e) =>
        {
            e.preventDefault();
            if (this.#data["recipients"].length > 0)
            {
                this.#sendMessage();
            }
        })
        this.#innerContainer.appendChild(this.#sendButton);

        let loadingBox = document.createElement("span");
        loadingBox.classList.add("loading-placeholder");
        loadingBox.classList.add("large-spacer");
        loadingBox.innerText = random_length_placeholder_text([4, 16]);
        this.#recipientsBox.appendChild(loadingBox);

        this.#draftId = draftId;


        this.#loadDraft();
        window.setInterval(() =>
        {
            if (this.#autosave)
            {
                this.#saveDraft()
            }
        }, 5000);
    }

    render()
    {
        return this.#rootContainer;
    }

    focus()
    {
        if (this.#currentFocus != null)
        {
            this.#currentFocus.focus();
        }
    }

    #saveDraft()
    {
        let http = new XMLHttpRequest();
        let url = "/api/v2/drafts/" + this.#draftId;

        http.open("POST", url, true);
        http.setRequestHeader("Content-Type", "application/json");
        let responseHad = false;

        this.#data["body"] = this.#textArea.value;

        http.onreadystatechange = (e) =>
        {
            if (http.readyState == 4 && !responseHad)
            {
                let response = {};
                responseHad = true;

                try
                {
                    response = JSON.parse(http.responseText);
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                }
            }
        }

        http.send(JSON.stringify(this.#data));
    }

    #showRecipients()
    {

        if (typeof this.#data["recipients"] === "undefined")
        {
            this.#data["recipients"] = [];
        }
        if (this.#data["recipients"].length > 0)
        {
            let newChildren = [];

            let label = document.createElement("span");
            label.innerText = "Send to";
            label.style.margin = "0 0.5em 0 0";
            newChildren.push(label);

            let rebuiltRecipients = [];

            for (let i = 0; i < this.#data["recipients"].length; i++)
            {
                if (this.#data["recipients"][i] != null)
                {

                    let recipient = document.createElement("span");
                    recipient.classList.add("removeable-badge");
                    let user = new PathNameView(this.#data["recipients"][i]);
                    recipient.appendChild(user.render());

                    let removeRecipientButton = document.createElement("a");
                    removeRecipientButton.classList.add("inline-icon-low-margin");
                    removeRecipientButton.classList.add("close-icon");
                    removeRecipientButton.href = "#";
                    removeRecipientButton.style.margin = "0 0 0 0.5em";
                    recipient.appendChild(removeRecipientButton);

                    removeRecipientButton.addEventListener("click", (e) =>
                    {
                        e.preventDefault();
                        this.#data["recipients"][i] = null;
                        this.#showRecipients();
                    })

                    if ((!rebuiltRecipients.includes(this.#data["recipients"][i])) && this.#data["recipients"][i] != null)
                    {
                        rebuiltRecipients.push(this.#data["recipients"][i]);
                        newChildren.push(recipient);
                    }
                }
            }

            this.#data["recipients"] = rebuiltRecipients;
            //console.log(rebuiltRecipients)
            if (rebuiltRecipients.length == 0)
            {
                newChildren = [];
                this.#recipientsBox.style.display = null;
                this.#recipientsBox.focus();
            }
            else
            {
                this.#recipientsBox.style.display = "none";
                let newRecipientButton = document.createElement("a");
                newRecipientButton.classList.add("large-inline-icon");
                newRecipientButton.classList.add("add-icon");
                newRecipientButton.href = "#";

                newRecipientButton.addEventListener("click", (e) =>
                {
                    e.preventDefault();
                    this.#recipientsBox.style.display = null;
                    this.#recipientsBox.focus();
                })

                newChildren.push(newRecipientButton);
            }

            this.#recipientsContainerBox.replaceChildren(...newChildren);
        }
        else
        {
            console.log(this.#data["recipients"])
        }
    }

    #loadDraft()
    {
        let http = new XMLHttpRequest();
        let url = "/api/v2/drafts/" + this.#draftId;

        http.open("GET", url, true);
        let responseHad = false;


        http.onreadystatechange = (e) =>
        {
            if (http.readyState == 4 && !responseHad)
            {
                responseHad = true;

                try
                {
                    let response = JSON.parse(http.responseText);
                    this.#data = response["content"];

                    if (typeof this.#data["body"] !== "undefined")
                    {
                        this.#textArea.value = this.#data["body"];
                    }
                    this.#showRecipients();
                    this.#autosave = true;
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                }
            }
        }

        http.send(JSON.stringify(this.#data));
    }

    #sendMessage()
    {
        this.#sendButton.disabled = true;
        this.#recipientsBox.disabled = true;
        this.#textArea.disabled = true;
        let loadingIcon = document.createElement("span");
        loadingIcon.classList.add("loading-icon");
        let loadingText = document.createElement("span");
        loadingText.innerText = "Sending your message ";
        this.#recipientsBox.style.display = "none";
        this.#recipientsContainerBox.style.display = "none";
        this.#sendButton.after(loadingText);
        loadingText.after(loadingIcon);
        this.#autosave = false;

        // Save draft finally, then send it

        let http = new XMLHttpRequest();
        let url = "/api/v2/drafts/" + this.#draftId;

        http.open("POST", url, true);
        http.setRequestHeader("Content-Type", "application/json");
        let responseHad = false;

        this.#data["body"] = this.#textArea.value;

        http.onreadystatechange = (e) =>
        {
            if (http.readyState == 4 && !responseHad)
            {
                let response = {};
                responseHad = true;

                try
                {
                    response = JSON.parse(http.responseText);
                    //console.log(response);

                    //Now we give the instruction to send the draft

                    let httpInner = new XMLHttpRequest();
                    let url = "/api/v2/drafts/" + this.#draftId + "/send";
                    httpInner.open("GET", url, true);
                    let innerResponseHad = false;
                    httpInner.onreadystatechange = (e) =>
                    {
                        if (httpInner.readyState == 4 && !innerResponseHad)
                        {
                            let response = {};
                            innerResponseHad = true;

                            try
                            {
                                response = JSON.parse(httpInner.responseText);
                                let jobRef = response["job_reference"];
                                jobRef = jobRef.split(".");
                                let jobId = jobRef[0] + "." + jobRef[1];
                                this.#awaitJob(jobId, () =>
                                {
                                    console.log("Sent message");
                                    console.log(response);
                                    window.location = "/graph/~" + response["message_node_id"];
                                }, () =>
                                {
                                    console.log("Failed to send message!");
                                    console.log(response);
                                    LanguagePack.whenTemplateAvailable("ui_text/message_failed_to_send", (templ) =>
                                    {
                                        if (templ != null)
                                        {
                                            window.alert(capitalize(templ));
                                        }

                                    });

                                    this.#recipientsContainerBox.style.display = null;
                                    loadingText.remove();
                                    loadingIcon.remove();
                                    this.#sendButton.disabled = false;
                                    this.#recipientsBox.disabled = false;
                                    this.#textArea.disabled = false;
                                    this.#autosave = true;
                                });
                                //console.log(response);
                            } catch (e)
                            {
                                console.log(e);
                                console.log(http.responseText);
                            }
                        }
                    }
                    httpInner.send();
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                }
            }
        }

        http.send(JSON.stringify(this.#data));
    }

    #awaitJob(jobId, callback, callbackFailed)
    {
        let intervalId = window.setInterval(() =>
        {
            let http = new XMLHttpRequest();
            let url = "/api/v2/jobs/" + jobId;

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
                        //console.log(response);
                        if (response["status"] == "DONE")
                        {
                            window.clearInterval(intervalId);
                            callback();
                        }
                        if (response["status"] == "FAILED")
                        {
                            window.clearInterval(intervalId);
                            callbackFailed();
                        }
                    } catch (e)
                    {
                        console.log(e);
                        console.log(http.responseText);
                    }
                }
            }

            http.send();
        }, 200);
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
