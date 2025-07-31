document.addEventListener('DOMContentLoaded', function() {
    console.log('Chatbot JS loaded - Version 1.0.29');
    var chatInput = document.getElementById('chat-input');
    var chatOutput = document.getElementById('chat-output');
    var sendButton = document.getElementById('send-button');
    var container = document.getElementById('chat-container');

    if (!chatInput || !chatOutput || !sendButton) {
        console.error('Chat elements not found');
        return;
    }

    var userName = localStorage.getItem('sparkBotUserName') || null;
    var isFirstMessage = !userName;
    var chatHistory = JSON.parse(sessionStorage.getItem('sparkBotChatHistory')) || [];
    var isMinimized = sessionStorage.getItem('sparkBotMinimized') !== 'false'; // Start minimized

    // Load chat history
    chatHistory.forEach(function(msg) {
        var div = document.createElement('div');
        div.innerHTML = parseMarkdown(msg);
        chatOutput.appendChild(div);
    });
    scrollToBottom();

    // Minimize/maximize with bot graphic
    var titleSpan = document.createElement('span');
    titleSpan.id = 'chat-title';
    titleSpan.innerHTML = 'SparkBot';
    titleSpan.style.display = isMinimized ? 'inline-block' : 'none';
    container.prepend(titleSpan);

    // Minimize dash icon
    var minimizeDash = document.createElement('span');
    minimizeDash.id = 'minimize-dash';
    minimizeDash.textContent = '−';
    minimizeDash.style.display = isMinimized ? 'none' : 'inline-block';
    container.appendChild(minimizeDash);

    // Apply initial minimized state
    if (isMinimized) {
        chatOutput.style.display = 'none';
        chatInput.style.display = 'none';
        sendButton.style.display = 'none';
        container.style.height = '40px';
        container.style.width = window.innerWidth < 768 ? '50px' : '12.5%';
        container.classList.add('minimized');
    }

    container.addEventListener('click', function(event) {
        if (isMinimized) {
            toggleMinimize();
            chatInput.focus();
        }
    });

    minimizeDash.addEventListener('click', function(event) {
        event.stopPropagation(); // Prevent container click
        if (!isMinimized) toggleMinimize();
    });
    
    function toggleMinimize() {
        isMinimized = !isMinimized;
        chatOutput.style.display = isMinimized ? 'none' : 'block';
        chatInput.style.display = isMinimized ? 'none' : 'block';
        sendButton.style.display = isMinimized ? 'none' : 'block';
        container.style.height = isMinimized ? '40px' : 'auto';
        container.style.width = isMinimized ? (window.innerWidth < 768 ? '50px' : '12.5%') : (window.innerWidth < 768 ? '80%' : '25%');
        container.classList.toggle('minimized', isMinimized);
        titleSpan.style.display = isMinimized ? 'inline-block' : 'none';
        minimizeDash.style.display = isMinimized ? 'none' : 'inline-block';
        sessionStorage.setItem('sparkBotMinimized', isMinimized);
        if (!isMinimized) scrollToBottom();
    }

    function scrollToBottom() {
        chatOutput.scrollTop = chatOutput.scrollHeight;
    }

    function parseMarkdown(text) {
        const lines = text.split('\n');
        let result = '';
        let inList = false;

        for (let line of lines) {
            if (line.match(/^#+\s+.*/)) {
                if (inList) {
                    result += '</ul>';
                    inList = false;
                }
                if (line.match(/^#\s+(.*)/)) {
                    result += '<h1>' + line.replace(/^#\s+/, '') + '</h1>';
                } else if (line.match(/^##\s+(.*)/)) {
                    result += '<h2>' + line.replace(/^##\s+/, '') + '</h2>';
                } else if (line.match(/^###\s+(.*)/)) {
                    result += '<h3>' + line.replace(/^###\s+/, '') + '</h3>';
                }
            } else if (line.match(/^[-*+]\s+(.*)/) || line.match(/^\d+\.\s+(.*)/)) {
                if (!inList) {
                    result += '<ul>';
                    inList = true;
                }
                let content = line.replace(/^[-*+]\s+/, '').replace(/^\d+\.\s+/, '');
                result += '<li>' + content + '</li>';
            } else {
                if (inList) {
                    result += '</ul>';
                    inList = false;
                }
                result += line + '\n';
            }
        }
        if (inList) result += '</ul>';

        result = result.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        result = result.replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
        result = result.replace(/~~(.*?)~~/g, '<del>$1</del>');
        result = result.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');

        return result.trim();
    }

    function typeMessage(text, element, callback) {
        text = parseMarkdown(text);
        element.innerHTML = '';
        const fullHtml = text;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = fullHtml;
        const fullText = tempDiv.textContent;
        let index = 0;

        const interval = setInterval(function() {
            if (index < fullText.length) {
                let slicedHtml = '';
                let charCount = 0;
                const nodes = tempDiv.childNodes;
                for (let node of nodes) {
                    if (charCount >= index + 1) break;
                    if (node.nodeType === 3) {
                        const textContent = node.textContent;
                        const remainingChars = index + 1 - charCount;
                        slicedHtml += textContent.slice(0, Math.min(remainingChars, textContent.length));
                        charCount += textContent.length;
                    } else if (node.nodeType === 1) {
                        const nodeText = node.textContent;
                        if (charCount + nodeText.length <= index + 1) {
                            slicedHtml += node.outerHTML;
                            charCount += nodeText.length;
                        } else {
                            slicedHtml += '<' + node.tagName.toLowerCase() + '>';
                            const innerNodes = node.childNodes;
                            let innerCharCount = 0;
                            for (let innerNode of innerNodes) {
                                if (innerNode.nodeType === 3) {
                                    const innerText = innerNode.textContent;
                                    const remainingChars = index + 1 - charCount;
                                    const sliceLength = Math.min(remainingChars - innerCharCount, innerText.length);
                                    slicedHtml += innerText.slice(0, sliceLength);
                                    innerCharCount += sliceLength;
                                    charCount += sliceLength;
                                    if (charCount >= index + 1) break;
                                }
                            }
                            slicedHtml += '</' + node.tagName.toLowerCase() + '>';
                        }
                    }
                }
                element.innerHTML = slicedHtml;
                index++;
                scrollToBottom();
            } else {
                element.innerHTML = fullHtml;
                clearInterval(interval);
                if (callback) callback();
            }
        }, 15);
    }

    if (isFirstMessage && !chatHistory.includes("SparkBot: Hello! I'm SparkBot, your SparkPoint assistant. What's your name?")) {
        var welcomeDiv = document.createElement('div');
        chatOutput.appendChild(welcomeDiv);
        typeMessage("SparkBot: Hello! I'm SparkBot, your SparkPoint assistant. What's your name?", welcomeDiv, function() {
            scrollToBottom();
            chatHistory.push("SparkBot: Hello! I'm SparkBot, your SparkPoint assistant. What's your name?");
            sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));
        });
    }

    function sendMessage() {
        var message = chatInput.value.trim();
        console.log('Input value:', message);
        if (!message) return;

        var userLabel = userName || 'User';
        var userDiv = document.createElement('div');
        chatOutput.appendChild(userDiv);
        typeMessage(userLabel + ': ' + message, userDiv, function() {
            scrollToBottom();
            chatHistory.push(userLabel + ': ' + message);
            sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));

            if (isFirstMessage) {
                userName = message;
                localStorage.setItem('sparkBotUserName', userName);
                var replyDiv = document.createElement('div');
                chatOutput.appendChild(replyDiv);
                typeMessage('SparkBot: Nice to meet you, ' + userName + '! How can I assist you today?', replyDiv, function() {
                    scrollToBottom();
                    chatHistory.push('SparkBot: Nice to meet you, ' + userName + '! How can I assist you today?');
                    sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));
                });
                isFirstMessage = false;
                chatInput.value = '';
                return;
            }

            var requestBody = JSON.stringify({ message: message });
            console.log('Request body:', requestBody);
            var botDiv = document.createElement('div');
            chatOutput.appendChild(botDiv);
            botDiv.innerHTML = '<span class="thinking-spinner"></span>';

            fetch(bionicChatbot.ajaxUrl + '?action=bionic_gpt', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: requestBody
            })
            .then(function(response) {
                console.log('Fetch status:', response.status);
                return response.text();
            })
            .then(function(text) {
                console.log('Fetch response text:', text);
                var data = JSON.parse(text);
                console.log('AJAX Response:', data);
                if (!data || typeof data.success !== 'undefined' && !data.success) {
                    typeMessage('SparkBot: ' + (data.data || 'Unknown error'), botDiv, function() {
                        scrollToBottom();
                        chatHistory.push('SparkBot: ' + (data.data || 'Unknown error'));
                        sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));
                    });
                } else if (data.choices && data.choices.length > 0 && data.choices[0].message && data.choices[0].message.content) {
                    var reply = data.choices[0].message.content;
                    typeMessage('SparkBot: ' + reply, botDiv, function() {
                        scrollToBottom();
                        chatHistory.push('SparkBot: ' + reply);
                        sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));
                    });
                } else {
                    typeMessage('SparkBot: Unexpected response format', botDiv, function() {
                        scrollToBottom();
                        chatHistory.push('SparkBot: Unexpected response format');
                        sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));
                    });
                }
            })
            .catch(function(error) {
                console.error('Fetch error:', error);
                typeMessage('SparkBot: Error - ' + error.message, botDiv, function() {
                    scrollToBottom();
                    chatHistory.push('SparkBot: Error - ' + error.message);
                    sessionStorage.setItem('sparkBotChatHistory', JSON.stringify(chatHistory));
                });
            })
            .finally(function() {
                chatInput.value = '';
            });
        });
        chatInput.value = '';
    }

    sendButton.addEventListener('click', sendMessage);
    chatInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendMessage();
        }
    });
});