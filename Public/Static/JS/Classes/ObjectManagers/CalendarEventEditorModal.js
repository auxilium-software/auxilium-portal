class CalendarEventEditorModal extends BaseManager
{
    constructor(calendarManager)
    {
        super();
        this.calendarManager = calendarManager;
        this.editingServerId = null;
    }

    async openForCreate(prefillDate = null)
    {
        this.closeModal();

        this.editingServerId = null;
        await this._buildModal(false);
        this._populateCategorySelect();

        const start = prefillDate ? new Date(prefillDate) : this._roundToNextHour(new Date());
        const end = new Date(start.getTime() + 60 * 60 * 1000);

        this.form.title.value = '';
        this.form.location.value = '';
        this.form.description.value = '';
        this.form.allDay.checked = false;
        this._setDateTimeFields(start, end);
        this._syncAllDayVisibility();
    }

    async openForEdit(event)
    {
        this.closeModal();

        this.editingServerId = event.customTags.serverId;
        await this._buildModal(true);
        this._populateCategorySelect();

        this.form.title.value = event.title || '';
        this.form.location.value = event.location || '';
        this.form.description.value = event.description || '';
        this.form.allDay.checked = Boolean(event.isAllDayEvent);
        this._setDateTimeFields(event.from, event.to);
        this._syncAllDayVisibility();

        const taggedCategoryId = event.customTags && event.customTags.categoryId;
        if (taggedCategoryId && this.form.category.querySelector(`option[value="${taggedCategoryId}"]`))
        {
            this.form.category.value = taggedCategoryId;
        }
    }

    async _buildModal(isEditing)
    {
        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal';

        const [
            heading, titleLabel, categoryLabel, categoryPlaceholder,
            allDayLabel, startLabel, endLabel, locationLabel,
            descriptionLabel, saveLabel, deleteLabel, cancelLabel,
        ] = await Promise.all([
            Localisation.translate(isEditing ? 'Edit event' : 'New event'),
            Localisation.translate('Title'),
            Localisation.translate('Category'),
            Localisation.translate('Select a category…'),
            Localisation.translate('All day'),
            Localisation.translate('Start'),
            Localisation.translate('End'),
            Localisation.translate('Location'),
            Localisation.translate('Description'),
            Localisation.translate('Save'),
            Localisation.translate('Delete'),
            Localisation.translate('Cancel'),
        ]);

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>${this.escapeHtml(heading)}</h2>
            </div>

            <div class="ToDoCreatorModal-Body">
                <div class="cal-field">
                    <label for="cal-event-title">${this.escapeHtml(titleLabel)}</label>
                    <input type="text" id="cal-event-title" class="fullwidth-text-input">
                </div>

                <div class="cal-field">
                    <label for="cal-event-category">${this.escapeHtml(categoryLabel)}</label>
                    <select id="cal-event-category">
                        <option value="">${this.escapeHtml(categoryPlaceholder)}</option>
                    </select>
                </div>

                <div class="checkbox-holder">
                    <input type="checkbox" id="cal-event-allday">
                    <label for="cal-event-allday"><p>${this.escapeHtml(allDayLabel)}</p></label>
                </div>

                <div class="cal-field" id="cal-event-start-datetime-field">
                    <label for="cal-event-start-datetime">${this.escapeHtml(startLabel)}</label>
                    <input type="datetime-local" id="cal-event-start-datetime">
                </div>
                <div class="cal-field" id="cal-event-start-date-field" hidden>
                    <label for="cal-event-start-date">${this.escapeHtml(startLabel)}</label>
                    <input type="date" id="cal-event-start-date">
                </div>

                <div class="cal-field" id="cal-event-end-datetime-field">
                    <label for="cal-event-end-datetime">${this.escapeHtml(endLabel)}</label>
                    <input type="datetime-local" id="cal-event-end-datetime">
                </div>
                <div class="cal-field" id="cal-event-end-date-field" hidden>
                    <label for="cal-event-end-date">${this.escapeHtml(endLabel)}</label>
                    <input type="date" id="cal-event-end-date">
                </div>

                <div class="cal-field">
                    <label for="cal-event-location">${this.escapeHtml(locationLabel)}</label>
                    <input type="text" id="cal-event-location" class="fullwidth-text-input">
                </div>

                <div class="cal-field">
                    <label for="cal-event-description">${this.escapeHtml(descriptionLabel)}</label>
                    <textarea id="cal-event-description" class="fullwidth-text-input" rows="4"></textarea>
                </div>
            </div>

            <div class="todo-modal-buttons">
                ${isEditing ? `<button type="button" id="cal-event-delete-btn" class="button dangerous-button">${this.escapeHtml(deleteLabel)}</button>` : ''}
                <button type="button" id="cal-event-cancel-btn" class="button todo-cancel-btn">${this.escapeHtml(cancelLabel)}</button>
                <button type="button" id="cal-event-save-btn" class="button">${this.escapeHtml(saveLabel)}</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        this.form = {
            title: modal.querySelector('#cal-event-title'),
            category: modal.querySelector('#cal-event-category'),
            allDay: modal.querySelector('#cal-event-allday'),
            startDateTime: modal.querySelector('#cal-event-start-datetime'),
            endDateTime: modal.querySelector('#cal-event-end-datetime'),
            startDate: modal.querySelector('#cal-event-start-date'),
            endDate: modal.querySelector('#cal-event-end-date'),
            location: modal.querySelector('#cal-event-location'),
            description: modal.querySelector('#cal-event-description'),
        };

        this._startDateTimeField = modal.querySelector('#cal-event-start-datetime-field');
        this._endDateTimeField = modal.querySelector('#cal-event-end-datetime-field');
        this._startDateField = modal.querySelector('#cal-event-start-date-field');
        this._endDateField = modal.querySelector('#cal-event-end-date-field');

        this.form.allDay.addEventListener('change', () => this._syncAllDayVisibility());
        modal.querySelector('#cal-event-save-btn').onclick = () => this._save();
        modal.querySelector('#cal-event-cancel-btn').onclick = () => this.closeModal();
        const deleteBtn = modal.querySelector('#cal-event-delete-btn');
        if (deleteBtn) deleteBtn.onclick = () => this._delete();
        overlay.onclick = (e) => { if (e.target === overlay) this.closeModal(); };

        const escHandler = (e) => {
            if (e.key === 'Escape')
            {
                this.closeModal();
            }
        };
        document.addEventListener('keydown', this._escHandler);
    }

    _populateCategorySelect()
    {
        const categories = this.calendarManager.categories || [];
        this.form.category.innerHTML = this.form.category.querySelector('option[value=""]').outerHTML +
            categories.map((c) => `<option value="${this.escapeHtml(c.id)}">${this.escapeHtml(c.name)}</option>`).join('');

        if (categories.length > 0 && !this.form.category.value)
        {
            this.form.category.value = categories[0].id;
        }
    }

    _syncAllDayVisibility()
    {
        const isAllDay = this.form.allDay.checked;
        this._startDateTimeField.hidden = isAllDay;
        this._endDateTimeField.hidden = isAllDay;
        this._startDateField.hidden = !isAllDay;
        this._endDateField.hidden = !isAllDay;
    }

    _setDateTimeFields(start, end)
    {
        this.form.startDateTime.value = this.formatDateTimeLocal(start);
        this.form.endDateTime.value = this.formatDateTimeLocal(end);
        this.form.startDate.value = this._toDateOnly(start);
        this.form.endDate.value = this._toDateOnly(end);
    }

    _toDateOnly(date)
    {
        const d = new Date(date);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    _roundToNextHour(date)
    {
        const d = new Date(date);
        d.setMinutes(0, 0, 0);
        d.setHours(d.getHours() + 1);
        return d;
    }

    _readDates()
    {
        if (this.form.allDay.checked)
        {
            const start = new Date(`${this.form.startDate.value}T00:00:00`);
            const end = new Date(`${this.form.endDate.value}T23:59:59`);
            return { start, end };
        }

        return {
            start: new Date(this.form.startDateTime.value),
            end: new Date(this.form.endDateTime.value),
        };
    }

    async _save()
    {
        const title = this.form.title.value.trim();
        if (!title)
        {
            this.showError(await Localisation.translate("Give the event a title before saving."));
            return;
        }

        const categoryId = this.form.category.value || null;
        if (!categoryId)
        {
            this.showError(await Localisation.translate("Pick a category before saving - ask an admin to add one if the list is empty."));
            return;
        }

        const { start, end } = this._readDates();
        if (!(end > start))
        {
            this.showError(await Localisation.translate("End must be after start."));
            return;
        }

        const payload = {
            title,
            start: start.toISOString(),
            end: end.toISOString(),
            allDay: this.form.allDay.checked,
            categoryId,
            location: this.form.location.value.trim() || null,
            description: this.form.description.value.trim() || null,
        };

        try
        {
            if (this.editingServerId)
            {
                await this.makeRequest('PATCH', `${this.calendarManager.basePath}/${encodeURIComponent(this.editingServerId)}`, payload);
                this.showSuccess(await Localisation.translate('Event updated'));
            }
            else
            {
                await this.makeRequest('POST', this.calendarManager.basePath, payload);
                this.showSuccess(await Localisation.translate('Event created'));
            }

            this.calendarManager.refetchRange(this.calendarManager.loadedRange.start, this.calendarManager.loadedRange.end);
            this.closeModal();
        }
        catch (error)
        {
            this.showError(`${await Localisation.translate("Couldn't save that event")}: ${error.message}`);
        }
    }

    async _delete()
    {
        if (!this.editingServerId) return;

        try
        {
            await this.makeRequest('DELETE', `${this.calendarManager.basePath}/${encodeURIComponent(this.editingServerId)}`);
            this.showSuccess(await Localisation.translate('Event deleted'));
            this.calendarManager.refetchRange(this.calendarManager.loadedRange.start, this.calendarManager.loadedRange.end);
            this.closeModal();
        }
        catch (error)
        {
            this.showError(`${await Localisation.translate("Couldn't delete that event")}: ${error.message}`);
        }
    }
}
