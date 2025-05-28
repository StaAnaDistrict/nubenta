/**
 * Facebook-Style Popup Chat System
 * Manages multiple chat windows in the lower right corner
 */

class PopupChatManager {
    constructor() {
        this.chatWindows = new Map(); // userId -> ChatWindow instance
        this.windowPositions = []; // Track window positions
        this.maxWindows = 3; // Maximum number of open chat windows
        this.windowWidth = 300;
        this.windowHeight = 400;
        this.windowSpacing = 10;
        this.init();
    }

    init() {
        console.log('PopupChatManager initializing...');

        // Create chat container if it doesn't exist
        if (!document.getElementById('popup-chat-container')) {
            const container = document.createElement('div');
            container.id = 'popup-chat-container';
            container.className = 'popup-chat-container';
            document.body.appendChild(container);
            console.log('Created popup chat container');
        } else {
            console.log('Popup chat container already exists');
        }

        // Add CSS styles
        this.addStyles();

        console.log('PopupChatManager initialized successfully');
    }

    addStyles() {
        if (document.getElementById('popup-chat-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'popup-chat-styles';
        styles.textContent = `
            .popup-chat-container {
                position: fixed;
                bottom: 0;
                right: 20px;
                z-index: 10000;
                pointer-events: none;
                width: auto;
                height: auto;
            }

            .chat-window {
                position: absolute;
                bottom: 0;
                width: 300px;
                height: 400px;
                background: #fff;
                border: 1px solid #ddd;
                border-bottom: none;
                border-radius: 8px 8px 0 0;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                display: flex;
                flex-direction: column;
                pointer-events: auto;
                transition: all 0.3s ease;
            }

            .chat-window.minimized {
                height: 40px;
            }

            .chat-window-header {
                background: #2c3e50;
                color: white;
                padding: 8px 12px;
                border-radius: 8px 8px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: pointer;
                user-select: none;
            }

            .chat-window-title {
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
            }

            .chat-online-indicator {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #28a745;
                margin-right: 6px;
            }

            .chat-window-controls {
                display: flex;
                gap: 8px;
            }

            .chat-control-btn {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 2px 4px;
                border-radius: 3px;
                font-size: 12px;
                transition: background-color 0.2s;
            }

            .chat-control-btn:hover {
                background: rgba(255,255,255,0.2);
            }

            .chat-window-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .chat-messages {
                flex: 1;
                padding: 8px;
                overflow-y: auto;
                background: #f8f9fa;
                font-size: 13px;
            }

            .chat-message {
                margin-bottom: 8px;
                display: flex;
                align-items: flex-start;
            }

            .chat-message.own {
                justify-content: flex-end;
            }

            .chat-message-bubble {
                max-width: 70%;
                padding: 6px 10px;
                border-radius: 12px;
                word-wrap: break-word;
            }

            .chat-message.own .chat-message-bubble {
                background: #007bff;
                color: white;
            }

            .chat-message:not(.own) .chat-message-bubble {
                background: #e9ecef;
                color: #333;
            }

            .chat-message-time {
                font-size: 10px;
                color: #666;
                margin-top: 2px;
                text-align: right;
            }

            .chat-input-container {
                border-top: 1px solid #ddd;
                padding: 8px;
                background: white;
            }

            .chat-input {
                width: 100%;
                border: 1px solid #ddd;
                border-radius: 15px;
                padding: 6px 12px;
                font-size: 13px;
                resize: none;
                outline: none;
                max-height: 60px;
            }

            .chat-input:focus {
                border-color: #007bff;
            }

            .chat-typing-indicator {
                padding: 4px 12px;
                font-size: 11px;
                color: #666;
                font-style: italic;
                background: #f8f9fa;
                border-top: 1px solid #eee;
            }

            .chat-unread-badge {
                background: #dc3545;
                color: white;
                border-radius: 10px;
                padding: 2px 6px;
                font-size: 10px;
                margin-left: 6px;
                min-width: 16px;
                text-align: center;
            }

            /* Scrollbar styling for chat messages */
            .chat-messages::-webkit-scrollbar {
                width: 4px;
            }

            .chat-messages::-webkit-scrollbar-track {
                background: #f1f1f1;
            }

            .chat-messages::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 2px;
            }

            .chat-messages::-webkit-scrollbar-thumb:hover {
                background: #a8a8a8;
            }
        `;
        document.head.appendChild(styles);
    }

    // Open a chat window with a specific user
    async openChat(userId, userName, userProfilePic) {
        console.log('🎯 PopupChatManager.openChat called with:', userName, 'ID:', userId);
        console.log('📊 Current state:', {
            chatWindows: this.chatWindows.size,
            maxWindows: this.maxWindows,
            windowWidth: this.windowWidth,
            windowHeight: this.windowHeight
        });

        // Check if chat window already exists
        if (this.chatWindows.has(userId)) {
            console.log('♻️ Chat window already exists for user:', userId);
            const existingWindow = this.chatWindows.get(userId);
            existingWindow.show();
            existingWindow.focus();
            return existingWindow;
        }

        // Close oldest window if we have too many
        if (this.chatWindows.size >= this.maxWindows) {
            console.log('🗑️ Too many windows, closing oldest');
            const oldestUserId = this.chatWindows.keys().next().value;
            this.closeChat(oldestUserId);
        }

        console.log('🆕 Creating new chat window for:', userName);

        // Create new chat window
        const chatWindow = new ChatWindow(userId, userName, userProfilePic, this);
        this.chatWindows.set(userId, chatWindow);

        console.log('📍 Positioning window...');
        // Position the window
        this.positionWindow(chatWindow);

        // Initialize the chat
        console.log('🔧 Initializing chat window...');
        try {
            await chatWindow.init();
            console.log('✅ Chat window initialized successfully for:', userName);
            console.log('🎉 Chat window should now be visible!');
        } catch (error) {
            console.error('❌ Failed to initialize chat window:', error);
            this.chatWindows.delete(userId);
            throw error;
        }

        return chatWindow;
    }

    // Close a specific chat window
    closeChat(userId) {
        const chatWindow = this.chatWindows.get(userId);
        if (chatWindow) {
            chatWindow.destroy();
            this.chatWindows.delete(userId);
            this.repositionWindows();
        }
    }

    // Position a new window
    positionWindow(chatWindow) {
        const windowIndex = this.chatWindows.size - 1;
        const rightOffset = windowIndex * (this.windowWidth + this.windowSpacing);
        console.log('📍 Positioning window at offset:', rightOffset, 'for window index:', windowIndex);

        // Don't position until the element is created
        chatWindow.pendingPosition = rightOffset;
    }

    // Reposition all windows after closing one
    repositionWindows() {
        let index = 0;
        for (const [userId, chatWindow] of this.chatWindows) {
            const rightOffset = index * (this.windowWidth + this.windowSpacing);
            chatWindow.setPosition(rightOffset);
            index++;
        }
    }

    // Get or create thread for conversation
    async getOrCreateThread(userId) {
        try {
            const response = await fetch('api/start_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            });

            const data = await response.json();
            if (data.success && data.thread_id) {
                return data.thread_id;
            } else {
                throw new Error(data.error || 'Failed to create conversation');
            }
        } catch (error) {
            console.error('Error getting/creating thread:', error);
            throw error;
        }
    }
}

/**
 * Individual Chat Window Class
 */
class ChatWindow {
    constructor(userId, userName, userProfilePic, manager) {
        this.userId = userId;
        this.userName = userName;
        this.userProfilePic = userProfilePic;
        this.manager = manager;
        this.threadId = null;
        this.isMinimized = false;
        this.unreadCount = 0;
        this.isTyping = false;
        this.lastMessageTime = null;
        this.element = null;
        this.messagesContainer = null;
        this.inputElement = null;
        this.typingTimeout = null;
        this.renderedMessageIds = new Set(); // Track rendered message IDs
    }

    async init() {
        console.log('🔧 ChatWindow.init() starting for:', this.userName);

        // Get or create thread
        try {
            console.log('🔗 Getting/creating thread for user:', this.userId);
            this.threadId = await this.manager.getOrCreateThread(this.userId);
            console.log('✅ Thread created/found:', this.threadId);
        } catch (error) {
            console.error('❌ Failed to get/create thread:', error);
            throw error; // Re-throw to be caught by openChat
        }

        // Create DOM elements
        console.log('🏗️ Creating DOM elements...');
        this.createElement();

        // Apply pending position if set
        if (this.pendingPosition !== undefined) {
            console.log('📍 Applying pending position:', this.pendingPosition);
            this.setPosition(this.pendingPosition);
            this.pendingPosition = undefined;
        }

        console.log('🎛️ Attaching event listeners...');
        this.attachEventListeners();

        // Load initial messages
        console.log('📨 Loading initial messages...');
        await this.loadMessages();

        // Start real-time updates
        console.log('⚡ Starting real-time updates...');
        this.startRealTimeUpdates();

        console.log('🎉 ChatWindow initialization complete for:', this.userName);
    }

    createElement() {
        const container = document.getElementById('popup-chat-container');

        if (!container) {
            console.error('Popup chat container not found! Creating it...');
            const newContainer = document.createElement('div');
            newContainer.id = 'popup-chat-container';
            newContainer.className = 'popup-chat-container';
            document.body.appendChild(newContainer);
        }

        this.element = document.createElement('div');
        this.element.className = 'chat-window';
        this.element.innerHTML = `
            <div class="chat-window-header">
                <div class="chat-window-title">
                    <span class="chat-online-indicator"></span>
                    ${this.userName}
                    <span class="chat-unread-badge" style="display: none;">0</span>
                </div>
                <div class="chat-window-controls">
                    <button class="chat-control-btn minimize-btn" title="Minimize">−</button>
                    <button class="chat-control-btn fullscreen-btn" title="Open in full screen">⛶</button>
                    <button class="chat-control-btn close-btn" title="Close">×</button>
                </div>
            </div>
            <div class="chat-window-body">
                <div class="chat-messages"></div>
                <div class="chat-typing-indicator" style="display: none;">
                    ${this.userName} is typing...
                </div>
                <div class="chat-input-container">
                    <textarea class="chat-input" placeholder="Type a message..." rows="1"></textarea>
                </div>
            </div>
        `;

        const finalContainer = document.getElementById('popup-chat-container');
        finalContainer.appendChild(this.element);

        // Store references
        this.messagesContainer = this.element.querySelector('.chat-messages');
        this.inputElement = this.element.querySelector('.chat-input');
        this.unreadBadge = this.element.querySelector('.chat-unread-badge');
        this.typingIndicator = this.element.querySelector('.chat-typing-indicator');
    }

    attachEventListeners() {
        // Header click to minimize/maximize
        const header = this.element.querySelector('.chat-window-header');
        header.addEventListener('click', (e) => {
            if (!e.target.classList.contains('chat-control-btn')) {
                this.toggleMinimize();
            }
        });

        // Control buttons
        this.element.querySelector('.minimize-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleMinimize();
        });

        this.element.querySelector('.fullscreen-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.openFullScreen();
        });

        this.element.querySelector('.close-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.manager.closeChat(this.userId);
        });

        // Input handling
        this.inputElement.addEventListener('keydown', (e) => {
            console.log('⌨️ Key pressed:', e.key, 'Shift:', e.shiftKey);
            if (e.key === 'Enter' && !e.shiftKey) {
                console.log('✅ Enter key detected, sending message...');
                e.preventDefault();
                this.sendMessage();
            }
        });

        this.inputElement.addEventListener('input', () => {
            this.handleTyping();
        });

        // Auto-resize textarea
        this.inputElement.addEventListener('input', () => {
            this.inputElement.style.height = 'auto';
            this.inputElement.style.height = Math.min(this.inputElement.scrollHeight, 60) + 'px';
        });

        // Focus handling
        this.element.addEventListener('click', () => {
            this.focus();
        });
    }

    async loadMessages() {
        try {
            console.log('📨 Loading messages for thread:', this.threadId);
            const response = await fetch(`api/get_chat_messages.php?thread_id=${this.threadId}&limit=20`);
            console.log('📨 Response status:', response.status);

            const data = await response.json();
            console.log('📨 Messages data:', data);

            if (data.success && data.messages) {
                console.log('✅ Found', data.messages.length, 'messages');
                this.renderMessages(data.messages);
                this.scrollToBottom();
            } else {
                console.log('❌ No messages or error:', data.error);
            }
        } catch (error) {
            console.error('❌ Error loading messages:', error);
        }
    }

    renderMessages(messages) {
        console.log('🎨 Rendering', messages.length, 'messages');

        // Clear existing messages and reset tracking
        this.messagesContainer.innerHTML = '';
        this.renderedMessageIds.clear();

        messages.forEach(message => {
            if (!this.renderedMessageIds.has(message.id)) {
                this.addMessageToUI(message);
                this.renderedMessageIds.add(message.id);
            } else {
                console.log('⚠️ Skipping duplicate message ID:', message.id);
            }
        });

        console.log('✅ Rendered', this.renderedMessageIds.size, 'unique messages');
    }

    addMessageToUI(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${message.is_own ? 'own' : ''}`;

        const bubble = document.createElement('div');
        bubble.className = 'chat-message-bubble';
        bubble.textContent = message.content;

        const time = document.createElement('div');
        time.className = 'chat-message-time';
        time.textContent = this.formatTime(message.created_at);

        messageDiv.appendChild(bubble);
        messageDiv.appendChild(time);
        this.messagesContainer.appendChild(messageDiv);
    }

    async sendMessage() {
        const content = this.inputElement.value.trim();
        console.log('💬 Attempting to send message:', content);

        if (!content) {
            console.log('❌ Empty message, not sending');
            return;
        }

        // Clear input immediately
        this.inputElement.value = '';
        this.inputElement.style.height = 'auto';

        try {
            console.log('📤 Sending message to thread:', this.threadId);
            const response = await fetch('api/send_chat_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    thread_id: this.threadId,
                    content: content
                })
            });

            console.log('📤 Send response status:', response.status);
            const data = await response.json();
            console.log('📤 Send response data:', data);

            if (data.success) {
                console.log('✅ Message sent successfully');
                // Message will be added via real-time updates
                this.scrollToBottom();
            } else {
                throw new Error(data.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('❌ Error sending message:', error);
            // Restore input content on error
            this.inputElement.value = content;
        }
    }

    handleTyping() {
        // Clear existing timeout
        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }

        // Send typing indicator
        if (!this.isTyping) {
            this.isTyping = true;
            // TODO: Send typing indicator to server
        }

        // Stop typing after 3 seconds of inactivity
        this.typingTimeout = setTimeout(() => {
            this.isTyping = false;
            // TODO: Send stop typing indicator to server
        }, 3000);
    }

    startRealTimeUpdates() {
        // Poll for new messages every 2 seconds
        this.messagePolling = setInterval(async () => {
            await this.checkForNewMessages();
        }, 2000);
    }

    async checkForNewMessages() {
        try {
            const lastTime = this.lastMessageTime || '1970-01-01 00:00:00';
            const response = await fetch(`api/get_new_chat_messages.php?thread_id=${this.threadId}&since=${encodeURIComponent(lastTime)}`);
            const data = await response.json();

            if (data.success && data.messages && data.messages.length > 0) {
                console.log('📨 Received', data.messages.length, 'new messages');

                data.messages.forEach(message => {
                    // Only add if not already rendered
                    if (!this.renderedMessageIds.has(message.id)) {
                        this.addMessageToUI(message);
                        this.renderedMessageIds.add(message.id);
                        this.lastMessageTime = message.created_at;

                        // Update unread count if window is minimized and message is not from current user
                        if (this.isMinimized && !message.is_own) {
                            this.unreadCount++;
                            this.updateUnreadBadge();
                        }
                    } else {
                        console.log('⚠️ Skipping duplicate real-time message ID:', message.id);
                    }
                });

                this.scrollToBottom();

                // Play notification sound for new messages from others
                if (data.messages.some(m => !m.is_own && !this.renderedMessageIds.has(m.id))) {
                    this.playNotificationSound();
                }
            }
        } catch (error) {
            console.error('Error checking for new messages:', error);
        }
    }

    toggleMinimize() {
        this.isMinimized = !this.isMinimized;
        this.element.classList.toggle('minimized', this.isMinimized);

        if (!this.isMinimized) {
            this.unreadCount = 0;
            this.updateUnreadBadge();
            this.focus();
        }
    }

    show() {
        this.element.style.display = 'flex';
        if (this.isMinimized) {
            this.toggleMinimize();
        }
    }

    focus() {
        this.inputElement.focus();
        this.unreadCount = 0;
        this.updateUnreadBadge();
    }

    setPosition(rightOffset) {
        console.log('📍 setPosition called with offset:', rightOffset);

        if (!this.element) {
            console.error('❌ Cannot set position: element is null');
            this.pendingPosition = rightOffset;
            return;
        }

        console.log('✅ Setting element position to:', rightOffset + 'px');
        this.element.style.right = rightOffset + 'px';
    }

    openFullScreen() {
        window.location.href = `messages.php?thread=${this.threadId}`;
    }

    updateUnreadBadge() {
        if (this.unreadCount > 0) {
            this.unreadBadge.textContent = this.unreadCount;
            this.unreadBadge.style.display = 'inline-block';
        } else {
            this.unreadBadge.style.display = 'none';
        }
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    playNotificationSound() {
        // Simple notification sound
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
            audio.volume = 0.3;
            audio.play().catch(() => {}); // Ignore errors
        } catch (error) {
            // Ignore audio errors
        }
    }

    destroy() {
        // Clear intervals
        if (this.messagePolling) {
            clearInterval(this.messagePolling);
        }

        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }

        // Remove DOM element
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
    }
}

// Make classes globally available
window.PopupChatManager = PopupChatManager;
window.ChatWindow = ChatWindow;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (!window.popupChatManager) {
        window.popupChatManager = new PopupChatManager();
    }
});
