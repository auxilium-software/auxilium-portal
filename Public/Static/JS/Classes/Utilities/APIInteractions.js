class APIInteractions
{
    #refreshPromise = null; // Prevent multiple simultaneous refresh attempts

    #getHeaders(method, useAuth)
    {
        const headers = {
            "Authorization": "Bearer " + CookieUtilities.getCookie("access_token"),
            "credentials": 'include',
        };

        if (method === 'POST' || method === 'PATCH')
        {
            headers["Content-Type"] = "application/json";
        }

        return headers;
    }

    async #refreshAccessToken()
    {
        if (this.#refreshPromise)
        {
            return await this.#refreshPromise;
        }

        this.#refreshPromise = (async () => {
            try
            {
                const response = await fetch(`${API_BASE_URL}/authentication/refresh`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok)
                {
                    window.location.href = '/login';
                    throw new Error('Session expired');
                }

                const data = await response.json();
                CookieUtilities.setCookie("access_token", data.access_token, 30);

                return true;
            }
            catch (error)
            {
                window.location.href = '/login';
                throw error;
            }
            finally
            {
                this.#refreshPromise = null;
            }
        })();

        return await this.#refreshPromise;
    }

    async #apiRequest(method, target, useAuth, payload = null, isRetry = false)
    {
        try
        {
            const options = {
                method: method,
                headers: this.#getHeaders(method, useAuth),
                body: payload ? JSON.stringify(payload) : null,
                credentials: 'include',
            };

            const response = await fetch(`${API_BASE_URL}${target}`, options);

            if (response.status === 401 && useAuth && !isRetry)
            {
                await this.#refreshAccessToken();
                return await this.#apiRequest(method, target, useAuth, payload, true);
            }

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

    async API_FILE_UPLOAD(target, file, description = null, useAuth = true, isRetry = false)
    {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('description', description);

        const response = await fetch(`${API_BASE_URL}${target}`, {
            method: 'POST',
            headers: {
                "Authorization": "Bearer " + API_KEY,
            },
            body: formData,
            credentials: 'include'
        });

        if (response.status === 401 && useAuth && !isRetry)
        {
            await this.#refreshAccessToken();
            return await this.API_FILE_UPLOAD(target, file, description, useAuth, true);
        }

        return [response.status, await response.json()];
    }
}
