let consoleOutput = [];
let history = [];
let editableHistory = [""];
let historyPointer = 0;

let environmentVariables = {
    "case": "\"https://schemas.auxiliumsoftware.co.uk/v1/case.json\"",
    "user": "\"https://schemas.auxiliumsoftware.co.uk/v1/user.json\"",
    "group": "\"https://schemas.auxiliumsoftware.co.uk/v1/organisation.json\"",
    "organisation": "\"https://schemas.auxiliumsoftware.co.uk/v1/organisation.json\"",
    "document": "\"https://schemas.auxiliumsoftware.co.uk/v1/document.json\"",
    "message": "\"https://schemas.auxiliumsoftware.co.uk/v1/message.json\"",
    "instance_fqdn": document.getElementById("lfs_instance_fqdn").textContent
};

document.getElementById("no_js").style.display = "none";
document.getElementById("js_console").style.display = null;

function scrollToBottomOfConsole()
{
    let stickySlop = 256;

    let currentScroll = document.getElementById("js_console_scrollable_container").scrollTop + document.getElementById("js_console_scrollable_container").clientHeight;
    let totalHeight = document.getElementById("js_console_scrollable_container").scrollHeight;
    //console.log(currentScroll + "/" + totalHeight);
    let gluedToBottom = (currentScroll > (totalHeight - stickySlop));

    if (gluedToBottom)
    {
        document.getElementById("js_console_scrollable_container").scrollTop = document.getElementById("js_console_scrollable_container").scrollHeight;
    }
}

function pushContentToScreen(content)
{
    let stickySlop = 64;

    let currentScroll = document.getElementById("js_console_scrollable_container").scrollTop + document.getElementById("js_console_scrollable_container").clientHeight;
    let totalHeight = document.getElementById("js_console_scrollable_container").scrollHeight;
    //console.log(currentScroll + "/" + totalHeight);
    let gluedToBottom = (currentScroll > (totalHeight - stickySlop));

    document.getElementById("js_console_scrollable_container").appendChild(content);

    if (gluedToBottom)
    {
        document.getElementById("js_console_scrollable_container").scrollTop = document.getElementById("js_console_scrollable_container").scrollHeight;
    }
}

class ConsoleSegment
{
    constructor(lines)
    {
        this.segmentContainer = document.createElement("div");
        this.segmentContainer.classList.add("js-console-segment-container");

        if (lines != null)
        {
            lines.forEach((item, index) =>
            {
                this.segmentContainer.appendChild(item);
            });
        }
    }

    getContent()
    {
        return this.segmentContainer;
    }
}

function csvSanitize(input)
{
    return "\"" + input.replaceAll("\"", "\\\"") + "\"";
}

class TableConsoleSegment extends ConsoleSegment
{
    constructor(headings, rows, unparsedRows, rowMetadata)
    {
        let tableRendered = document.createElement("table");
        tableRendered.classList.add("js-console-table");

        let csv = "";
        let csvUnparsed = "";
        let csvLine = "";
        let csvUnparsedLine = "";
        let headingsRendered = document.createElement("tr");
        for (let columnId = 0; columnId < headings.length; columnId++)
        {
            let column = headings[columnId];
            let cellRendered = document.createElement("th");
            cellRendered.innerHTML = column.replaceAll(" ", "&nbsp;");
            csvLine = csvLine + ((columnId == 0) ? "" : ",") + csvSanitize(column);
            csvUnparsedLine = csvUnparsedLine + ((columnId == 0) ? "" : ",") + csvSanitize(column);
            headingsRendered.appendChild(cellRendered);
        }
        csv = csvLine + "\n";
        csvUnparsed = csvUnparsedLine + "\n";
        tableRendered.appendChild(headingsRendered);
        for (let row = 0; row < rows.length; row++)
        {
            let rowRendered = document.createElement("tr");
            csvLine = "";
            csvUnparsedLine = "";
            for (let columnId = 0; columnId < headings.length; columnId++)
            {
                let column = headings[columnId];
                let cellRendered = document.createElement("td");
                let link = document.createElement("a");
                link.classList.add("inline-icon");
                link.classList.add("open-icon-white");
                link.target = "_blank";
                //console.log(rows[row]);
                if (rows[row].hasOwnProperty(column))
                {
                    if (rows[row][column] == null)
                    {
                        cellRendered.innerHTML = "<em>null</em> ";
                        csvLine = csvLine + ((columnId == 0) ? "" : ",") + "";
                        csvUnparsedLine = csvUnparsedLine + ((columnId == 0) ? "" : ",") + "null";
                    }
                    else
                    {
                        cellRendered.innerHTML = rows[row][column].replaceAll(" ", "&nbsp;") + " ";
                        csvLine = csvLine + ((columnId == 0) ? "" : ",") + csvSanitize(rows[row][column]);
                        csvUnparsedLine = csvUnparsedLine + ((columnId == 0) ? "" : ",") + csvSanitize(unparsedRows[row][column]);
                    }
                    cellRendered.title = "{" + rowMetadata[row][column]["@id"] + "}"
                    let splitPath = rowMetadata[row][column]["@path"].split("/");
                    for (let i = 0; i < splitPath.length; i++)
                    {
                        if (/^{[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}}$/i.test(splitPath[i]))
                        {
                            splitPath[i] = "~" + splitPath[i].substring(1, 37);
                        }
                    }
                    link.href = "/graph/" + splitPath.join("/");
                }
                else
                {
                    cellRendered.innerHTML = "<em>missing</em> ";
                    csvLine = csvLine + ((columnId == 0) ? "" : ",") + "";
                    csvUnparsedLine = csvUnparsedLine + ((columnId == 0) ? "" : ",") + "missing";
                }
                cellRendered.appendChild(link);
                rowRendered.appendChild(cellRendered);
            }
            tableRendered.appendChild(rowRendered);
            csv = csv + csvLine + "\n";
            csvUnparsed = csvUnparsed + csvUnparsedLine + "\n";
        }


        let tableContainer = document.createElement("table");
        tableContainer.appendChild(tableRendered);
        tableContainer.classList.add("js-console-table-container");

        let downloadButton = document.createElement("a");
        downloadButton.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csv));
        downloadButton.innerHTML = "[ Download table as CSV ]";
        downloadButton.setAttribute("download", "query.csv");

        let downloadRawButton = document.createElement("a");
        downloadRawButton.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csvUnparsed));
        downloadRawButton.innerHTML = "[ Download raw data as CSV ]";
        downloadRawButton.setAttribute("download", "raw_query.csv");

        super([tableContainer, downloadButton, downloadRawButton]);
    }
}

class StandardOutputSegment extends ConsoleSegment
{
    constructor()
    {
        let soContainer = document.createElement("div");
        soContainer.classList.add("logical-box");
        super([soContainer]);
        this.soContainer = soContainer;
        this.currentLine = document.createElement("span");
        this.currentLine.classList.add("js-console-segment-container");
        this.soContainer.appendChild(this.currentLine);
    }

    exit(code)
    {
        this.currentLine = document.createElement("span");
        this.currentLine.innerText = "Process exited with code " + code;
        this.currentLine.classList.add("js-console-segment-container");
        this.soContainer.appendChild(this.currentLine);
    }

    write(content)
    {
        console.log(content);
        let idx = content.indexOf("\n");
        while (idx !== -1)
        {
            this.currentLine.innerText = this.currentLine.innerText + content.substring(0, idx);
            content = content.substring(idx + 1);
            idx = content.indexOf("\n");
            this.currentLine = document.createElement("span");
            this.currentLine.classList.add("js-console-segment-container");
            this.soContainer.appendChild(this.currentLine);
        }
        this.currentLine.innerText = this.currentLine.innerText + content;
    }
}

class TextConsoleSegment extends ConsoleSegment
{
    constructor(lines)
    {
        let linesRendered = [];
        for (let line = 0; line < lines.length; line++)
        {
            let lineRendered = document.createElement("span");
            lineRendered.innerHTML = lines[line].replaceAll(" ", "&nbsp;");
            linesRendered[line] = lineRendered
        }
        super(linesRendered);
        this.content = lines;
    }

    read()
    {
        return this.content.join("\n");
    }
}

class ErrorConsoleSegment extends ConsoleSegment
{
    constructor(command)
    {
        let lineRendered = document.createElement("span");
        lineRendered.innerHTML = command.replaceAll(" ", "&nbsp;");
        lineRendered.classList.add("js-console-error-record");
        super([lineRendered]);
    }
}

class CommandConsoleSegment extends ConsoleSegment
{
    constructor(command)
    {
        let lineRendered = document.createElement("span");
        lineRendered.innerHTML = command.replaceAll(" ", "&nbsp;");
        lineRendered.classList.add("js-console-user-input-record");
        super([lineRendered]);
    }
}

const helpText = [
    "[ Deegraph queries ]",
    "",
    "SELECT &lt;path&gt;[, path]... [WHERE condition] [FROM path] [INSTANCEOF url]",
    "  Runs a query to select data from the database. You can then download this as a CSV file.",
    "",
    "DIRECTORY &lt;path&gt;",
    "DIR ... (alias)",
    "LS ... (alias)",
    "  Lists all properties of an object.",
    "",
    "[ Console commands ]",
    "",
    "MOUNT",
    "  Upload an mount a zip file for reading.",
    "",
    "ECHO",
    "  Prints the argument to screen.",
    "",
    "CAT",
    "  Opens and reads a binary object (BLOB) as text.",
    "",
    "SOURCE",
    "  Runs a variable/file as a series of commands.",
    "",
    "STRINGIFY",
    "  Encoded object as JSON.",
    "",
    "PARSE",
    "  Parses JSON into an object representation.",
    "",
    "FULLSCREEN",
    "  Fills the browser window with the terminal.",
    "",
    "MINIMISE",
    "  Returns the console window to an embedded view in the page."
];

function urlToContent(url, textOnly)
{
    if (url.startsWith("data:"))
    {
        let dataStartIndex = url.indexOf(",") + 1;
        let typeString = url.substring(5, dataStartIndex - 1);
        let mimeType = (typeString.indexOf(";base64") > -1) ? typeString.substring(0, typeString.indexOf(";base64")) : typeString;
        //let base64 = url.indexOf(";base64");
        //base64 = (base64 > 0) && (base64 < dataStartIndex); // Only match the base64 string if it's before the data stream - a data stream could have this in anyway!
        let encodedData = url.substring(dataStartIndex);
        let rawData = null;
        if (typeString.indexOf(";base64") > -1)
        {
            rawData = atob(encodedData);
        }
        else
        {
            rawData = decodeURIComponent(encodedData.replaceAll("+", " "));
        }
        return rawData;
    }
    return null;
}

function deegraphQuery(query, callback)
{
    let http = new XMLHttpRequest();
    let url = "/console";

    let data = new FormData();
    data.append("query", query);
    data.append("return_format", "RAW");

    http.open("POST", url, true);
    http.send(data);

    let responseHad = false;

    http.onreadystatechange = (e) =>
    {
        if (http.readyState == 4 && !responseHad)
        {
            let result = {};
            responseHad = true;
            let customOutput = false;
            let consoleOutput = null;
            let outputValue = null;

            try
            {
                result = JSON.parse(http.responseText);
            } catch (e)
            {
                customOutput = true;
                consoleOutput = new ErrorConsoleSegment("Parse error returned from database");
                console.log(http.responseText);
            }
            if (result.hasOwnProperty("@rows"))
            {
                customOutput = true;
                consoleOutput = [];
                let tableRows = [];
                let tableRowsMetadata = [];
                let tableRowsUnparsed = [];
                let tableHeadings = [];

                for (let i = 0; i < result["@rows"].length; i++)
                {
                    let row = result["@rows"][i]

                    console.log(row)

                    let rowDefinition = {};
                    let rowMetadataDefinition = {};
                    let unparsedRowDefinition = {};
                    for (let property in row)
                    {
                        if (row.hasOwnProperty(property))
                        {
                            for (let path in row[property])
                            {
                                if (row[property].hasOwnProperty(path))
                                {

                                    let parsed = row[property][path];
                                    if (parsed != null)
                                    {
                                        if (parsed.startsWith("data:"))
                                        {
                                            parsed = parsed.substring(5);
                                            let header = parsed.substring(0, parsed.indexOf(","));
                                            let body = parsed.substring(header.length + 1);
                                            let b64 = false;
                                            //console.log(header);
                                            if (header.endsWith(";base64"))
                                            {
                                                b64 = true;
                                                header = header.substring(0, header.length - 7);
                                            }
                                            if (b64)
                                            {
                                                parsed = atob(body);
                                            }
                                            else
                                            {
                                                parsed = decodeURIComponent(body.replaceAll("+", " "));
                                            }
                                        }
                                    }

                                    rowDefinition[property] = parsed; // Parse code TODO
                                    unparsedRowDefinition[property] = row[property][path];
                                    rowMetadataDefinition[property] = {};
                                    rowMetadataDefinition[property]["@path"] = path;
                                    if (!tableHeadings.includes(property))
                                    {
                                        tableHeadings.push(property);
                                    }
                                }
                            }
                        }
                    }
                    tableRows.push(rowDefinition);
                    tableRowsUnparsed.push(unparsedRowDefinition);
                    tableRowsMetadata.push(rowMetadataDefinition);
                }

                outputValue = {};
                for (let rowNumber = 0; rowNumber < tableRowsMetadata.length; rowNumber++)
                {
                    for (let outputPath in tableRowsMetadata[rowNumber])
                    {
                        if (tableRowsMetadata[rowNumber].hasOwnProperty(outputPath))
                        {
                            if (outputValue[rowNumber] === undefined)
                            {
                                outputValue[rowNumber] = {};
                            }
                            outputValue[rowNumber][outputPath] = tableRowsMetadata[rowNumber][outputPath]["@path"];
                        }
                    }
                }
                //console.log(outputValue);

                console.log(tableRows);
                if (result.hasOwnProperty("@generated_query"))
                {
                    consoleOutput.push(new TextConsoleSegment(["Query:", result["@generated_query"], "Response:"]));
                }
                consoleOutput.push(new TableConsoleSegment(tableHeadings, tableRows, tableRowsUnparsed, tableRowsMetadata));
            }
            if (result.hasOwnProperty("@map"))
            {
                customOutput = true;
                consoleOutput = [];
                let outputs = [];
                for (let keyId in result["@map"])
                {
                    outputs.push(keyId);
                }
                outputValue = result["@map"];
                consoleOutput.push(new TextConsoleSegment(outputs));
            }
            if (result.hasOwnProperty("@nodes"))
            {
                customOutput = true;
                consoleOutput = [];
                outputValue = result["@nodes"];
                //consoleOutput.push(new TextConsoleSegment(outputs));
            }
            if (!customOutput)
            {
                consoleOutput = new TextConsoleSegment(http.responseText.split("\n"));
            }
            //console.log(result);

            if (callback != null)
            {
                if (callback instanceof Function)
                {
                    callback(consoleOutput, outputValue);
                }
            }
        }
    }
}

function drawConsoleOutput(co)
{
    consoleOutput.push(co);
    pushContentToScreen(co.getContent());
}

function runCommandInput(input)
{
    drawConsoleOutput(new CommandConsoleSegment("> " + input));
    history.push(input);
    editableHistory = history.slice();
    editableHistory.push("");
    historyPointer = editableHistory.length - 1;

    let loadingContentMarker = document.createElement("span");
    loadingContentMarker.classList.add("loading-placeholder");
    loadingContentMarker.innerHTML = "Content is loading";
    loadingContentMarker.setAttribute("title", "Content is loading");
    pushContentToScreen(loadingContentMarker);

    let callback = (output, valueOutput = null) =>
    {
        if (output instanceof ConsoleSegment)
        {
            drawConsoleOutput(output);
        }
        else if (Array.isArray(output))
        {
            for (let i = 0; i < output.length; i++)
            {
                if (output[i] instanceof ConsoleSegment)
                {
                    drawConsoleOutput(output[i]);
                }
                else if (typeof output[i] === "string")
                {
                    drawConsoleOutput(new TextConsoleSegment([output[i]]));
                }
            }
        }
        else if (typeof output === "string")
        {
            drawConsoleOutput(new TextConsoleSegment([output[i]]));
        }
        else
        {
            drawConsoleOutput(new TextConsoleSegment(["OK"]));
        }
        loadingContentMarker.remove();
    }

    processCommandInput(input, callback);
}

function processCommandInput(input, callback = null)
{
    input = input.trim();
    //console.log(input)

    let getVariable = (buffer, environmentVariables) =>
    {
        if (environmentVariables.hasOwnProperty(buffer.split("->")[0]))
        {
            let path = buffer.split("->");
            let opath = [path.shift()];
            let cpos = environmentVariables[opath[0]];
            while (path.length > 0)
            {
                let prop = path.shift();
                opath.push(prop);
                if (cpos != null)
                {
                    console.log(cpos);
                    if (Object.hasOwnProperty.bind(cpos)(prop))
                    {
                        cpos = cpos[prop];
                    }
                    else
                    {
                        return undefined;
                    }
                }
                else
                {
                    return undefined;
                }
            }
            return cpos;
        }
        else
        {
            return undefined;
        }
    }


    let consoleOutput = null;
    let callbackPassed = false;

    let bracketLevel = 0;
    let quoted = false;
    let parsedInput = [];
    let buffer = "";
    let variableSwitch = false;
    let skipBufferInput = false;
    let assignment = false;
    let assignmentCommand = "";
    for (let i = 0; i < input.length; i++)
    {
        if (variableSwitch)
        {
            let exitVariable = !input[i].match(/[a-z0-9.@>_-]/i);
            if (!exitVariable)
            {
                buffer = buffer + input[i];
            }
            if (i == (input.length - 1))
            {
                exitVariable = true;
            }
            if (exitVariable)
            {
                let variable = getVariable(buffer, environmentVariables);
                if (variable === undefined)
                {
                    if (callback != null)
                    {
                        if (callback instanceof Function)
                        {
                            callback(new ErrorConsoleSegment("'$" + buffer + "' does not map to any value."));
                        }
                    }
                    return false;
                }
                else
                {
                    if (typeof variable == "object")
                    {
                        if (!(variable instanceof Blob))
                        {
                            if (callback != null)
                            {
                                if (callback instanceof Function)
                                {
                                    callback(new ErrorConsoleSegment("'$" + buffer + "' is an object. Consider using the command 'STRINGIFY " + buffer + "' to view the data in this object."));
                                }
                            }
                            return false;
                        }
                    }

                    if (quoted)
                    {
                        parsedInput[parsedInput.length - 1] = parsedInput[parsedInput.length - 1] + variable;
                    }
                    else
                    {
                        parsedInput[parsedInput.length] = variable;
                    }

                    if (input[i] != ";")
                    {
                        if (!input[i].match(/[a-z0-9.@>_-]/i))
                        {
                            if (quoted)
                            {
                                parsedInput[parsedInput.length - 1] = parsedInput[parsedInput.length - 1] + input[i];
                            }
                            else
                            {
                                parsedInput.push(input[i]);
                            }
                        }
                    }
                }
                buffer = "";
                variableSwitch = false;
            }
        }
        else
        {
            if (input[i] == "$")
            {
                let valid = true;
                if (i > 0)
                {
                    if (input[i - 1] == "\\")
                    {
                        valid = false;
                    }
                }
                if (valid)
                {
                    //console.log(buffer)
                    if (buffer.length > 0)
                    {
                        parsedInput.push(buffer);
                    }
                    variableSwitch = true;
                    skipBufferInput = true;
                    buffer = "";
                }
            }
            if (input[i] == "\"")
            {
                let valid = true;
                if (i > 0)
                {
                    if (input[i - 1] == "\\")
                    {
                        valid = false;
                    }
                }
                if (valid)
                {
                    quoted = !quoted;
                }
            }
            if (!quoted)
            {
                if (input[i] == "=")
                {
                    if (parsedInput.length < 3)
                    {
                        let ok = false;
                        if (parsedInput.length == 0)
                        {
                            parsedInput[0] = buffer;
                            ok = parsedInput[0].match(/[a-z][a-z0-9.@_]*/i);
                        }
                        else if (parsedInput.length == 2)
                        {
                            if (parsedInput[1] == " ")
                            {
                                ok = parsedInput[0].match(/[a-z][a-z0-9.@_]+/i);
                            }
                        }
                        if (ok)
                        {
                            assignment = true;
                            assignmentCommand = input.substring(i + 1).trim();
                            break;
                        }
                    }
                }
                if (input[i] == " ")
                {
                    skipBufferInput = true;
                    if (buffer.length > 0)
                    {
                        parsedInput.push(buffer);
                    }
                    parsedInput.push(" ");
                    buffer = "";
                }
            }
            if (skipBufferInput)
            {
                skipBufferInput = false;
            }
            else
            {
                buffer = buffer + input[i];
            }
        }
    }
    parsedInput.push(buffer);
    let idx = 0;
    let sep = true;
    let sepset = false;
    while (idx < parsedInput.length)
    {
        if (parsedInput[idx] == " ")
        {
            sepset = true;
        }
        if (!sep && !sepset)
        {
            parsedInput[idx - 1] = parsedInput[idx - 1] + parsedInput[idx];
            parsedInput = parsedInput.slice(0, idx).concat(parsedInput.slice(idx + 1))
        }
        else
        {
            idx++;
        }
        if (sepset)
        {
            sep = true;
            sepset = false;
        }
        else
        {
            sep = false;
        }
    }
    console.log(parsedInput);

    if (assignment)
    {
        console.log(assignmentCommand + " => " + parsedInput[0]);

        let innerCallback = (consoleOutput, returnValue) =>
        {
            environmentVariables[parsedInput[0]] = returnValue;
            if (consoleOutput instanceof ErrorConsoleSegment)
            {
                callback(consoleOutput, null);
            }
            else if (Array.isArray(consoleOutput))
            {
                let errors = [];
                for (let i = 0; i < consoleOutput.length; i++)
                {
                    if (consoleOutput[i] instanceof ErrorConsoleSegment)
                    {
                        errors.push(consoleOutput[i]);
                    }
                }
                callback((errors.length > 0) ? errors : null, null);
            }
            else
            {
                callback(null, null);
            }
        }

        processCommandInput(assignmentCommand, innerCallback)
        //processCommandInput(parsedInput.join(" "), callback);
    }
    else
    {
        if (typeof parsedInput[0] !== "string")
        {
            parsedInput[0] = "";
        }

        let valueOutput = null;

        switch (parsedInput[0].toLowerCase())
        {
            case "select":
            case "put":
            case "ls":
            case "dir":
            case "directory":
            case "ln":
            case "link":
            case "grant":
            case "refs":
            case "unlink":
            case "references":
            case "perms":
            case "permissions":
            case "insert":
            case "del":
            case "delete":
                deegraphQuery(parsedInput.join(""), callback);
                callbackPassed = true;
                break;
            case "clear":
                document.getElementById("js_console_scrollable_container").innerHTML = "";
                break;
            case "fullscreen":
                document.getElementById("js_console").style.width = "100vw";
                document.getElementById("js_console").style.height = "100vh";
                document.getElementById("js_console").style.top = "0";
                document.getElementById("js_console").style.left = "0";
                document.getElementById("js_console").style.position = "fixed";
                document.getElementById("js_console").style.zIndex = "1024";
                break;
            case "minimize":
            case "minimise":
                document.getElementById("js_console").style.width = "100%";
                document.getElementById("js_console").style.height = "80vh";
                document.getElementById("js_console").style.top = null;
                document.getElementById("js_console").style.left = null;
                document.getElementById("js_console").style.position = "relative";
                document.getElementById("js_console").style.zIndex = null;
                break;
            case "echo":
                if (parsedInput.length > 2)
                {
                    let content = parsedInput.slice(2);
                    for (let i = 0; i < content.length; i++)
                    {
                        if (typeof content[i] === "string")
                        {
                            content[i] = content[i].replaceAll("\\\"", "\"").replaceAll("\\'", "'").replaceAll("\\$", "$");
                        }
                    }

                    let echoInput = content.join("");
                    if (echoInput.startsWith("\"") && echoInput.endsWith("\""))
                    {
                        echoInput = echoInput.substring(1, echoInput.length - 1);
                    }
                    consoleOutput = new TextConsoleSegment(echoInput.split("\n"));
                    valueOutput = echoInput;
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing input value");
                }
                break;
            case "cat":
                if (parsedInput.length > 2)
                {
                    let blob = getVariable(parsedInput[2], environmentVariables);
                    if (blob instanceof Blob)
                    {
                        blob.text().then((text) =>
                        {
                            if (callback != null)
                            {
                                if (callback instanceof Function)
                                {
                                    callback(new TextConsoleSegment(text.split("\n")), text);
                                }
                            }
                        });
                        callbackPassed = true;
                    }
                    else
                    {
                        consoleOutput = new ErrorConsoleSegment("Path does not resolve to a binary file (BLOB)");
                    }
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing variable name");
                }
                break;
            case "source":
                if (parsedInput.length > 2)
                {
                    let blob = getVariable(parsedInput[2], environmentVariables);
                    if (blob instanceof Blob)
                    {
                        blob.text().then((text) =>
                        {
                            if (callback != null)
                            {
                                if (callback instanceof Function)
                                {
                                    let lines = text.split("\n");
                                    let trimmedLines = [];
                                    for (let i = 0; i < lines.length; i++)
                                    {
                                        if (lines[i].length != 0)
                                        {
                                            trimmedLines.push(lines[i]);
                                        }
                                    }
                                    // mount
                                    // source mount->import.aql
                                    let consoleSegment = new StandardOutputSegment();
                                    let callbackStack = [];
                                    for (let i = 0; i < trimmedLines.length; i++)
                                    {
                                        callbackStack[i] = (consoleOutput, valueOutput) =>
                                        {
                                            if (consoleOutput instanceof TextConsoleSegment)
                                            {
                                                consoleSegment.write(consoleOutput.read() + "\n");
                                            }

                                            consoleSegment.write("> " + trimmedLines[i] + "\n");
                                            scrollToBottomOfConsole();
                                            //console.log("> " + trimmedLines[i] + "\n");
                                            processCommandInput(trimmedLines[i], callbackStack[i + 1]);
                                        };
                                    }
                                    callbackStack[trimmedLines.length] = (consoleOutput, valueOutput) =>
                                    {
                                        if (consoleOutput instanceof TextConsoleSegment)
                                        {
                                            consoleSegment.write(consoleOutput.read() + "\n");
                                        }


                                        consoleSegment.exit(0);
                                        scrollToBottomOfConsole();

                                        //console.log("EXIT \n");
                                    };

                                    //console.log(callbackStack.length);
                                    //console.log(callbackStack);
                                    callbackStack[0]();
                                    if (callback != null)
                                    {
                                        if (callback instanceof Function)
                                        {
                                            callback(consoleSegment, null);
                                        }
                                    }
                                }
                            }
                        });
                        callbackPassed = true;
                    }
                    else
                    {
                        consoleOutput = new ErrorConsoleSegment("Path does not resolve to a binary file (BLOB)");
                    }
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing variable name");
                }
                break;

            case "help":
                consoleOutput = new TextConsoleSegment(helpText);
                break;
            case "stringify":
                if (parsedInput.length > 2)
                {
                    let dumpInput = getVariable(parsedInput[2], environmentVariables);
                    valueOutput = JSON.stringify((dumpInput == undefined) ? null : dumpInput);
                    consoleOutput = (dumpInput == undefined) ? (new ErrorConsoleSegment("Undefined variable " + parsedInput[2] + "!")) : (new TextConsoleSegment(valueOutput.split("\n")));
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing variable name");
                }
                break;
            case "parse":
                if (parsedInput.length > 2)
                {
                    let dumpInput = getVariable(parsedInput[2], environmentVariables);
                    valueOutput = JSON.parse((dumpInput == undefined) ? null : dumpInput);
                    consoleOutput = (dumpInput == undefined) ? (new ErrorConsoleSegment("Undefined variable " + parsedInput[2] + "!")) : (new TextConsoleSegment(["[object Object]"]));
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing variable name");
                }
                break;
            case "restore":
            {
                processCommandInput("mount", (co, val) =>
                {
                    processCommandInput("source mount->import.ash", callback);
                });
                callbackPassed = true;
            }
                break;
            case "lfspush":
            {
                if (parsedInput.length > 2)
                {
                    let data = getVariable(parsedInput[2], environmentVariables);
                    let uuid = parsedInput[4];
                    //console.log(data);
                    //console.log(uuid);
                    callbackPassed = true;

                    storeToLfs(uuid, data, () =>
                    {
                        //console.log("UPLOADED");
                        let tcs = new TextConsoleSegment(["Uploaded to " + parsedInput[4]]);
                        callback(tcs, null);
                    }, (progress) =>
                    {
                        //console.log("Progress " + progress);
                        if (progress == -1)
                        {
                            //console.log("Upload failed")
                            callback(new ErrorConsoleSegment("Upload failed"), null);
                        }
                    });
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing variable name");
                }
            }
                break;
            case "mount":
            {
                let input = document.createElement('input');
                input.type = 'file';
                input.onchange = (e) =>
                {
                    let files = Array.from(input.files);
                    if (files.length > 0)
                    {
                        JSZip.loadAsync(files[0]).then((zip) =>
                        {
                            console.log(zip);
                            let zipfiles = {};
                            for (const key in zip["files"])
                            {
                                let file = zip.file(key);
                                if (file != null)
                                {
                                    if (!file.dir)
                                    {
                                        file.async("blob").then((obj) =>
                                        {
                                            let path = key.split("/");
                                            let cobj = zipfiles;
                                            for (let i = 0; i < path.length; i++)
                                            {
                                                if (path[i].length > 0)
                                                {
                                                    if (!(path[i] in cobj))
                                                    {
                                                        cobj[path[i]] = {};
                                                    }
                                                    if (i == path.length - 1)
                                                    {
                                                        cobj[path[i]] = obj;
                                                    }
                                                    cobj = cobj[path[i]];
                                                }
                                            }
                                        });
                                    }
                                    //cobj = zip.file(key).async("blob");
                                }
                            }
                            environmentVariables["mount"] = zipfiles;
                            if (callback != null)
                            {
                                if (callback instanceof Function)
                                {
                                    callback(new TextConsoleSegment(["Mounted zip file at $mount"]), environmentVariables["mount"]);
                                }
                            }
                        });
                    }
                    else
                    {
                        if (callback != null)
                        {
                            if (callback instanceof Function)
                            {
                                callback(new ErrorConsoleSegment("No file selected!"), valueOutput);
                            }
                        }
                    }
                };
                input.click();
                callbackPassed = true;
            }
                break;
            case "exec":
            {
                if (parsedInput.length > 2)
                {
                    let dumpInput = getVariable(parsedInput[2], environmentVariables);
                    console.log(dumpInput);
                    callbackPassed = true;
                }
                else
                {
                    consoleOutput = new ErrorConsoleSegment("Missing variable name");
                }
            }
                break;
            case "vars":
                let varText = [];
                for (let varName in environmentVariables)
                {
                    varText.push("$" + varName + " = " + environmentVariables[varName]);
                }
                consoleOutput = new TextConsoleSegment(varText);
                break;
            default:
                consoleOutput = new ErrorConsoleSegment("'" + parsedInput[0] + "' is not a valid command.");
                break;
        }

        if (!callbackPassed)
        {
            if (callback != null)
            {
                if (callback instanceof Function)
                {
                    callback(consoleOutput, valueOutput);
                }
            }
        }
    }
}


document.getElementById("js_console_input").addEventListener("keyup", ({key}) =>
{
    //console.log(key);
    if (key === "Enter")
    {
        runCommandInput(document.getElementById("js_console_input").value);
        document.getElementById("js_console_input").value = "";
    }
    if (key === "ArrowUp")
    {
        editableHistory[historyPointer] = document.getElementById("js_console_input").value;
        if (historyPointer > 0)
        {
            historyPointer--;
        }
        document.getElementById("js_console_input").value = editableHistory[historyPointer];
    }
    if (key === "ArrowDown")
    {
        editableHistory[historyPointer] = document.getElementById("js_console_input").value;
        if (historyPointer < (editableHistory.length - 1))
        {
            historyPointer++;
        }
        document.getElementById("js_console_input").value = editableHistory[historyPointer];
    }
})
document.getElementById("js_console_input").focus();

let preamble = [
    "Auxilium Console",
    "Use the command 'help' to get started"
];
drawConsoleOutput(new TextConsoleSegment(preamble));
