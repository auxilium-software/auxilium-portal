class MessageManager extends BaseManager {
    constructor(caseId) {
        super();
        this.caseId = caseId;
        this.basePath = `/api/v3/cases/${caseId}/messages`;
        this.messages = [];
        this.users = [];
        this.userCache = null;
        this.userCacheTime = null;
        this.CACHE_DURATION = 5 * 60 * 1000; // 5 minutes
    }

    showCreateModal()
    {
        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal';

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>New Message</h2>
                <p style="font-size: 0.9em; color: #666; margin-top: 0.5em;">Send a message related to this case</p>
            </div>
            
            <form id="message-form" class="ToDoCreatorModal-Body">
                <div>
                    <label>
                        Subject <span class="required">*</span>
                    </label>
                    <input type="text" id="message-subject" required placeholder="Message subject...">
                </div>

                <div>
                    <label>
                        Message <span class="required">*</span>
                    </label>
                    <textarea id="message-content" rows="6" required placeholder="Type your message..."></textarea>
                </div>

                <div>
                    <label>
                        <input type="checkbox" id="message-urgent" style="margin-right: 0.5em;">
                        Mark as urgent
                    </label>
                </div>

                <div class="todo-modal-buttons">
                    <button type="button" id="cancel-btn" class="button todo-cancel-btn">
                        Cancel
                    </button>
                    <button type="submit" id="send-btn" class="button todo-save-btn">
                        Send Message
                    </button>
                </div>
            </form>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        document.getElementById('cancel-btn').onclick = () => this.closeModal();
        document.getElementById('message-form').onsubmit = (e) => this.handleSubmit(e);
        overlay.onclick = (e) => {
            if (e.target === overlay) this.closeModal();
        };

        document.getElementById('message-subject').focus();
    }


    async handleSubmit(e)
    {
        e.preventDefault();

        const formData = {
            subject: document.getElementById('message-subject').value.trim(),
            content: document.getElementById('message-content').value.trim(),
            is_urgent: document.getElementById('message-urgent').checked
        };

        if (!formData.subject)
        {
            this.showError('Subject is required');
            return;
        }

        if (!formData.content)
        {
            this.showError('Message content is required');
            return;
        }

        const submitBtn = document.getElementById('send-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        try
        {
            const response = await this.makeRequest('POST', this.basePath, formData);

            this.showSuccess('Message sent successfully');
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
            console.error('Failed to send message:', error);
            this.showError(`Failed to send message: ${error.message}`);

            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
}
