class TodoManager extends BaseManager
{
    constructor(caseId)
    {
        super();
        this.caseId = caseId;
        this.basePath = `/api/v3/cases/${caseId}/todos`;
        this.users = [];
        this.selectedUser = null;
        this.userCache = null;
        this.userCacheTime = null;
        this.CACHE_DURATION = 5 * 60 * 1000;
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

    showCreateModal()
    {
        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal';

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>New Todo</h2>
            </div>
            
            <form id="todo-form" class="ToDoCreatorModal-Body">
                <div>
                    <label>
                        Title <span class="required">*</span>
                    </label>
                    <input type="text" id="todo-summary" required placeholder="What needs to be done?">
                </div>

                <div>
                    <label>Description</label>
                    <textarea id="todo-description" rows="3" placeholder="Additional details..."></textarea>
                </div>
                
                <div>
                    <label>Priority</label>
                    <select id="todo-priority">
                        <option value="LOW">🟢 Low</option>
                        <option value="MEDIUM" selected>🟡 Medium</option>
                        <option value="HIGH">🟠 High</option>
                        <option value="URGENT">🔴 Urgent</option>
                    </select>
                </div>

                <div>
                    <label>Due Date</label>
                    <input type="datetime-local" id="todo-due-date">
                </div>

                <div class="user-assignment-container">
                    <label>
                        Assign To 
                        <span class="assign-to-me-link" id="assign-to-me">
                            ${typeof Localisation !== 'undefined' ? Localisation.translate("Assign to me") : "Assign to me"}
                        </span>
                    </label>
                    <div>
                        <input type="text" id="todo-assigned-to" placeholder="Search for a user..." autocomplete="off">
                        <input type="hidden" id="todo-assigned-to-id">
                        <div id="user-dropdown" class="user-dropdown" style="display: none;">
                            <div class="loading-users">Loading users...</div>
                        </div>
                    </div>
                </div>

                <div>
                    <label>Reminder</label>
                    <input type="datetime-local" id="todo-reminder">
                </div>

                <div class="todo-modal-buttons">
                    <button type="button" id="todo-cancel-btn" class="button todo-cancel-btn">
                        Cancel
                    </button>
                    <button type="submit" id="todo-save-btn" class="button todo-save-btn">
                        Create Todo
                    </button>
                </div>
            </form>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(9, 0, 0, 0);
        document.getElementById('todo-due-date').value = this.formatDateTimeLocal(tomorrow);

        this.loadUsers().then(() => {
            this.setupUserSelection();
        });

        document.getElementById('todo-cancel-btn').onclick = () => this.closeModal();
        document.getElementById('todo-form').onsubmit = (e) => this.handleSubmit(e);
        overlay.onclick = (e) => {
            if (e.target === overlay) this.closeModal();
        };

        document.getElementById('todo-summary').focus();
    }

    setupUserSelection()
    {
        const input = document.getElementById('todo-assigned-to');
        const dropdown = document.getElementById('user-dropdown');
        const assignToMeLink = document.getElementById('assign-to-me');

        assignToMeLink.onclick = () => {
            const currentUser = this.getCurrentUser();
            if (currentUser) {
                this.selectUser(currentUser);
                input.value = `${currentUser.full_name} (${currentUser.email_address})`;
                dropdown.style.display = 'none';
            }
        };

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
            if (e.key === 'Escape')
            {
                dropdown.style.display = 'none';
            } else if (e.key === 'ArrowDown')
            {
                e.preventDefault();
                const firstItem = dropdown.querySelector('.user-dropdown-item');
                if (firstItem) firstItem.focus();
            }
            else if (input.value && (e.key === 'Backspace' || e.key === 'Delete'))
            {
                this.selectedUser = null;
                document.getElementById('todo-assigned-to-id').value = '';
            }
        };

        const originalClose = this.closeModal.bind(this);
        this.closeModal = () => {
            document.removeEventListener('click', closeDropdown);
            originalClose();
        };
    }

    filterUsers(searchTerm = '')
    {
        const dropdown = document.getElementById('user-dropdown');

        if (!this.users || this.users.length === 0)
        {
            dropdown.innerHTML = '<div class="loading-users">No users available</div>';
            return;
        }

        const filteredUsers = this.users.filter(user => {
            const search = searchTerm.toLowerCase();
            return user.full_name.toLowerCase().includes(search) || user.email_address.toLowerCase().includes(search);
        });

        if (filteredUsers.length === 0)
        {
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
                const user = this.users.find(u => u.id == userId);
                if (user) {
                    this.selectUser(user);
                    document.getElementById('todo-assigned-to').value = `${user.full_name} (${user.email_address})`;
                    dropdown.style.display = 'none';
                }
            };

            item.onclick = selectThisUser;
            item.onkeydown = (e) => {
                if (e.key === 'Enter')
                {
                    selectThisUser();
                }
                else if (e.key === 'ArrowDown' && index < items.length - 1)
                {
                    e.preventDefault();
                    items[index + 1].focus();
                }
                else if (e.key === 'ArrowUp')
                {
                    e.preventDefault();
                    if (index > 0)
                    {
                        items[index - 1].focus();
                    }
                    else
                    {
                        document.getElementById('todo-assigned-to').focus();
                    }
                }
            };
        });
    }

    selectUser(user)
    {
        this.selectedUser = user;
        document.getElementById('todo-assigned-to-id').value = user.id;
    }

    getCurrentUser()
    {
        if (window.currentUser) return window.currentUser;
        if (window.USER) return window.USER;
        return this.users.find(u => u.isCurrentUser || u.is_me) || this.users[0];
    }

    async handleSubmit(e)
    {
        e.preventDefault();

        const formData = {
            summary: document.getElementById('todo-summary').value.trim(),
            description: document.getElementById('todo-description').value.trim() || null,
            priority: document.getElementById('todo-priority').value,
            due_date: document.getElementById('todo-due-date').value || null,
            assigned_to: document.getElementById('todo-assigned-to-id').value || null,
            reminder: document.getElementById('todo-reminder').value || null
        };

        if (!formData.summary)
        {
            this.showError('Title is required');
            return;
        }

        const submitBtn = document.getElementById('todo-save-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';

        try
        {
            const response = await this.makeRequest('POST', this.basePath, formData);

            this.showSuccess('Todo created successfully');
            this.closeModal();

            if (this.onSuccess)
            {
                this.onSuccess(response);
            }
            else
            {
                setTimeout(() => window.location.reload(), 500);
            }

        } catch (error) {
            console.error('Failed to create todo:', error);
            this.showError(`Failed to create todo: ${error.message}`);

            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    async updateStatus(todoId, newStatus)
    {
        try
        {
            const response = await this.makeRequest('PATCH', `${this.basePath}/${todoId}`, { status: newStatus });
            this.showSuccess('Todo updated');
            return response;
        }
        catch (error)
        {
            this.showError(`Failed to update: ${error.message}`);
            throw error;
        }
    }

    async deleteTodo(todoId, confirmFirst = true)
    {
        if (confirmFirst && !confirm('Delete this todo?'))
        {
            return false;
        }

        try
        {
            await this.makeRequest('DELETE', `${this.basePath}/${todoId}`);
            this.showSuccess('Todo deleted');
            return true;
        }
        catch (error)
        {
            this.showError(`Failed to delete: ${error.message}`);
            return false;
        }
    }
}
