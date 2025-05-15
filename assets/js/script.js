document.addEventListener('DOMContentLoaded', () => {
    const loginSection = document.getElementById('login-section');
    const chatSection = document.getElementById('chat-section');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.getElementById('login-btn');
    const loginError = document.getElementById('login-error');

    const welcomeMessage = document.getElementById('welcome-message');
    const chatWindow = document.getElementById('chat-window');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const quotaDisplay = document.getElementById('quota-display');
    const chatInfo = document.getElementById('chat-info');
    const logoutBtn = document.getElementById('logout-btn');

    let currentUser = null; 
    let currentSessionToken = null; // Pourrait être utilisé pour une authentification basée sur session plus tard

    // --- Gestion de la connexion ---
    if (loginBtn) {
        loginBtn.addEventListener('click', async () => {
            const username = usernameInput.value.trim();
            const password = passwordInput.value;

            if (!username || !password) {
                loginError.textContent = "Nom d'utilisateur et mot de passe requis.";
                return;
            }
            loginError.textContent = "";
            loginBtn.disabled = true;
            loginBtn.textContent = "Connexion...";

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const data = await response.json();

                if (data.success) {
                    currentUser = data.user;
                    // currentSessionToken = data.token; // Si vous implémentez des sessions PHP
                    localStorage.setItem('chatbotUser', JSON.stringify(currentUser)); // Sauvegarder l'utilisateur
                    showChatSection();
                    welcomeMessage.textContent = `Chat - ${currentUser.nom}`;
                    updateQuotaDisplay(currentUser.requetes_restantes, currentUser.quota_max);
                    addMessageToChat("Système", `Bienvenue ${currentUser.nom} ! Vous pouvez commencer à discuter.`, "system");
                } else {
                    loginError.textContent = data.message || "Identifiants incorrects.";
                }
            } catch (error) {
                loginError.textContent = "Erreur de connexion au serveur.";
                console.error("Login error:", error);
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = "Se connecter";
            }
        });
    }
    
    // Tentative de reconnexion si un utilisateur est en localStorage
    const savedUser = localStorage.getItem('chatbotUser');
    if (savedUser && chatSection) { // Vérifier si chatSection existe pour ne pas exécuter sur register.php
        try {
            currentUser = JSON.parse(savedUser);
            // Ici, idéalement, vous devriez re-valider la session/token avec le serveur
            // Pour la simplicité, on fait confiance au localStorage pour cet exemple.
            // Mais pour une vraie app, il faudrait envoyer un token au serveur pour vérifier la session.
            // Pour l'instant, on va juste afficher le chat si l'user est dans localStorage.
            // Et lors du premier appel à chat.php, l'auth sera revérifiée par username.
            showChatSection();
            welcomeMessage.textContent = `Chat - ${currentUser.nom}`;
            updateQuotaDisplay(currentUser.requetes_restantes, currentUser.quota_max);
             addMessageToChat("Système", `Re-bonjour ${currentUser.nom} !`, "system");
        } catch(e) {
            localStorage.removeItem('chatbotUser'); // Données corrompues
            currentUser = null;
        }
    }


    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            // Optionnel: appeler un endpoint de déconnexion côté serveur si vous utilisez des sessions PHP
            // await fetch('api/logout.php'); 
            currentUser = null;
            currentSessionToken = null;
            localStorage.removeItem('chatbotUser');
            chatWindow.innerHTML = ''; 
            showLoginSection();
            window.location.href = 'index.php?logout=success'; // Rediriger pour afficher le message
        });
    }

    function showLoginSection() {
        if (loginSection) loginSection.classList.remove('d-none');
        if (chatSection) chatSection.classList.add('d-none');
    }

    function showChatSection() {
        if (loginSection) loginSection.classList.add('d-none');
        if (chatSection) chatSection.classList.remove('d-none');
        if (userInput) userInput.focus();
    }

    // --- Gestion du Chat ---
    if (sendBtn) {
        sendBtn.addEventListener('click', handleSendMessage);
    }
    if (userInput) {
        userInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { // Envoyer avec Entrée, sauf si Maj+Entrée
                e.preventDefault(); // Empêcher le retour à la ligne par défaut d'Entrée dans textarea
                handleSendMessage();
            }
        });
    }

    async function handleSendMessage() {
        const messageText = userInput.value.trim();
        if (!messageText || !currentUser || !currentUser.username) { // Vérifier currentUser.username
            addMessageToChat("Erreur", "Vous n'êtes pas connecté correctement.", "error");
            // Option: déconnecter l'utilisateur
            // localStorage.removeItem('chatbotUser');
            // window.location.reload();
            return;
        }

        addMessageToChat(currentUser.nom || "Vous", messageText, "user");
        userInput.value = "";
        sendBtn.disabled = true;
        userInput.disabled = true;
        chatInfo.textContent = "L'IA réfléchit...";

        try {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                // Envoyer le nom d'utilisateur pour que le backend puisse identifier l'utilisateur
                // Si vous aviez un token de session, vous l'enverriez ici aussi.
                body: JSON.stringify({ username: currentUser.username, prompt: messageText })
            });
            const data = await response.json();

            if (data.success) {
                addMessageToChat("GPT", data.reply, "bot");
                updateQuotaDisplay(data.requetes_restantes, currentUser.quota_max);
                currentUser.requetes_restantes = data.requetes_restantes; // Mettre à jour l'état local
                localStorage.setItem('chatbotUser', JSON.stringify(currentUser)); // Sauvegarder l'état mis à jour

                if (data.requetes_restantes <= 0) {
                    disableChatInput("Quota atteint. Vous ne pouvez plus envoyer de messages.");
                }
            } else {
                addMessageToChat("Erreur", data.message || "Une erreur est survenue.", "error");
                if (data.quota_exceeded) {
                    disableChatInput("Quota atteint. Vous ne pouvez plus envoyer de messages.");
                }
                if (data.auth_failed) { // Si l'authentification échoue côté serveur
                    localStorage.removeItem('chatbotUser');
                    addMessageToChat("Système", "Votre session a expiré ou est invalide. Veuillez vous reconnecter.", "error");
                    setTimeout(() => window.location.reload(), 3000);
                }
            }
        } catch (error) {
            addMessageToChat("Erreur", "Impossible de contacter le serveur de chat.", "error");
            console.error("Chat error:", error);
        } finally {
            if (!currentUser || currentUser.requetes_restantes > 0) {
                sendBtn.disabled = false;
                userInput.disabled = false;
                userInput.focus();
            }
            chatInfo.textContent = "";
        }
    }

    function addMessageToChat(sender, text, type) {
        const messageContainer = document.createElement('div');
        messageContainer.classList.add('message-container', 'd-flex', 'mb-2');
         // Ajout pour le style Markdown simple (gras, italique, listes)
        const remarkable = new Remarkable({html: true, breaks: true});


        const messageElement = document.createElement('div');
        messageElement.classList.add('message', 'p-2', 'rounded');
        
        let formattedText = text;
        if (type === 'bot') { // Appliquer Markdown uniquement pour les messages du bot
             try {
                // Remplacer les blocs de code ```lang\ncode``` par <pre><code class="language-lang">code</code></pre>
                // Et les `code en ligne` par <code>code en ligne</code>
                // C'est une simplification, une vraie lib Markdown ferait mieux.
                formattedText = text.replace(/```(\w*)\n([\s\S]*?)\n```/g, (match, lang, code) => {
                    const languageClass = lang ? `language-${lang}` : '';
                    const escapedCode = code.replace(/</g, '<').replace(/>/g, '>');
                    return `<pre><code class="${languageClass}">${escapedCode}</code></pre>`;
                });
                formattedText = formattedText.replace(/`([^`]+)`/g, '<code>$1</code>');
                // Puis le reste avec Remarkable
                formattedText = remarkable.render(formattedText);
            } catch (e) {
                console.warn("Erreur de rendu Markdown:", e);
                // Fallback si Remarkable échoue ou n'est pas chargé
                formattedText = text.replace(/\n/g, '<br>');
            }
        } else {
            formattedText = text.replace(/\n/g, '<br>'); // Simple remplacement pour les messages utilisateur/système
        }


        if (type === 'user') {
            messageElement.classList.add('user-message', 'bg-primary', 'text-white', 'ms-auto');
            messageElement.innerHTML = `<strong>${sender}:</strong><br>${formattedText}`;
        } else if (type === 'bot') {
            messageElement.classList.add('bot-message', 'bg-light', 'text-dark', 'me-auto', 'border');
             messageElement.innerHTML = `<strong>${sender}:</strong><br>${formattedText}`; // remarkable.render ajoute déjà les <p> et <br>
        } else if (type === 'system' || type === 'error') {
            messageElement.classList.add('text-muted', 'fst-italic', 'small', 'text-center', 'w-100', 'bg-transparent', 'border-0');
            messageElement.style.maxWidth = '100%'; 
            messageElement.innerHTML = `<em>${text}</em>`;
        }
        

        messageContainer.appendChild(messageElement);
        chatWindow.appendChild(messageContainer);
        chatWindow.scrollTop = chatWindow.scrollHeight; 
    }

    function updateQuotaDisplay(remaining, max) {
        if (!currentUser) return; // S'assurer que currentUser est défini
        currentUser.requetes_restantes = remaining; 
        quotaDisplay.textContent = `${remaining}/${max} req.`;
        if (remaining <= 0) {
            quotaDisplay.classList.add('text-danger', 'fw-bold');
            quotaDisplay.classList.remove('text-success', 'text-warning');
        } else if (remaining < max * 0.2) { 
            quotaDisplay.classList.add('text-warning');
            quotaDisplay.classList.remove('text-success', 'text-danger', 'fw-bold');
        }
        else {
            quotaDisplay.classList.remove('text-danger', 'text-warning', 'fw-bold');
            quotaDisplay.classList.add('text-success');
        }
    }

    function disableChatInput(message) {
        userInput.disabled = true;
        sendBtn.disabled = true;
        chatInfo.textContent = message;
        chatInfo.classList.add('text-danger');
    }

    // Si Remarkable est disponible (via CDN par exemple), l'initialiser
    // Vous devrez ajouter le CDN de Remarkable dans index.php et admin/index.php si vous voulez du Markdown
    // <script src="https://cdnjs.cloudflare.com/ajax/libs/remarkable/2.0.1/remarkable.min.js"></script>
    // Pour l'instant, on suppose qu'il n'est pas là et on gère le fallback.
    if (typeof Remarkable === 'undefined') {
        console.warn("Remarkable n'est pas chargé. Le formatage Markdown sera limité.");
        // Créer un objet Remarkable factice pour éviter les erreurs si non chargé
        window.Remarkable = function() {
            return {
                render: function(text) { return text.replace(/\n/g, '<br>'); }
            };
        };
    }
});