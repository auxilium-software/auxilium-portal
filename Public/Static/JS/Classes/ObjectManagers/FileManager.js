class FileManager extends BaseManager
{
    constructor(documentType, documentID) {
        super();
        this.documentType = documentType;
        this.documentID = documentID;

        if (this.documentType === "CASE")
        {
            this.basePath = `/api/v3/cases/${this.documentID}/files`;
        }
        else if (this.documentType === "USER")
        {
            this.basePath = `/api/v3/users/${this.documentID}/files`;
        }
        else
        {
            console.error("Invalid document type");
            throw new Error("Invalid document type. Must be 'CASE' or 'USER'");
        }

        this.files = [];
        this.selectedFile = null;
        this.allowedExtensions = null; // null == allow all
        this.maxFileSizeMB = 50;
    }

    async loadFiles(forceRefresh = false)
    {
        try
        {
            console.log('Loading files...');
            const files = await this.makeRequest('GET', this.basePath);
            this.files = Array.isArray(files) ? files : [];
            console.log('Files loaded:', this.files.length);
            return this.files;
        }
        catch (error)
        {
            console.error('Failed to load files:', error);
            this.files = [];
            return [];
        }
    }

    showUploadModal(config = {})
    {
        const {
            title = 'Upload File',
            description = 'Select a file to upload',
            allowedExtensions = null,
            maxFileSizeMB = this.maxFileSizeMB,
            showDescription = true
        } = config;

        this.allowedExtensions = allowedExtensions;
        this.maxFileSizeMB = maxFileSizeMB;

        const overlay = document.createElement('div');
        overlay.className = 'ToDoCreatorModal-Overlay';

        const modal = document.createElement('div');
        modal.className = 'ToDoCreatorModal';

        const acceptAttr = allowedExtensions ? `accept="${allowedExtensions.join(',')}"` : '';
        const extensionHint = allowedExtensions
            ? `<small style="color: #666;">Allowed: ${allowedExtensions.join(', ')}</small>`
            : '';

        modal.innerHTML = `
            <div class="ToDoCreatorModal-Header">
                <h2>${title}</h2>
                ${description ? `<p style="font-size: 0.9em; color: #666; margin-top: 0.5em;">${description}</p>` : ''}
            </div>
            
            <form id="file-upload-form" class="ToDoCreatorModal-Body">
                <div>
                    <label>
                        Select File <span class="required">*</span>
                    </label>
                    <input type="file" 
                           id="file-input" 
                           required
                           ${acceptAttr}>
                    ${extensionHint}
                    <small style="color: #666; display: block; margin-top: 0.25em;">
                        Max size: ${maxFileSizeMB}MB
                    </small>
                </div>

                ${showDescription ? `
                <div>
                    <label>Description (optional)</label>
                    <textarea id="file-description" 
                              rows="3" 
                              placeholder="Add a description for this file..."></textarea>
                </div>
                ` : ''}

                <div id="file-preview" style="display: none; margin-top: 1em; padding: 1em; background: #f5f5f5; border-radius: 4px;">
                    <strong>Selected file:</strong>
                    <div style="margin-top: 0.5em;">
                        <div id="preview-filename" style="font-weight: 500;"></div>
                        <div id="preview-filesize" style="font-size: 0.85em; color: #666;"></div>
                    </div>
                </div>

                <div class="todo-modal-buttons">
                    <button type="button" id="cancel-btn" class="button todo-cancel-btn">
                        Cancel
                    </button>
                    <button type="submit" id="upload-btn" class="button todo-save-btn">
                        Upload File
                    </button>
                </div>
            </form>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        this.modalElement = overlay;

        const fileInput = document.getElementById('file-input');
        fileInput.onchange = () => this.handleFileSelection();

        document.getElementById('cancel-btn').onclick = () => this.closeModal();
        document.getElementById('file-upload-form').onsubmit = (e) => this.handleAddFile(e);
        overlay.onclick = (e) => {
            if (e.target === overlay) this.closeModal();
        };

        fileInput.focus();
    }

    handleFileSelection()
    {
        const fileInput = document.getElementById('file-input');
        const file = fileInput.files[0];

        if (!file) {
            document.getElementById('file-preview').style.display = 'none';
            return;
        }

        const fileSizeMB = file.size / (1024 * 1024);
        if (fileSizeMB > this.maxFileSizeMB)
        {
            this.showError(`File size exceeds ${this.maxFileSizeMB}MB limit`);
            fileInput.value = '';
            return;
        }

        if (this.allowedExtensions)
        {
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            if (!this.allowedExtensions.includes(fileExtension))
            {
                this.showError(`File type not allowed. Allowed types: ${this.allowedExtensions.join(', ')}`);
                fileInput.value = '';
                return;
            }
        }

        // show preview
        this.selectedFile = file;
        document.getElementById('preview-filename').textContent = file.name;
        document.getElementById('preview-filesize').textContent = this.formatFileSize(file.size);
        document.getElementById('file-preview').style.display = 'block';
    }

    formatFileSize(bytes)
    {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    async handleAddFile(e)
    {
        e.preventDefault();

        const fileInput = document.getElementById('file-input');
        const file = fileInput.files[0];

        if (!file)
        {
            this.showError('Please select a file');
            return;
        }

        const descriptionElement = document.getElementById('file-description');
        const description = descriptionElement ? descriptionElement.value.trim() : '';

        const submitBtn = document.getElementById('upload-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Uploading...';

        try
        {
            const [statusCode, response] = await new APIInteractions().API_FILE_UPLOAD(
                this.basePath,
                file,
                description,
                true
            )

            if (statusCode !== 201)
            {
                throw new Error(response);
            }

            this.showSuccess('File uploaded successfully');
            this.closeModal();

            if (this.onSuccess)
            {
                this.onSuccess(response);
            }
            else
            {
                window.location.reload();
            }

        }
        catch (error)
        {
            console.error('Failed to upload file:', error);
            this.showError(`Failed to upload file: ${error.message}`);

            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }

    async removeFile(auxLFSURL, confirmFirst = true)
    {
        if (confirmFirst && !confirm('Delete this file? This action cannot be undone.'))
        {
            return false;
        }

        try
        {
            const fileID = (new FileController()).ExtractFileIDFromAuxLFSURL(auxLFSURL);

            await this.makeRequest('DELETE', `/files/${fileID}`);
            this.showSuccess('File deleted');

            // remove from local cache
            this.files = this.files.filter(f => f.id !== fileID);

            return true;
        }
        catch (error)
        {
            this.showError(`Failed to delete file: ${error.message}`);
            return false;
        }
    }

    async downloadFile(auxLFSURL, fileName)
    {
        try
        {
            const fileID = (new FileController()).ExtractFileIDFromAuxLFSURL(auxLFSURL);

            const downloadUrl = `/files/${fileID}/download`;

            // create temp link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = fileName || 'download';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            this.showSuccess('Download started');
            return true;
        }
        catch (error)
        {
            this.showError(`Failed to download file: ${error.message}`);
            return false;
        }
    }

    async updateFileDescription(auxLFSURL, newDescription)
    {
        try
        {
            const fileID = (new FileController()).ExtractFileIDFromAuxLFSURL(auxLFSURL);

            const response = await this.makeRequest('PATCH', `/files/${fileID}`, {
                description: newDescription
            });

            this.showSuccess('File description updated');

            // update local cache
            const file = this.files.find(f => f.id === fileID);
            if (file)
            {
                file.description = newDescription;
            }

            return response;
        } catch (error) {
            this.showError(`Failed to update description: ${error.message}`);
            throw error;
        }
    }
}
