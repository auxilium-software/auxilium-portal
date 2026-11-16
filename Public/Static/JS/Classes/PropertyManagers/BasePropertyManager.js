
class BasePropertyManager
{
    constructor(subject, subjectId)
    {
        this.apiClient = new APIInteractions();
        this.subject = subject;
        this.subjectId = subjectId;
        this.basePath = `/api/v3/${subject}/${encodeURIComponent(subjectId)}/additional_properties`;
    }





    static async editProperty(subjectId, propertyKey, currentContent, displayName = null)
    {
        return new this(subjectId).editProperty(propertyKey, currentContent, displayName);
    }

    static async editEnumProperty(subjectId, propertyKey, currentValueId, enumTypeId, displayName = null)
    {
        return new this(subjectId).editEnumProperty(propertyKey, currentValueId, enumTypeId, displayName);
    }

    static async moveProperty(subjectId, propertyKey, currentGroupId, displayName = null)
    {
        return new this(subjectId).moveProperty(propertyKey, currentGroupId, displayName);
    }

    static async deleteProperty(subjectId, propertyKey, displayName = null)
    {
        return new this(subjectId).deleteProperty(propertyKey, displayName);
    }





    async editProperty(propertyKey, currentContent, displayName = null)
    {
        const heading = await Localisation.translate('Edit property: {name}', { '{name}': displayName || propertyKey });
        const newContent = prompt(heading, currentContent);

        if (newContent === null || newContent === currentContent)
        {
            return;
        }

        try
        {
            const [status, response] = await this.apiClient.API_PATCH(
                `${this.basePath}/${encodeURIComponent(propertyKey)}`,
                { content: newContent, contentType: 'text/plain' }
            );

            if (status === null || status >= 400)
            {
                throw new Error(this.#errorDetail(response));
            }

            window.location.reload();
        }
        catch (error)
        {
            await this.#showFailure('Failed to update property', error);
        }
    }





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
                throw new Error(this.#errorDetail(response));
            }
            values = response;
        }
        catch (error)
        {
            await this.#showFailure('Failed to load options', error, 'cloud-off');
            return;
        }

        const options = values
            .slice()
            .sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0))
            .map(v => ({ value: v.id, label: v.label || v.canonicalName || v.id }));

        const heading = await Localisation.translate('Edit property: {name}', { '{name}': displayName || propertyKey });
        const chosenId = await this._promptChoice(heading, options, currentValueId);
        if (chosenId === null || chosenId === currentValueId)
        {
            return;
        }

        try
        {
            const [status, response] = await this.apiClient.API_PATCH(
                `${this.basePath}/${encodeURIComponent(propertyKey)}`,
                { content: chosenId, contentType: window.ENUM_REF_CONTENT_TYPE }
            );

            if (status === null || status >= 400)
            {
                throw new Error(this.#errorDetail(response));
            }

            window.location.reload();
        }
        catch (error)
        {
            await this.#showFailure('Failed to update property', error);
        }
    }





    async moveProperty(propertyKey, currentGroupId, displayName = null)
    {
        const groupController = new AdditionalPropertyGroupController(this.subject);
        const groups = await groupController.GetGroups();
        if (groups === false)
        {
            await this.#showFailure('Failed to load property groups', new Error(groupController.LastErrorDetail || ''), 'cloud-off');
            return;
        }

        const current = currentGroupId || '';

        const options = [{ value: '', label: await Localisation.translate('No group') }].concat(
            groups
                .slice()
                .sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0))
                .map(g => ({ value: g.id, label: g.displayName || g.canonicalName }))
        );

        const heading = await Localisation.translate('Move property: {name}', { '{name}': displayName || propertyKey });
        const chosen = await this._promptChoice(heading, options, current);
        if (chosen === null || chosen === current)
        {
            return;
        }

        try
        {
            const [status, response] = await this.apiClient.API_PATCH(
                `${this.basePath}/${encodeURIComponent(propertyKey)}/group`,
                { groupId: chosen === '' ? null : chosen }
            );

            if (status === null || status >= 400)
            {
                throw new Error(this.#errorDetail(response));
            }

            window.location.reload();
        }
        catch (error)
        {
            await this.#showFailure('Failed to move property', error);
        }
    }






    async deleteProperty(propertyKey, displayName = null)
    {
        const confirmMessage = await Localisation.translate(
            'Are you sure you want to delete the property "{name}"? This action cannot be undone.',
            { '{name}': displayName || propertyKey }
        );

        if (!confirm(confirmMessage))
        {
            return;
        }

        try
        {
            const [status, response] = await this.apiClient.API_DELETE(
                `${this.basePath}/${encodeURIComponent(propertyKey)}`
            );

            if (status === null || status >= 400)
            {
                throw new Error(this.#errorDetail(response));
            }

            window.location.reload();
        }
        catch (error)
        {
            await this.#showFailure('Failed to delete property', error);
        }
    }





    async _promptChoice(heading, options, currentValue)
    {
        const cancelLabel = this.#capitalize(await Localisation.translate('Cancel'));
        const saveLabel = this.#capitalize(await Localisation.translate('Save data'));

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

            for (const option of options)
            {
                const opt = document.createElement('option');
                opt.value = option.value;
                opt.textContent = option.label;
                if (option.value === currentValue) opt.selected = true;
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






    #errorDetail(response)
    {
        if (response && typeof response === 'object')
        {
            return response.detail || response.message || '';
        }
        return typeof response === 'string' ? response : '';
    }

    async #showFailure(messageKey, error, icon = 'alert-circle')
    {
        console.error(messageKey + ':', error);
        const message = await Localisation.translate(messageKey);
        const detail = error && error.message ? ': ' + error.message : '';
        new ToastNotification(message + detail, icon, 'error');
    }

    #capitalize(str)
    {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
}
