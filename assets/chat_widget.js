class ChatWidget {
    constructor(threadId, container, stickers) {
        console.log('ChatWidget constructor called for thread ID:', threadId);
        this.threadId = threadId;
        this.container = container;
        this.stickers = stickers;
        this.setupUI();
        this.loadMessages();
        this.setupPolling();
        console.log('ChatWidget constructor finished.');
        
        // Start heartbeat polling for status updates
        this.heartbeat();
    }

    setupUI() {
        this.container.innerHTML = `
            <div class="chat-messages"></div>
        `;

        this.messagesDiv = this.container.querySelector('.chat-messages');
        
        // Use the existing form elements
        this.textarea = document.querySelector('.chat-input');
        this.sendButton = document.querySelector('.send-button');
        this.emojiButton = document.querySelector('#btnEmoji');
        this.attachButton = document.querySelector('#btnAttach');

        this.sendButton.onclick = () => this.sendMessage();
        this.textarea.onkeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        };
        this.emojiButton.onclick = () => this.toggleStickerPicker();
        this.attachButton.onclick = () => this.handleFileAttachment();

        // Intersection Observer for read receipts
        this.setupReadObserver();
    }

    handleFileAttachment() {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = 'image/*,video/*,application/pdf';
        
        input.onchange = (e) => {
            const files = Array.from(e.target.files);
            if (files.length === 0) return;

            // Create preview container
            const previewContainer = document.createElement('div');
            previewContainer.className = 'file-preview-container';
            
            files.forEach(file => {
                const preview = document.createElement('div');
                preview.className = 'file-preview';
                preview.dataset.fileName = file.name;
                preview.dataset.fileType = file.type;
                
                // Store the actual File object
                preview.file = file; // Store directly as a property instead of in dataset
                
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    preview.appendChild(img);
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-file';
                    preview.appendChild(icon);
                }
                
                const info = document.createElement('div');
                info.className = 'file-info';
                info.textContent = file.name;
                preview.appendChild(info);
                
                const remove = document.createElement('span');
                remove.className = 'remove-file';
                remove.innerHTML = '×';
                remove.onclick = () => preview.remove();
                preview.appendChild(remove);
                
                previewContainer.appendChild(preview);
            });
            
            // Insert preview before the input container
            const inputContainer = document.querySelector('.chat-input-container');
            inputContainer.parentNode.insertBefore(previewContainer, inputContainer);
        };
        
        input.click();
    }

    renderMessage(msg, isLastFromSender = false) {
        console.log('Rendering message:', msg); // Debug log
        const isSent = msg.sender_id === window.me;
        const messageClass = isSent ? 'sent msg-me' : 'received msg-you';

        const div = document.createElement('div');
        div.className = `message ${messageClass}`;
        div.dataset.messageId = msg.id;
        div.dataset.me = (msg.sender_id === window.me ? '1' : '0');
        
        // Create message bubble content
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';

        // Add message text or sticker
        const textDiv = document.createElement('div');
        textDiv.className = 'message-text';
        
        // Handle text content, replacing sticker codes with images inline
        let processedContent = msg.body || msg.content || '';
        if (processedContent) {
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
            bubbleDiv.appendChild(textDiv);
        }

        // Add file attachments if present
        if (msg.file_path) {
            console.log('Processing file path:', msg.file_path); // Debug log
            const filePaths = msg.file_path.split(',');
            filePaths.forEach(filePath => {
                console.log('Processing individual file:', filePath); // Debug log
                const fileDiv = document.createElement('div');
                fileDiv.className = 'message-file';
                
                // Remove leading slash if present
                const cleanPath = filePath.startsWith('/') ? filePath.substring(1) : filePath;
                console.log('Clean file path:', cleanPath); // Debug log
                
                if (filePath.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                    const img = document.createElement('img');
                    img.src = cleanPath;
                    img.className = 'message-image';
                    img.onclick = () => window.open(cleanPath, '_blank');
                    img.onerror = (e) => {
                        console.error('Error loading image:', cleanPath, e);
                        img.src = 'assets/images/error.png'; // Fallback image
                    };
                    fileDiv.appendChild(img);
                } else {
                    const link = document.createElement('a');
                    link.href = cleanPath;
                    link.className = 'message-file-link';
                    link.target = '_blank';
                    link.innerHTML = `<i class="fas fa-file"></i> ${filePath.split('/').pop()}`;
                    fileDiv.appendChild(link);
                }
                
                bubbleDiv.appendChild(fileDiv);
            });
        }
        
        // Add timestamp and tick marks
        const metaDiv = document.createElement('div');
        metaDiv.className = 'message-meta';
        
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.textContent = new Date(msg.created_at || msg.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        metaDiv.appendChild(timeSpan);
        
        // Add tick marks for sent messages based on conditions
        if (isSent) {
            const hasStatus = msg.delivered_at !== null || msg.read_at !== null;
            const shouldShowTicks = (hasStatus && isLastFromSender) || (!hasStatus);
            
            if (shouldShowTicks) {
                const tickSpan = document.createElement('span');
                tickSpan.className = 'message-ticks';
                tickSpan.innerHTML = '✓';
                if (msg.delivered_at) {
                    tickSpan.classList.add('delivered');
                }
                if (msg.read_at) {
                    tickSpan.classList.add('read');
                }
                metaDiv.appendChild(tickSpan);
            }
        }
        
        bubbleDiv.appendChild(metaDiv);
        div.appendChild(bubbleDiv);

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

    async loadMessages() {
        try {
            const response = await fetch(`api/chat_messages.php?thread_id=${this.threadId}`);
            const data = await response.json();
            console.log('Loaded messages:', data); // Debug log
            
            if (!data.success) {
                console.error('Error loading messages:', data.error);
                return;
            }
            
            this.messagesDiv.innerHTML = '';
            if (Array.isArray(data.messages)) {
                // Group messages by sender
                const messagesBySender = {};
                data.messages.forEach(msg => {
                    if (!messagesBySender[msg.sender_id]) {
                        messagesBySender[msg.sender_id] = [];
                    }
                    messagesBySender[msg.sender_id].push(msg);
                });

                // Render messages
                data.messages.forEach((msg, index) => {
                    // Check if this is the last message from this sender
                    const senderMessages = messagesBySender[msg.sender_id];
                    const isLastFromSender = msg.id === senderMessages[senderMessages.length - 1].id;
                    
                    const messageElement = this.renderMessage(msg, isLastFromSender);
                    this.messagesDiv.appendChild(messageElement);
                    
                    // Observe newly added messages
                    if (msg.sender_id !== window.me) { // Only observe received messages
                        this.observer.observe(messageElement);
                    }
                });
                
                this.messagesDiv.scrollTop = this.messagesDiv.scrollHeight;
                
                // After loading messages, mark received messages as delivered
                const receivedMessageIds = data.messages
                    .filter(msg => msg.sender_id !== window.me)
                    .map(msg => msg.id);
                
                if (receivedMessageIds.length > 0) {
                    this.markMessagesAsDelivered(receivedMessageIds);
                }
            } else {
                console.error('Invalid messages format:', data);
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    async sendMessage() {
        const content = this.textarea.value.trim();
        const filePreviews = document.querySelectorAll('.file-preview');
        
        // Get the actual File objects from the preview elements
        const files = Array.from(filePreviews).map(preview => preview.file);

        // Allow sending if either content or files exist
        if (!content && files.length === 0) {
            console.log('sendMessage: Both content and files are empty.');
            return;
        }

        console.log('sendMessage: Sending message with content:', content, 'and files:', files, 'to thread ID:', this.threadId);

        try {
            const formData = new FormData();
            formData.append('thread_id', this.threadId);
            formData.append('body', content || ''); // Always send body, even if empty
            
            // Add files to formData
            files.forEach((file, index) => {
                if (file instanceof File) {
                    console.log('Adding file to FormData:', file.name, file.type, file.size);
                    formData.append('files[]', file);
                } else {
                    console.error('Invalid file object:', file);
                }
            });

            const response = await fetch('api/chat_send.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('sendMessage: HTTP error!', response.status, errorText);
                alert('Failed to send message: ' + errorText);
                return;
            }

            const data = await response.json();
            console.log('sendMessage: API response:', data);

            if (data.ok) {
                this.textarea.value = '';
                // Reset textarea height
                this.textarea.style.height = '40px';
                
                // Remove any file previews
                const previewContainer = document.querySelector('.file-preview-container');
                if (previewContainer) {
                    previewContainer.remove();
                }
                
                this.loadMessages();
                console.log('sendMessage: Message sent successfully.');
            } else {
                console.error('sendMessage: API reported failure:', data.error);
                alert('Failed to send message: ' + data.error);
            }
        } catch (error) {
            console.error('sendMessage: Error sending message:', error);
            alert('Error sending message: ' + error.message);
        }
    }

    setupPolling() {
        // This polling is primarily for new messages
        // Poll for new messages every 5 seconds
        setInterval(() => this.loadMessages(), 5000);
    }

    toggleStickerPicker() {
        const picker = document.getElementById('picker');
        const emojiButton = document.querySelector('#btnEmoji');
        
        if (!picker) {
            console.error('Sticker picker element not found');
            return;
        }

        if (picker.style.display === 'none') {
            // Position the picker relative to the emoji button
            const buttonRect = emojiButton.getBoundingClientRect();
            picker.style.position = 'fixed';
            picker.style.bottom = `${window.innerHeight - buttonRect.top}px`;
            picker.style.right = `${window.innerWidth - buttonRect.right}px`;
            picker.style.display = 'block';
            picker.innerHTML = '';
            
            // Create a 10-column grid of stickers
            const grid = document.createElement('div');
            grid.style.display = 'grid';
            grid.style.gridTemplateColumns = 'repeat(10, 1fr)';
            grid.style.gap = '2px';
            grid.style.padding = '2px';
            
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

            // Close picker when clicking outside
            const closePicker = (e) => {
                if (!picker.contains(e.target) && !emojiButton.contains(e.target)) {
                    picker.style.display = 'none';
                    document.removeEventListener('click', closePicker);
                }
            };
            document.addEventListener('click', closePicker);
        } else {
            picker.style.display = 'none';
        }
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
