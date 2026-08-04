let uploadFileProgressLocation = document.getElementById("uploadFileProgressLocation");
let dragElem = null;
let dragTimeout = 0;

let handleItem = (item) =>
{
    console.log(item);
    item.getAsString((data) =>
    {
        console.log(data);
    });
}

let doFileUpload = (file, uuid, feedbackElements) =>
{
    let onProgress = (prog) =>
    {
        if (prog < 1)
        {
            let percent = Math.round(prog * 100);
            feedbackElements.barInner.style.width = percent + "%";
        }
        else if (prog == 1)
        {
            feedbackElements.barInner.style.width = "100%";
        }
    };

    let onSuccess = (resp) =>
    {
        console.log(resp);

        let newNodeView = new InlineNodeView("{" + uuid + "}");
        feedbackElements.tag.parentNode.insertBefore(newNodeView.render(), feedbackElements.tag);
        feedbackElements.tag.remove();
    };

    storeToLfs(uuid, file, onSuccess, onProgress);
}

let prepareFileUpload = (file) =>
{
    //let randomId = generateRandomId(8);

    let feedbackElements = [];
    feedbackElements.tag = document.createElement("div");
    feedbackElements.tag.classList.add("uploading-file-entry");
    feedbackElements.heading = document.createElement("span");
    feedbackElements.heading.classList.add("file-label");
    feedbackElements.heading.textContent = "Uploading " + file.name;
    feedbackElements.tag.appendChild(feedbackElements.heading);
    feedbackElements.bar = document.createElement("div");
    feedbackElements.bar.classList.add("file-bar");
    feedbackElements.tag.appendChild(feedbackElements.bar);
    feedbackElements.barInner = document.createElement("div");
    feedbackElements.bar.appendChild(feedbackElements.barInner);
    uploadFileProgressLocation.parentNode.insertBefore(feedbackElements.tag, uploadFileProgressLocation);
    feedbackElements.tag.scrollIntoView();

    let http = new XMLHttpRequest();

    let url = "/api/v2/query";

    let hash = "";
    let size = file.size;
    let fileName = file.name;
    let fileType = file.type.replaceAll("/", "%3A");
    let library = "documents";

    switch (file.type.toLowerCase())
    {
        case "message/rfc822":
            library = "messages";
            break;
        case "text/plain":
            library = "notes";
            break;
        default:
            library = "documents";
            if (fileName.endsWith(".msg"))
            {
                library = "messages"
            }
            break;
    }

    let queries = [
        "INSERT VALUES null SCHEMAS \"https://schemas.auxiliumsoftware.co.uk/v1/collection.json\" KEYS " + library + " INTO {" + thisObject + "}",
        "INSERT VALUES \"auxlfs://" + lfsInstanceFqdn + "/+" + hash + "+" + fileType + "+" + size + "\" INTO {" + thisObject + "}/" + library
    ];

    let data = new FormData();
    data.append("queries", JSON.stringify(queries));

    http.open("POST", url, true);
    http.send(data);

    let responseHad = false;

    let nameInsertQuery = (uuid) =>
    {
        let http = new XMLHttpRequest();

        let data = new FormData();
        data.append("query", "INSERT VALUES \"data:text/plain," + encodeURIComponent(fileName) + "\" KEYS filename INTO {" + uuid + "}");

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
                } catch (e)
                {
                    console.log(e);
                    console.log(http.responseText);
                }
            }
        }
    }

    http.onreadystatechange = (e) =>
    {
        if (http.readyState == 4 && !responseHad)
        {
            let response = {};
            responseHad = true;

            try
            {
                response = JSON.parse(http.responseText);
                console.log(response)

                if (response.hasOwnProperty("results"))
                {
                    let uuid = response["results"][1]["@nodes"][0]["@id"];
                    doFileUpload(file, uuid, feedbackElements);
                    nameInsertQuery(uuid);
                }
            } catch (e)
            {
                console.log(e);
                console.log(e.stack);
                console.log(http.responseText);
            }
        }
    }

    //let uuid = "8d83bc2b-0f62-425a-a5be-a5c09969cec7";

    //doFileUpload(file, uuid, feedbackElements);
}

let dragEnterEventHandler = (e) =>
{
    e.preventDefault();
    e.stopPropagation();
    let tagName = null;
    if (e.srcElement != undefined)
    {
        tagName = e.srcElement.tagName.toUpperCase();
    }
    if (tagName == "HTML" || e.srcElement.classList.contains("drag-signal"))
    {
        if (dragElem != null)
        {
            dragElem.remove();
            dragElem = null;
        }
    }
    let dt = e.dataTransfer;
    let files = dt.files;
    ([...files]).forEach(prepareFileUpload);
    let items = dt.items;
    ([...items]).forEach(handleItem);
    if (items.length > 0)
    {
        if (dragElem != null)
        {
            dragElem.remove();
            dragElem = null;
        }
    }
}

const dragEndEvents = ["dragleave", "drop"];
dragEndEvents.forEach(eventName =>
{
    window.addEventListener(eventName, dragEnterEventHandler, false);
});

const dragStartEvents = ["dragenter", "dragover"];
dragStartEvents.forEach(eventName =>
{
    window.addEventListener(eventName, (e) =>
    {
        e.preventDefault();
        e.stopPropagation();
        if (dragElem == null)
        {
            dragElem = document.createElement("div");
            dragElem.classList.add("drag-signal");
            dragEndEvents.forEach(eventName =>
            {
                dragElem.addEventListener(eventName, dragEnterEventHandler, false);
            });
            document.body.appendChild(dragElem);
        }
    }, false);
});
