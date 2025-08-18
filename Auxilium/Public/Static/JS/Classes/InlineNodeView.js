class InlineNodeView
{
    #loadingIndicator;
    #target;
    #targetSecondaryActions;
    #schema = null;
    #path = null;
    #allowEdit = false;
    #data = null;
    #nodeId = null;
    #inhibitPropertyLists = false;

    constructor(nodePath, existingInfo = null, inhibitPropertyLists = false, allowEdit = true)
    {
        this.#target = document.createElement("div");
        this.#targetSecondaryActions = document.createElement("span");
        this.#allowEdit = allowEdit;

        this.#inhibitPropertyLists = inhibitPropertyLists;

        this.#target.classList.add("logical-box");
        //this.#targetSecondaryActions.classList.add("logical-box");

        this.#loadingIndicator = document.createElement("div");
        this.#loadingIndicator.classList.add("logical-box");

        let indicatorBox = document.createElement("div");
        indicatorBox.classList.add("loading-placeholder");
        indicatorBox.classList.add("large-spacer");
        this.#loadingIndicator.appendChild(indicatorBox);

        this.#target.appendChild(this.#loadingIndicator);

        this.#path = nodePath;

        if (existingInfo != null)
        {
            if (!(existingInfo instanceof AuxiliumNode))
            {
                existingInfo = null;
            }
        }
        //existingInfo = null;

        if (existingInfo != null)
        {
            existingInfo.getRawSchema().then((schema) =>
            {
                existingInfo.getRawData().then((data) =>
                {
                    this.#baseInfoLoadHook(data, existingInfo.getUuid(), schema);
                });
            });
        }
        else
        {
            let http = new XMLHttpRequest();
            let url = "/api/v2/query";

            let data = new FormData();
            data.append("query", "SELECT @data, @id, @schema FROM " + nodePath + "");
            data.append("paginate", false);

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
                        let result = response["result"];

                        //console.log(result);

                        let infoFound = false;

                        if (result.hasOwnProperty("@rows"))
                        {
                            let vals = Object.values(result["@rows"]);
                            let val = (vals.length > 0) ? vals[0] : null;
                            if (val != null)
                            {
                                let keys = Object.keys(val);
                                //console.log(keys);
                                let data = null;
                                let id = null;
                                let schema = null;
                                infoFound = true;
                                let map = {};
                                for (let i = 0; i < keys.length; i++)
                                {
                                    let ivals = Object.values(val[keys[i]]);
                                    let ival = (ivals.length > 0) ? ivals[0] : null;
                                    if (ival != null)
                                    {
                                        switch (keys[i])
                                        {
                                            case "@data":
                                                data = ival;
                                                break;
                                            case "@id":
                                                id = ival.replace(/[{}]/g, "");
                                                break;
                                            case "@schema":
                                                schema = ival;
                                                break;
                                        }
                                    }
                                }
                                this.#baseInfoLoadHook(data, id, schema);
                            }
                        }

                        if (!infoFound)
                        {
                            this.#target.innerHTML = "<span><em>Missing data</em></span>";
                            console.log("[WARNING] Missing data: " + nodePath);
                        }

                    } catch (e)
                    {
                        console.log(e);
                        console.log(nodePath);
                        console.log(http.responseText);
                    }
                }
            }
        }
    }

    render()
    {
        return this.#target;
    }

    renderSecondaryActions()
    {
        return this.#targetSecondaryActions;
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
                    console.log("QUERY \"" + query + "\" FAILED");
                    if (failCallback != null)
                    {
                        failCallback();
                    }
                }
            }
        }

        http.send(data);
    }

    #fileDisplay(downloadLink, mediaType, size, deleteAction = null)
    {
        let downloadBox = document.createElement("a");
        downloadBox.href = downloadLink;
        //innerElem.innerText = auxLfsUri;
        downloadBox.classList.add("download-box");

        let nameElem = document.createElement("span");
        nameElem.classList.add("file-name");
        let spinner = document.createElement("span");
        spinner.classList.add("loading-placeholder");
        spinner.innerText = random_length_placeholder_text([16, 32]);
        nameElem.appendChild(spinner);
        downloadBox.appendChild(nameElem);
        let deleteAttributes = {
            "button": document.createElement("a")

        };
        if (deleteAction != null)
        {
            deleteAttributes.button.classList.add("inline-icon");
            deleteAttributes.button.classList.add("delete-icon");
            deleteAttributes.button.href = "javascript:void(0);";
            deleteAttributes.title = mediaType
            deleteAttributes.button.addEventListener("click", () =>
            {
                deleteAction(deleteAttributes["title"]);
            });
            downloadBox.appendChild(deleteAttributes.button);
        }

        this.#fetchInfo("SELECT filename FROM {" + this.#nodeId + "}", (result) =>
        {
            spinner.remove();
            nameElem.innerHTML = "<em>Unnamed file</em>";
            //console.log(result);
            if (result.hasOwnProperty("filename"))
            {
                let resRow = result["filename"][Object.keys(result["filename"])[0]];
                url_to_data(resRow).then((data) =>
                {
                    data.text().then((data) =>
                    {
                        nameElem.innerText = data;
                        deleteAttributes.title = data;
                    });
                });
            }
        }, () =>
        {
            spinner.remove();
            nameElem.innerHTML = "<em>Unnamed file</em>";
        });

        let elem = document.createElement("div");
        elem.classList.add("separator");
        downloadBox.appendChild(elem);

        elem = document.createElement("span");
        elem.classList.add("file-meta-info");
        if (isNaN(size))
        {
            size = 1;
        }
        elem.innerText = human_filesize(size) + " (" + mediaType + ")";
        downloadBox.appendChild(elem);

        this.#target.appendChild(downloadBox);
    }

    #messageDisplay(deleteAction = null)
    {
        let innerDataDisplay = (downloadUrl, url, metadata) =>
        {
            // Create a container for the message
            let messageBox = document.createElement("div");
            messageBox.classList.add("message-box");

            // Add a placeholder for the subject
            let subjectElem = document.createElement("span");
            subjectElem.classList.add("email-subject");
            let spinner = document.createElement("span");
            spinner.classList.add("loading-placeholder");
            spinner.innerText = random_length_placeholder_text([16, 32]);
            subjectElem.appendChild(spinner);
            messageBox.appendChild(subjectElem);

            // Add a download button
            let elem = document.createElement("span");
            elem.innerText = " ";
            let innerElem = document.createElement("a");
            innerElem.classList.add("inline-icon-low-margin");
            innerElem.classList.add("download-icon");
            innerElem.classList.add("download-button");
            innerElem.href = downloadUrl;
            elem.appendChild(innerElem);
            messageBox.appendChild(elem);

            // Add a separator
            elem = document.createElement("div");
            elem.classList.add("separator");
            messageBox.appendChild(elem);

            // Add a placeholder for the sender
            let senderElem = document.createElement("span")
            senderElem.classList.add("email-correspondent");
            spinner = document.createElement("span");
            spinner.classList.add("loading-placeholder");
            spinner.innerText = random_length_placeholder_text([16, 32]);
            senderElem.appendChild(spinner);
            messageBox.appendChild(senderElem);

            // Add an arrow icon
            let arrowIcon = document.createElement("span")
            arrowIcon.classList.add("email-correspondent");
            arrowIcon.classList.add("inline-icon-low-margin");
            arrowIcon.classList.add("arrow-right-icon");
            messageBox.appendChild(arrowIcon);

            // Add a placeholder for the recipient
            let recipientElem = document.createElement("span")
            recipientElem.classList.add("email-correspondent");
            spinner = document.createElement("span");
            spinner.classList.add("loading-placeholder");
            spinner.innerText = random_length_placeholder_text([16, 32]);
            recipientElem.appendChild(spinner);
            messageBox.appendChild(recipientElem);

            // Add a line separator
            elem = document.createElement("div");
            elem.classList.add("message-box-line");
            messageBox.appendChild(elem);

            // Add a placeholder for the content
            let contentElem = document.createElement("span")
            contentElem.classList.add("content");
            spinner = document.createElement("span");
            spinner.classList.add("loading-placeholder");
            spinner.innerText = random_length_placeholder_text([256, 384]);
            contentElem.appendChild(spinner);
            messageBox.appendChild(contentElem);

            // Add another line separator
            elem = document.createElement("div");
            elem.classList.add("message-box-line");
            messageBox.appendChild(elem);

            // Send a request to fetch message data
            let http = new XMLHttpRequest();
            let ecurl = "/api/v2/retrieve-rfc822-component/" + this.#nodeId + "?subject,from,to,text-content,x-auxilium-message-version";

            http.open("GET", ecurl, true);

            let responseHad = false;

            // Handle the response from the API
            http.onreadystatechange = (e) =>
            {
                if (http.readyState == 4 && !responseHad)
                {
                    let response = {};
                    responseHad = true;

                    response = JSON.parse(http.responseText);

                    // Update the subject
                    if (response["subject"] == null)
                    {
                        subjectElem.innerHTML = "</span><em>No subject</em>";
                    }
                    else
                    {
                        subjectElem.innerText = response["subject"];
                    }

                    // Add an icon based on the message type
                    let typeIcon = document.createElement("span")
                    typeIcon.classList.add("inline-icon");
                    typeIcon.classList.add("inline-icon-shift-left");
                    let basicAuxiliumMessageFeatures = false;

                    if (response["x-auxilium-message-version"] == null)
                    {
                        typeIcon.classList.add("email-icon");
                        typeIcon.title = "This was an external email";
                    }
                    else
                    {
                        basicAuxiliumMessageFeatures = true;
                        typeIcon.classList.add("chat-icon");
                        typeIcon.title = "This message was sent using Auxilium";
                    }
                    subjectElem.prepend(typeIcon);

                    // Process the sender information
                    let auxInboxRegex = /auxiliuminbox\+[a-f0-9]{8}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{12}@.+/;

                    if (response["from"] == null)
                    {
                        senderElem.innerHTML = "<em>Unknown sender</em>";
                    }
                    else
                    {
                        senderElem.innerHTML = "";

                        if (auxInboxRegex.test(response["from"]))
                        {
                            let senderUuid = /[a-f0-9]{8}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{12}/g.exec(response["from"]);
                            let senderPartElem = new PathNameView("{" + senderUuid[0] + "}", true, true);
                            senderElem.appendChild(senderPartElem.render());
                        }
                        else
                        {
                            let senderPartElem = document.createElement("span");
                            senderPartElem.classList.add("email-correspondent");
                            senderPartElem.innerText = response["from"];
                            senderElem.appendChild(senderPartElem);
                        }
                    }

                    // Process the recipient information
                    if (response["to"] == null)
                    {
                        recipientElem.innerHTML = "";
                    }
                    else
                    {
                        recipientElem.innerHTML = "";
                        let recipients = response["to"];
                        if (typeof recipients === 'string' || recipients instanceof String)
                        {
                            recipients = [recipients];
                        }
                        for (let i = 0; i < recipients.length; i++)
                        {
                            if (auxInboxRegex.test(recipients[i]))
                            {
                                let recipientUuid = /[a-f0-9]{8}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{12}/g.exec(recipients[i]);
                                let recipientPartElem = new PathNameView("{" + recipientUuid[0] + "}", true, true);
                                recipientElem.appendChild(recipientPartElem.render());
                            }
                            else
                            {
                                let recipientPartElem = document.createElement("span")
                                recipientPartElem.classList.add("email-correspondent");
                                recipientPartElem.innerText = recipients[i];
                                recipientElem.appendChild(recipientPartElem);
                            }

                            if ((recipients.length - 1) != i)
                            {
                                let separator = document.createElement("span");
                                separator.classList.add("email-correspondent");
                                separator.innerText = ", ";
                                recipientElem.appendChild(separator);
                            }
                        }
                    }

                    // Process the content
                    if (response["text-content"] == null)
                    {
                        contentElem.innerHTML = "<em>Error loading content</em>";
                    }
                    else
                    {
                        contentElem.innerHTML = "";
                        if (response["text-content"].length > 1200)
                        {
                            let fullTextElem = document.createElement("span");
                            fullTextElem.id = "large_text_message-" + this.#nodeId + "_full";
                            fullTextElem.innerText = response["text-content"].trim() + " ";
                            fullTextElem.style.display = "none";
                            let toggleButton = document.createElement("a");
                            toggleButton.href = "javascript:hide_large_text(\"message-" + this.#nodeId + "\");";
                            toggleButton.classList.add("inline-icon");
                            toggleButton.classList.add("close-fullscreen-icon");
                            fullTextElem.appendChild(toggleButton);
                            contentElem.appendChild(fullTextElem);

                            let shortTextElem = document.createElement("span");
                            shortTextElem.id = "large_text_message-" + this.#nodeId + "_preview";
                            shortTextElem.innerText = response["text-content"].trim().substr(0, 1000).trim() + " ";
                            toggleButton = document.createElement("a");
                            toggleButton.href = "javascript:expand_large_text(\"message-" + this.#nodeId + "\");";
                            toggleButton.classList.add("inline-icon");
                            toggleButton.classList.add("more-icon");
                            shortTextElem.appendChild(toggleButton);
                            contentElem.appendChild(shortTextElem);
                        }
                        else
                        {
                            let fullTextElem = document.createElement("span");
                            fullTextElem.id = "large_text_message-" + this.#nodeId + "_full";
                            fullTextElem.innerText = response["text-content"].trim();
                            contentElem.appendChild(fullTextElem);
                        }
                    }
                }
            }

            http.send();

            elem = document.createElement("span");
            elem.classList.add("file-meta-info");
            elem.innerText = human_filesize(metadata["size"]) + " (" + metadata["type"] + ")";
            messageBox.appendChild(elem);

            this.#target.appendChild(messageBox);
        };

        if (this.#data.startsWith("data:"))
        {
            urlToData(this.#data, (data, mediaType) =>
            {
                this.#target.appendChild((extractedData, mediaType) =>
                {
                    let metadata = {
                        "size": extractedData.length,
                        "type": mediaType
                    }
                    innerDataDisplay(this.#data, this.#data, metadata);
                });
            });
        }
        else if (this.#data.startsWith("auxlfs://"))
        {
            let auxLfsUri = expand_aux_lfs_uri(this.#data, this.#nodeId);
            let metadata = extract_aux_lfs_metadata(auxLfsUri);
            let downloadLink = aux_lfs_uri_to_https(auxLfsUri);
            innerDataDisplay(downloadLink, auxLfsUri, metadata);
        }
        else
        {
            content = document.createElement("span");
            let innerError = document.createElement("em");
            innerError.innerText = "Could not display unknown URI schema: " + this.#data;
            content.appendChild(innerError);
            this.#target.appendChild(content);
        }
    }

    #chatMessageDisplay(deleteAction = null)
    {
        let innerDataDisplay = (downloadUrl, url, metadata) =>
        {
            let messageBox = document.createElement("div");
            messageBox.classList.add("message");
            let messageContentBox = document.createElement("div");
            messageContentBox.classList.add("message-content");

            let spinner = document.createElement("span");
            spinner.classList.add("loading-placeholder");
            spinner.innerText = random_length_placeholder_text([256, 384]);
            messageContentBox.appendChild(spinner);

            messageBox.appendChild(messageContentBox);

            let http = new XMLHttpRequest();
            let ecurl = "/api/v2/retrieve-rfc822-component/" + this.#nodeId + "?subject,from,to,text-content,x-auxilium-message-version";

            http.open("GET", ecurl, true);

            let responseHad = false;

            http.onreadystatechange = (e) =>
            {
                if (http.readyState === 4 && !responseHad)
                {
                    let response = {};
                    responseHad = true;

                    response = JSON.parse(http.responseText);

                    const uid = "2ed3d7cf-460e-47b8-bebb-2157924ccf7f";
                    if (response.from.startsWith("auxiliuminbox+" + uid))
                    {
                        messageBox.classList.add("sent");
                    }
                    else
                    {
                        messageBox.classList.add("received");
                    }

                    if (response["text-content"] == null)
                    {
                        messageContentBox.innerHTML = "<em>Error loading content</em>";
                    }
                    else
                    {
                        messageContentBox.innerHTML = "";
                        if (response["text-content"].length > 1200)
                        {
                            messageContentBox.id = "large_text_message-" + this.#nodeId + "_full";
                            messageContentBox.innerText = response["text-content"].trim() + " ";
                        }
                        else
                        {
                            messageContentBox.id = "large_text_message-" + this.#nodeId + "_full";
                            messageContentBox.innerText = response["text-content"].trim();
                        }
                    }
                }
            }
            http.send();
            this.#target.appendChild(messageBox);
        };

        if (this.#data.startsWith("data:"))
        {
            urlToData(this.#data, (data, mediaType) =>
            {
                this.#target.appendChild((extractedData, mediaType) =>
                {
                    let metadata = {
                        "size": extractedData.length,
                        "type": mediaType
                    }
                    innerDataDisplay(this.#data, this.#data, metadata);
                });
            });
        }
        else if (this.#data.startsWith("auxlfs://"))
        {
            let auxLfsUri = expand_aux_lfs_uri(this.#data, this.#nodeId);
            let metadata = extract_aux_lfs_metadata(auxLfsUri);
            let downloadLink = aux_lfs_uri_to_https(auxLfsUri);
            innerDataDisplay(downloadLink, auxLfsUri, metadata);
        }
        else
        {
            content = document.createElement("span");
            let innerError = document.createElement("em");
            innerError.innerText = "Could not display unknown URI schema: " + this.#data;
            content.appendChild(innerError);
            this.#target.appendChild(content);
        }
    }






    #mimeDisplay()
    {
        this.#target.innerHTML = "";
        this.#targetSecondaryActions.innerHTML = "";

        if (this.#data == null)
        {
            let container = document.createElement("span");
            let content = document.createElement("em");
            content.innerText = "Empty";
            container.appendChild(content);
            this.#target.appendChild(container);
        }
        else
        {

            let displayLogic = (mediaType, size, data = null) =>
            {
                let displayAs = "file";

                if (mediaType.startsWith("text/"))
                {
                    displayAs = "mime-inline";
                }
                if (mediaType.startsWith("image/"))
                {
                    displayAs = "image";
                }
                if (mediaType.startsWith("audio/"))
                {
                    displayAs = "audio";
                }
                if (mediaType.startsWith("video/"))
                {
                    displayAs = "video";
                }
                if (mediaType.startsWith("message/"))
                {
                    displayAs = "message";
                }

                if (size > (1024 * 1024))
                { // Too big to display inline (1MB), offer download
                    displayAs = "file";
                }

                let dataNeededToDisplay = true;
                switch (displayAs)
                {
                    case "file":
                        dataNeededToDisplay = false;
                        break;
                }

                let pathSplit = this.#path.split("/");
                //console.log(pathSplit);
                let truncatedPath = null;
                while (pathSplit.length > 0)
                {
                    truncatedPath = pathSplit.pop();
                    if (/^-?\d+$/.test(truncatedPath))
                    {
                        truncatedPath = null; // drop numeric ids
                    }
                    else
                    {
                        break;
                    }
                }

                let printToast = (pathName, newData = null) =>
                {
                    if (newData == null || newData.size == 0)
                    {
                        new MicroTemplate("ui_text/deleted_path", {
                            path: pathName
                        }).asString().then((str) =>
                        {
                            new ToastNotification(capitalize(str), "cloud-done");
                        });
                    }
                    else
                    {
                        new MicroTemplate("ui_text/changes_saved_to", {
                            path: pathName
                        }).asString().then((str) =>
                        {
                            new ToastNotification(capitalize(str), "cloud-done");
                        });
                    }
                }


                let printToastFail = (pathName, newData = null) =>
                {
                    if (newData == null || newData.size == 0)
                    {
                        new MicroTemplate("ui_text/failed_to_delete_path", {
                            path: pathName
                        }).asString().then((str) =>
                        {
                            new ToastNotification(capitalize(str), "cloud-off", "error");
                        });
                    }
                    else
                    {
                        new MicroTemplate("ui_text/failed_to_save_chages_to", {
                            path: pathName
                        }).asString().then((str) =>
                        {
                            new ToastNotification(capitalize(str), "cloud-off", "error");
                        });
                    }
                }

                let doFinalDisplay = (data) =>
                {
                    switch (displayAs)
                    {
                        case "message":
                            if (window.location.pathname === "/message-centre")
                            {
                                this.#chatMessageDisplay(data, () =>
                                {
                                    store_data(this.#path, null).then(() =>
                                    {
                                        new MicroTemplate("data_types/message").asString().then((str) =>
                                        {
                                            printToast(str);
                                        }).catch((resp) =>
                                        {
                                            printToast(resp.substitute);
                                        });
                                    });
                                });
                            }
                            else
                            {
                                this.#messageDisplay(data, () =>
                                {
                                    store_data(this.#path, null).then(() =>
                                    {
                                        new MicroTemplate("data_types/message").asString().then((str) =>
                                        {
                                            printToast(str);
                                        }).catch((resp) =>
                                        {
                                            printToast(resp.substitute);
                                        });
                                    });
                                });
                            }
                            break;
                        case "mime-inline":
                            data_display(data, (newData) =>
                            {
                                store_data(this.#path, newData).then(() =>
                                {
                                    new MicroTemplate("data_types/" + truncatedPath).asString().then((str) =>
                                    {
                                        printToast(str, newData);
                                    }).catch((resp) =>
                                    {
                                        printToast(resp.substitute, newData);
                                    });
                                }).catch(() =>
                                {
                                    new MicroTemplate("data_types/" + truncatedPath).asString().then((str) =>
                                    {
                                        printToastFail(str, newData);
                                    }).catch((resp) =>
                                    {
                                        printToastFail(resp.substitute, newData);
                                    });
                                });
                            }, this.#path).then((domObject) =>
                            {
                                this.#target.appendChild(domObject);
                            });
                            break;
                        case "file":
                        default:
                            let downloadLink = null;
                            if (this.#data.startsWith("data:"))
                            {
                                downloadLink = this.#data;
                            }
                            else if (this.#data.startsWith("auxlfs://"))
                            {
                                downloadLink = aux_lfs_uri_to_https(this.#data)
                            }
                            //console.log("FILE");
                            this.#fileDisplay(downloadLink, mediaType, size, (filename) =>
                            {
                                store_data(this.#path, null).then(() =>
                                {
                                    console.log(filename);
                                    printToast(filename);
                                });
                            });
                            break;
                    }
                }

                if ((data == null) && dataNeededToDisplay)
                {
                    if (this.#data.startsWith("auxlfs://"))
                    {
                        this.#data = expand_aux_lfs_uri(this.#data, this.#nodeId);
                    }
                    url_to_data(this.#data).then((data) =>
                    {
                        doFinalDisplay(data);
                    });
                }
                else
                {
                    doFinalDisplay(data);
                }
            }

            if (this.#data.startsWith("data:"))
            {
                url_to_data(this.#data).then((data) =>
                {
                    displayLogic(data.type, data.length, data);
                });
            }
            else if (this.#data.startsWith("auxlfs://"))
            {
                this.#data = expand_aux_lfs_uri(this.#data, this.#nodeId);
                let metadata = extract_aux_lfs_metadata(this.#data);
                displayLogic(metadata["type"], metadata["size"]);
            }
            else
            {
                let content = document.createElement("span");
                let innerError = document.createElement("em");
                innerError.innerText = "Could not display unknown URI schema: " + this.#data;
                content.appendChild(innerError);
                this.#target.appendChild(content);
            }

            /*else {
             content = document.createElement("span");
             let innerError = document.createElement("em");
             innerError.innerText = "Could not display unknown URI schema: " + this.#data;
             content.appendChild(innerError);
             this.#target.appendChild(content);
             }*/
        }
    }

    #baseInfoLoadHook(data, id, schema)
    {
        let schemaDisplay = (schema) =>
        {
            if (schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/case.json"))
            {
                this.#target.innerHTML = "";
                this.#targetSecondaryActions.innerHTML = "";

                let link = document.createElement("a");
                let icon = document.createElement("span");
                icon.classList.add("inline-icon-shift-left");
                icon.classList.add("inline-icon-low-margin");
                icon.classList.add("work-icon");
                link.appendChild(icon);
                let titleTag = document.createElement("span")
                titleTag.classList.add("loading-placeholder");
                titleTag.innerText = random_length_placeholder_text([24, 48]);
                link.appendChild(titleTag);
                link.classList.add("navigational-link");

                link.href = "/graph/" + deegraph_path_to_link(this.#path);

                let finalDisplay = (title, name) =>
                {
                    titleTag.classList.remove("loading-placeholder");
                    titleTag.innerText = "";


                    let finishWithName = (name) =>
                    {
                        if (name != null)
                        {
                            let sp = document.createElement("span")
                            sp.innerText = " - ";
                            titleTag.appendChild(sp);
                            sp = document.createElement("span")
                            sp.innerText = name;
                            titleTag.appendChild(sp);
                        }
                    }

                    if (title == null)
                    {
                        let emTag = document.createElement("em")
                        emTag.innerText = "Untitled Case";
                        titleTag.appendChild(emTag);
                        finishWithName(name);
                    }
                    else
                    {
                        MicroTemplate.from_packed_template(title).then((str) =>
                        {
                            let sp = document.createElement("span")
                            sp.innerText = capitalize(str);
                            titleTag.appendChild(sp);
                            finishWithName(name);
                        }).catch((err) =>
                        {
                            let sp = document.createElement("span")
                            sp.innerText = err.path;
                            titleTag.appendChild(sp);
                            finishWithName(name);
                        });
                    }

                    /*
                     if (title == null) {
                     let emTag = document.createElement("em")
                     emTag.innerText = "Untitled Case";
                     titleTag.appendChild(emTag);
                     } else {
                     let sp = document.createElement("span")
                     sp.innerText = title;
                     titleTag.appendChild(sp);
                     }

                     if (name != null) {
                     let sp = document.createElement("span")
                     sp.innerText = " - ";
                     titleTag.appendChild(sp);
                     sp = document.createElement("span")
                     sp.innerText = name;
                     titleTag.appendChild(sp);
                     }
                     */
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

                this.#target.appendChild(link);
                if (!this.#inhibitPropertyLists)
                {
                    this.#target.appendChild(new PropertyList(this.#path, false, ["description"], null, false, true, true).render());
                }
            }
            else if (schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/collection.json"))
            {
                this.#target.innerHTML = "";
                this.#targetSecondaryActions.innerHTML = "";
                //console.log("PROPERTY LIST " + this.#path);
                this.#target.appendChild(new PropertyList(this.#path, true).render());
                let link = document.createElement("a");
                link.classList.add("format-list-bulleted-icon");
                link.classList.add("inline-icon");
                link.href = "/graph/" + deegraph_path_to_link(this.#path);
                this.#targetSecondaryActions.appendChild(link);
            }
            else if (schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/user.json"))
            {
                this.#target.innerHTML = "";
                this.#targetSecondaryActions.innerHTML = "";

                let link = document.createElement("a");
                let icon = document.createElement("span");
                icon.classList.add("inline-icon-shift-left");
                icon.classList.add("inline-icon-low-margin");
                icon.classList.add("account-box-icon");
                link.appendChild(icon);
                let titleTag = document.createElement("span");
                titleTag.classList.add("loading-placeholder");
                titleTag.innerText = random_length_placeholder_text([24, 48]);
                link.appendChild(titleTag);
                link.classList.add("navigational-link");

                this.#target.appendChild(link);

                link.href = "/graph/" + deegraph_path_to_link(this.#path);

                let finalDisplay = (name) =>
                {
                    titleTag.classList.remove("loading-placeholder");
                    if (name == null)
                    {
                        let emTag = document.createElement("em")
                        emTag.innerText = "Unknown User";
                        titleTag.appendChild(emTag);
                    }
                    else
                    {
                        titleTag.innerText = name;
                    }
                }

                extract_text_property("name", this.#path).then((name) =>
                {
                    finalDisplay(name);
                }).catch((e) =>
                {
                    finalDisplay(null);
                });

                if (!this.#inhibitPropertyLists)
                {
                    this.#target.appendChild(new PropertyList(this.#path, false, ["documents", "messages", "display_name", "name", "cases"]).render());
                }
            }
            else if (schema.instanceOf("https://schemas.auxiliumsoftware.co.uk/v1/organisation.json"))
            {
                this.#target.innerHTML = "";
                this.#targetSecondaryActions.innerHTML = "";

                let link = document.createElement("a");
                let icon = document.createElement("span");
                icon.classList.add("inline-icon-shift-left");
                icon.classList.add("inline-icon-low-margin");
                icon.classList.add("account-box-icon");
                link.appendChild(icon);
                let titleTag = document.createElement("span")
                titleTag.classList.add("loading-placeholder");
                titleTag.innerText = random_length_placeholder_text([24, 48]);
                link.appendChild(titleTag);
                link.classList.add("navigational-link");

                this.#target.appendChild(link);

                link.href = "/graph/" + deegraph_path_to_link(this.#path);

                let finalDisplay = (name) =>
                {
                    titleTag.classList.remove("loading-placeholder");
                    if (name == null)
                    {
                        let emTag = document.createElement("em")
                        emTag.innerText = "Unnamed Orgnisation";
                        titleTag.appendChild(emTag);
                    }
                    else
                    {
                        titleTag.innerText = name;
                    }
                }

                extract_text_property("name", this.#path).then((name) =>
                {
                    finalDisplay(name);
                }).catch((e) =>
                {
                    finalDisplay(null);
                });

                if (!this.#inhibitPropertyLists)
                {
                    this.#target.appendChild(new PropertyList(this.#path, false, ["workers", "display_name", "name", "cases"]).render());
                }
            }
            else
            {
                this.#mimeDisplay();
            }
        }

        //console.log([data, id])
        this.#data = data;
        this.#nodeId = id;
        if (schema != null)
        {
            this.#schema = new Schema(schema, schemaDisplay, (data) =>
            {
                this.#mimeDisplay(data);
            });
        }
        if (this.#schema == null)
        {
            this.#mimeDisplay();
        }
    }
}
