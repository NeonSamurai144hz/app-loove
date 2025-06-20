class ChatManager {
    constructor() {
        // DOM elements
        this.chatList = document.getElementById('existing-chats');
        this.messageContainer = document.getElementById('message-container');
        this.messageForm = document.getElementById('message-form');
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-button');
        this.loadingIndicator = document.getElementById('loading-indicator');
        this.typingIndicator = document.getElementById('typing-indicator');
        this.chatWelcome = document.querySelector('.chat-welcome');
        this.activeChatHeader = document.getElementById('active-chat-header');
        this.messageFormContainer = document.getElementById('message-form-container');

        // State variables
        this.activeMatchId = null;
        this.activeUserId = null;
        this.currentUserId = null;
        this.typingTimeout = null;
        this.pusher = null;
        this.channel = null;
        this.isTyping = false;

        this.init();
    }

    async init() {
        try {
            const userResponse = await fetch('/api/auth/me');
            const userData = await userResponse.json();

            if (!userData.success) {
                window.location.href = '/login';
                return;
            }

            this.currentUserId = userData.user.id;
            console.log('Current user ID:', this.currentUserId);

            // this.initializePusher();

            // Load existing chats
            await this.loadExistingChats();

            // Set up event listeners
            this.setupEventListeners();

        } catch (error) {
            console.error('Error initializing chat:', error);
        }
    }

    async loadExistingChats() {
        try {
            console.log('Loading existing chats...');
            const response = await fetch('/api/chats');
            const data = await response.json();

            console.log('Chats response:', data);

            if (data.success) {
                this.renderExistingChats(data.chats);
            } else {
                console.error('Failed to load chats:', data.message);
            }
        } catch (error) {
            console.error('Error loading chats:', error);
        }
    }

    renderExistingChats(chats) {
        if (!this.chatList) return;

        this.chatList.innerHTML = '';

        if (chats.length === 0) {
            this.chatList.innerHTML = '<div class="no-chats">No conversations yet</div>';
            return;
        }

        chats.forEach(chat => {
            const chatItem = document.createElement('div');
            chatItem.className = 'chat-item';
            chatItem.setAttribute('data-match-id', chat.match_id);
            chatItem.setAttribute('data-user-id', chat.other_user_id);

            const profilePhoto = chat.other_pfp;
            const name = `${chat.other_name || ''} ${chat.other_last_name || ''}`.trim();
            const lastMessage = chat.last_message || 'No messages yet';

            chatItem.innerHTML = `
                <div class="chat-avatar">
                    <img src="${profilePhoto}" alt="${name}" >
                </div>
                <div class="chat-info">
                    <div class="chat-name">${name}</div>
                    <div class="chat-preview">${lastMessage}</div>
                </div>
                <div class="chat-meta">
                    ${chat.last_message_time ? `<div class="chat-time">${this.formatTime(chat.last_message_time)}</div>` : ''}
                    ${chat.message_count > 0 ? `<div class="message-count">${chat.message_count}</div>` : ''}
                </div>
            `;

            chatItem.addEventListener('click', () => {
                console.log('Opening chat:', chat.match_id, chat.other_user_id, name);
                this.openChat(chat.match_id, chat.other_user_id, name, profilePhoto);
            });

            this.chatList.appendChild(chatItem);
        });
    }

    openChatWithUser(matchId, userId, name, profilePhoto) {
        this.openChat(matchId, userId, name, profilePhoto);
    }

    async openChat(matchId, userId, name, profilePhoto) {
        console.log('Opening chat with match ID:', matchId);
        this.activeMatchId = matchId;
        this.activeUserId = userId;

        // Update UI
        this.chatWelcome.style.display = 'none';
        this.activeChatHeader.style.display = 'flex';
        this.messageContainer.style.display = 'block';
        this.messageFormContainer.style.display = 'block';

        // Update chat header
        document.getElementById('chat-avatar').src = profilePhoto;
        document.getElementById('chat-name').textContent = name;

        // Load messages
        await this.loadMessages(matchId);

        // Mark active chat item
        document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
        const activeChatItem = document.querySelector(`[data-match-id="${matchId}"]`);
        if (activeChatItem) {
            activeChatItem.classList.add('active');
        }
    }

    async loadMessages(matchId) {
        try {
            console.log('Loading messages for match:', matchId);
            if (this.loadingIndicator) this.loadingIndicator.style.display = 'block';

            const response = await fetch(`/api/messages?matchId=${matchId}`);
            const data = await response.json();

            console.log('Messages response:', data);

            if (data.success) {
                this.renderMessages(data.messages);
            } else {
                console.error('Failed to load messages:', data.message);
                this.messageContainer.innerHTML = `<div class="error-message">Failed to load messages: ${data.message}</div>`;
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            this.messageContainer.innerHTML = `<div class="error-message">Error loading messages: ${error.message}</div>`;
        } finally {
            if (this.loadingIndicator) this.loadingIndicator.style.display = 'none';
        }
    }

    renderMessages(messages) {
        if (!this.messageContainer) return;

        console.log('Rendering messages:', messages);

        if (messages.length === 0) {
            this.messageContainer.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
            return;
        }

        const messagesHTML = messages.map(message => {
            const isOwn = message.sender_id == this.currentUserId;
            const messageClass = isOwn ? 'message own' : 'message';

            return `
                <div class="${messageClass}">
                    <div class="message-content">${message.content}</div>
                    <div class="message-time">${this.formatTime(message.sent_at)}</div>
                </div>
            `;
        }).join('');

        this.messageContainer.innerHTML = messagesHTML;
        this.scrollToBottom();
    }

    setupEventListeners() {
        if (this.messageForm) {
            this.messageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
        }

        if (this.messageInput) {
            this.messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
    }

    async sendMessage() {
        const content = this.messageInput.value.trim();
        if (!content || !this.activeMatchId) {
            console.log('Cannot send message: no content or no active match');
            return;
        }

        console.log('Sending message:', content, 'to match:', this.activeMatchId);

        try {
            const response = await fetch('/api/messages', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    matchId: this.activeMatchId,
                    content: content
                })
            });

            const data = await response.json();
            console.log('Send message response:', data);

            if (data.success) {
                this.messageInput.value = '';
                // Reload messages to show the new one
                await this.loadMessages(this.activeMatchId);
            } else {
                console.error('Failed to send message:', data.message);
                alert('Failed to send message: ' + data.message);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Error sending message: ' + error.message);
        }
    }

    scrollToBottom() {
        if (this.messageContainer) {
            this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
        }
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
}