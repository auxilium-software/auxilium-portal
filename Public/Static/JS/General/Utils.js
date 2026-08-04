function generateRandomId(length)
{
    console.log("Deprecated function usage REPLACE generateRandomId(length) WITH generate_random_id(length)");
    console.trace();
    return generate_random_id(length);
}

function generate_random_id(length)
{
    let output = "";
    let characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    let charactersLength = characters.length;
    for (var i = 0; i < length; i++)
    {
        output += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return output;
}

function base64_decode_url_safe(input)
{
    // Replace non-url compatible chars with base64 standard chars
    input = input
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    // Pad out with standard base64 required padding characters
    var pad = input.length % 4;
    if (pad)
    {
        if (pad === 1)
        {
            throw new Error('InvalidLengthError: Input base64url string is the wrong length to determine padding');
        }
        input += new Array(5 - pad).join('=');
    }

    return input;
}

function get_tz_date(date = null)
{
    let pad2 = (num) =>
    {
        let st = "0" + num;
        return st.substr(st.length - 2);
    }
    if (date == null)
    {
        date = new Date();
    }
    return date.getUTCFullYear().toString() + pad2(date.getUTCMonth() + 1) + pad2(date.getUTCDay() + 1) + "T" + pad2(date.getUTCHours()) + pad2(date.getUTCMinutes()) + pad2(date.getUTCSeconds()) + "Z";
}

function ical_sanitize(text)
{
    return text.replaceAll("\\", "\\\\").replaceAll("\r", "\\r").replaceAll("\n", "\\n").replaceAll(",", "\\,").replaceAll(";", "\\;");
}

function ical_wrap(text)
{
    text = text.replaceAll("\r", "").replaceAll("\n", "");
    if (text.length > 60)
    {
        let chunks = [];
        for (let i = 1, charsLength = text.length; i < charsLength; i += 75)
        {
            if (i == 1)
            {
                chunks.push(str.substring(0, 76));
            }
            else
            {
                chunks.push(" " + str.substring(i, i + 75));
            }
        }
        text = chunks.join("\r\n");
    }
    return text
}

function ical_unsanitize(text)
{
    return text.replaceAll("\r", "").replaceAll("\n", "").replaceAll("\\;", ";").replaceAll("\\,", ",").replaceAll("\\r", "\r").replaceAll("\\n", "\n").replaceAll("\\\\", "\\");
}

function toggle_hidden_box(box_number)
{
    if (document.getElementById("hidden_box_" + box_number).style.display == "none")
    {
        document.getElementById("hidden_box_" + box_number).style.display = null;
        document.getElementById("hidden_box_" + box_number + "_toggle_button").style.transform = "scaleY(-1)";
    }
    else
    {
        document.getElementById("hidden_box_" + box_number).style.display = "none";
        document.getElementById("hidden_box_" + box_number + "_toggle_button").style.transform = null;
    }
    document.getElementById("hidden_box_" + box_number + "_toggle_button").style.display = null;
}

function expand_large_text(id_number)
{
    document.getElementById("large_text_" + id_number + "_preview").style.display = "none";
    document.getElementById("large_text_" + id_number + "_full").style.display = null;
    console.log("Expand large_text_" + id_number + "_full!")
}

function hide_large_text(id_number)
{
    document.getElementById("large_text_" + id_number + "_preview").style.display = null;
    document.getElementById("large_text_" + id_number + "_full").style.display = "none";
}


function randomLengthPlaceholderText(range)
{
    console.log("Deprecated function usage REPLACE randomLengthPlaceholderText(range) WITH random_length_placeholder_text(range)");
    console.trace();
    return random_length_placeholder_text(range);
}

function random_length_placeholder_text(range = [16, 64])
{
    let length = (Math.random() * (range[1] - range[0])) + range[0];
    let output = "";
    let possible = "0123456789 ";
    for (let i = 0; i < length; i++)
    {
        output = output + possible.charAt(Math.floor(Math.random() * possible.length));
    }
    return output;
}

function humanFilesize(size)
{
    console.log("Deprecated function usage REPLACE humanFilesize(size) WITH human_filesize(size)");
    console.trace();
    return human_filesize(size);
}

function human_filesize(size)
{
    if (size <= 256)
    {
        return size + " B";
    }
    else if (size <= 256 * Math.pow(1024, 1))
    {
        return (size / Math.pow(1024, 1) + "").substring(0, 3) + " KiB";
    }
    else if (size <= 256 * Math.pow(1024, 2))
    {
        return (size / Math.pow(1024, 2) + "").substring(0, 3) + " MiB";
    }
    else if (size <= 256 * Math.pow(1024, 3))
    {
        return (size / Math.pow(1024, 3) + "").substring(0, 3) + " GiB";
    }
    return (size / Math.pow(1024, 4) + "").substring(0, 3) + " TiB";
}

function query(query, callback, failCallback = null)
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
                        failCallback(result);
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
                    failCallback(null);
                }
            }
        }
    }

    http.send(data);
}

/*
 * 
 urlToData("auxlfs://hiroko.indentationerror.com/3210e85c-84aa-4cc0-816c-2be0bfb9ab11++audio%3Aflac+28485417", (data, mimeType) => {
 let a = document.createElement('a');
 let blob = new Blob([data], {'type':mimeType});
 let filename = "blob";
 a.href = window.URL.createObjectURL(blob);
 a.download = filename;
 a.click();
 });

 urlToData("data:text/plain,test", (data, mimeType) => {
 let a = document.createElement('a');
 let blob = new Blob([data], {'type':mimeType});
 let filename = "blob";
 a.href = window.URL.createObjectURL(blob);
 a.download = filename;
 a.click();
 });

 urlToData("data:text/plain;base64,dGVzdCBzdHJpbmcKdXd1Cg", (data, mimeType) => {
 let a = document.createElement('a');
 let blob = new Blob([data], {'type':mimeType});
 let filename = "blob";
 a.href = window.URL.createObjectURL(blob);
 a.download = filename;
 a.click();
 });

 */
/*
 urlToData("auxlfs://hiroko.indentationerror.com/3210e85c-84aa-4cc0-816c-2be0bfb9ab11++audio%3Aflac+28485417", (data, mimeType) => {
 console.log(mimeType)
 });
 */

// urlToData("data:text/plain,test", (data, mimeType) => {console.log(mimeType)});

function storeToLfs(uuid, data, onSuccess, onProgress)
{
    console.log("Function storeToLfs(uuid, data, onSuccess, onProgress) will soon be deprecated");
    console.trace();
    return store_to_lfs(uuid, data, onSuccess, onProgress);
}

function store_to_lfs(uuid, data, onSuccess = null, onProgress = null)
{
    let url = "/api/v2/lfs/" + uuid;
    let xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);

    let dataSize = data.size;

    console.log("Storing " + dataSize + "B to " + uuid);

    if (onProgress != null)
    {
        xhr.upload.addEventListener("progress", (e) =>
        {
            if (e.loaded <= dataSize)
            {
                onProgress(e.loaded / dataSize);
            }
            if (e.loaded == e.total)
            {
                onProgress(1);
            }
        });
    }

    xhr.addEventListener("readystatechange", (e) =>
    {
        if (xhr.readyState == 4 && xhr.status == 200)
        {
            let resp = JSON.parse(xhr.response);
            //console.log(resp);

            if (onSuccess != null)
            {
                onSuccess(resp);
            }
        }
        else if (xhr.readyState == 4 && xhr.status != 200)
        {
            if (onProgress != null)
            {
                onProgress(-1);
            }
        }
    })

    let formData = new FormData();
    formData.append("file", data);
    xhr.send(formData);
}

/*
 function toDataUrl(data, mimeType) {
 let b64flag = false;
 if (data && data.byteLength !== undefined) {
 b64flag = true;
 }
 let dataUri = null;
 if (!b64flag) {
 dataUri = "data:" + mimeType + "," + encodeURIComponent(data);
 if (dataUri.length > ((data.length * 1.33) + 32)) { // We're better off storing as base64
 b64flag = true;
 }
 }
 if (b64flag) {
 dataUri = "data:" + mimeType + ";base64," + btoa(data);
 }

 return dataUri;
 }


 }*/

function storeData(path, blob, onSuccess, onProgress)
{
    console.log("Function storeData(path, blob, onSuccess, onProgress) will soon be deprecated");
    console.trace();
    let onProgressWrapper = (obj) =>
    {
        if (onProgress != null)
        {
            onProgress(obj["response"]);
        }
    }
    let promise = store_data(path, blob, onProgressWrapper)
    promise.then((content) =>
    {
        if (onSuccess != null)
        {
            onSuccess(content["response"]);
        }
    });
    return promise;
}


function store_data(path, blob, onProgress = null)
{
    let updateParent = () =>
    {
        console.log(path)
        let pathComponents = path.split("/");
        if (pathComponents.length > 1)
        {
            pathComponents.pop();
            default_auxilium_client.updatePath(pathComponents.join("/"));
        }
    };

    return new Promise((resolve, reject) =>
    {
        let deletePath = () =>
        {
            query("DELETE " + path, (resp) =>
            {
                resolve({
                    "action": "DELETE",
                    "response": resp
                });
                updateParent();
            }, () =>
            {
                console.log("[FAILED] " + path + " ==[ SET TO ]=> " + newData);
                reject();
            });
        }
        if (blob == null)
        {
            deletePath();
        }
        else if (!(blob instanceof Blob))
        {
            if (onProgress != null)
            {
                onProgress(-1);
            }
            reject();
        }
        else if (blob.size == 0)
        {
            deletePath();
        }
        else if (blob.size > 1024)
        {
            let lfsInstanceFqdn = document.getElementById("lfs_instance_fqdn").textContent;
            let hash = ""; // TODO implement hash for security
            let mediaType = blob.type.split(";");
            mediaType = mediaType[0].replaceAll("/", "%3A");
            let uri = "auxlfs://" + lfsInstanceFqdn + "/+" + hash + "+" + mediaType + "+" + blob.size;
            query("PUT URI \"" + uri + "\" AT " + path, (response) =>
            {
                storeToLfs(response["@node"]["@id"], blob, (resp) =>
                {
                    resolve({
                        "action": "PUT",
                        "id": response["@node"]["@id"],
                        "response": resp
                    });
                    updateParent();
                }, onProgress);
            }, () =>
            {
                if (onProgress != null)
                {
                    onProgress(-1);
                }
                reject();
            });
        }
        else
        {
            var a = new FileReader();
            a.onload = (e) =>
            {
                let uri = e.target.result;
                query("PUT URI \"" + uri + "\" AT " + path, (resp) =>
                {
                    let id = resp["@node"]["@id"];
                    resolve({
                        "action": "PUT",
                        "id": id,
                        "response": resp
                    });
                    updateParent();
                }, () =>
                {
                    if (onProgress != null)
                    {
                        onProgress(-1);
                    }
                    reject();
                });
            };
            a.readAsDataURL(blob);
        }
    })
}

function putDataAtPath(path, data, mimeType, makeParents)
{
    console.log("Function putDataAtPath(path, data, mimeType, makeParents) will soon be deprecated");
    console.trace();
    let blob = new Blob([data], {type: mimeType});
    return put_data_at_path(path, blob, makeParents);
}

function put_data_at_path(path, blob, makeParents = false, progressCallback = null)
{
    return new Promise((resolve, reject) =>
    {

        let onPathAvalaible = () =>
        {
            store_data(path, blob, (progress) =>
            {
                //console.log("progress " + progress);
                if (progressCallback != null)
                {
                    progressCallback(progress)
                }
            }).then((resp) =>
            {
                resolve(resp);
            });
        };

        let fail = (e) =>
        {
            console.log("FAIL TO CREATE PARENTS");
            reject();
        };

        if (makeParents)
        {
            let pathIntermediaries = path.split("/");
            if (pathIntermediaries.length > 2)
            {
                let construct = [];
                while (pathIntermediaries.length > 2)
                {
                    pathIntermediaries.pop();
                    construct.unshift(pathIntermediaries.join("/"));
                }
                let next = [];
                next.push(() =>
                {
                    onPathAvalaible();
                });
                while (construct.length > 0)
                {
                    let cs = construct.pop();
                    console.log(cs);
                    next.push(() =>
                    {
                        //console.log("making " + cs);
                        query("PUT SCHEMA \"https://schemas.auxiliumsoftware.co.uk/v1/collection.json\" AT " + cs + " SAFE", next.pop(), fail);
                    });
                }
                (next.pop())();
            }
            else
            {
                onPathAvalaible();
            }
        }
        else
        {
            onPathAvalaible();
        }
    });
}

function urlToData(url, callback, progressCallback)
{
    console.log("Deprecated function usage REPLACE urlToData(url, callback, progressCallback) WITH url_to_data(url) :: Promise");
    console.trace();
    let promise = url_to_data(url);
    promise.then((blob) =>
    {
        callback(blob, blob.type);
    });
}

function url_to_data(url)
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

function extractTextProperty(property, path, onSuccess, onProgress)
{
    console.log("Deprecated function usage REPLACE extractTextProperty(property, path, onSuccess, onProgress) WITH extract_text_property(property, path, onProgress) :: Promise");
    console.trace();
    let promise = extract_text_property(property, path, onProgress);
    promise.then((text) =>
    {
        onSuccess(text);
    });
}


function extract_text_property(property, path, onProgress)
{
    return new Promise((resolve, reject) =>
    {
        query("SELECT " + property + " FROM " + path + "", (result) =>
        {
            if (result.hasOwnProperty("@rows") && (result["@rows"].length > 0))
            {
                let dataUri = null;
                let result2 = result["@rows"][0];
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
                    url_to_data(dataUri).then((blob) =>
                    {
                        if (blob instanceof Blob && blob.type.startsWith("text/plain"))
                        {
                            blob.text().then(resolve).catch(() =>
                            {
                                if (onProgress != null)
                                {
                                    onProgress(-1);
                                    reject();
                                }
                            });
                        }
                        else
                        {
                            if (onProgress != null)
                            {
                                onProgress(-1);
                                reject();
                            }
                        }
                    }).catch(() =>
                    {
                        if (onProgress != null)
                        {
                            onProgress(-1);
                            reject();
                        }
                    });
                }
                else
                {
                    if (onProgress != null)
                    {
                        onProgress(-1);
                        reject();
                    }
                }
            }
            else if (onProgress != null)
            {
                onProgress(-1);
                reject();
            }
        }, () =>
        {
            if (onProgress != null)
            {
                onProgress(-1);
                reject();
            }
        });
    });
}

function dataDisplay(data, renderCallback, editCallback, path)
{
    console.log("Deprecated function usage REPLACE dataDisplay(data, renderCallback, editCallback, path) WITH data_display(data, editCallback, path) :: Promise");
    console.trace();
    let promise = data_display(data, editCallback, path);
    promise.then((content) =>
    {
        renderCallback(content);
    });
}

// Data must be of type BLOB;
function data_display(data, editCallback = null, path = null)
{
    return new Promise((resolve, reject) =>
    {
        let content = null;
        let editButton = null;
        let mediaType = null;
        if (data instanceof Blob)
        {
            mediaType = data.type;
        }
        let params = mediaType.split(";");
        mediaType = params.shift();
        switch (mediaType)
        {
            case "text/plain":
                let plaintextDisplay = (string) =>
                {
                    if (string == null)
                    {
                        string = "";
                    }
                    content = document.createElement("span");
                    content.innerText = string;
                    if (editCallback != null)
                    {
                        editButton = document.createElement("a");
                        editButton.classList.add("inline-icon");
                        editButton.classList.add("edit-icon");
                        editButton.href = "javascript:;";
                        content.appendChild(editButton);
                        editButton.addEventListener("click", () =>
                        {
                            editButton.style.display = "none";
                            let editBox = null;
                            let multiline = string.includes("\n");
                            if (multiline)
                            {
                                editBox = document.createElement("textarea");
                            }
                            else
                            {
                                editBox = document.createElement("input");
                            }
                            editBox.type = "text";
                            editBox.value = string;
                            content.style.textDecoration = "line-through";
                            content.after(editBox);
                            let editExit = () =>
                            {
                                editCallback(new Blob([editBox.value], {type: mediaType}));
                                editBox.remove();
                                if (editBox.value.length != 0)
                                {
                                    content.style.opacity = null;
                                    content.style.textDecoration = null;
                                    content.innerText = editBox.value;
                                }
                                else
                                {
                                    content.style.opacity = "0.5";
                                    content.innerText = string;
                                }
                                editButton.style.display = null;
                                string = editBox.value;
                                content.appendChild(editButton);
                            }
                            editBox.addEventListener("blur", editExit);
                            if (!multiline)
                            {
                                editBox.addEventListener("keyup", (e) =>
                                {
                                    if (e.key === 'Enter')
                                    {

                                        editExit();
                                    }
                                });
                            }
                            editBox.focus();
                        });
                    }
                    resolve(content);
                };
                data.text().then(plaintextDisplay);
                break;
            case "text/calendar":
                let calendarDisplay = (string) =>
                {
                    if (string == null)
                    {
                        string = "";
                    }
                    content = document.createElement("div");
                    content.classList.add("logical-box")
                    //content.innerText = string;

                    let jCal = ICAL.parse(string);

                    let arrToAssoc = (array) =>
                    {
                        let assoc = {};
                        for (let i = 0; i < array.length; i++)
                        {
                            assoc[array[i][0]] = {
                                "type": array[i][2],
                                "value": array[i][3],
                                "props": array[i][1]
                            };
                        }
                        return assoc;
                    }

                    let renderComponent = (jCalElement) =>
                    {
                        let contentBox = document.createElement("p");
                        contentBox.innerHtml = "Failed to render " + jCalElement[0];
                        switch (jCalElement[0])
                        {
                            case "vtodo":
                            {
                                let jCalContent = arrToAssoc(jCalElement[1]);
                                contentBox = document.createElement("div");
                                contentBox.classList.add("todo-box");
                                let heading = document.createElement("h4");
                                let completeLink = document.createElement("a");
                                completeLink.classList.add("task-alt-icon-black");
                                completeLink.classList.add("inline-icon-low-margin");
                                completeLink.href = "javascript:void(0);";
                                completeLink.addEventListener("click", (e) =>
                                {
                                    editCallback(new Blob([], {type: mediaType}));
                                    if (path != null)
                                    {
                                        console.log("TODO: Need to add timeline note!");
                                        let icalNote = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" +
                                            "BEGIN:VJOURNAL" + "\r\n" +
                                            "UID:" + generateRandomId(48) + "\r\n" +
                                            "DTSTAMP:" + get_tz_date() + "\r\n" +
                                            ical_wrap("SUMMARY:" + ical_sanitize("::auxpckstr:node_page_text/generic/todo_completed::")) + "\r\n" + ical_wrap("DESCRIPTION:" + ical_sanitize(jCalContent["summary"]["value"])) + "\r\n" +
                                            "END:VJOURNAL" + "\r\n" +
                                            "END:VCALENDAR\r\n";
                                        let pathCmps = path.split("/");
                                        pathCmps.splice(-2);
                                        pathCmps.push("timeline");
                                        pathCmps.push("#");
                                        //console.log(pathCmps.join("/"));
                                        let promise = putDataAtPath(pathCmps.join("/"), icalNote, "text/calendar", true);
                                    }
                                });
                                MicroTemplate.from_packed_template(jCalContent["summary"]["value"]).then((str) =>
                                {
                                    heading.innerText = capitalize(str);
                                    heading.appendChild(completeLink);
                                });
                                contentBox.appendChild(heading);
                            }
                                break;
                            case "vjournal":
                            {
                                let jCalContent = arrToAssoc(jCalElement[1]);
                                contentBox = document.createElement("div");
                                contentBox.classList.add("timeline-element-box");
                                let heading = document.createElement("h4");
                                //heading.innerText = jCalContent["summary"]["value"];
                                let date = document.createElement("p");
                                let dateInner = document.createElement("em");
                                date.appendChild(dateInner);
                                let dateVal = new Date(jCalContent["dtstamp"]["value"]);
                                dateInner.innerText = dateVal.toString();
                                let description = document.createElement("p");
                                description.innerText = jCalContent["description"]["value"];
                                //console.log(jCalContent);
                                MicroTemplate.from_packed_template(jCalContent["summary"]["value"]).then((str) =>
                                {
                                    heading.innerText = capitalize(str);
                                });
                                contentBox.appendChild(heading);
                                contentBox.appendChild(date);
                                contentBox.appendChild(description);
                            }
                                break;
                            default:
                                break;
                        }
                        return contentBox;
                    }

                    if (jCal[0] == "vcalendar")
                    {
                        for (let i = 1; i < jCal.length; i++)
                        {
                            switch (jCal[i][0][0])
                            {
                                case "vtodo":
                                case "vjournal":
                                    content.appendChild(renderComponent(jCal[i][0]));
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                    resolve(content);
                };
                data.text().then(calendarDisplay);
                break;
            default:
                content = document.createElement("span");
                let innerError = document.createElement("em");
                innerError.innerText = "Could not display media type: " + mediaType + " ";
                content.appendChild(innerError);
                let br = document.createElement("br");
                content.appendChild(br);
                let dl = document.createElement("a");
                dl.innerText = "Download file";
                dl.href = window.URL.createObjectURL(data);
                content.appendChild(dl);
                resolve(content);
        }
    });
}

function deegraphPathToLink(path)
{
    console.log("Deprecated function usage REPLACE deegraphPathToLink(path) WITH deegraph_path_to_link(path)");
    console.trace();
    return deegraph_path_to_link(path);
}

function deegraph_path_to_link(path)
{
    let link = "";
    let bracket = false;
    for (let i = 0; i < path.length; i++)
    {
        if (path.charAt(i) == "{")
        {
            bracket = true;
            link = link + "~";
        }
        else if (path.charAt(i) == "}")
        {
            bracket = false;
        }
        else
        {
            link = link + path.charAt(i);
        }
    }
    return link;
}

function expandAuxLfsUri(auxLfsUri, uuid)
{
    console.log("Deprecated function usage REPLACE expandAuxLfsUri(auxLfsUri, uuid) WITH expand_aux_lfs_uri(aux_lfs_uri, uuid)");
    console.trace();
    return expand_aux_lfs_uri(auxLfsUri, uuid);
}

function expand_aux_lfs_uri(aux_lfs_uri, uuid = null)
{
    let uriBits = aux_lfs_uri.split('/');
    let domain = uriBits[2];
    uriBits = uriBits[3].split('+');
    if (uuidRegex.test(uriBits[0]))
    {
        uuid = uriBits[0];
    }
    let hash = "";
    if (uriBits.length > 1)
    {
        hash = uriBits[1];
    }
    let mediaType = "application%2Foctet-stream";
    if (uriBits.length > 2)
    {
        mediaType = uriBits[2];
    }
    let size = "";
    if (uriBits.length > 3)
    {
        size = uriBits[3];
    }

    return "auxlfs://" + domain + "/" + uuid + "+" + hash + "+" + mediaType + "+" + size;
}

function extractAuxLfsMetadata(auxLfsUri)
{
    console.log("Deprecated function usage REPLACE extractAuxLfsMetadata(auxLfsUri) WITH extract_aux_lfs_metadata(aux_lfs_uri)");
    console.trace();
    return extract_aux_lfs_metadata(auxLfsUri);
}

function extract_aux_lfs_metadata(aux_lfs_uri)
{
    let uriBits = aux_lfs_uri.split('/');
    let domain = uriBits[2];
    let httpsPort = null;
    if (domain.length != 0)
    {
        let ptps = domain.split(":");
        domain = ptps[0];
        if (ptps.length > 1)
        {
            httpsPort = ptps[1];
        }
    }
    uriBits = uriBits[3].split('+');
    let uuid = null;
    if (uuidRegex.test(uriBits[0]))
    {
        uuid = uriBits[0];
    }
    let hash = null;
    if (uriBits.length > 1)
    {
        hash = uriBits[1];
    }
    let mediaType = null;
    if (uriBits.length > 2)
    {
        mediaType = uriBits[2].replaceAll("%3A", "/").replaceAll("%3a", "/");
    }
    let size = null;
    if (uriBits.length > 3)
    {
        size = uriBits[3];
    }

    return {
        "size": size,
        "uuid": uuid,
        "domain": domain,
        "https_port": httpsPort,
        "hash": hash,
        "type": mediaType
    };
}

function auxLfsUriToHttps(auxLfsUri)
{
    console.log("Deprecated function usage REPLACE auxLfsUriToHttps(auxLfsUri) WITH aux_lfs_uri_to_https(aux_lfs_uri)");
    console.trace();
    return aux_lfs_uri_to_https(auxLfsUri);
}

function aux_lfs_uri_to_https(aux_lfs_uri)
{
    //console.log(auxLfsUri + " => ")
    let metadata = extract_aux_lfs_metadata(aux_lfs_uri);
    //console.log(metadata)
    return "https://" + metadata["domain"] + ((metadata["https_port"] == null) ? "" : ":" + metadata["https_port"]) + "/api/v2/lfs/" + metadata["uuid"] + "+" + ((metadata["hash"] == null) ? "" : metadata["hash"]) + "+" + ((metadata["type"] == null) ? "" : metadata["type"].replaceAll("/", "%3A")) + "+" + ((metadata["size"] == null) ? "" : metadata["size"]);
    //return "https://";
}

const uuidRegex = /^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/i;

const guidRegex = /^\{[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}\}$/i;

const numberRegex = /^[0-9]+$/i;

const metaPropRegex = /^@[a-zA-Z-]/i;

const common_regex = {
    "uuid": /^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/i,
    "guid": /^\{[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}\}$/i,
    "path": /^(((\{[0-9a-f]{8}\b-[0-9a-f]{4}\b-[0-9a-f]{4}\b-[0-9a-f]{4}\b-[0-9a-f]{12}\}))|(\{[0-9a-f]{8}\b-[0-9a-f]{4}\b-[0-9a-f]{4}\b-[0-9a-f]{4}\b-[0-9a-f]{12}\}(\/(@?[a-z][a-z0-9_]*|[0-9]+|#|\*))+)|((@?[a-z][a-z0-9_]*|[0-9]+|\.|#|\*)?(\/(@?[a-z][a-z0-9_]*|[0-9]+|#|\*))*))$/i,
    "number": /^[0-9]+$/i,
    "meta_prop": /^@[a-zA-Z-]/i
}

function capitalize(str)
{
    str = str + "";
    return str[0].toUpperCase() + str.slice(1);
}

function titleCase(s)
{
    console.log("Deprecated function usage REPLACE titleCase(s) WITH title_case(s)");
    console.trace();
    return title_case(s);
}

function title_case(s)
{
    let strpcs = s.split(" ");
    let str = "";
    for (let i = 0; i < strpcs.length; i++)
    {
        str = ((str.length > 0) ? (str + " ") : "") + capitalize(strpcs[i]);
    }
    return str;
}


function levenshteinDistance(a, b)
{
    console.log("Deprecated function usage REPLACE levenshteinDistance(a, b) WITH levenshtein_distance(a, b)");
    console.trace();
    return levenshtein_distance(a, b);
}

function levenshtein_distance(a, b)
{
    // Derived from https://github.com/gustf/js-levenshtein

    /*

     MIT License

     Copyright (c) 2017 Gustaf Andersson

     Permission is hereby granted, free of charge, to any person obtaining a copy
     of this software and associated documentation files (the "Software"), to deal
     in the Software without restriction, including without limitation the rights
     to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     copies of the Software, and to permit persons to whom the Software is
     furnished to do so, subject to the following conditions:

     The above copyright notice and this permission notice shall be included in all
     copies or substantial portions of the Software.

     THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
     SOFTWARE.

     */

    const _min = (d0, d1, d2, bx, ay) =>
    {
        return d0 < d1 || d2 < d1
            ? d0 > d2
                ? d2 + 1
                : d0 + 1
            : bx === ay
                ? d1
                : d1 + 1;
    }

    if (a === b)
    {
        return 0;
    }

    if (a.length > b.length)
    {
        var tmp = a;
        a = b;
        b = tmp;
    }

    var la = a.length;
    var lb = b.length;

    while (la > 0 && (a.charCodeAt(la - 1) === b.charCodeAt(lb - 1)))
    {
        la--;
        lb--;
    }

    var offset = 0;

    while (offset < la && (a.charCodeAt(offset) === b.charCodeAt(offset)))
    {
        offset++;
    }

    la -= offset;
    lb -= offset;

    if (la === 0 || lb < 3)
    {
        return lb;
    }

    var x = 0;
    var y;
    var d0;
    var d1;
    var d2;
    var d3;
    var dd;
    var dy;
    var ay;
    var bx0;
    var bx1;
    var bx2;
    var bx3;

    var vector = [];

    for (y = 0; y < la; y++)
    {
        vector.push(y + 1);
        vector.push(a.charCodeAt(offset + y));
    }

    var len = vector.length - 1;

    for (; x < lb - 3;)
    {
        bx0 = b.charCodeAt(offset + (d0 = x));
        bx1 = b.charCodeAt(offset + (d1 = x + 1));
        bx2 = b.charCodeAt(offset + (d2 = x + 2));
        bx3 = b.charCodeAt(offset + (d3 = x + 3));
        dd = (x += 4);
        for (y = 0; y < len; y += 2)
        {
            dy = vector[y];
            ay = vector[y + 1];
            d0 = _min(dy, d0, d1, bx0, ay);
            d1 = _min(d0, d1, d2, bx1, ay);
            d2 = _min(d1, d2, d3, bx2, ay);
            dd = _min(d2, d3, dd, bx3, ay);
            vector[y] = dd;
            d3 = d2;
            d2 = d1;
            d1 = d0;
            d0 = dy;
        }
    }

    for (; x < lb;)
    {
        bx0 = b.charCodeAt(offset + (d0 = x));
        dd = ++x;
        for (y = 0; y < len; y += 2)
        {
            dy = vector[y];
            vector[y] = dd = _min(dy, d0, dd, bx0, vector[y + 1]);
            d0 = dy;
        }
    }

    return dd;
}





