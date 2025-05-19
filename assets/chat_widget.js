class ChatWidget{
  constructor(threadId, mount, stickers) {
     this.tid = threadId;
     this.after = 0;
     this.box = mount;
     this.stickers = stickers;
     this.render();
     this.poll();
     this.markAsRead();
     // inside ChatWidget.open()
    fetch(`api/chat_mark_read.php?thread_id=${this.tid}`);

    this.heartbeat = () => {
        const ids = Array.from(this.body.querySelectorAll('[data-me="1"]'))
                        .map(el => el.dataset.msgId);
                        if (!ids.length) { setTimeout(this.heartbeat, 2000); return; }
                        
                               fetch('api/chat_status.php', {
                                   method: 'POST',
                                   body: new URLSearchParams({ ids: JSON.stringify(ids) })
                               })
                               .then(r => r.json())
                               .then(rows => {
                                   rows.forEach(s => {
                                       const el = this.body.querySelector(
                                           `[data-msg-id="${s.id}"] .status-tick`
                                       );
                                       if (!el) return;
                                       if (s.read_at)           el.innerHTML = '<span class="read">✓✓</span>';
                                       else if (s.delivered_at) el.innerHTML = '<span class="delivered">✓</span>';
                                   });
                                   setTimeout(this.heartbeat, 2000);
                               })
                               .catch(()=>setTimeout(this.heartbeat, 4000));
                           };
                           this.heartbeat();            // start polling

  }
  render(){
     this.box.innerHTML = `
       <div class="chat-body"></div>
       <div class="chat-input">
         <div class="file-preview" style="display: none;"></div>
         <input type="file" id="filePick" multiple hidden>
         <button class="btn btn-sm btn-outline-secondary" id="btnFile" title="Attach">📎</button>
         <textarea id="msg" rows="1" class="form-control d-inline" placeholder="Type a message..."></textarea>
         <button class="btn btn-sm btn-outline-secondary" id="btnEmoji" title="Emoji">😊</button>
         <button class="btn btn-primary" id="btnSend">Send</button>
       </div>`;
     this.body = this.box.querySelector('.chat-body');
     this.filePreview = this.box.querySelector('.file-preview');
     this.box.querySelector('#btnFile').onclick = () => this.box.querySelector('#filePick').click();
     this.box.querySelector('#filePick').onchange = (e) => this.handleFileSelect(e);
     this.box.querySelector('#btnEmoji').onclick = (e) => this.toggleEmojiPicker(e);
     this.box.querySelector('#btnSend').onclick = () => this.send();
     this.box.querySelector('#msg').onkeydown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            this.send();
        }
     };
  }
  toggleEmojiPicker(e) {
     e.preventDefault();
     e.stopPropagation();
     
     const picker = document.getElementById('picker');
     if (!picker) return;
     
     // Clear existing stickers
     picker.innerHTML = '';
     
     // Add stickers to picker
     this.stickers.forEach(sticker => {
        const img = document.createElement('img');
        img.src = `assets/stickers/${sticker}.gif`;
        img.className = 'sticker';
        img.title = `:${sticker}:`;
        img.onclick = (e) => {
           e.stopPropagation();
           const textarea = this.box.querySelector('#msg');
           if (textarea) {
              const cursorPos = textarea.selectionStart;
              const textBefore = textarea.value.substring(0, cursorPos);
              const textAfter = textarea.value.substring(textarea.selectionEnd);
              textarea.value = textBefore + `:${sticker}:` + textAfter;
              textarea.focus();
              textarea.selectionStart = textarea.selectionEnd = cursorPos + sticker.length + 2;
           }
           picker.style.display = 'none';
        };
        picker.appendChild(img);
     });
     
     // Position picker
     const rect = e.target.getBoundingClientRect();
     picker.style.bottom = `${window.innerHeight - rect.top + 10}px`;
     picker.style.right = `${window.innerWidth - rect.right}px`;
     picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
  }
  handleFileSelect(e) {
     const files = Array.from(e.target.files);
     if (files.length === 0) return;

     this.filePreview.style.display = 'flex';
     this.filePreview.innerHTML = '';
     
     files.forEach(file => {
        const fileContainer = document.createElement('div');
        fileContainer.className = 'file-preview-item';
        
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            fileContainer.appendChild(img);
        }
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        fileInfo.textContent = file.name;
        fileContainer.appendChild(fileInfo);
        
        const removeButton = document.createElement('span');
        removeButton.className = 'remove-file';
        removeButton.innerHTML = '×';
        removeButton.onclick = () => {
            fileContainer.remove();
            if (this.filePreview.children.length === 0) {
                this.filePreview.style.display = 'none';
            }
            // Remove file from input
            const dt = new DataTransfer();
            const input = this.box.querySelector('#filePick');
            const { files } = input;
            for (let i = 0; i < files.length; i++) {
                if (files[i] !== file) {
                    dt.items.add(files[i]);
                }
            }
            input.files = dt.files;
        };
        fileContainer.appendChild(removeButton);
        
        this.filePreview.appendChild(fileContainer);
     });
  }
  async send(){
     const txt=this.box.querySelector('#msg').value.trim();
     const files = Array.from(this.box.querySelector('#filePick').files);
     if(!txt && files.length === 0) return;
     const fd=new FormData();
     fd.append('thread_id',this.tid);
     fd.append('body',txt);
     files.forEach(file => fd.append('files[]', file));
     try {
        console.log('Sending message to thread:', this.tid);
        const response = await fetch('api/chat_send.php',{method:'POST',body:fd});
        console.log('Response status:', response.status);
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse response as JSON:', parseError);
            throw new Error('Server returned invalid JSON: ' + responseText);
        }
        
        if (data.error) {
            console.error('Error sending message:', data.error);
            alert('Error sending message: ' + data.error);
            return;
        }
        this.box.querySelector('#msg').value='';
        this.box.querySelector('#filePick').value='';
        this.filePreview.style.display = 'none';
        this.filePreview.innerHTML = '';
        
        // Immediately poll for the new message
        await this.poll();
     } catch (error) {
        console.error('Error sending message:', error);
        alert('Error sending message: ' + error.message);
     }
  }
  async poll(){
     try {
        const response = await fetch(`api/chat_poll.php?thread_id=${this.tid}&after_id=${this.after}`);
        const rows = await response.json();
        if (rows.error) {
            console.error('Error polling messages:', rows.error);
            return;
        }
        rows.forEach(msg => {
            if (msg.id > this.after) {
                this.after = msg.id;
                const messageElement = this.renderMessage(msg);
                this.body.appendChild(messageElement);
                this.body.scrollTop = this.body.scrollHeight;
            }
        });
     } catch (error) {
        console.error('Error polling messages:', error);
     }
     setTimeout(()=>this.poll(),2000);
  }
  async markAsRead() {
     try {
        const response = await fetch('api/chat_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                thread_id: this.tid
            })
        });
        const data = await response.json();
        if (data.error) {
            console.error('Error marking messages as read:', data.error);
        }
     } catch (error) {
        console.error('Error marking messages as read:', error);
     }
  }
  renderMessage(msg) {
    const div = document.createElement('div');
    div.className = `msg-${msg.sender_id == window.me ? 'me' : 'you'}`;
    div.dataset.msgId = msg.id;
    div.dataset.me = (msg.sender_id == window.me ? '1' : '0');
    
    const content = document.createElement('div');
    content.className = 'message-content';
    
    // Add status tick before message if it's from current user
    if (msg.sender_id == window.me) {
        const tick = document.createElement('span');
        tick.className = 'status-tick';
        
        if (msg.read_at) {
            tick.innerHTML = '<span class="read">✓✓</span>';
        } else if (msg.delivered_at) {
            tick.innerHTML = '<span class="delivered">✓</span>';
        } else {
            tick.innerHTML = '<span class="sent">✓</span>';
        }
        content.appendChild(tick);
    }
    
    // Add message text with preserved line breaks and clickable URLs
    if (msg.body) {
        const text = document.createElement('span');
        text.className = 'message-text';
        
        // First replace URLs with clickable links (including domain-only URLs)
        const urlRegex = /(https?:\/\/[^\s]+)|(www\.[^\s]+)|([a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+\.?)/g;
        const linkedText = msg.body.replace(urlRegex, url => {
            if (url.match(/^(https?:\/\/|www\.)/)) {
                const href = url.startsWith('www.') ? `https://${url}` : url;
                return `<a href="${href}" target="_blank">${url}</a>`;
            } else if (url.match(/^[a-zA-Z0-9][-a-zA-Z0-9]{0,62}(\.[a-zA-Z0-9][-a-zA-Z0-9]{0,62})+\.?$/)) {
                return `<a href="https://${url}" target="_blank">${url}</a>`;
            }
            return url;
        });
        
        // Then replace emoticons
        const emoticonText = linkedText.replace(/:(\w+):/g, (_, name) => {
            return `<img src="assets/stickers/${name}.gif" class="sticker" title=":${name}:">`;
        });
        
        // Preserve line breaks without extra spacing
        text.innerHTML = emoticonText.replace(/\n/g, '<br>');
        content.appendChild(text);
    }
    
    // Add file previews if present
    if (msg.file_path) {
        const filePaths = msg.file_path.split(',');
        filePaths.forEach(filePath => {
            const fileExt = filePath.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt);
            
            const filePreview = document.createElement('div');
            filePreview.className = 'file-preview';
            
            if (isImage) {
                const img = document.createElement('img');
                img.src = filePath;
                img.style.maxWidth = '200px';
                img.style.maxHeight = '200px';
                img.style.cursor = 'pointer';
                img.onclick = () => window.open(filePath, '_blank');
                filePreview.appendChild(img);
            } else {
                const fileLink = document.createElement('a');
                fileLink.href = filePath;
                fileLink.target = '_blank';
                fileLink.textContent = 'Download File';
                filePreview.appendChild(fileLink);
            }
            
            content.appendChild(filePreview);
        });
    }
    
    div.appendChild(content);
    
    // Add timestamp below the message
    const timestamp = document.createElement('div');
    timestamp.className = 'message-timestamp';
    const date = new Date(msg.sent_at);
    timestamp.textContent = date.toLocaleString();
    
    div.appendChild(timestamp);
    return div;
  }
}
