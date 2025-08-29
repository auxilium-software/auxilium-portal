class NewPropertyPopup
{
    #rootContainer = null;
    #innerContainer = null;
    #data = {};
    #currentFocus = null;
    #okButton = null;
    #onSuccess = null;

    /**
     * Creates an instance of a modal interface for processing and saving different types of data.
     *
     * @param {Array|string|null} types An array of types or a single type the instance supports. If null, default types are used.
     * @param {string|null} targetPath The target path where data will be saved. Must be specified and formatted correctly.
     * @param {boolean} floating Determines if the interface should act as a floating panel or not.
     *
     * @param {boolean} makeParents Specifies whether intermediate directories should be created during the data saving process.
     * @return {void} Does not return any value.
     */
    constructor(
        types = null,
        targetPath = null,
        floating = false,
        makeParents = true
    )
    {
        this.#rootContainer = document.createElement("div");
        console.log(targetPath);

        if (types == null)
        {
            types = [
                "ICALENDAR_TODO",
                "ICALENDAR_JOURNAL",
                "ICALENDAR_EVENT",
                "FILE_UPLOAD",
                "PLAIN_TEXT",
            ];
        }

        if (!Array.isArray(types))
        {
            types = [types];
        }

        if (floating)
        {
            this.#innerContainer = document.createElement("div");
            this.#innerContainer.classList.add("inner-content");
            this.#innerContainer.classList.add("message-draft");
            let closeButton = document.createElement("a");
            closeButton.classList.add("action-link");
            closeButton.classList.add("action-link-delete");
            closeButton.innerText = "Cancel";
            LanguagePack.whenTemplateAvailable("ui_text/cancel", (templ) =>
            {
                if (templ != null)
                {
                    closeButton.innerText = capitalize(templ);
                }
            });
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

        let propName = null;

        /**
         *
         */
        if (targetPath.endsWith("/*"))
        {
            let pathRoot = document.createElement("span");
            pathRoot.innerText = "Saving to " + targetPath.substring(0, targetPath.length - 1) + "...";
            this.#innerContainer.appendChild(pathRoot);

            propName = document.createElement("input");
            propName.type = "text";
            propName.placeholder = "preferred_name";
            this.#innerContainer.appendChild(propName);

            this.#currentFocus = propName;
        }
        /**
         * For "Array" type objects...
         */
        else if (targetPath.endsWith("/#"))
        {
            let pathRoot = document.createElement("span");
            pathRoot.innerText = "Saving to " + targetPath;
            this.#innerContainer.appendChild(pathRoot);
        }
        /**
         *
         */
        else
        {
            let pathRoot = document.createElement("span");
            pathRoot.innerText = "Saving to " + targetPath;
            this.#innerContainer.appendChild(pathRoot);
        }

        let pageOne = document.createElement("div");
        pageOne.classList.add("logical-box");

        let calculateFinalPath = (propNameDefault = null) =>
        {
            let finalPath = targetPath;
            if (finalPath.endsWith("/*"))
            {
                let propNameString = propName.value;
                if (propNameDefault != null)
                {
                    if (propNameString.length === 0)
                    {
                        propNameString = propNameDefault; // If we didn't get a good name supplied by the user, fallback to defaulkt
                    }
                }
                propNameString = propNameString.substring(0, 1).replace(/[^a-z]/gi, '_') + propNameString.substring(1, propNameString.length).replace(/[^0-9a-z]/gi, '_');
                propNameString = propNameString.toLowerCase();
                if (propNameString.length === 0)
                {
                    propNameString = "#" // Fallback to just letting deegraph enumerate if everything has gone to pot
                }
                finalPath = finalPath.substring(0, finalPath.length - 1) + propNameString;
            }
            return finalPath;
        }

        let putData = (data, mimeType, propNameDefault = null, afterPut = null) =>
        {
            let finalPath = calculateFinalPath(propNameDefault);

            let promise = put_data_at_path(
                finalPath,
                new Blob([data], {type: mimeType}),
                makeParents
            );
            promise.then((resp) =>
            {
                //console.log(resp)
                if (afterPut != null)
                {
                    afterPut(resp, () =>
                    {
                        if (this.#onSuccess != null)
                        {
                            this.#onSuccess(resp);
                        }
                    });
                }
                else
                {
                    if (this.#onSuccess != null)
                    {
                        this.#onSuccess(resp);
                    }
                }
            });
            return promise;
        }


        let option = null;

        let plainTextAction = (e) =>
        {
            pageOne.remove();
            let textArea = document.createElement("textarea");
            this.#innerContainer.appendChild(textArea);
            textArea.focus();

            this.#okButton.addEventListener("click", (e) =>
            {
                e.preventDefault();
                putData(textArea.value, "text/plain");
                this.#rootContainer.remove();
            });
            this.#innerContainer.appendChild(this.#okButton);
        }

        let uploadFileAction = (e) =>
        {
            pageOne.remove();
            let inputLabel = document.createElement("label");
            inputLabel.innerText = "Choose another file";
            inputLabel.classList.add("button");
            inputLabel.htmlFor = "file_upload_" + generate_random_id(16);
            this.#innerContainer.appendChild(inputLabel);
            let input = document.createElement("input");
            input.type = "file";
            input.id = inputLabel.htmlFor;
            this.#innerContainer.appendChild(input);
            input.click();

            let completeFunction = (e) =>
            {
                e.preventDefault();

                let uploadFile = (file) =>
                {
                    console.log(file);
                    let defaultFileName = file.name.replace(/\.[^/.]+$/, "");
                    let finalWrapUp = (resp, func) =>
                    {
                        console.log(resp)
                        console.log("Adding name " + file.name + " to " + resp["id"])
                        put_data_at_path("{" + resp["id"] + "}/filename", new Blob([file.name], {type: "text/plain"}))

                        func(resp);
                    };
                    putData(file, file.type, defaultFileName, finalWrapUp); // Set the filename sans-extension to be the default
                }

                //let dt = e.dataTransfer;
                let files = e.target.files;
                ([...files]).forEach(uploadFile);
                this.#rootContainer.remove();
            }

            input.addEventListener("change", completeFunction);

            this.#okButton.addEventListener("click", completeFunction);
            this.#innerContainer.appendChild(this.#okButton);
        }

        let icalTodoAction = (e) =>
        {
            pageOne.remove();
            let textArea = document.createElement("textarea");
            this.#innerContainer.appendChild(textArea);
            textArea.focus();

            this.#okButton.addEventListener("click", (e) =>
            {
                e.preventDefault();
                //"STATUS:NEEDS-ACTION" + "\r\n" +
                //"DUE;VALUE=DATE:20070501" + "\n" +
                let icalNote = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" +
                    "BEGIN:VTODO" + "\r\n" +
                    "UID:" + generateRandomId(48) + "\r\n" +
                    "DTSTAMP:" + get_tz_date() + "\r\n" +
                    ical_wrap("SUMMARY:" + ical_sanitize(textArea.value)) + "\r\n" +
                    "END:VTODO" + "\r\n" +
                    "END:VCALENDAR\r\n";
                putData(icalNote, "text/calendar");
                this.#rootContainer.remove();
            });
            this.#innerContainer.appendChild(this.#okButton);
        }


        let icalJournalAction = (e) =>
        {
            pageOne.remove();

            let summaryBox = document.createElement("input");
            summaryBox.type = "text";
            this.#innerContainer.appendChild(summaryBox);
            summaryBox.focus();

            let descriptionBox = document.createElement("textarea");
            this.#innerContainer.appendChild(descriptionBox);

            this.#okButton.addEventListener("click", (e) =>
            {
                e.preventDefault();
                //"STATUS:NEEDS-ACTION" + "\r\n" +
                //"DUE;VALUE=DATE:20070501" + "\n" +
                let icalNote = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" +
                    "BEGIN:VJOURNAL" + "\r\n" +
                    "UID:" + generateRandomId(48) + "\r\n" +
                    "DTSTAMP:" + get_tz_date() + "\r\n" +
                    ical_wrap("SUMMARY:" + ical_sanitize(summaryBox.value)) + "\r\n" +
                    ((descriptionBox.value.length > 0) ? (ical_wrap("DESCRIPTION:" + ical_sanitize(descriptionBox.value)) + "\r\n") : "") +
                    "END:VJOURNAL" + "\r\n" +
                    "END:VCALENDAR\r\n";
                putData(icalNote, "text/calendar");
                this.#rootContainer.remove();
            });
            this.#innerContainer.appendChild(this.#okButton);
        }

        let spacer = document.createElement("div");
        spacer.classList.add("small-spacer");
        this.#innerContainer.appendChild(spacer);

        this.#okButton = document.createElement("input");
        this.#okButton.type = "submit"
        this.#okButton.value = "Save data";

        this.#innerContainer.appendChild(pageOne);
        if (types.length == 1)
        {
            if (types.indexOf("PLAIN_TEXT") != -1)
            {
                plainTextAction();
            }

            if (types.indexOf("ICALENDAR_TODO") != -1)
            {
                icalTodoAction();
            }

            if (types.indexOf("ICALENDAR_JOURNAL") != -1)
            {
                icalJournalAction();
            }

            if (types.indexOf("FILE_UPLOAD") != -1)
            {
                uploadFileAction();
            }
        }
        else
        {
            if (types.indexOf("PLAIN_TEXT") != -1)
            {
                option = document.createElement("a");
                option.classList.add("navigational-link");
                option.innerText = "New text property";
                option.href = "javascript:;";
                option.addEventListener("click", plainTextAction);
                pageOne.appendChild(option);
                pageOne.appendChild(document.createElement("br"));
            }

            if (types.indexOf("FILE_UPLOAD") != -1)
            {
                option = document.createElement("a");
                option.classList.add("navigational-link");
                option.innerText = "Upload a file";
                option.href = "javascript:;";
                option.addEventListener("click", uploadFileAction);
                pageOne.appendChild(option);
                pageOne.appendChild(document.createElement("br"));
            }

            if (types.indexOf("ICALENDAR_TODO") != -1)
            {
                option = document.createElement("a");
                option.classList.add("navigational-link");
                option.innerText = "New todo note";
                option.href = "javascript:;";
                option.addEventListener("click", icalTodoAction);
                pageOne.appendChild(option);
                pageOne.appendChild(document.createElement("br"));
            }

            if (types.indexOf("ICALENDAR_JOURNAL") != -1)
            {
                option = document.createElement("a");
                option.classList.add("navigational-link");
                option.innerText = "New timeline note";
                option.href = "javascript:;";
                option.addEventListener("click", icalJournalAction);
                pageOne.appendChild(option);
                pageOne.appendChild(document.createElement("br"));
            }
        }

        LanguagePack.whenTemplateAvailable("ui_text/save_data", (templ) =>
        {
            if (templ != null)
            {
                this.#okButton.value = capitalize(templ);
            }
        });


        //window.setInterval(() => {if (this.#autosave) {this.#saveDraft()}}, 5000);
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

    onSuccess(func)
    {
        this.#onSuccess = func;
    }

    #runQuery(query, callback, failCallback = null)
    {
        let http = new XMLHttpRequest();
        let url = "/api/v2/query";

        let data = new FormData();
        data.append("query", query);
        data.append("paginate", false);

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

                    if (response.hasOwnProperty("result"))
                    {
                        callback(response["result"]);
                    }
                    else
                    {
                        if (failCallback != null)
                        {
                            failCallback();
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
}
