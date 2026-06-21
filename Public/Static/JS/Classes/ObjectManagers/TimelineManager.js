class TimelineManager extends BaseManager
{
    constructor(caseId)
    {
        super();
        this.caseId = caseId;
        this.basePath = `/api/v3/cases/${caseId}/timeline`;
    }

    async showCreateModal()
    {
        await this._showModal({
            heading: await Localisation.translate("New Timeline Entry"),
            submitLabel: await Localisation.translate("Create Entry"),
            entry: null,
            onSubmit: (e) => this.handleCreate(e)
        });
    }

    async showEditModal(entryId, title, description)
    {
        await this._showModal({
            heading: await Localisation.translate("Edit Timeline Entry"),
            submitLabel: await Localisation.translate("Save Changes"),
            entry: { title, description },
            onSubmit: (e) => this.handleEdit(e, entryId)
        });
    }

    async _showModal({ heading, submitLabel: translatedSubmitLabel, entry, onSubmit })
    {
        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal';

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>${this.escapeHtml(heading)}</h2>
            </div>

            <form id="timeline-form" class="ToDoCreatorModal-Body">
                <div>
                    <label>
                        Title <span class="asterisk-hidden-text">(${await Localisation.translate("required")})</span>
                    </label>
                    <input type="text" id="timeline-title" required placeholder="${await Localisation.translate("What happened?")}">
                </div>
                
                <div>
                    <label>
                        ${await Localisation.translate("When did this happen?")} <span class="asterisk-hidden-text">(${await Localisation.translate("required")})</span>
                    </label>
                    <div class="timestamp-input-box" id="timeline-occurredAt">
                        <div class="logical-box">
                            <input id="ts_y" name="ts_y" class="ts-box-year" title="Year" type="number" min="1800" max="3000" placeholder="YYYY" value="2026">
                            <span>-</span>
                            <input id="ts_m" name="ts_m" class="ts-box-month" title="Month (1-12)" type="number" min="1" max="12" placeholder="MM" value="06">
                            <span>-</span>
                            <input id="ts_d" name="ts_d" class="ts-box-day" title="Day of month" type="number" min="1" max="31" placeholder="DD" value="20">
                        </div>
                        <div class="logical-box">
                            <input id="ts_hr" name="ts_hr" class="ts-box-hour" title="Hour (24 hour time)" type="number" min="0" max="23" placeholder="HH" value="22">
                            <span>:</span>
                            <input id="ts_min" name="ts_min" class="ts-box-minute" title="Minute" type="number" min="0" max="59" placeholder="MM" value="30">
                        </div>
                    </div>
                </div>

                <div>
                    <label>${await Localisation.translate("Description")}</label>
                    <textarea id="timeline-description" rows="4" placeholder="${await Localisation.translate("Additional details...")}"></textarea>
                </div>

                <div class="todo-modal-buttons">
                    <button type="button" id="timeline-cancel-btn" class="button todo-cancel-btn">
                        ${await Localisation.translate("Cancel")}
                    </button>
                    <button type="submit" id="timeline-save-btn" class="button todo-save-btn">
                        ${this.escapeHtml(translatedSubmitLabel)}
                    </button>
                </div>
            </form>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        if (entry)
        {
            document.getElementById('timeline-title').value = entry.title || '';
            document.getElementById('timeline-occurredAt').value = entry.occurredAtUtc || '';
            document.getElementById('timeline-description').value = entry.description || '';
        }

        document.getElementById('timeline-cancel-btn').onclick = () => this.closeModal();
        document.getElementById('timeline-form').onsubmit = onSubmit;
        overlay.onclick = (e) => {
            if (e.target === overlay) this.closeModal();
        };

        document.getElementById('timeline-title').focus();
    }

    _collectFormData()
    {
        const pad = (v) => String(v).padStart(2, '0');
        const y   = document.getElementById('ts_y').value;
        const mo  = document.getElementById('ts_m').value;
        const d   = document.getElementById('ts_d').value;
        const hr  = document.getElementById('ts_hr').value;
        const min = document.getElementById('ts_min').value;

        const occurredAtUtc = `${y}-${pad(mo)}-${pad(d)}T${pad(hr)}:${pad(min)}`;

        return {
            title: document.getElementById('timeline-title').value.trim(),
            occurredAtUtc,
            description: document.getElementById('timeline-description').value.trim() || null
        };
    }

    async handleCreate(e)
    {
        e.preventDefault();

        const formData = this._collectFormData();
        if (!formData.title)
        {
            this.showError(await Localisation.translate("Title is required"));
            return;
        }
        if (!formData.occurredAtUtc || isNaN(new Date(formData.occurredAtUtc).getTime()))
        {
            this.showError(await Localisation.translate("A valid date and time is required"));
            return;
        }

        const submitBtn = document.getElementById('timeline-save-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = await Localisation.translate("Creating...");

        try
        {
            const response = await this.makeRequest('POST', this.basePath, formData);
            this.showSuccess(await Localisation.translate("Timeline entry created"));
            this.closeModal();

            if (this.onSuccess)
            {
                this.onSuccess(response);
            }
            else
            {
                setTimeout(() => window.location.reload(), 500);
            }
        }
        catch (error)
        {
            console.error('Failed to create timeline entry:', error);
            this.showError(`Failed to create entry: ${error.message}`);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    async handleEdit(e, entryId)
    {
        e.preventDefault();

        const formData = this._collectFormData();
        if (!formData.title)
        {
            this.showError('Title is required');
            return;
        }

        const submitBtn = document.getElementById('timeline-save-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try
        {
            const response = await this.makeRequest('PATCH', `${this.basePath}/${entryId}`, formData);
            this.showSuccess('Timeline entry updated');
            this.closeModal();

            if (this.onSuccess)
            {
                this.onSuccess(response);
            }
            else
            {
                setTimeout(() => window.location.reload(), 500);
            }
        }
        catch (error)
        {
            console.error('Failed to update timeline entry:', error);
            this.showError(`Failed to update entry: ${error.message}`);
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    async deleteEntry(entryId, confirmFirst = true)
    {
        if (confirmFirst && !confirm('Delete this timeline entry?'))
        {
            return false;
        }

        try
        {
            await this.makeRequest('DELETE', `${this.basePath}/${entryId}`);
            this.showSuccess('Timeline entry deleted');
            return true;
        }
        catch (error)
        {
            this.showError(`Failed to delete: ${error.message}`);
            return false;
        }
    }
}
