class TemplateString
{
    #str = null;
    #props = {};

    constructor(string, properties)
    {
        this.#str = string;
        this.#props = properties;
    }

    toString()
    {
        let properties = {};
        if (typeof this.#props === 'object' && this.#props !== null)
        {
            properties = this.#props;
        }
        let escape = false;
        let literalLatch = true;
        let cstr = "";
        let literals = [];
        for (let i = 0; i < this.#str.length; i++)
        {
            if (literalLatch)
            {
                if (escape)
                {
                    escape = false;
                    cstr += this.#str.charAt(i);
                }
                else
                {
                    if (this.#str.charAt(i) == '\\')
                    {
                        escape = true;
                    }
                    else
                    {
                        if (this.#str.length > (i + 3))
                        {
                            if (this.#str.substring(i, i + 2) == "{{")
                            {
                                literalLatch = false;
                                literals.push(cstr);
                                cstr = "";
                                i++;
                            }
                        }
                        if (literalLatch)
                        {
                            cstr += this.#str.charAt(i);
                        }
                    }
                }
            }
            else
            {
                if (this.#str.length > (i + 3))
                {
                    if (this.#str.substring(i, i + 2) == "}}")
                    {
                        literalLatch = true;
                        cstr = cstr.trim();

                        if (properties.hasOwnProperty(cstr))
                        {
                            literals.push(properties[cstr]);
                        }

                        cstr = "";
                        i++;
                    }
                }
                if (!literalLatch)
                {
                    cstr += this.#str.charAt(i);
                }
            }
        }
        literals.push(cstr);
        return literals.join('');
    }
}
