class CaseManager extends BaseManager {
    constructor(caseId) {
        super();
        this.caseId = caseId;
        this.basePath = `/api/v3/cases/${caseId}`;
        this.users = [];
        this.selectedUser = null;
        this.userCache = null;
        this.userCacheTime = null;
        this.CACHE_DURATION = 5 * 60 * 1000; // 5 minutes
    }

    async loadUsers(forceRefresh = false)
    {
        if (!forceRefresh && this.userCache && this.userCacheTime)
        {
            const cacheAge = Date.now() - this.userCacheTime;
            if (cacheAge < this.CACHE_DURATION)
            {
                this.users = this.userCache;
                return this.users;
            }
        }

        try
        {
            console.log('Loading users...');
            const userController = new UserController();
            this.users = await userController.GetAllUsers_DontDoThisLol();

            this.userCache = this.users;
            this.userCacheTime = Date.now();

            console.log('Users loaded:', this.users.length);

            if (document.getElementById('user-dropdown'))
            {
                this.filterUsers('');
            }

            return this.users;
        }
        catch (error)
        {
            console.error('Failed to load users:', error);
            this.users = [];
            return [];
        }
    }

    showAddClientModal()
    {
        this.showAddPersonModal('client', 'Add Client', 'Select a client to add to this case');
    }

    showAddWorkerModal()
    {
        this.showAddPersonModal('worker', 'Add Case Worker', 'Select a case worker to add to this case');
    }

    showAddPersonModal(type, title, description)
    {
        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal';

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>${title}</h2>
                <p style="font-size: 0.9em; color: #666; margin-top: 0.5em;">${description}</p>
            </div>
            
            <form id="add-person-form" class="ToDoCreatorModal-Body">
                <div class="user-assignment-container">
                    <label>
                        Search User <span class="required">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="text" 
                               id="person-search" 
                               placeholder="Type to search by name or email..." 
                               autocomplete="off"
                               required>
                        <input type="hidden" id="selected-person-id">
                        <div id="user-dropdown" class="user-dropdown" style="display: none;">
                            <div class="loading-users">Loading users...</div>
                        </div>
                    </div>
                </div>

                <div id="selected-user-preview" style="display: none; margin-top: 1em; padding: 1em; background: #f5f5f5; border-radius: 4px;">
                    <strong>Selected:</strong>
                    <div style="display: flex; align-items: center; margin-top: 0.5em;">
                        <div class="user-avatar" id="preview-avatar"></div>
                        <div style="margin-left: 0.75em;">
                            <div id="preview-name" style="font-weight: 500;"></div>
                            <div id="preview-email" style="font-size: 0.85em; color: #666;"></div>
                        </div>
                    </div>
                </div>

                <div class="todo-modal-buttons">
                    <button type="button" id="cancel-btn" class="button todo-cancel-btn">
                        Cancel
                    </button>
                    <button type="submit" id="add-btn" class="button todo-save-btn">
                        Add ${type === 'client' ? 'Client' : 'Case Worker'}
                    </button>
                </div>
            </form>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        this.loadUsers().then(() => {
            this.setupUserSelection();
        });

        document.getElementById('cancel-btn').onclick = () => this.closeModal();
        document.getElementById('add-person-form').onsubmit = (e) => this.handleAddPerson(e, type);
        overlay.onclick = (e) => {
            if (e.target === overlay) this.closeModal();
        };

        document.getElementById('person-search').focus();
    }

    setupUserSelection() {
        const input = document.getElementById('person-search');
        const dropdown = document.getElementById('user-dropdown');

        input.onfocus = () => {
            this.filterUsers(input.value);
            dropdown.style.display = 'block';
        };

        input.oninput = () => {
            this.filterUsers(input.value);
            dropdown.style.display = 'block';
        };

        const closeDropdown = (e) => {
            if (!e.target.closest('.user-assignment-container')) {
                dropdown.style.display = 'none';
            }
        };
        document.addEventListener('click', closeDropdown);

        input.onkeydown = (e) => {
            if (e.key === 'Escape') {
                dropdown.style.display = 'none';
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                const firstItem = dropdown.querySelector('.user-dropdown-item');
                if (firstItem) firstItem.focus();
            } else if (input.value && (e.key === 'Backspace' || e.key === 'Delete')) {
                this.selectedUser = null;
                document.getElementById('selected-person-id').value = '';
                document.getElementById('selected-user-preview').style.display = 'none';
            }
        };

        // Cleanup listener on modal close
        const originalClose = this.closeModal.bind(this);
        this.closeModal = () => {
            document.removeEventListener('click', closeDropdown);
            originalClose();
        };
    }

    filterUsers(searchTerm = '') {
        const dropdown = document.getElementById('user-dropdown');

        if (!this.users || this.users.length === 0) {
            dropdown.innerHTML = '<div class="loading-users">No users available</div>';
            return;
        }

        const filteredUsers = this.users.filter(user => {
            const search = searchTerm.toLowerCase();
            return user.full_name.toLowerCase().includes(search) || user.email_address.toLowerCase().includes(search);
        });

        if (filteredUsers.length === 0) {
            dropdown.innerHTML = '<div class="no-users-found">No users found</div>';
            return;
        }

        dropdown.innerHTML = filteredUsers.map(user => `
            <div class="user-dropdown-item" data-user-id="${user.id}" tabindex="0">
                <div class="user-avatar">${this.getInitials(user.full_name)}</div>
                <div class="user-info">
                    <div class="user-name">${user.full_name}</div>
                    <div class="user-email">${user.email_address}</div>
                </div>
            </div>
        `).join('');

        dropdown.querySelectorAll('.user-dropdown-item').forEach((item, index, items) => {
            const selectThisUser = () => {
                const userId = item.dataset.userId;
                const user = this.users.find(u => u.id === userId);
                if (user) {
                    this.selectUser(user);
                    document.getElementById('person-search').value = user.full_name;
                    dropdown.style.display = 'none';
                    this.showUserPreview(user);
                }
            };

            item.onclick = selectThisUser;
            item.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    selectThisUser();
                } else if (e.key === 'ArrowDown' && index < items.length - 1) {
                    e.preventDefault();
                    items[index + 1].focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (index > 0) {
                        items[index - 1].focus();
                    } else {
                        document.getElementById('person-search').focus();
                    }
                }
            };
        });
    }

    selectUser(user) {
        this.selectedUser = user;
        document.getElementById('selected-person-id').value = user.id;
    }

    showUserPreview(user) {
        const preview = document.getElementById('selected-user-preview');
        document.getElementById('preview-avatar').textContent = this.getInitials(user.full_name);
        document.getElementById('preview-name').textContent = user.full_name;
        document.getElementById('preview-email').textContent = user.email_address;
        preview.style.display = 'block';
    }

    async handleAddPerson(e, type) {
        e.preventDefault();

        const personId = document.getElementById('selected-person-id').value;

        if (!personId) {
            this.showError('Please select a user');
            return;
        }

        const submitBtn = document.getElementById('add-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        try {
            const endpoint = type === 'client' ? `${this.basePath}/clients` : `${this.basePath}/workers`;

            const response = await this.makeRequest('POST', endpoint, { user_id: personId });

            const personType = type === 'client' ? 'Client' : 'Case worker';
            this.showSuccess(`${personType} added successfully`);
            this.closeModal();

            if (this.onSuccess) {
                this.onSuccess(response);
            } else {
                setTimeout(() => window.location.reload(), 500);
            }

        } catch (error) {
            console.error(`Failed to add ${type}:`, error);
            const personType = type === 'client' ? 'client' : 'case worker';
            this.showError(`Failed to add ${personType}: ${error.message}`);

            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    async removeClient(clientId, confirmFirst = true) {
        return this.removePerson(clientId, 'client', confirmFirst);
    }

    async removeWorker(workerId, confirmFirst = true) {
        return this.removePerson(workerId, 'worker', confirmFirst);
    }

    async removePerson(personId, type, confirmFirst = true) {
        const personType = type === 'client' ? 'client' : 'case worker';

        if (confirmFirst && !confirm(`Remove this ${personType} from the case?`)) {
            return false;
        }

        try {
            const endpoint = type === 'client' ? `${this.basePath}/clients/${personId}` : `${this.basePath}/workers/${personId}`;

            await this.makeRequest('DELETE', endpoint);

            const personTypeCapitalized = type === 'client' ? 'Client' : 'Case worker';
            this.showSuccess(`${personTypeCapitalized} removed`);
            return true;

        } catch (error) {
            this.showError(`Failed to remove ${personType}: ${error.message}`);
            return false;
        }
    }


    async deleteCase(confirmFirst = true)
    {
        this.showError(`Not yet implemented - speak to the team about this one`);
    }

    async requestCloseCase(confirmFirst = true)
    {
        this.showError(`Not yet implemented - speak to the team about this one`);
    }
}
