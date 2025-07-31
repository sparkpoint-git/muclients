document.addEventListener('DOMContentLoaded', () => {
    // Retrieve settings passed from WordPress
    const settings = window.ignyteChatbot || {};
    const botName = settings.botName || 'Ignyte Bot';
    const ajaxUrl = settings.ajaxUrl;
    const nonce = settings.nonce;

    // Find chat elements
    const container = document.getElementById('chat-container');
    const chatInput = document.getElementById('chat-input');
    const chatOutput = document.getElementById('chat-output');
    const sendButton = document.getElementById('send-button');

    if (!container || !chatInput || !chatOutput || !sendButton || !ajaxUrl) {
        console.error('Ignyte Chatbot: A required element or setting is missing.');
        return;
    }

    // State variables
    let chatHistory = JSON.parse(localStorage.getItem('ignyteChatHistory')) || [];
    let isMinimized = localStorage.getItem('ignyteMinimized') !== 'false';
    let isDragging = false;
    let isResizing = false;
    let startX, startY, startLeft, startTop, startWidth, startHeight;

    function initChat() {
        // Create header and its components
        const header = document.createElement('div');
        header.className = 'chat-header';
        
        const titleSpan = document.createElement('span');
        titleSpan.id = 'chat-title';
        titleSpan.textContent = botName; // Use the dynamic bot name
        
        const minimizeIcon = document.createElement('span');
        minimizeIcon.id = 'minimize-icon';
        minimizeIcon.innerHTML = '&#8722;'; // Minus sign
        
        header.appendChild(titleSpan);
        header.appendChild(minimizeIcon);
        container.prepend(header);

        // Load history and set initial state
        chatHistory.forEach(msg => appendMessage(msg.user, msg.text, false, true));
        scrollToBottom();
        restoreState();

        if (isMinimized) {
            container.classList.add('minimized');
        }

        // Send initial greeting if chat is new
        if (chatHistory.length === 0) {
            const greeting = `Hi! I'm ${botName}, how can I help you today?`;
            appendMessage(botName, greeting, true);
        }

        attachEventListeners();
    }

    function restoreState() {
        const savedSize = JSON.parse(localStorage.getItem('ignyteSize'));
        if (savedSize) {
            container.style.width = savedSize.width;
            container.style.height = savedSize.height;
        }

        const savedPosition = JSON.parse(localStorage.getItem('ignytePosition'));
        if (savedPosition) {
            container.style.top = savedPosition.top;
            container.style.left = savedPosition.left;
        } else {
            // Default position
            container.style.top = '45px';
            container.style.left = '45px';
        }
    }

    function attachEventListeners() {
        sendButton.addEventListener('click', handleSendMessage);
        chatInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                handleSendMessage();
            }
        });

        const header = container.querySelector('.chat-header');
        header.addEventListener('mousedown', onDragStart);
        
        const minimizeIcon = container.querySelector('#minimize-icon');
        minimizeIcon.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMinimize();
        });

        container.addEventListener('mousedown', onResizeStart);
    }

    function toggleMinimize() {
        isMinimized = !isMinimized;
        container.classList.toggle('minimized');
        localStorage.setItem('ignyteMinimized', isMinimized);
        if (!isMinimized) {
            scrollToBottom();
            chatInput.focus();
        }
    }

    function onDragStart(e) {
        if (e.target.id === 'minimize-icon' || isResizing) return;
        e.preventDefault();
        
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        startLeft = container.offsetLeft;
        startTop = container.offsetTop;

        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', onDragEnd);
    }

    function onDrag(e) {
        if (!isDragging) return;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        let newLeft = startLeft + dx;
        let newTop = startTop + dy;

        // Keep within viewport
        newLeft = Math.max(0, Math.min(newLeft, window.innerWidth - container.offsetWidth));
        newTop = Math.max(0, Math.min(newTop, window.innerHeight - container.offsetHeight));

        container.style.left = `${newLeft}px`;
        container.style.top = `${newTop}px`;
    }

    function onDragEnd(e) {
        if (!isDragging) return;
        isDragging = false;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', onDragEnd);

        // Check if it was a click or a drag
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        if (Math.abs(dx) < 5 && Math.abs(dy) < 5) {
            toggleMinimize();
        } else {
            localStorage.setItem('ignytePosition', JSON.stringify({
                top: container.style.top,
                left: container.style.left
            }));
        }
    }

    function onResizeStart(e) {
        const rect = container.getBoundingClientRect();
        const handleSize = 20; // As defined in CSS
        if (e.clientX > rect.right - handleSize && e.clientY > rect.bottom - handleSize) {
            isResizing = true;
            e.preventDefault();
            startX = e.clientX;
            startY = e.clientY;
            startWidth = parseInt(document.defaultView.getComputedStyle(container).width, 10);
            startHeight = parseInt(document.defaultView.getComputedStyle(container).height, 10);

            document.addEventListener('mousemove', onResize);
            document.addEventListener('mouseup', onResizeEnd);
        }
    }

    function onResize(e) {
        if (!isResizing) return;
        let newWidth = startWidth + (e.clientX - startX);
        let newHeight = startHeight + (e.clientY - startY);

        // Enforce boundaries
        newWidth = Math.max(320, Math.min(newWidth, window.innerWidth - container.offsetLeft));
        newHeight = Math.max(400, Math.min(newHeight, window.innerHeight - container.offsetTop));

        container.style.width = `${newWidth}px`;
        container.style.height = `${newHeight}px`;
    }

    function onResizeEnd() {
        if (!isResizing) return;
        isResizing = false;
        document.removeEventListener('mousemove', onResize);
        document.removeEventListener('mouseup', onResizeEnd);
        
        localStorage.setItem('ignyteSize', JSON.stringify({
            width: container.style.width,
            height: container.style.height
        }));
    }

    function handleSendMessage() {
        const messageText = chatInput.value.trim();
        if (!messageText) return;

        chatInput.value = '';
        appendMessage('User', messageText);
        fetchBotResponse(messageText);
    }

    function fetchBotResponse(message) {
        const thinkingDiv = appendMessage(botName, '', false, true, true);
        
        fetch(`${ajaxUrl}?action=ignyte_chatbot_proxy`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message, nonce: nonce }) // Send nonce
        })
        .then(response => response.json())
        .then(data => {
            thinkingDiv.remove();
            if (data.success) {
                const reply = data.data?.choices?.[0]?.message?.content || 'Sorry, I could not generate a response.';
                appendMessage(botName, reply, true);
            } else {
                const errorMsg = data.data?.message || 'An unknown error occurred.';
                appendMessage(botName, errorMsg, true);
            }
        })
        .catch(error => {
            console.error('Ignyte Chatbot Fetch error:', error);
            thinkingDiv.remove();
            appendMessage(botName, 'I did not quite understand, can you explain?', true);
        });
    }

    function appendMessage(user, text, isTyping, instant = false, isThinking = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = (user === botName) ? 'chat-message bot-message' : 'chat-message user-message';
        
        const contentSpan = document.createElement('span');
        messageDiv.appendChild(contentSpan);
        chatOutput.appendChild(messageDiv);

        if (isThinking) {
            contentSpan.innerHTML = '<span class="thinking-spinner"></span>';
            scrollToBottom();
            return messageDiv;
        }

        if (!instant) {
            chatHistory.push({ user, text });
            localStorage.setItem('ignyteChatHistory', JSON.stringify(chatHistory));
        }

        if (isTyping && !instant) {
            typeMessage(text, contentSpan);
        } else {
            contentSpan.innerHTML = parseMarkdown(text);
        }
        
        scrollToBottom();
        return messageDiv;
    }

    function typeMessage(text, element) {
        const safeText = String(text || '');
        const tokens = safeText.split(/(\s+)/);
        const speed = 20;
        let i = 0;
        let buffer = '';

        function typing() {
            if (i < tokens.length) {
                buffer += tokens[i];
                element.innerHTML = parseMarkdown(buffer);
                i++;
                scrollToBottom();
                setTimeout(typing, speed);
            }
        }
        typing();
    }

    function parseMarkdown(text) {
        const safeText = String(text || '');
        // Basic markdown for bold, italics, and newlines
        let html = safeText
            .replace(/\n/g, '<br />')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Convert URLs to clickable links
        const urlRegex = /(https?:\/\/[^\s<]+)/g;
        html = html.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');

        return html;
    }

    function scrollToBottom() {
        chatOutput.scrollTop = chatOutput.scrollHeight;
    }

    // Start the chatbot
    initChat();
});
