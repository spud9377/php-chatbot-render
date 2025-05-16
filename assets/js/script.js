document.addEventListener('DOMContentLoaded', () => {
    // Sections principales
    const loginSection = document.getElementById('login-section');
    const simulationCodeSection = document.getElementById('simulation-code-section');
    const chatSection = document.getElementById('chat-section');
    
    // Éléments de la section de connexion
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.getElementById('login-btn');
    const loginError = document.getElementById('login-error');

    // Éléments de la section du code de simulation
    const simulationCodeInput = document.getElementById('simulation-code');
    const simulationRoleSelect = document.getElementById('simulation-role');
    const submitSimulationCodeBtn = document.getElementById('submit-simulation-code-btn');
    const simulationCodeError = document.getElementById('simulation-code-error');
    const skipSimulationBtn = document.getElementById('skip-simulation-btn');

    // Éléments de la section de chat
    const welcomeMessage = document.getElementById('welcome-message');
    const chatWindow = document.getElementById('chat-window');
    const userInput = document.getElementById('user-input');
    const sendBtn = document.getElementById('send-btn');
    const quotaDisplay = document.getElementById('quota-display');
    const chatInfo = document.getElementById('chat-info');
    const logoutBtn = document.getElementById('logout-btn');

    // État de l'utilisateur et de la simulation
    let currentUser = null; 
    let simulationProfile = null; // Peut être 'etudiant', 'jury', ou null (mode standard)
    let collectingSimParams = false; // True si on est en train de collecter les 8 paramètres
    let simParamsToCollect = ["Nom", "Prénom", "Classe", "Niveau (1, 2, ou 3)", "Ville de la simulation", "Secteur d'activité de l'entreprise du scénario", "Type de client (Particulier, Professionnel)", "Nom de l'entreprise du scénario"];
    let collectedSimParams = {};
    let currentParamIndex = 0;


    // Initialiser Remarkable (pour le rendu Markdown des messages du bot)
    let remarkable;
    if (typeof Remarkable !== 'undefined') {
        remarkable = new Remarkable({html: true, breaks: true, linkify: true});
    } else {
        console.warn("Remarkable.js n'est pas chargé. Le formatage Markdown sera limité.");
        // Fallback simple si Remarkable n'est pas là
        remarkable = { 
            render: function(text) { 
                return text.replace(/&/g, "&")
                           .replace(/</g, "<")
                           .replace(/>/g, ">")
                           .replace(/"/g, """)
                           .replace(/'/g, "'")
                           .replace(/\n/g, '<br>'); 
            } 
        };
    }

    // --- GESTION DE L'ÉTAT INITIAL ET DE LA CONNEXION ---

    if (loginBtn) {
        loginBtn.addEventListener('click', handleLogin);
    }
    if (passwordInput) { // Permettre la connexion avec la touche Entrée dans le champ mot de passe
        passwordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleLogin();
            }
        });
    }


    async function handleLogin() {
        // Vérifier que les éléments existent avant de lire leur valeur
        const username = usernameInput ? usernameInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';

        if (!username || !password) {
            if (loginError) loginError.textContent = "Nom d'utilisateur et mot de passe requis.";
            return;
        }
        if (loginError) loginError.textContent = "";
        if (loginBtn) {
            loginBtn.disabled = true;
            loginBtn.textContent = "Connexion...";
        }

        try {
            const response = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await response.json();

            if (data.success) {
                currentUser = data.user;
                localStorage.setItem('chatbotUser', JSON.stringify(currentUser));
                // Après une connexion réussie, on propose toujours de choisir le mode de simulation
                showSimulationCodeSection(); 
            } else {
                if (loginError) loginError.textContent = data.message || "Identifiants incorrects.";
            }
        } catch (error) {
            if (loginError) loginError.textContent = "Erreur de connexion au serveur.";
            console.error("Login error:", error);
        } finally {
            if (loginBtn) {
                loginBtn.disabled = false;
                loginBtn.textContent = "Se connecter";
            }
        }
    }
    
    // --- GESTION DU CODE DE SIMULATION ---

    if (submitSimulationCodeBtn) {
        submitSimulationCodeBtn.addEventListener('click', handleSubmitSimulationCode);
    }
     if (simulationCodeInput) {
        simulationCodeInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleSubmitSimulationCode();
            }
        });
    }

    async function handleSubmitSimulationCode() {
        const code = simulationCodeInput ? simulationCodeInput.value.trim() : '';
        const role = simulationRoleSelect ? simulationRoleSelect.value : 'etudiant';

        if (!code) {
            if (simulationCodeError) simulationCodeError.textContent = "Veuillez entrer un code d'accès.";
            return;
        }
        if (simulationCodeError) simulationCodeError.textContent = "";
        if (submitSimulationCodeBtn) {
            submitSimulationCodeBtn.disabled = true;
            submitSimulationCodeBtn.textContent = "Validation...";
        }

        try {
            const response = await fetch('api/set_simulation_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    username: currentUser.username, // Assurez-vous que currentUser est défini
                    simulation_code: code, 
                    role: role 
                })
            });
            const data = await response.json();

            if (data.success) {
                simulationProfile = data.profile; 
                localStorage.setItem('chatbotSimulationProfile', simulationProfile);
                showChatSection();
                if (welcomeMessage) welcomeMessage.textContent = `Simulation (${simulationProfile}) - ${currentUser.nom}`;
                addMessageToChat("Système", data.message_to_user || `Profil de simulation '${simulationProfile}' activé.`, "system");
                
                // Démarrer la collecte des paramètres si c'est un étudiant ou un jury qui veut définir un scénario
                if (simulationProfile === 'etudiant' || simulationProfile === 'jury') { // Jury pourrait aussi vouloir configurer un scénario à observer
                    startSimParamsCollection();
                }
            } else {
                if (simulationCodeError) simulationCodeError.textContent = data.message || "Code ou rôle invalide.";
            }
        } catch (error) {
            if (simulationCodeError) simulationCodeError.textContent = "Erreur de communication avec le serveur.";
            console.error("Simulation code error:", error);
        } finally {
            if (submitSimulationCodeBtn) {
                submitSimulationCodeBtn.disabled = false;
                submitSimulationCodeBtn.textContent = "Valider le profil de simulation";
            }
        }
    }

    if (skipSimulationBtn) {
        skipSimulationBtn.addEventListener('click', () => {
            simulationProfile = null; 
            collectingSimParams = false;
            localStorage.removeItem('chatbotSimulationProfile');
            showChatSection();
            if (welcomeMessage) welcomeMessage.textContent = `Chat Standard - ${currentUser.nom}`;
            addMessageToChat("Système", `Bienvenue ${currentUser.nom} ! Vous utilisez le chatbot en mode standard.`, "system");
        });
    }

    // --- RECONNEXION ET GESTION DE L'ÉTAT AU CHARGEMENT DE LA PAGE ---
    const savedUserJSON = localStorage.getItem('chatbotUser');
    const savedSimProfile = localStorage.getItem('chatbotSimulationProfile');

    if (savedUserJSON) {
        try {
            currentUser = JSON.parse(savedUserJSON);
            // TODO: Idéalement, revalider la session avec le backend ici pour la sécurité
            // fetch('api/check_session.php').then(res => res.json()).then(data => { if(!data.valid) logout(); });
            
            if (savedSimProfile) {
                simulationProfile = savedSimProfile;
                // Si un profil de simulation était actif, on vérifie si on était en train de collecter des params
                // Pour la simplicité, on va redemander les params si la page a été rechargée pendant la collecte.
                // Une gestion plus fine impliquerait de sauvegarder collectedSimParams et currentParamIndex dans localStorage.
                if (localStorage.getItem('collectingSimParams') === 'true') {
                    showChatSection(); // Aller au chat, car on va continuer la collecte
                    if (welcomeMessage) welcomeMessage.textContent = `Simulation (${simulationProfile}) - ${currentUser.nom}`;
                    addMessageToChat("Système", "Reprise de la configuration de la simulation...", "system");
                    startSimParamsCollection(true); // true pour indiquer une reprise
                } else {
                    showChatSection();
                    if (welcomeMessage) welcomeMessage.textContent = `Simulation (${simulationProfile}) - ${currentUser.nom}`;
                    addMessageToChat("Système", `Session de simulation (${simulationProfile}) reprise.`, "system");
                }
            } else {
                // Utilisateur connecté, mais pas de profil de simulation choisi ou en cours
                showSimulationCodeSection();
            }
            if (currentUser && currentUser.quota_max !== undefined) {
                 updateQuotaDisplay(currentUser.requetes_restantes, currentUser.quota_max);
            }
        } catch(e) {
            console.error("Erreur parsing localStorage:", e);
            logoutAndReset();
        }
    } else {
        showLoginSection(); // Si pas d'utilisateur, afficher la section de connexion
    }

    // --- DÉCONNEXION ---
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logoutAndReset);
    }

    function logoutAndReset() {
        // Optionnel : appeler un endpoint serveur pour invalider la session PHP
        // fetch('api/logout_session.php'); 
        currentUser = null;
        simulationProfile = null;
        collectingSimParams = false;
        collectedSimParams = {};
        currentParamIndex = 0;
        localStorage.removeItem('chatbotUser');
        localStorage.removeItem('chatbotSimulationProfile');
        localStorage.removeItem('collectingSimParams'); 
        if (chatWindow) chatWindow.innerHTML = ''; 
        showLoginSection();
        if (loginBtn) { // S'assurer que le bouton de login est réactivé
             loginBtn.disabled = false;
             loginBtn.textContent = "Se connecter";
        }
        // Rediriger avec un message de déconnexion n'est pas toujours nécessaire si on gère tout en JS.
        // window.location.href = 'index.php?logout=success'; 
        if (loginError) loginError.textContent = "Vous avez été déconnecté."; // Simple message
    }


    // --- AFFICHAGE DES SECTIONS ---
    function showLoginSection() {
        if (loginSection) loginSection.classList.remove('d-none');
        if (simulationCodeSection) simulationCodeSection.classList.add('d-none');
        if (chatSection) chatSection.classList.add('d-none');
    }
    function showSimulationCodeSection() {
        if (loginSection) loginSection.classList.add('d-none');
        if (simulationCodeSection) simulationCodeSection.classList.remove('d-none');
        if (chatSection) chatSection.classList.add('d-none');
        if (simulationCodeInput) simulationCodeInput.focus();
    }
    function showChatSection() {
        if (loginSection) loginSection.classList.add('d-none');
        if (simulationCodeSection) simulationCodeSection.classList.add('d-none');
        if (chatSection) chatSection.classList.remove('d-none');
        if (userInput) userInput.focus();
        if (currentUser && currentUser.quota_max !== undefined) {
             updateQuotaDisplay(currentUser.requetes_restantes, currentUser.quota_max);
        }
    }

    // --- COLLECTE DES PARAMÈTRES DE SIMULATION ---
    function startSimParamsCollection(isReprise = false) {
        collectingSimParams = true;
        localStorage.setItem('collectingSimParams', 'true'); // Sauvegarder l'état de collecte
        collectedSimParams = {}; // Réinitialiser pour une nouvelle collecte (ou charger depuis localStorage si reprise plus avancée)
        currentParamIndex = 0;   // Idem

        if (!isReprise) {
            addMessageToChat("Système", "Pour commencer la simulation, veuillez fournir les informations suivantes :", "system");
        }
        askNextSimParam();
    }

    function askNextSimParam() {
        if (currentParamIndex < simParamsToCollect.length) {
            const paramName = simParamsToCollect[currentParamIndex];
            addMessageToChat("Système", `Veuillez entrer : **${paramName}**`);
            // Le message de l'utilisateur sera la réponse à cette question.
        } else {
            // Tous les paramètres ont été collectés
            collectingSimParams = false;
            localStorage.removeItem('collectingSimParams');
            addMessageToChat("Système", "Merci ! Tous les paramètres ont été collectés. La simulation va commencer.");
            // Envoyer les paramètres collectés au backend pour qu'il les stocke en session
            // et initie la première étape de la simulation avec l'IA.
            sendCollectedParamsToBackend();
        }
    }
    
    async function sendCollectedParamsToBackend() {
        if (!currentUser || !simulationProfile) return;

        sendBtn.disabled = true;
        userInput.disabled = true;
        chatInfo.textContent = "Configuration de la simulation...";

        try {
            const response = await fetch('api/chat.php', { // On utilise chat.php, qui aura une logique spéciale
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    username: currentUser.username, 
                    simulation_profile: simulationProfile,
                    action: "start_simulation_with_params", // Action spécifique
                    params: collectedSimParams 
                })
            });
            const data = await response.json();

            if (data.success && data.reply) { // Le backend devrait renvoyer la première intervention de l'IA
                addMessageToChat("GPT", data.reply, "bot");
                 if(data.requetes_restantes !== undefined) { // Le backend peut avoir utilisé un quota pour cette initialisation
                    updateQuotaDisplay(data.requetes_restantes, currentUser.quota_max);
                    currentUser.requetes_restantes = data.requetes_restantes;
                    localStorage.setItem('chatbotUser', JSON.stringify(currentUser));
                }
            } else {
                addMessageToChat("Erreur", data.message || "Erreur lors du démarrage de la simulation.", "error");
            }
        } catch (error) {
            addMessageToChat("Erreur", "Impossible de démarrer la simulation avec le serveur.", "error");
            console.error("Start sim error:", error);
        } finally {
            if (!currentUser || (currentUser.requetes_restantes !== undefined && currentUser.requetes_restantes > 0)) {
                sendBtn.disabled = false;
                userInput.disabled = false;
                if(userInput) userInput.focus();
            }
            chatInfo.textContent = "";
        }
    }


    // --- GESTION DE L'ENVOI DES MESSAGES (CHAT) ---
    if (sendBtn) {
        sendBtn.addEventListener('click', handleSendMessage);
    }
    if (userInput) {
        userInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { 
                e.preventDefault(); 
                handleSendMessage();
            }
        });
    }

    async function handleSendMessage() {
        const messageText = userInput.value.trim();
        if (!messageText) return;
        if (!currentUser || !currentUser.username) {
            addMessageToChat("Erreur", "Vous n'êtes pas connecté correctement.", "error");
            logoutAndReset(); // Forcer la déconnexion si l'état est incohérent
            return;
        }

        addMessageToChat(currentUser.nom || "Vous", messageText, "user");
        userInput.value = ""; // Vider le champ de saisie

        if (collectingSimParams) {
            // Si on est en train de collecter des paramètres pour la simulation
            const paramName = simParamsToCollect[currentParamIndex];
            collectedSimParams[paramName.split(" ")[0].toLowerCase()] = messageText; // Clé simple (ex: "nom", "prénom")
            currentParamIndex++;
            askNextSimParam();
            return; // Ne pas envoyer à l'IA pour une réponse normale
        }
        
        // Logique d'envoi normal à l'IA
        sendBtn.disabled = true;
        userInput.disabled = true;
        chatInfo.textContent = "L'IA réfléchit...";

        try {
            const payload = { 
                username: currentUser.username, 
                prompt: messageText,
                simulation_profile: simulationProfile // Peut être null
            };
            // Si une simulation est active et configurée, on pourrait ajouter l'état de la sim ici
            // if (simulationProfile && localStorage.getItem('simParamsCollected') === 'true') {
            //    payload.simulation_state = { niveau: X, ... }; // À définir
            // }

            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            if (data.success) {
                addMessageToChat("GPT", data.reply, "bot");
                updateQuotaDisplay(data.requetes_restantes, currentUser.quota_max);
                currentUser.requetes_restantes = data.requetes_restantes; 
                localStorage.setItem('chatbotUser', JSON.stringify(currentUser)); 

                if (data.requetes_restantes <= 0) {
                    disableChatInput("Quota atteint. Vous ne pouvez plus envoyer de messages.");
                }
            } else {
                addMessageToChat("Erreur", data.message || "Une erreur est survenue.", "error");
                if (data.quota_exceeded) {
                    disableChatInput("Quota atteint. Vous ne pouvez plus envoyer de messages.");
                }
                if (data.auth_failed) { 
                    logoutAndReset();
                    addMessageToChat("Système", "Votre session a expiré ou est invalide. Veuillez vous reconnecter.", "error");
                    // Le addMessageToChat après logoutAndReset n'apparaîtra pas, mais c'est pour l'idée.
                }
            }
        } catch (error) {
            addMessageToChat("Erreur", "Impossible de contacter le serveur de chat.", "error");
            console.error("Chat error:", error);
        } finally {
            // Réactiver les boutons seulement si le quota n'est pas atteint.
            if (!currentUser || (currentUser.requetes_restantes !== undefined && currentUser.requetes_restantes > 0)) {
                sendBtn.disabled = false;
                userInput.disabled = false;
                if(userInput) userInput.focus();
            }
            chatInfo.textContent = "";
        }
    }

    // --- FONCTIONS UTILITAIRES POUR LE CHAT ---
    function addMessageToChat(sender, text, type = 'system') { // type par défaut 'system'
        if (!text) return; // Ne pas ajouter de messages vides

        const messageContainer = document.createElement('div');
        messageContainer.classList.add('message-container', 'd-flex', 'mb-2');
        
        const messageElement = document.createElement('div');
        messageElement.classList.add('message', 'p-2', 'rounded', 'shadow-sm');
        
        let formattedText;
        // Utiliser Remarkable pour le bot, sinon échappement simple et <br>
        if (type === 'bot') { 
             try {
                // Nettoyer un peu le texte avant Remarkable pour éviter des problèmes
                // (ex: si l'IA renvoie du HTML non voulu ou des caractères de contrôle)
                const cleanText = text.replace(/<script.*?>.*?<\/script>/gi, ''); // Enlever les scripts
                formattedText = remarkable.render(cleanText);
            } catch (e) { 
                console.warn("Erreur de rendu Markdown:", e, "Texte original:", text);
                formattedText = text.replace(/&/g, "&").replace(/</g, "<").replace(/>/g, ">").replace(/\n/g, '<br>');
            }
        } else { 
            formattedText = text.replace(/&/g, "&")
                               .replace(/</g, "<")
                               .replace(/>/g, ">")
                               .replace(/\n/g, '<br>');
        }

        let senderPrefix = "";
        if (sender && (type === 'user' || type === 'bot')) {
            senderPrefix = `<strong>${sender}:</strong><br>`;
        }
        
        messageElement.innerHTML = senderPrefix + formattedText;

        if (type === 'user') {
            messageElement.classList.add('user-message', 'bg-primary', 'text-white', 'ms-auto');
        } else if (type === 'bot') {
            messageElement.classList.add('bot-message', 'bg-light', 'text-dark', 'me-auto', 'border');
        } else if (type === 'system') {
            messageElement.classList.add('text-muted', 'fst-italic', 'small', 'text-center', 'w-100', 'bg-transparent', 'border-0', 'shadow-none');
            messageElement.style.maxWidth = '100%'; 
        } else if (type === 'error') {
            messageElement.classList.add('text-danger', 'fst-italic', 'small', 'text-center', 'w-100', 'bg-transparent', 'border-0', 'shadow-none', 'fw-bold');
            messageElement.style.maxWidth = '100%';
        }
        
        messageContainer.appendChild(messageElement);
        if (chatWindow) {
            chatWindow.appendChild(messageContainer);
            chatWindow.scrollTop = chatWindow.scrollHeight; 
        } else {
            console.warn("chatWindow n'est pas défini lors de l'ajout de message:", sender, text);
        }
    }

    function updateQuotaDisplay(remaining, max) {
        if (!currentUser) return; 
        currentUser.requetes_restantes = remaining; 
        if (quotaDisplay) {
            quotaDisplay.textContent = `${remaining}/${max} req.`;
            quotaDisplay.classList.remove('text-danger', 'text-warning', 'text-success', 'fw-bold'); 
            if (remaining <= 0) {
                quotaDisplay.classList.add('text-danger', 'fw-bold');
            } else if (max > 0 && remaining < max * 0.2) { // Vérifier max > 0 pour éviter division par zéro
                quotaDisplay.classList.add('text-warning');
            } else {
                quotaDisplay.classList.add('text-success');
            }
        }
    }

    function disableChatInput(message) {
        if (userInput) userInput.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
        if (chatInfo) {
            chatInfo.textContent = message;
            chatInfo.classList.add('text-danger');
        }
    }
});