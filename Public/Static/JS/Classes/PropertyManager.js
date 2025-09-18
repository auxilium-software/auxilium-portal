class PropertyManager {
    constructor(userId) {
        this.apiClient = new APIInteractions();
        this.basePath = `/api/v3/users/${userId}/additional_properties`;
    }

    async editProperty(propertyKey, currentContent) {
        const newContent = prompt(`Edit property "${propertyKey}":`, currentContent);

        if (newContent === null || newContent === currentContent) {
            return;
        }

        try {
            const apiPath = `${this.basePath}/${encodeURIComponent(propertyKey)}`;
            const payload = {
                content: newContent,
                content_type: 'text/plain'
            };

            const [status, response] = await this.apiClient.API_PATCH(apiPath, payload);

            if (status === null || status >= 400) {
                throw new Error(response?.message || response || 'Failed to update property');
            }

            window.location.reload();
        } catch (error) {
            console.error('Failed to update property:', error);
            new ToastNotification(
                `Failed to update property: ${error.message}`,
                "alert-circle",
                "error"
            );
        }
    }

    async deleteProperty(propertyKey, displayName) {
        const confirmMessage = `Are you sure you want to delete the property "${displayName}"?\n\nThis action cannot be undone.`;

        if (!confirm(confirmMessage)) {
            return;
        }

        try {
            const apiPath = `${this.basePath}/${encodeURIComponent(propertyKey)}`;
            const [status, response] = await this.apiClient.API_DELETE(apiPath);

            if (status === null || status >= 400) {
                throw new Error(response?.message || response || 'Failed to delete property');
            }

            window.location.reload();
        } catch (error) {
            console.error('Failed to delete property:', error);
            new ToastNotification(
                `Failed to delete property: ${error.message}`,
                "alert-circle",
                "error"
            );
        }
    }

    static async editProperty(userId, propertyKey, currentContent) {
        const manager = new PropertyManager(userId);
        return manager.editProperty(propertyKey, currentContent);
    }

    static async deleteProperty(userId, propertyKey, displayName) {
        const manager = new PropertyManager(userId);
        return manager.deleteProperty(propertyKey, displayName);
    }
}
