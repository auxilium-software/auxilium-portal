class APIInteractions
{
    #getHeaders(method, useAuth)
    {
        const headers = {
            "Authorization": "Bearer " + API_KEY,
            "credentials": 'include',
        };

        if (method === 'POST' || method === 'PATCH')
        {
            headers["Content-Type"] = "application/json";
        }

        return headers;
    }

    async #apiRequest(method, target, useAuth, payload = null)
    {
        try
        {
            const options = {
                method: method,
                headers: this.#getHeaders(method, useAuth),
                body: payload ? JSON.stringify(payload) : null,
                credentials: 'include',
            };

            const response = await fetch(`http://localhost:1983${target}`, options);

            if (!response.ok)
            {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            return [response.status, await response.json()];
        } catch (error)
        {
            return [null, error.message];
        }
    }

    async API_PATCH(target, payload, useAuth = true)
    {
        return await this.#apiRequest('PATCH', target, useAuth, payload);
    }

    async API_POST(target, payload, useAuth = true)
    {
        return await this.#apiRequest('POST', target, useAuth, payload);
    }

    async API_GET(target, useAuth = true)
    {
        return await this.#apiRequest('GET', target, useAuth);
    }

    async API_DELETE(target, useAuth = true)
    {
        return await this.#apiRequest('DELETE', target, useAuth);
    }

    async API_FILE_UPLOAD(target, file, description=null, useAuth = true)
    {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('description', description);

        const response = await fetch(`http://localhost:1983${target}`, {
            method: 'POST',
            headers: {
                "Authorization": "Bearer " + API_KEY,
            },
            body: formData,
            credentials: 'include'
        });

        return [response.status, await response.json()];
    }
}
