class APIInteractions
{
    #getHeaders(method, useAuth)
    {
        const headers = {
            "Authorization": "Bearer " + API_KEY,
            "credentials": 'include',
        };

        if (method === 'POST' || method === 'PATCH') {
            headers["Content-Type"] = "application/json"; // Ensure JSON format
        }

        return headers;
    }

    async #apiRequest(method, target, useAuth, payload = null) {
        try {
            const options = {
                method: method,
                headers: this.#getHeaders(method, useAuth),
                body: payload ? JSON.stringify(payload) : null,
                credentials: 'include',
            };

            const response = await fetch(`http://localhost:1983${target}`, options);

            // Check if the response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            return [response.status, await response.json()];
        }
        catch (error)
        {
            return [null, error.message];
        }
    }

    async API_PATCH(target, payload, useAuth = true) {
        return await this.#apiRequest('PATCH', target, useAuth, payload);
    }

    async API_POST(target, payload, useAuth = true) {
        return await this.#apiRequest('POST', target, useAuth, payload);
    }

    async API_GET(target, useAuth = true) {
        return await this.#apiRequest('GET', target, useAuth);
    }

    async API_DELETE(target, useAuth = true) {
        return await this.#apiRequest('DELETE', target, useAuth);
    }
}
