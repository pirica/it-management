/**
 * Chatbot UI Logic
 */
(function() {
    const chatbotHtml = `
        <div id="itmChatbotToggle" class="itm-chatbot-toggle">💬</div>
        <div id="itmChatbotContainer" class="itm-chatbot-container hidden">
            <div class="itm-chatbot-header" id="itmChatbotHeader">
                <h3>IT Support Assistant</h3>
                <span id="itmChatbotClose">✖</span>
            </div>
            <div class="itm-chatbot-messages" id="itmChatbotMessages">
                <div class="itm-chat-msg bot">Hello! I am your IT Support Assistant. How can I help you today?</div>
            </div>
            <div class="itm-chatbot-input-area">
                <input type="text" id="itmChatbotInput" placeholder="Type your message..." autocomplete="off">
                <button id="itmChatbotSend">Send</button>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', chatbotHtml);

    const toggle = document.getElementById('itmChatbotToggle');
    const container = document.getElementById('itmChatbotContainer');
    const closeBtn = document.getElementById('itmChatbotClose');
    const header = document.getElementById('itmChatbotHeader');
    const input = document.getElementById('itmChatbotInput');
    const sendBtn = document.getElementById('itmChatbotSend');
    const messages = document.getElementById('itmChatbotMessages');

    toggle.addEventListener('click', () => {
        container.classList.remove('hidden');
        toggle.classList.add('hidden');
    });

    closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        container.classList.add('hidden');
        toggle.classList.remove('hidden');
    });

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function addMessage(text, side) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `itm-chat-msg ${side}`;

        // Sanitize first to prevent XSS
        let safeText = escapeHtml(text);

        // Simple markdown-ish conversion for bold and newlines
        msgDiv.innerHTML = safeText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
        messages.appendChild(msgDiv);
        messages.scrollTop = messages.scrollHeight;
    }

    async function sendMessage() {
        const query = input.value.trim();
        if (!query) return;

        addMessage(query, 'user');
        input.value = '';

        try {
            const response = await fetch(`${window.ITM_BASE_URL}modules/knowledge_base/chat_api.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.ITM_CSRF_TOKEN
                },
                body: JSON.stringify({ query: query })
            });

            if (!response.ok) throw new Error('API Error');

            const data = await response.json();
            addMessage(data.response, 'bot');
        } catch (err) {
            console.error('Chatbot Error:', err);
            addMessage('Sorry, I am having trouble connecting to the support server. Please try again later.', 'bot');
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
})();
