
class BasePropertyManager
{
    constructor(basePath)
    {
        this.apiClient = new APIInteractions();
        this.basePath = basePath;
    }

    // ==================================================
    // PLAIN TEXT PROPERTY
    async editProperty(propertyKey, currentContent, displayName)
    {
        const newContent = prompt(`Edit property "${displayName || propertyKey}":`, currentContent);

        if (newContent === null || newContent === currentContent)
        {
            return;
        }

        try {
            const apiPath = `${this.basePath}/${encodeURIComponent(propertyKey)}`;
            const payload = {
                content: newContent,
                contentType: 'text/plain'
            };

            const [status, response] = await this.apiClient.API_PATCH(apiPath, payload);

            if (status === null || status >= 400) {
                throw new Error(response?.message || response || 'Failed to update property');
            }

            window.location.reload();
        }
        catch (error)
        {
            console.error('Failed to update property:', error);
            new ToastNotification(
                `Failed to update property: ${error.message}`,
                "alert-circle",
                "error"
            );
        }
    }

    // ==================================================
    // DATA ENUMERATOR PROPERTY
    async editEnumProperty(propertyKey, currentValueId, enumTypeId, displayName = null)
    {
        let values;
        try
        {
            const [status, response] = await this.apiClient.API_GET(
                `/api/v3/data-enumerators/${encodeURIComponent(enumTypeId)}/values?includeInactive=false`
            );

            if (status === null || status >= 400 || !Array.isArray(response))
            {
                throw new Error(response?.message || response || 'Failed to load options');
            }
            values = response;
        }
        catch (error)
        {
            console.error('Failed to load enum options:', error);
            new ToastNotification(
                `Failed to load options: ${error.message}`,
                "cloud-off",
                "error"
            );
            return;
        }

        const chosenId = await this._promptEnumChoice(displayName || propertyKey, values, currentValueId);
        if (chosenId === null || chosenId === currentValueId)
        {
            return;
        }

        try
        {
            const apiPath = `${this.basePath}/${encodeURIComponent(propertyKey)}`;
            const payload = {
                content: chosenId,
                contentType: window.ENUM_REF_CONTENT_TYPE
            };

            const [status, response] = await this.apiClient.API_PATCH(apiPath, payload);

            if (status === null || status >= 400)
            {
                throw new Error(response?.message || response || 'Failed to update property');
            }

            window.location.reload();
        }
        catch (error)
        {
            console.error('Failed to update property:', error);
            new ToastNotification(
                `Failed to update property: ${error.message}`,
                "alert-circle",
                "error"
            );
        }
    }

    // floating panel with a <select>, styled like NewPropertyPopup; resolves to chosen id or null
    async _promptEnumChoice(propertyKey, values, currentValueId)
    {
        const cancelLabel = this.#capitalize(await Localisation.translate('Cancel'));
        const saveLabel = this.#capitalize(await Localisation.translate('Save data'));
        const heading = await Localisation.translate('Edit property: {name}', { '{name}': propertyKey });

        return new Promise((resolve) =>
        {
            const root = document.createElement('div');
            root.classList.add('floating-panel');

            const inner = document.createElement('div');
            inner.classList.add('inner-content', 'message-draft', 'logical-box');

            const cancel = document.createElement('a');
            cancel.classList.add('action-link', 'action-link-delete');
            cancel.href = 'javascript:;';
            cancel.innerText = cancelLabel;

            const spacer = document.createElement('div');
            spacer.classList.add('small-spacer');

            const label = document.createElement('span');
            label.innerText = heading;

            const select = document.createElement('select');
            select.classList.add('property-input');

            // sort by sortOrder so the dropdown matches the admin ordering
            const ordered = values.slice().sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0));
            for (const v of ordered)
            {
                const opt = document.createElement('option');
                opt.value = v.id;
                // label falls back to canonicalName until the values read path carries translations
                opt.textContent = v.label || v.canonicalName || v.id;
                if (v.id === currentValueId) opt.selected = true;
                select.appendChild(opt);
            }

            const okButton = document.createElement('input');
            okButton.type = 'submit';
            okButton.value = saveLabel;

            const close = (result) =>
            {
                document.removeEventListener('keydown', onKey);
                if (root.parentNode) root.parentNode.removeChild(root);
                resolve(result);
            };

            const onKey = (e) =>
            {
                if (e.key === 'Escape') { e.preventDefault(); close(null); }
                else if (e.key === 'Enter') { e.preventDefault(); close(select.value); }
            };

            cancel.addEventListener('click', (e) => { e.preventDefault(); close(null); });
            okButton.addEventListener('click', (e) => { e.preventDefault(); close(select.value); });
            document.addEventListener('keydown', onKey);

            inner.appendChild(cancel);
            inner.appendChild(spacer);
            inner.appendChild(label);
            inner.appendChild(select);
            inner.appendChild(okButton);
            root.appendChild(inner);

            const anchor = document.getElementById('main-content');
            if (anchor) anchor.after(root);
            else document.body.appendChild(root);

            select.focus();
        });
    }

    #capitalize(str)
    {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // ==================================================
    // PROPERTY DELETION
    async deleteProperty(propertyKey, displayName)
    {
        const confirmMessage = `Are you sure you want to delete the property "${displayName}"?\n\nThis action cannot be undone.`;

        if (!confirm(confirmMessage))
        {
            return;
        }

        try
        {
            const apiPath = `${this.basePath}/${encodeURIComponent(propertyKey)}`;
            const [status, response] = await this.apiClient.API_DELETE(apiPath);

            if (status === null || status >= 400)
            {
                throw new Error(response?.message || response || 'Failed to delete property');
            }

            window.location.reload();
        }
        catch (error)
        {
            console.error('Failed to delete property:', error);
            new ToastNotification(
                `Failed to delete property: ${error.message}`,
                "alert-circle",
                "error"
            );
        }
    }
}
