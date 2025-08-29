class MultipartContent
{
    #rawHeaders = [];
    #headers = {};
    #content = "";

    constructor(raw = null)
    {
        if (raw == null)
        {
            raw = "";
        }


    }
}

class InternetMessage
{
    #rawHeaders = [];
    #headers = {};
    #content = [];

    constructor(raw = null)
    {
        if (raw == null)
        {
            raw = "";
        }

        let buffer = "";
        let line = "";
        let contentSwitch = false;

        for (let i = 0; i < raw.length; i++)
        {
            if (contentSwitch)
            {
                buffer = buffer + raw.charAt(i);
            }
            else
            {
                if (raw.charAt(i) == "\n")
                {
                    if (line.length == 0 || (line.length == 1 && line == "\r"))
                    {
                        contentSwitch = true;
                        buffer = "";
                    }
                    else
                    {
                        buffer = buffer + line;
                        line = "";
                    }
                }
                else
                {
                    line = line + raw.charAt(i);
                }
            }
        }

        console.log(buffer);
        console.log(rawHeaders);
        console.log(headers);
    }
}
