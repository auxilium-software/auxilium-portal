class APIInteractions
{
    #getHeaders(method, totpCode = null)
    {
        const headers = {};

        if (method === 'POST' || method === 'PATCH' || method === 'PUT')
        {
            headers['Content-Type'] = 'application/json';
        }

        if (totpCode !== null)
        {
            headers['X-TOTP-Code'] = totpCode;
        }

        return headers;
    }

    async #apiRequest(method, target, payload = null, totpCode = null)
    {
        try
        {
            const options = {
                method:      method,
                headers:     this.#getHeaders(method, totpCode),
                credentials: 'include',
            };

            if (payload !== null)
            {
                options.body = JSON.stringify(payload);
            }

            const response = await fetch(`/API/BFFAPIProxy${target}`, options);

            // 401 means the BFF already attempted a refresh, and it failed, which means that the session has genuinely expired, so redirect to the login page
            if (response.status === 401)
            {
                window.location.href = '/login';
                return [401, null];
            }

            if(response.status === 204)
            {
                return [response.status, null];
            }

            return [response.status, await response.json()];
        }
        catch (error)
        {
            return [null, error.message];
        }
    }

    async API_GET(target, totpCode = null)
    {
        return await this.#apiRequest('GET', target, null, totpCode);
    }

    async API_POST(target, payload, totpCode = null)
    {
        return await this.#apiRequest('POST', target, payload, totpCode);
    }

    async API_PATCH(target, payload, totpCode = null)
    {
        return await this.#apiRequest('PATCH', target, payload, totpCode);
    }

    async API_PUT(target, payload, totpCode = null)
    {
        return await this.#apiRequest('PUT', target, payload, totpCode);
    }

    async API_DELETE(target, totpCode = null)
    {
        return await this.#apiRequest('DELETE', target, {}, totpCode);
    }

    async API_FILE_UPLOAD(target, file, description = null)
    {
        try
        {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('description', description);

            const response = await fetch(`/API/BFFAPIFileProxy${target}`, {
                method:      'POST',
                credentials: 'include',
                body:        formData,
                // no Content-Type header - browser sets it with the correct multipart boundary
            });

            if (response.status === 401)
            {
                window.location.href = '/login';
                return [401, null];
            }

            return [response.status, await response.json()];
        }
        catch (error)
        {
            return [null, error.message];
        }
    }
}
