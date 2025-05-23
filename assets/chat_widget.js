class ChatWidget {
    constructor(threadId, container, stickers = []) {
        console.log('ChatWidget constructor called for thread ID:', threadId);
        this.threadId = threadId;
        this.container = container;
        this.stickers = stickers;
        this.attachments = [];
        this.me = window.me;
        this.isArchived = window.location.pathname.includes('messages_archive.php');
        this.loadedMessageIds = new Set();
        this.lastLoadTime = 0;
        this.loadTimeout = null;
        this.isSending = false;
        this.pollingInterval = null;
        this.heartbeatTimeout = null;
        this.observer = null;
        
        // Clean up any existing chat widget
        if (window.currentChatWidget) {
            window.currentChatWidget.cleanup();
        }
        window.currentChatWidget = this;
        
        this.setupUI();
        this.loadMessages();
        this.setupPolling();
        this.heartbeat();
    }

    cleanup() {
        // Clear all intervals and timeouts
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        if (this.heartbeatTimeout) {
            clearTimeout(this.heartbeatTimeout);
        }
        if (this.loadTimeout) {
            clearTimeout(this.loadTimeout);
        }
        if (this.observer) {
            this.observer.disconnect();
        }
        
        // Remove event listeners
        if (this.textarea) {
            this.textarea.removeEventListener('keydown', this.handleKeydown);
            this.textarea.removeEventListener('focus', this.handleFocus);
        }
        if (this.btnSend) {
            this.btnSend.removeEventListener('click', this.handleSend);
        }
        if (this.btnAttach) {
            this.btnAttach.removeEventListener('click', this.handleAttachment);
        }
        if (this.btnEmoji) {
            this.btnEmoji.removeEventListener('click', this.handleEmoji);
        }
        
        // Clear the container
        this.container.innerHTML = '';
    }

    setupUI() {
        this.container.innerHTML = `<div class="chat-messages"></div>`;
        this.messagesDiv = this.container.querySelector('.chat-messages');
        
        this.textarea = document.querySelector('.chat-input');
        this.btnSend = document.querySelector('#btnSend');
        this.btnEmoji = document.querySelector('#btnEmoji');
        this.btnAttach = document.querySelector('#btnAttach');

        // Bind event handlers
        this.handleKeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        };
        
        this.handleSend = () => this.sendMessage();
        this.handleAttachment = () => {
            // Prevent multiple file inputs
            if (document.querySelector('.file-preview-container')) {
                return;
            }
            
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.accept = '*/*';
            
            input.onchange = (e) => {
                const files = Array.from(e.target.files);
                this.attachments = [...this.attachments, ...files];
                this.updateAttachmentPreview();
            };
            
            input.click();
        };
        this.handleEmoji = () => this.toggleStickerPicker();
        this.handleFocus = () => this.clearNotificationCount();

        // Add event listeners
        this.textarea.addEventListener('keydown', this.handleKeydown);
        this.btnSend.addEventListener('click', this.handleSend);
        this.btnAttach.addEventListener('click', this.handleAttachment);
        this.btnEmoji.addEventListener('click', this.handleEmoji);
        this.textarea.addEventListener('focus', this.handleFocus);

        this.setupReadObserver();
    }

    clearNotificationCount() {
        // Find and clear the notification badge for this thread
        const threadItem = document.querySelector(`.thread-item[data-thread-id="${this.threadId}"]`);
        if (threadItem) {
            const badge = threadItem.querySelector('.notification-badge');
            if (badge) {
                badge.classList.remove('show');
                badge.textContent = '';
            }
        }
    }

    handleAttachment() {
        // Prevent multiple file inputs
        if (document.querySelector('.file-preview-container')) {
            return;
        }
        
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = '*/*';
        
        input.onchange = (e) => {
            const files = Array.from(e.target.files);
            this.attachments = [...this.attachments, ...files];
            this.updateAttachmentPreview();
        };
        
        input.click();
    }

    updateAttachmentPreview() {
        // Remove existing preview container if any
        const existingPreview = document.querySelector('.file-preview-container');
        if (existingPreview) {
            existingPreview.remove();
        }

        if (this.attachments.length > 0) {
            const previewContainer = document.createElement('div');
            previewContainer.className = 'file-preview-container';
            
            this.attachments.forEach((file, index) => {
                const preview = document.createElement('div');
                preview.className = 'file-preview';
                
                // Create preview content based on file type
                const fileType = file.type.split('/')[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                if (fileType === 'image') {
                    // Image preview
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="${file.name}" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                            <div class="file-info">
                                <div>${file.name}</div>
                                <div>${this.formatFileSize(file.size)}</div>
                            </div>
                            <button class="remove-file" onclick="this.closest('.file-preview').remove(); this.attachments.splice(${index}, 1);">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Document or other file preview
                    const icon = this.getFileIcon(fileExt);
                    preview.innerHTML = `
                        <i class="${icon} fa-2x"></i>
                        <div class="file-info">
                            <div>${file.name}</div>
                            <div>${this.formatFileSize(file.size)}</div>
                        </div>
                        <button class="remove-file" onclick="this.closest('.file-preview').remove(); this.attachments.splice(${index}, 1);">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                }
                
                previewContainer.appendChild(preview);
            });

            // Insert the preview container before the chat input container
            const chatForm = document.querySelector('.chat-form');
            const chatInputContainer = document.querySelector('.chat-input-container');
            chatForm.insertBefore(previewContainer, chatInputContainer);
        }
    }

    getFileIcon(ext) {
        const iconMap = {
            // Documents
            'pdf': 'fas fa-file-pdf',
            'doc': 'fas fa-file-word',
            'docx': 'fas fa-file-word',
            'xls': 'fas fa-file-excel',
            'xlsx': 'fas fa-file-excel',
            'ppt': 'fas fa-file-powerpoint',
            'pptx': 'fas fa-file-powerpoint',
            'txt': 'fas fa-file-alt',
            'csv': 'fas fa-file-csv',
            // Archives
            'zip': 'fas fa-file-archive',
            'rar': 'fas fa-file-archive',
            '7z': 'fas fa-file-archive',
            // Default
            'default': 'fas fa-file'
        };
        
        return iconMap[ext] || iconMap.default;
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    renderMessage(msg, isLastFromSender = false) {
        console.log('Rendering message:', msg);
        const isSent = msg.sender_id === window.me;
        const messageClass = isSent ? 'sent msg-me' : 'received msg-you';

        const div = document.createElement('div');
        div.className = `message ${messageClass}`;
        div.dataset.messageId = msg.id;
        div.dataset.me = (msg.sender_id === window.me ? '1' : '0');
        
        // Create message content container
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';

        // Add message text or sticker
        if (msg.content) {
            const textDiv = document.createElement('div');
            textDiv.className = 'message-text';
            
            // Handle text content, replacing sticker codes with images inline
            let processedContent = msg.content;
            this.stickers.forEach(sticker => {
                const stickerCode = `:${sticker}:`;
                const stickerImgTag = `<img src="assets/stickers/${sticker}.gif" alt="${stickerCode}" class="chat-sticker">`;
                const escapedStickerCode = stickerCode.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const regex = new RegExp(escapedStickerCode, 'g');
                processedContent = processedContent.replace(regex, stickerImgTag);
            });

            // Handle line breaks from Shift+Enter
            processedContent = processedContent.replace(/\n/g, '<br>');
            textDiv.innerHTML = processedContent;
            contentDiv.appendChild(textDiv);
        }

        // Add file attachments if they exist
        if (msg.file_path) {
            const fileDiv = document.createElement('div');
            fileDiv.className = 'message-file';
            
            // Parse file info if it exists
            let fileInfo = null;
            try {
                fileInfo = msg.file_info ? JSON.parse(msg.file_info) : null;
            } catch (e) {
                console.error('Error parsing file info:', e);
            }

            // Handle multiple files if they exist
            const filePaths = msg.file_path.split(',');
            const fileInfos = fileInfo ? (Array.isArray(fileInfo) ? fileInfo : [fileInfo]) : [];

            filePaths.forEach((filePath, index) => {
                const currentFileInfo = fileInfos[index] || {};
                const fileExt = filePath.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(fileExt);

                if (isImage) {
                    // Image file
                    const img = document.createElement('img');
                    // Construct URL path with single uploads directory
                    img.src = '/nubenta/uploads/' + filePath.replace(/^\/+/, '');
                    img.className = 'message-image';
                    img.onclick = () => window.open(img.src, '_blank');
                    fileDiv.appendChild(img);
                } else {
                    // Other file type
                    const fileLink = document.createElement('a');
                    // Construct URL path with single uploads directory
                    fileLink.href = '/nubenta/uploads/' + filePath.replace(/^\/+/, '');
                    fileLink.className = 'message-file-link';
                    fileLink.target = '_blank';
                    fileLink.innerHTML = `
                        <i class="fas ${this.getFileIcon(fileExt)}"></i>
                        <span>${currentFileInfo.name || 'Download File'}</span>
                    `;
                    fileDiv.appendChild(fileLink);
                }
            });

            contentDiv.appendChild(fileDiv);
        }

        // Create message meta container for timestamp and ticks
        const metaDiv = document.createElement('div');
        metaDiv.className = 'message-meta';
        
        // Add timestamp
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        const messageDate = new Date(msg.created_at);
        timeDiv.textContent = `${messageDate.toLocaleDateString()} ${messageDate.toLocaleTimeString()}`;
        metaDiv.appendChild(timeDiv);

        // Add tick marks for sent messages
        if (isSent) {
            const tickSpan = document.createElement('span');
            tickSpan.className = 'message-ticks';
            
            if (msg.read_at) {
                tickSpan.textContent = '✓✓';
                tickSpan.classList.add('read');
            } else if (msg.delivered_at) {
                tickSpan.textContent = '✓';
                tickSpan.classList.add('delivered');
            } else {
                tickSpan.textContent = '✓';
            }
            
            metaDiv.appendChild(tickSpan);
        }

        contentDiv.appendChild(metaDiv);
        div.appendChild(contentDiv);

        // Add message actions (delete, flag, etc.)
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'message-actions';
        
        if (isSent) {
            const deleteBtn = document.createElement('i');
            deleteBtn.className = 'fas fa-trash message-action';
            deleteBtn.title = 'Delete message';
            deleteBtn.onclick = () => this.deleteMessage(msg.id);
            actionsDiv.appendChild(deleteBtn);
        } else {
            const flagBtn = document.createElement('i');
            flagBtn.className = 'fas fa-flag message-action';
            flagBtn.title = 'Report message';
            flagBtn.onclick = () => this.flagMessage(msg.id, 'spam');
            actionsDiv.appendChild(flagBtn);
        }
        
        div.appendChild(actionsDiv);
        return div;
    }

    async deleteMessage(messageId) {
        if (confirm('Are you sure you want to delete this message?')) {
            try {
                const response = await fetch('api/chat_flag.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message_id: messageId,
                        action: 'delete'
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.loadMessages();
                } else {
                    alert('Failed to delete message');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting message');
            }
        }
    }

    async flagMessage(messageId, flag) {
        try {
            const response = await fetch('api/chat_flag.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message_id: messageId,
                    action: flag
                })
            });
            const data = await response.json();
            if (data.success) {
                this.loadMessages();
            } else {
                alert(`Failed to mark message as ${flag}`);
            }
        } catch (error) {
            console.error('Error:', error);
            alert(`Error marking message as ${flag}`);
        }
    }

    async loadMessages(force = false) {
        // Debounce message loading
        const now = Date.now();
        if (!force && now - this.lastLoadTime < 2000) { // 2 second debounce
            if (this.loadTimeout) {
                clearTimeout(this.loadTimeout);
            }
            this.loadTimeout = setTimeout(() => this.loadMessages(true), 2000);
            return;
        }
        
        try {
            console.log('Loading messages for thread:', this.threadId);
            const response = await fetch(`api/chat_messages.php?thread_id=${this.threadId}`);
            const data = await response.json();
            console.log('Received messages data:', data);
            
            if (!data.success) {
                console.error('Error loading messages:', data.error);
                return;
            }
            
            if (!Array.isArray(data.messages)) {
                console.error('Invalid messages format:', data.messages);
                return;
            }
            
            // Only update if we have new messages
            const newMessages = data.messages.filter(msg => !this.loadedMessageIds.has(msg.id));
            if (newMessages.length > 0 || force) {
                this.messagesDiv.innerHTML = '';
                data.messages.forEach(msg => {
                    console.log('Processing message:', msg);
                    const messageElement = this.renderMessage(msg);
                    this.messagesDiv.appendChild(messageElement);
                    this.loadedMessageIds.add(msg.id);
                });
                
                // Scroll to bottom
                this.messagesDiv.scrollTop = this.messagesDiv.scrollHeight;
                
                // Mark messages as delivered
                const undeliveredMessages = newMessages
                    .filter(msg => msg.sender_id !== window.me && !msg.delivered_at)
                    .map(msg => msg.id);
                
                if (undeliveredMessages.length > 0) {
                    this.markMessagesAsDelivered(undeliveredMessages);
                }
            }
            
            this.lastLoadTime = now;
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    async sendMessage() {
        if (this.isSending) {
            console.log('Message send already in progress');
            return;
        }

        const content = this.textarea.value.trim();
        if (!content && this.attachments.length === 0) return;
        
        try {
            this.isSending = true;
            const formData = new FormData();
            formData.append('thread_id', this.threadId);
            formData.append('content', content);
            
            this.attachments.forEach((file, index) => {
                formData.append('attachments[]', file);
            });
            
            const response = await fetch('api/chat_send.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                this.textarea.value = '';
                this.attachments = [];
                this.updateAttachmentPreview();
                this.loadMessages(true);
                this.clearNotificationCount();
            } else {
                console.error('Error sending message:', data.error);
            }
        } catch (error) {
            console.error('Error sending message:', error);
        } finally {
            this.isSending = false;
        }
    }

    async moveThreadToInbox() {
        try {
            const response = await fetch('api/chat_flag.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    thread_id: this.threadId,
                    action: 'unarchive'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Redirect to main inbox
                window.location.href = 'messages.php?thread=' + this.threadId;
            } else {
                console.error('Error moving thread to inbox:', data.error);
            }
        } catch (error) {
            console.error('Error moving thread to inbox:', error);
        }
    }

    setupPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        this.pollingInterval = setInterval(() => this.loadMessages(false), 5000);
    }

    toggleStickerPicker() {
        const picker = document.getElementById('picker');
        if (!picker) return;
        
        if (picker.style.display === 'block') {
            picker.style.display = 'none';
            return;
        }
        
        // Position the picker above the emoticon button
        const buttonRect = this.btnEmoji.getBoundingClientRect();
        picker.style.position = 'fixed';
        picker.style.bottom = `${window.innerHeight - buttonRect.top + 10}px`;
        picker.style.right = `${window.innerWidth - buttonRect.right}px`;
        
        // Clear and rebuild picker
        picker.innerHTML = '';
        const grid = document.createElement('div');
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(10, 1fr)';
        grid.style.gap = '5px';
        
        this.stickers.forEach(sticker => {
            const img = document.createElement('img');
            img.src = `assets/stickers/${sticker}.gif`;
            img.className = 'sticker';
            img.onclick = () => {
                this.textarea.value += `:${sticker}:`;
                picker.style.display = 'none';
            };
            grid.appendChild(img);
        });
        
        picker.appendChild(grid);
        picker.style.display = 'block';
        
        // Close picker when clicking outside
        const closePicker = (e) => {
            if (!picker.contains(e.target) && !this.btnEmoji.contains(e.target)) {
                picker.style.display = 'none';
                document.removeEventListener('click', closePicker);
            }
        };
        
        // Delay adding the click listener to avoid immediate trigger
        setTimeout(() => {
            document.addEventListener('click', closePicker);
        }, 100);
    }

    setupReadObserver() {
        const options = {
            root: this.messagesDiv, // Observe within the messages container
            rootMargin: '0px',
            threshold: 1.0 // Message is considered visible when 100% in view
        };

        this.observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const messageElement = entry.target;
                    const messageId = messageElement.dataset.messageId;
                    
                    if (messageId && !messageElement.classList.contains('read')) { // Prevent marking as read multiple times
                        this.markMessageAsRead(messageId);
                        observer.unobserve(messageElement); // Stop observing once marked as read
                    }
                }
            });
        }, options);
    }

    async markMessageAsRead(messageId) {
        console.log('Attempting to mark message as read:', messageId);
        try {
            const response = await fetch('api/chat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'read',
                    message_id: messageId
                })
            });
            const data = await response.json();
            if (data.success) {
                console.log('Message marked as read:', messageId);
                // Update the message element visually if needed (though polling should handle this)
                const messageElement = this.messagesDiv.querySelector(`[data-message-id="${messageId}"]`);
                if (messageElement) {
                    // Add a class or update ticks if not relying solely on polling for visual update
                    // messageElement.classList.add('read'); 
                    // You might manually update the tick HTML here for instant feedback
                }
            } else {
                console.error('Failed to mark message as read:', data.error);
            }
        } catch (error) {
            console.error('Error marking message as read:', error);
        }
    }

    async markMessagesAsDelivered(messageIds) {
        console.log('Attempting to mark messages as delivered:', messageIds);
        try {
            const response = await fetch('api/chat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delivered',
                    ids: messageIds
                })
            });
            const data = await response.json();
            if (data.success) {
                console.log('Messages marked as delivered:', messageIds);
                // No need to manually update ticks here, polling will handle it
            } else {
                console.error('Failed to mark messages as delivered:', data.error);
            }
        } catch (error) {
            console.error('Error marking messages as delivered:', error);
        }
    }

    addMessage(message) {
        const messageElement = this.renderMessage(message, true);
        this.messagesDiv.appendChild(messageElement);
        this.messagesDiv.scrollTop = this.messagesDiv.scrollHeight;
        
        // If it's a received message, mark it as delivered
        if (message.sender_id !== window.me) {
            this.markMessageAsDelivered(message.id);
        }
    }

    /* -----------------------------------------------------------
     *  heartbeat – every 2 s ask for updated status
     * ----------------------------------------------------------- */
    heartbeat() {
        // gather the ids of my own messages currently rendered
        const ids = Array.from(this.messagesDiv.querySelectorAll('[data-message-id]'))
                         .filter(el => el.dataset.me === '1')
                         .map(el => el.dataset.messageId);

        if (!ids.length) {
            setTimeout(() => this.heartbeat(), 2000);
            return;
        }

        fetch('api/chat_status.php', {
            method:'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(r => r.json())
        .then(response => {
            if (!response.success) {
                console.error('Heartbeat error:', response.error);
                setTimeout(() => this.heartbeat(), 4000);
                return;
            }
            
            const statuses = response.statuses || [];
            
            // Group messages by sender and status
            const messagesBySender = {};
            const messagesWithStatus = new Set();
            const messagesWithReadStatus = new Set();
            
            statuses.forEach(s => {
                const messageElement = this.messagesDiv.querySelector(`[data-message-id="${s.id}"]`);
                if (!messageElement) return;
                
                const senderId = messageElement.dataset.me === '1' ? window.me : 'other';
                if (!messagesBySender[senderId]) {
                    messagesBySender[senderId] = [];
                }
                messagesBySender[senderId].push(s);
                
                // Track messages that have status
                if (s.delivered_at !== null || s.read_at !== null) {
                    messagesWithStatus.add(s.id);
                }
                // Track messages that have been read
                if (s.read_at !== null) {
                    messagesWithReadStatus.add(s.id);
                }
            });

            // Update tick marks based on conditions
            Object.values(messagesBySender).forEach(senderMessages => {
                const lastMessage = senderMessages[senderMessages.length - 1];
                const hasStatus = messagesWithStatus.has(lastMessage.id);
                const hasReadStatus = messagesWithReadStatus.has(lastMessage.id);
                
                // Remove all existing tick marks first
                senderMessages.forEach(s => {
                    const el = this.messagesDiv.querySelector(`[data-message-id="${s.id}"] .message-ticks`);
                    if (el) el.remove();
                });
                
                // Check if any previous messages have been read
                const hasPreviousRead = senderMessages.some(s => 
                    s.id !== lastMessage.id && messagesWithReadStatus.has(s.id)
                );
                
                // Check if any previous messages have been delivered but not read
                const hasPreviousDelivered = senderMessages.some(s => 
                    s.id !== lastMessage.id && 
                    messagesWithStatus.has(s.id) && 
                    !messagesWithReadStatus.has(s.id)
                );
                
                // Add tick marks based on conditions
                if (hasStatus) {
                    // Only show on last message if it has status
                    const el = this.messagesDiv.querySelector(`[data-message-id="${lastMessage.id}"] .message-meta`);
                    if (el) {
                        const tickSpan = document.createElement('span');
                        tickSpan.className = 'message-ticks';
                        if (lastMessage.read_at !== null && lastMessage.read_at !== '') {
                            tickSpan.textContent = '✓✓';
                            tickSpan.classList.add('read');
                        } else if (lastMessage.delivered_at !== null && lastMessage.delivered_at !== '') {
                            tickSpan.textContent = '✓';
                            tickSpan.classList.add('delivered');
                        } else {
                            tickSpan.textContent = '✓';
                        }
                        el.appendChild(tickSpan);
                    }
                } else if (hasPreviousRead) {
                    // If previous messages were read but last message has no status, only show on last message
                    const el = this.messagesDiv.querySelector(`[data-message-id="${lastMessage.id}"] .message-meta`);
                    if (el) {
                        const tickSpan = document.createElement('span');
                        tickSpan.className = 'message-ticks';
                        tickSpan.textContent = '✓';
                        el.appendChild(tickSpan);
                    }
                } else if (hasPreviousDelivered) {
                    // If previous messages were delivered but not read, show on all undelivered messages
                    senderMessages.forEach(s => {
                        if (!messagesWithStatus.has(s.id)) {
                            const el = this.messagesDiv.querySelector(`[data-message-id="${s.id}"] .message-meta`);
                            if (el) {
                                const tickSpan = document.createElement('span');
                                tickSpan.className = 'message-ticks';
                                tickSpan.textContent = '✓';
                                el.appendChild(tickSpan);
                            }
                        }
                    });
                } else {
                    // Show on all messages that don't have status
                    senderMessages.forEach(s => {
                        const el = this.messagesDiv.querySelector(`[data-message-id="${s.id}"] .message-meta`);
                        if (el) {
                            const tickSpan = document.createElement('span');
                            tickSpan.className = 'message-ticks';
                            tickSpan.textContent = '✓';
                            el.appendChild(tickSpan);
                        }
                    });
                }
            });

            setTimeout(() => this.heartbeat(), 2000);
        })
        .catch(error => {
            console.error('Heartbeat error:', error);
            setTimeout(() => this.heartbeat(), 4000);
       });
  }
}

function createMessageElement(message) {
    const div = document.createElement('div');
    
    if (message.is_system_message) {
        // System message styling
        div.className = 'message system-message';
        const isAdminResponse = message.content.includes('admin response');
        
        // Add a special class for admin responses
        if (isAdminResponse) {
            div.classList.add('admin-response');
        }
        
        div.innerHTML = `
            <div class="message-content">
                <div class="message-text">
                    ${isAdminResponse ? '<i class="fas fa-shield-alt"></i> ' : ''}
                    ${message.content}
                </div>
                <div class="message-time">${new Date(message.created_at).toLocaleTimeString()}</div>
            </div>
        `;
    } else {
        // Regular message styling
        div.className = `message ${message.sender_id == me ? 'sent' : 'received'}`;
        div.innerHTML = `
            <div class="message-content">
                ${message.content ? `<div class="message-text">${message.content}</div>` : ''}
                ${message.file_path ? createFileElement(message) : ''}
                <div class="message-time">${new Date(message.created_at).toLocaleTimeString()}</div>
            </div>
        `;
    }
    
    return div;
}
