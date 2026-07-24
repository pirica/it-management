/**
 * Chatbot UI Logic
 */
(function() {
    const baseUrl = (window.ITM_BASE_URL || '/').replace(/\/?$/, '/');

    const chatbotActions = [
        { id: 'live_chat', label: 'Live Chat', title: 'Live Agent', href: baseUrl + 'modules/live_chat/?flow=live_agent' },
        { id: 'chat_with', label: 'Chat With', title: 'Chat with a colleague', href: baseUrl + 'modules/live_chat/?flow=chat_with' },
        { id: 'list_all', label: 'List all', title: 'List all (knowledge-base)', href: baseUrl + 'modules/knowledge_base/list_all.php', target: '_blank' },
        { id: 'reopen_ticket', label: 'Re-open ticket', title: 'Re-open a closed ticket', href: baseUrl + 'modules/live_chat/?flow=reopen_ticket' }
    ];

    const chatbotHtml = `
        <div id="itmChatbotToggle" class="itm-chatbot-toggle">💬</div>
        <div id="itmChatbotContainer" class="itm-chatbot-container hidden">
            <div class="itm-chatbot-header" id="itmChatbotHeader">
                <h3>IT Support Assistant</h3>
                <span id="itmChatbotClose">✖</span>
            </div>
            <div class="itm-chatbot-messages" id="itmChatbotMessages"></div>
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

    function buildActionButtons() {
        const actions = document.createElement('div');
        actions.className = 'itm-chatbot-actions';
        chatbotActions.forEach(function (action) {
            const link = document.createElement('a');
            link.className = 'itm-chatbot-action-btn';
            link.href = action.href;
            link.title = action.title;
            link.textContent = action.label;
            if (action.target === '_blank') {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }
            actions.appendChild(link);
        });
        return actions;
    }

    function addMessage(text, side) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'itm-chat-msg ' + side;

        let safeText = escapeHtml(text);
        msgDiv.innerHTML = safeText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
        messages.appendChild(msgDiv);
        messages.scrollTop = messages.scrollHeight;
    }

    function addBotMessageWithActions(introText) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'itm-chat-msg bot itm-chatbot-actions-msg';

        const textDiv = document.createElement('div');
        textDiv.className = 'itm-chatbot-welcome-text';
        textDiv.textContent = introText;
        msgDiv.appendChild(textDiv);
        msgDiv.appendChild(buildActionButtons());

        messages.appendChild(msgDiv);
        messages.scrollTop = messages.scrollHeight;
    }

    function isLiveChatIntent(query) {
        const q = query.toLowerCase().trim();
        if (q === '') return false;
        const patterns = [
            'live agent',
            'live chat',
            'chat with',
            'list all',
            're-open',
            'reopen',
            'reopen ticket',
            're-open ticket',
            'knowledge base',
            'start live chat',
            'message colleague'
        ];
        return patterns.some(function (p) { return q.indexOf(p) !== -1; });
    }

    addBotMessageWithActions('Hello! I am your IT Support Assistant. How can I help you today?');

    async function sendMessage() {
        const query = input.value.trim();
        if (!query) return;

        addMessage(query, 'user');
        input.value = '';

        if (isLiveChatIntent(query)) {
            addBotMessageWithActions('Choose an option below to connect with support or browse the knowledge base.');
            return;
        }

        try {
            const response = await fetch(baseUrl + 'modules/knowledge_base/chat_api.php', {
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
