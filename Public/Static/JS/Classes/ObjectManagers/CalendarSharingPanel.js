class CalendarSharingPanel extends BaseManager
{
    constructor(calendarManager)
    {
        super();
        this.calendarManager = calendarManager;
        this.userController = new UserController();

        this.selectedEventServerId = null;
        this._searchDebounceTimer = null;
        this._isOpen = false;
    }

    refresh()
    {
        this._updateBadge();
        if (this._isOpen)
        {
            this._renderMyInvites();
            this._renderOwnedEventOptions();
        }
    }

    _updateBadge()
    {
        const badge = document.getElementById('cal-pending-invite-badge');
        if (!badge) return;
        const count = this.calendarManager.lastLoadedEvents.filter(
            (e) => e.customTags && e.customTags.myInviteStatus === 'pending'
        ).length;
        badge.textContent = String(count);
        badge.hidden = count === 0;
    }

    async open()
    {
        await this._buildModal();
        this._isOpen = true;
        this._renderMyInvites();
        this._renderOwnedEventOptions();
    }

    closeModal()
    {
        this._isOpen = false;
        super.closeModal();
    }

    async _buildModal()
    {
        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal cal-share-modal-inner';

        const [
            heading, myInvitesLabel, shareLabel, hint,
            selectPlaceholder, inviteSomeoneLabel, searchPlaceholder, closeLabel,
        ] = await Promise.all([
            Localisation.translate('Sharing'),
            Localisation.translate('My invites'),
            Localisation.translate('Share an event'),
            Localisation.translate("Events other people have shared with you. Drawn from whatever's currently loaded in the calendar view - an invite to something further out won't show here until you navigate near that date."),
            Localisation.translate('Select one of your events…'),
            Localisation.translate('Invite someone'),
            Localisation.translate('Search by name or email…'),
            Localisation.translate('Close'),
        ]);

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>${this.escapeHtml(heading)}</h2>
            </div>

            <div class="ToDoCreatorModal-Body">
                <div class="cal-share-tabs" role="tablist">
                    <button type="button" class="cal-share-tab" data-share-tab="invites" role="tab" aria-selected="true">${this.escapeHtml(myInvitesLabel)}</button>
                    <button type="button" class="cal-share-tab" data-share-tab="share" role="tab" aria-selected="false">${this.escapeHtml(shareLabel)}</button>
                </div>

                <div class="cal-share-panel" data-share-panel="invites">
                    <p class="cal-share-hint">${this.escapeHtml(hint)}</p>
                    <ul class="cal-share-invite-list" id="cal-my-invites-list"></ul>
                </div>

                <div class="cal-share-panel" data-share-panel="share" hidden>
                    <div class="cal-field">
                        <label for="cal-share-event-select">${this.escapeHtml(shareLabel)}</label>
                        <select id="cal-share-event-select">
                            <option value="">${this.escapeHtml(selectPlaceholder)}</option>
                        </select>
                    </div>

                    <div class="cal-field" id="cal-share-invite-section" hidden>
                        <label for="cal-share-user-search">${this.escapeHtml(inviteSomeoneLabel)}</label>
                        <input type="text" id="cal-share-user-search" placeholder="${this.escapeHtml(searchPlaceholder)}" autocomplete="off">
                        <ul class="cal-share-search-results" id="cal-share-search-results" hidden></ul>
                    </div>

                    <ul class="cal-share-attendees" id="cal-share-attendees"></ul>
                </div>
            </div>

            <div class="todo-modal-buttons">
                <button type="button" id="cal-share-close-btn" class="button todo-cancel-btn">${this.escapeHtml(closeLabel)}</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        this.tabs = modal.querySelectorAll('[data-share-tab]');
        this.panels = modal.querySelectorAll('[data-share-panel]');
        this.invitesList = modal.querySelector('#cal-my-invites-list');
        this.eventSelect = modal.querySelector('#cal-share-event-select');
        this.inviteSection = modal.querySelector('#cal-share-invite-section');
        this.userSearchInput = modal.querySelector('#cal-share-user-search');
        this.searchResultsEl = modal.querySelector('#cal-share-search-results');
        this.attendeesEl = modal.querySelector('#cal-share-attendees');

        this.tabs.forEach((tab) => tab.addEventListener('click', () => this._switchTab(tab.dataset.shareTab)));
        this.eventSelect.addEventListener('change', () => this._onEventSelected());
        this.userSearchInput.addEventListener('input', () => this._onUserSearchInput());
        modal.querySelector('#cal-share-close-btn').onclick = () => this.closeModal();
        overlay.onclick = (e) => { if (e.target === overlay) this.closeModal(); };

        const escHandler = (e) => {
            if (e.key === 'Escape')
            {
                this.closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    }

    _switchTab(name)
    {
        this.tabs.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.shareTab === name)));
        this.panels.forEach((panel) => { panel.hidden = panel.dataset.sharePanel !== name; });
    }


    async _renderMyInvites()
    {
        const pending = this.calendarManager.lastLoadedEvents.filter(
            (e) => e.customTags && e.customTags.myInviteStatus && e.customTags.myInviteStatus !== 'owner'
        );

        if (pending.length === 0)
        {
            this.invitesList.innerHTML = `<li class="cal-share-empty">${this.escapeHtml(await Localisation.translate('No pending invites right now.'))}</li>`;
            return;
        }

        const [acceptLabel, declineLabel] = await Promise.all([
            Localisation.translate('Accept'),
            Localisation.translate('Decline'),
        ]);

        this.invitesList.innerHTML = pending.map((e) => {
            const status = e.customTags.myInviteStatus;
            const dateLabel = e.from.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
            const actions = status === 'pending'
                ? `<div class="cal-share-invite-item__actions">
                       <button type="button" class="button cal-share-mini-btn cal-share-mini-btn--accept" data-respond="accepted">${this.escapeHtml(acceptLabel)}</button>
                       <button type="button" class="button dangerous-button cal-share-mini-btn cal-share-mini-btn--decline" data-respond="declined">${this.escapeHtml(declineLabel)}</button>
                   </div>`
                : '';
            return `
                <li class="cal-share-invite-item" data-event-id="${this.escapeHtml(e.customTags.serverId)}">
                    <div class="cal-share-invite-item__body">
                        <div class="cal-share-invite-item__title">${this.escapeHtml(e.title)}</div>
                        <div class="cal-share-invite-item__meta">${this.escapeHtml(dateLabel)}</div>
                    </div>
                    <span class="cal-share-status cal-share-status--${this.escapeHtml(status)}">${this.escapeHtml(status)}</span>
                    ${actions}
                </li>`;
        }).join('');

        this.invitesList.querySelectorAll('[data-respond]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const li = btn.closest('[data-event-id]');
                this._respondToInvite(li.dataset.eventId, btn.dataset.respond);
            });
        });
    }

    async _respondToInvite(eventServerId, status)
    {
        try
        {
            await this.makeRequest('PATCH', `${this.calendarManager.basePath}/${encodeURIComponent(eventServerId)}/invites/me`, { status });
            this.showSuccess(await Localisation.translate('Response saved'));
            this.calendarManager.refetchRange(this.calendarManager.loadedRange.start, this.calendarManager.loadedRange.end);
        }
        catch (error)
        {
            this.showError(`${await Localisation.translate("Couldn't respond to that invite")}: ${error.message}`);
        }
    }


    _renderOwnedEventOptions()
    {
        const owned = this.calendarManager.lastLoadedEvents.filter(
            (e) => e.customTags && e.customTags.isOwner && e.customTags.isShareable
        );
        const previousValue = this.eventSelect.value;
        const firstOptionText = this.eventSelect.options[0] ? this.eventSelect.options[0].textContent : '';

        this.eventSelect.innerHTML = `<option value="">${this.escapeHtml(firstOptionText)}</option>` +
            owned.map((e) => {
                const dateLabel = e.from.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
                return `<option value="${this.escapeHtml(e.customTags.serverId)}">${this.escapeHtml(e.title)} - ${this.escapeHtml(dateLabel)}</option>`;
            }).join('');

        if (previousValue && owned.some((e) => e.customTags.serverId === previousValue))
        {
            this.eventSelect.value = previousValue;
            this._onEventSelected();
        }
        else
        {
            this.selectedEventServerId = null;
            this.inviteSection.hidden = true;
            this.attendeesEl.innerHTML = '';
        }
    }

    _onEventSelected()
    {
        this.selectedEventServerId = this.eventSelect.value || null;
        this.inviteSection.hidden = !this.selectedEventServerId;
        this._renderAttendees();
    }

    async _renderAttendees()
    {
        this.attendeesEl.innerHTML = '';
        if (!this.selectedEventServerId) return;

        const event = this.calendarManager.lastLoadedEvents.find(
            (e) => e.customTags && e.customTags.serverId === this.selectedEventServerId
        );
        const invites = (event && event.customTags.invites) || [];

        if (invites.length === 0)
        {
            this.attendeesEl.innerHTML = `<li class="cal-share-empty">${this.escapeHtml(await Localisation.translate('Nobody invited yet.'))}</li>`;
            return;
        }

        this.attendeesEl.innerHTML = invites.map((inv) => `
            <li class="cal-share-attendee" data-user-id="${this.escapeHtml(inv.userId)}">
                <span class="cal-share-attendee__avatar">${this.escapeHtml(this.getInitials(inv.userName))}</span>
                <div class="cal-share-attendee__body">
                    <div class="cal-share-attendee__name">${this.escapeHtml(inv.userName || inv.userId)}</div>
                </div>
                <span class="cal-share-status cal-share-status--${this.escapeHtml(inv.status)}">${this.escapeHtml(inv.status)}</span>
                <button type="button" class="cal-share-mini-btn cal-share-mini-btn--remove" data-remove-invite aria-label="Remove">&times;</button>
            </li>`).join('');

        this.attendeesEl.querySelectorAll('[data-remove-invite]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const li = btn.closest('[data-user-id]');
                this._removeInvite(li.dataset.userId);
            });
        });
    }

    _onUserSearchInput()
    {
        clearTimeout(this._searchDebounceTimer);
        const query = this.userSearchInput.value.trim();
        if (query.length < 2)
        {
            this.searchResultsEl.hidden = true;
            this.searchResultsEl.innerHTML = '';
            return;
        }
        this._searchDebounceTimer = setTimeout(() => this._searchUsers(query), 250);
    }

    async _searchUsers(query)
    {
        try
        {
            // Existing shared component - APIControllers/UserController.js
            const data = await this.userController.GetUsersPage(1, 10, 'fullName', 'asc', query);
            if (data === false) throw new Error('Search failed');

            const alreadyInvited = new Set(
                Array.from(this.attendeesEl.querySelectorAll('[data-user-id]')).map((li) => li.dataset.userId)
            );
            const users = (data.users || []).filter(
                (u) => u.id !== this.calendarManager.currentUserId && !alreadyInvited.has(u.id)
            );

            if (users.length === 0)
            {
                this.searchResultsEl.innerHTML = `<li class="cal-share-empty">${this.escapeHtml(await Localisation.translate('No matches'))}</li>`;
            }
            else
            {
                this.searchResultsEl.innerHTML = users.map((u) => `
                    <li class="cal-share-search-result" data-user-id="${this.escapeHtml(u.id)}">
                        ${this.escapeHtml(u.fullName || u.emailAddress || 'Unknown')}
                        ${u.fullName ? `<div class="cal-share-search-result__email">${this.escapeHtml(u.emailAddress || '')}</div>` : ''}
                    </li>`).join('');
                this.searchResultsEl.querySelectorAll('[data-user-id]').forEach((li) => {
                    li.addEventListener('click', () => {
                        this._inviteUser(li.dataset.userId);
                        this.searchResultsEl.hidden = true;
                        this.userSearchInput.value = '';
                    });
                });
            }
            this.searchResultsEl.hidden = false;
        }
        catch (error)
        {
            console.error('SharingPanel: user search failed', error);
        }
    }

    async _inviteUser(userId)
    {
        if (!this.selectedEventServerId) return;
        try
        {
            await this.makeRequest('POST', `${this.calendarManager.basePath}/${encodeURIComponent(this.selectedEventServerId)}/invites`, { userIds: [userId] });
            this.showSuccess(await Localisation.translate('Invite sent'));
            this.calendarManager.refetchRange(this.calendarManager.loadedRange.start, this.calendarManager.loadedRange.end);
        }
        catch (error)
        {
            this.showError(`${await Localisation.translate("Couldn't invite that person")}: ${error.message}`);
        }
    }

    async _removeInvite(userId)
    {
        if (!this.selectedEventServerId) return;
        try
        {
            await this.makeRequest('DELETE', `${this.calendarManager.basePath}/${encodeURIComponent(this.selectedEventServerId)}/invites/${encodeURIComponent(userId)}`);
            this.showSuccess(await Localisation.translate('Invite removed'));
            this.calendarManager.refetchRange(this.calendarManager.loadedRange.start, this.calendarManager.loadedRange.end);
        }
        catch (error)
        {
            this.showError(`${await Localisation.translate("Couldn't remove that invite")}: ${error.message}`);
        }
    }
}
