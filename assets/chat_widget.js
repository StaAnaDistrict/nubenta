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
    }

    renderMessage(msg) {
        const isSent = msg.sender_id === window.me;
        const messageClass = isSent ? 'sent msg-me' : 'received msg-you';

        const div = document.createElement('div');
        div.className = `message ${messageClass}`;
        
        // Create message bubble content
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble'; // Add a bubble class for styling

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
            if (msg.read_at) {
                tickSpan.innerHTML = '✓✓'; // Double tick for read
                tickSpan.classList.add('read');
            } else if (msg.delivered_at) {
                tickSpan.innerHTML = '✓✓'; // Double tick for delivered (unread)
            } else {
                tickSpan.innerHTML = '✓'; // Single tick for sent
            }
            metaDiv.appendChild(tickSpan);
        }
        
        bubbleDiv.appendChild(metaDiv);
        
        // Removed message hover actions - they belong with thread actions

        div.appendChild(bubbleDiv); // Append the bubble div to the main message div

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
                    this.messagesDiv.appendChild(this.renderMessage(msg));
                });
                this.messagesDiv.scrollTop = this.messagesDiv.scrollHeight;
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
        setInterval(() => this.loadMessages(), 5000);
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
}
