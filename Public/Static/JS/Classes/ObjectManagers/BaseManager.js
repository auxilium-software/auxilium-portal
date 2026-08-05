class BaseManager {
    constructor()
    {
        this.apiClient = new APIInteractions();
        this.modalElement = null;
    }

    closeModal()
    {
        if (this._escHandler)
        {
            document.removeEventListener('keydown', this._escHandler);
            this._escHandler = null;
        }
        super.closeModal();
    }

    showError(message)
    {
        new ToastNotification(message, 'alert-circle', 'error');
    }

    showSuccess(message)
    {
        new ToastNotification(message, 'check-circle', 'success');
    }

    formatDateTimeLocal(date)
    {
        const d = new Date(date);
        const offset = d.getTimezoneOffset() * 60000;
        return new Date(d - offset).toISOString().slice(0, 16);
    }

    getInitials(name)
    {
        if (!name) return '??';
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    }

    async makeRequest(method, endpoint, data = null)
    {
        try {
            let status, response;

            switch(method) {
                case 'GET':
                    [status, response] = await this.apiClient.API_GET(endpoint);
                    break;
                case 'POST':
                    [status, response] = await this.apiClient.API_POST(endpoint, data);
                    break;
                case 'PATCH':
                    [status, response] = await this.apiClient.API_PATCH(endpoint, data);
                    break;
                case 'DELETE':
                    [status, response] = await this.apiClient.API_DELETE(endpoint);
                    break;
                default:
                    throw new Error(`Unsupported method: ${method}`);
            }

            if (status === null || status >= 400)
            {
                throw new Error(response?.message || response || 'Request failed');
            }

            return response;
        } catch (error) {
            console.error(`API ${method} request failed:`, error);
            throw error;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
