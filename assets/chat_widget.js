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
    }

    setupUI() {
        this.container.innerHTML = `
            <div class="chat-messages"></div>
            <div class="chat-input">
                <textarea placeholder="Type a message..."></textarea>
                <button class="btn btn-secondary" id="btnAttach">📎</button>
                <button class="btn btn-secondary" id="btnEmoji">😊</button>
                <button class="btn btn-primary">Send</button>
            </div>
        `;

        this.messagesDiv = this.container.querySelector('.chat-messages');
        this.textarea = this.container.querySelector('textarea');
        this.sendButton = this.container.querySelector('button.btn-primary');
        this.emojiButton = this.container.querySelector('#btnEmoji');
        this.attachButton = this.container.querySelector('#btnAttach');

        this.sendButton.onclick = () => this.sendMessage();
        this.textarea.onkeydown = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        };
        this.emojiButton.onclick = () => this.toggleStickerPicker();
        this.attachButton.onclick = () => alert('File attachment coming soon!');

        // Intersection Observer for read receipts
        this.setupReadObserver();
    }

    renderMessage(msg) {
        const isSent = msg.sender_id === window.me;
        const messageClass = isSent ? 'sent msg-me' : 'received msg-you';

        const div = document.createElement('div');
        div.className = `message ${messageClass}`;
        div.dataset.messageId = msg.id;
        
        // Create message bubble content
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';

        // Add message text or sticker
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
        
        bubbleDiv.appendChild(textDiv);
        
        // Add timestamp and tick marks
        const metaDiv = document.createElement('div');
        metaDiv.className = 'message-meta';
        
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.textContent = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        metaDiv.appendChild(timeSpan);
        
        // Add tick marks for sent messages
        if (isSent) {
            const tickSpan = document.createElement('span');
            tickSpan.className = 'message-ticks';
            // Initially show one tick; polling will update to two ticks and color
            tickSpan.innerHTML = '✓';
            // Add classes based on initial status, polling will refine
            if (msg.delivered_at) {
                tickSpan.classList.add('delivered');
            }
            if (msg.read_at) {
                tickSpan.classList.add('read'); // Both delivered and read might be set initially
            }
            metaDiv.appendChild(tickSpan);
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
            if (!data.success) {
                console.error('Error loading messages:', data.error);
                return;
            }
            this.messagesDiv.innerHTML = '';
            if (Array.isArray(data.messages)) {
                data.messages.forEach(msg => {
                    const messageElement = this.renderMessage(msg);
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
        if (!content) {
            console.log('sendMessage: Content is empty.');
            return;
        }

        console.log('sendMessage: Sending message with content:', content, 'to thread ID:', this.threadId);

        try {
            const response = await fetch('api/chat_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    thread_id: this.threadId,
                    content: content
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('sendMessage: HTTP error!', response.status, errorText);
                alert('Failed to send message: ' + errorText);
                return;
            }

            const data = await response.json();
            
            console.log('sendMessage: API response:', data);

            if (data.success) {
                this.textarea.value = '';
                
                // If a new thread was created, update the current thread ID
                if (data.thread_id && data.thread_id !== this.threadId) {
                    this.threadId = data.thread_id;
                    // Notify the parent page to update the thread list
                    window.dispatchEvent(new CustomEvent('threadCreated', {
                        detail: { threadId: data.thread_id }
                    }));
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
        // Poll for new messages every 5 seconds
        setInterval(() => this.loadMessages(), 5000);
        
        // Poll for message status updates every 2 seconds
        setInterval(() => this.checkMessageStatus(), 2000);
    }

    async checkMessageStatus() {
        if (!this.threadId) return;
        
        try {
            console.log('checkMessageStatus: Checking status for thread:', this.threadId);
            
            // Get all sent message IDs that haven't been read
            const sentMessages = Array.from(this.messagesDiv.querySelectorAll('.msg-me'))
                .map(el => el.dataset.messageId)
                .filter(id => id);
            
            if (sentMessages.length === 0) {
                console.log('checkMessageStatus: No sent messages to check.');
                return;
            }
            console.log('checkMessageStatus: Sent message IDs:', sentMessages);

            const response = await fetch('api/chat_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ids: sentMessages
                })
            });

            const data = await response.json();
            
            console.log('checkMessageStatus: API response data:', data);

            if (!data.success) {
                console.error('Error in message status response:', data.error);
                return;
            }

            const statuses = data.statuses || [];
            console.log('checkMessageStatus: Received statuses:', statuses);
            
            // Update tick marks based on status
            statuses.forEach(status => {
                console.log('checkMessageStatus: Processing status for message ID:', status.id, 'Status:', status);
                const messageEl = this.messagesDiv.querySelector(`[data-message-id="${status.id}"]`);
                if (messageEl) {
                    const tickSpan = messageEl.querySelector('.message-ticks');
                    if (tickSpan) {
                        console.log('checkMessageStatus: Found tick span for message ID:', status.id);
                        console.log('checkMessageStatus:', status.id, 'Delivered At:', status.delivered_at, '(' + typeof status.delivered_at + ')', 'Read At:', status.read_at, '(' + typeof status.read_at + ')');
                        console.log('checkMessageStatus:', status.id, 'status.delivered_at is truthy:', !!status.delivered_at, 'status.read_at is truthy:', !!status.read_at);
                        console.log('checkMessageStatus:', status.id, 'status.delivered_at explicitly check:', status.delivered_at !== null && status.delivered_at !== '', 'status.read_at explicitly check:', status.read_at !== null && status.read_at !== '');

                        if (status.read_at !== null && status.read_at !== '') {
                            tickSpan.innerHTML = '✓✓'; // Read is two ticks
                            tickSpan.classList.add('read');
                            tickSpan.classList.remove('delivered');
                            console.log('checkMessageStatus: Marked message', status.id, 'as read (✓✓ white).');
                        } else if (status.delivered_at !== null && status.delivered_at !== '') {
                            tickSpan.innerHTML = '✓'; // Delivered is one tick
                            tickSpan.classList.add('delivered');
                            tickSpan.classList.remove('read');
                            console.log('checkMessageStatus: Marked message', status.id, 'as delivered (✓ yellow).');
                        } else {
                            tickSpan.innerHTML = '✓'; // Sent is one tick
                            tickSpan.classList.remove('read', 'delivered');
                            console.log('checkMessageStatus: Marked message', status.id, 'as sent (✓ dark gray).');
                        }
                    }
                    // Add a class or update ticks if not relying solely on polling for visual update
                    // messageElement.classList.add('read'); 
                    // You might manually update the tick HTML here for instant feedback
                }
                else {
                    console.log('checkMessageStatus: Message element not found for ID:', status.id);
                }
            });
        } catch (error) {
            console.error('Error checking message status:', error);
        }
    }

    toggleStickerPicker() {
        const picker = document.getElementById('picker');
        if (picker.style.display === 'none') {
            picker.style.display = 'block';
            picker.innerHTML = '';
            this.stickers.forEach(sticker => {
                const img = document.createElement('img');
                img.src = `assets/stickers/${sticker}.gif`;
                img.className = 'sticker';
                img.onclick = () => {
                    this.textarea.value += `:${sticker}:`;
                    picker.style.display = 'none';
                };
                picker.appendChild(img);
            });
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
}
