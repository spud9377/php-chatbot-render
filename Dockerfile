# Dockerfile
# Utiliser une image PHP officielle avec Apache
FROM php:8.2-apache

# Définir DEBIAN_FRONTEND sur noninteractive pour éviter les invites pendant le build apt-get
ENV DEBIAN_FRONTEND=noninteractive

# Mettre à jour les listes de paquets, installer les dépendances système nécessaires,
# configurer et installer les extensions PHP, puis nettoyer.
# --no-install-recommends aide à réduire la taille de l'image.
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Dépendances pour intl
    libicu-dev \
    # Dépendances pour zip
    libzip-dev \
    zip \
    # Dépendances pour curl (souvent déjà présentes ou tirées par libcurl4-openssl-dev si nécessaire)
    # libcurl4-openssl-dev \
    # Dépendances pour pdo_sqlite
    libsqlite3-dev \
    # Autres utilitaires si besoin
    # git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) intl curl mbstring zip pdo pdo_sqlite \
    # Nettoyage pour réduire la taille de l'image
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier tous les fichiers de l'application dans l'image
COPY . /var/www/html/

# S'assurer que le serveur Apache (www-data) peut écrire dans le dossier data
# Le dossier data sera monté depuis un disque persistant sur Render.
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data # 775 est un peu plus permissif que 755 si le groupe a besoin d'écrire

# Apache écoute sur le port 80 par défaut.
EXPOSE 80

# La commande par défaut de php:8.x-apache est 'apache2-foreground',
# donc pas besoin de la redéfinir explicitement.