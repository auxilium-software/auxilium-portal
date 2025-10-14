class NewPropertyPopup
{
    #rootContainer = null;
    #innerContainer = null;
    #currentFocus = null;
    #okButton = null;
    #onSuccessCallback = null;
    #apiClient = null;
    #resourcePath = null;
    #userId = null;
    #types = null;
    #floating = false;
    /**
     * Possible types: 'dict', 'array', 'static'
     */
    #mode = null;

    constructor(types = null, resourcePath = null, floating = false, userId = 'me')
    {
        this.#apiClient = new APIInteractions();
        this.#resourcePath = resourcePath;
        this.#userId = userId;
        this.#floating = floating;

        if (resourcePath)
        {
            if (resourcePath.endsWith('/*'))
            {
                this.#mode = 'dict';
            }
            else if (resourcePath.endsWith('/#'))
            {
                this.#mode = 'array';
            }
            else
            {
                this.#mode = 'static';
            }
        }
        else
        {
            this.#mode = 'dict';
        }

        this.#types = this.#normalizeTypes(types);

        this.#initializeContainer();
        this.#buildInterface();
    }

    #normalizeTypes(types)
    {
        if (!types)
        {
            return ['ICALENDAR_TODO', 'ICALENDAR_JOURNAL', 'ICALENDAR_EVENT', 'FILE_UPLOAD', 'PLAIN_TEXT'];
        }
        return Array.isArray(types) ? types : [types];
    }

    #initializeContainer()
    {
        this.#rootContainer = document.createElement('div');

        if (this.#floating)
        {
            this.#innerContainer = document.createElement('div');
            this.#innerContainer.classList.add('inner-content', 'message-draft');

            const closeButton = document.createElement('a');
            closeButton.classList.add('action-link', 'action-link-delete');
            closeButton.innerText = this.#capitalize(Localisation.translate('Cancel'));
            closeButton.addEventListener('click', (e) =>
            {
                e.preventDefault();
                this.close();
            });

            this.#innerContainer.appendChild(closeButton);

            const spacer = document.createElement('div');
            spacer.classList.add('small-spacer');
            this.#innerContainer.appendChild(spacer);

            this.#rootContainer.appendChild(this.#innerContainer);
            this.#rootContainer.classList.add('floating-panel');
        }
        else
        {
            this.#innerContainer = this.#rootContainer;
            this.#rootContainer.classList.add('message-draft', 'logical-box');
        }
    }

    #buildInterface()
    {
        const header = this.#createHeader();
        if (header)
        {
            this.#innerContainer.appendChild(header.element);
            if (header.focusElement)
            {
                this.#currentFocus = header.focusElement;
            }
        }

        this.#okButton = document.createElement('input');
        this.#okButton.type = 'submit';
        this.#okButton.value = this.#capitalize(Localisation.translate('Save data'));

        const spacer = document.createElement('div');
        spacer.classList.add('small-spacer');
        this.#innerContainer.appendChild(spacer);

        if (this.#types.length === 1)
        {
            this.#buildSingleTypeInterface(this.#types[0]);
        }
        else
        {
            this.#buildMultiTypeInterface();
        }
    }

    #createHeader()
    {
        const result = {element: null, focusElement: null};

        if (this.#mode === 'dict')
        {
            const container = document.createElement('div');

            const label = document.createElement('span');
            label.innerText = `Adding property to: ${this.#getDisplayPath()}`;
            container.appendChild(label);

            const input = document.createElement('input');
            input.type = 'text';
            input.placeholder = 'property_name';
            input.classList.add('property-name-input');
            container.appendChild(input);

            result.element = container;
            result.focusElement = input;
        }
        else if (this.#mode === 'array')
        {
            const label = document.createElement('span');
            label.innerText = `Adding item to array: ${this.#getDisplayPath()}`;
            result.element = label;
        }
        else
        {
            const label = document.createElement('span');
            label.innerText = `Saving to: ${this.#resourcePath || 'user properties'}`;
            result.element = label;
        }

        return result;
    }

    #getDisplayPath()
    {
        if (!this.#resourcePath) return 'user properties';
        return this.#resourcePath.replace(/[\*#]$/, '');
    }

    #buildSingleTypeInterface(type)
    {
        const handlers = {
            'PLAIN_TEXT': () => this.#setupPlainText(),
            'FILE_UPLOAD': () => this.#setupFileUpload(),
            'ICALENDAR_TODO': () => this.#setupICalTodo(),
            'ICALENDAR_JOURNAL': () => this.#setupICalJournal(),
            'ICALENDAR_EVENT': () => this.#setupICalEvent()
        };

        const handler = handlers[type];
        if (handler)
        {
            handler();
        }
    }

    #buildMultiTypeInterface()
    {
        const pageOne = document.createElement('div');
        pageOne.classList.add('logical-box');

        const typeHandlers = {
            'PLAIN_TEXT': {label: 'New text property', handler: () => this.#setupPlainText(pageOne)},
            'FILE_UPLOAD': {label: 'Upload a file', handler: () => this.#setupFileUpload(pageOne)},
            'ICALENDAR_TODO': {label: 'New todo note', handler: () => this.#setupICalTodo(pageOne)},
            'ICALENDAR_JOURNAL': {label: 'New timeline note', handler: () => this.#setupICalJournal(pageOne)},
            'ICALENDAR_EVENT': {label: 'New calendar event', handler: () => this.#setupICalEvent(pageOne)}
        };

        this.#types.forEach(type =>
        {
            const config = typeHandlers[type];
            if (config)
            {
                const link = document.createElement('a');
                link.classList.add('navigational-link');
                link.innerText = config.label;
                link.href = 'javascript:;';
                link.addEventListener('click', (e) =>
                {
                    e.preventDefault();
                    config.handler();
                });
                pageOne.appendChild(link);
                pageOne.appendChild(document.createElement('br'));
            }
        });

        this.#innerContainer.appendChild(pageOne);
    }

    #setupPlainText(pageToRemove = null)
    {
        if (pageToRemove) pageToRemove.remove();

        const textArea = document.createElement('textarea');
        textArea.placeholder = 'Enter your text here...';
        textArea.classList.add('property-textarea');
        this.#innerContainer.appendChild(textArea);
        textArea.focus();

        this.#okButton.addEventListener('click', async (e) =>
        {
            e.preventDefault();
            try
            {
                const response = await this.#saveContent(textArea.value, 'text/plain', 'text_note');
                this.#handleSuccess(response);
            } catch (error)
            {
                this.#handleError(error);
            }
        });

        this.#innerContainer.appendChild(this.#okButton);
    }

    #setupFileUpload(pageToRemove = null)
    {
        if (pageToRemove) pageToRemove.remove();

        const inputId = 'file_upload_' + this.#generateId(16);

        const label = document.createElement('label');
        label.innerText = 'Choose a file';
        label.classList.add('button');
        label.htmlFor = inputId;

        const input = document.createElement('input');
        input.type = 'file';
        input.id = inputId;
        input.multiple = true;

        this.#innerContainer.appendChild(label);
        this.#innerContainer.appendChild(input);

        const handleFiles = async (files) =>
        {
            if (!files || files.length === 0) return;

            try
            {
                for (const file of files)
                {
                    const formData = new FormData();
                    formData.append('file', file);

                    const response = await this.#saveContent(
                        formData,
                        'multipart/form-data',
                        this.#sanitizeName(file.name)
                    );

                    if (files.length === 1)
                    {
                        this.#handleSuccess(response);
                    }
                }

                if (files.length > 1)
                {
                    this.#handleSuccess({message: 'All files uploaded successfully'});
                }
            } catch (error)
            {
                this.#handleError(error);
            }
        };

        input.addEventListener('change', (e) => handleFiles(e.target.files));

        this.#okButton.addEventListener('click', (e) =>
        {
            e.preventDefault();
            if (input.files.length === 0)
            {
                input.click();
            }
            else
            {
                handleFiles(input.files);
            }
        });

        this.#innerContainer.appendChild(this.#okButton);

        setTimeout(() => input.click(), 100);
    }

    #setupICalTodo(pageToRemove = null)
    {
        if (pageToRemove) pageToRemove.remove();

        const textArea = document.createElement('textarea');
        textArea.placeholder = 'Enter todo description...';
        textArea.classList.add('property-textarea');
        this.#innerContainer.appendChild(textArea);
        textArea.focus();

        this.#okButton.addEventListener('click', async (e) =>
        {
            e.preventDefault();
            try
            {
                const icalData = this.#createICalendar('VTODO', {
                    summary: textArea.value,
                    status: 'NEEDS-ACTION'
                });
                const response = await this.#saveContent(icalData, 'text/calendar', 'todo_' + Date.now());
                this.#handleSuccess(response);
            } catch (error)
            {
                this.#handleError(error);
            }
        });

        this.#innerContainer.appendChild(this.#okButton);
    }

    #setupICalJournal(pageToRemove = null)
    {
        if (pageToRemove) pageToRemove.remove();

        const summaryInput = document.createElement('input');
        summaryInput.type = 'text';
        summaryInput.placeholder = 'Journal title...';
        summaryInput.classList.add('property-input');

        const descriptionArea = document.createElement('textarea');
        descriptionArea.placeholder = 'Journal content...';
        descriptionArea.classList.add('property-textarea');

        this.#innerContainer.appendChild(summaryInput);
        this.#innerContainer.appendChild(descriptionArea);
        summaryInput.focus();

        this.#okButton.addEventListener('click', async (e) =>
        {
            e.preventDefault();
            try
            {
                const icalData = this.#createICalendar('VJOURNAL', {
                    summary: summaryInput.value,
                    description: descriptionArea.value
                });
                const response = await this.#saveContent(icalData, 'text/calendar', 'journal_' + Date.now());
                this.#handleSuccess(response);
            } catch (error)
            {
                this.#handleError(error);
            }
        });

        this.#innerContainer.appendChild(this.#okButton);
    }

    #setupICalEvent(pageToRemove = null)
    {
        if (pageToRemove) pageToRemove.remove();

        const summaryInput = document.createElement('input');
        summaryInput.type = 'text';
        summaryInput.placeholder = 'Event title...';
        summaryInput.classList.add('property-input');

        const startInput = document.createElement('input');
        startInput.type = 'datetime-local';
        startInput.classList.add('property-input');

        const endInput = document.createElement('input');
        endInput.type = 'datetime-local';
        endInput.classList.add('property-input');

        const descriptionArea = document.createElement('textarea');
        descriptionArea.placeholder = 'Event description...';
        descriptionArea.classList.add('property-textarea');

        this.#innerContainer.appendChild(summaryInput);
        this.#innerContainer.appendChild(startInput);
        this.#innerContainer.appendChild(endInput);
        this.#innerContainer.appendChild(descriptionArea);
        summaryInput.focus();

        this.#okButton.addEventListener('click', async (e) =>
        {
            e.preventDefault();
            try
            {
                const icalData = this.#createICalendar('VEVENT', {
                    summary: summaryInput.value,
                    description: descriptionArea.value,
                    dtstart: startInput.value ? new Date(startInput.value).toISOString() : null,
                    dtend: endInput.value ? new Date(endInput.value).toISOString() : null
                });
                const response = await this.#saveContent(icalData, 'text/calendar', 'event_' + Date.now());
                this.#handleSuccess(response);
            } catch (error)
            {
                this.#handleError(error);
            }
        });

        this.#innerContainer.appendChild(this.#okButton);
    }

    async #saveContent(content, contentType, defaultName = null)
    {
        const apiPath = this.#calculateApiPath(defaultName);

        let payload;
        if (contentType === 'multipart/form-data')
        {
            payload = content;
        }
        else
        {
            payload = {
                content: content,
                content_type: contentType
            };
        }

        const [status, response] = await this.#apiClient.API_POST(apiPath, payload);

        if (status === null || status >= 400)
        {
            throw new Error(response?.message || response || 'Failed to save');
        }

        return response;
    }

    #calculateApiPath(defaultName = null)
    {
        let propertyName = defaultName || 'untitled';

        if (this.#mode === 'dict')
        {
            const nameInput = this.#innerContainer.querySelector('.property-name-input');
            if (nameInput && nameInput.value.trim())
            {
                propertyName = this.#sanitizeName(nameInput.value.trim());
            }
            else if (!defaultName)
            {
                throw new Error('Property name is required');
            }
        }

        if (this.#resourcePath)
        {
            if (this.#mode === 'dict')
            {
                return this.#resourcePath.replace(/\/\*$/, '/' + propertyName);
            }
            else if (this.#mode === 'array')
            {
                return this.#resourcePath.replace(/\/#$/, '');
            }
            else
            {
                return this.#resourcePath;
            }
        }
        else
        {
            throw new Error('Resource path is required');
        }
    }

    #createICalendar(type, data)
    {
        const lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Auxilium Software//Auxilium Portal//EN',
            `BEGIN:${type}`,
            `UID:${this.#generateId(48)}`,
            `DTSTAMP:${new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '')}Z`
        ];

        if (data.summary)
        {
            lines.push(`SUMMARY:${this.#escapeICalText(data.summary)}`);
        }

        if (data.description)
        {
            lines.push(`DESCRIPTION:${this.#escapeICalText(data.description)}`);
        }

        if (data.status)
        {
            lines.push(`STATUS:${data.status}`);
        }

        if (data.dtstart)
        {
            lines.push(`DTSTART:${data.dtstart.replace(/[-:]/g, '').replace(/\.\d{3}/, '')}Z`);
        }

        if (data.dtend)
        {
            lines.push(`DTEND:${data.dtend.replace(/[-:]/g, '').replace(/\.\d{3}/, '')}Z`);
        }

        lines.push(`END:${type}`, 'END:VCALENDAR');

        return lines.join('\r\n') + '\r\n';
    }

    #escapeICalText(text)
    {
        return text
            .replace(/\\/g, '\\\\')
            .replace(/;/g, '\\;')
            .replace(/,/g, '\\,')
            .replace(/\n/g, '\\n');
    }

    #sanitizeName(name)
    {
        return name
            .replace(/\.[^/.]+$/, '')
            .replace(/[^a-zA-Z0-9_-]/g, '_')
            .toLowerCase()
            .substring(0, 50) || 'untitled';
    }

    #generateId(length)
    {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        return Array.from({length}, () =>
            chars.charAt(Math.floor(Math.random() * chars.length))
        ).join('');
    }

    #capitalize(str)
    {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    #handleSuccess(response)
    {
        if (this.#onSuccessCallback)
        {
            this.#onSuccessCallback(response);
        }
        this.close();
    }

    #handleError(error)
    {
        console.error('Operation failed:', error);
        new ToastNotification(error.message, "cloud-off", "error")
    }





    render()
    {
        return this.#rootContainer;
    }

    focus()
    {
        if (this.#currentFocus)
        {
            this.#currentFocus.focus();
        }
    }

    close()
    {
        this.#rootContainer.remove();
    }

    onSuccess(callback)
    {
        this.#onSuccessCallback = callback;
    }
}
