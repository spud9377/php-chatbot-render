# Dockerfile
# Utiliser une image PHP officielle avec Apache
FROM php:8.2-apache

# Définir DEBIAN_FRONTEND sur noninteractive pour éviter les invites pendant le build apt-get
ENV DEBIAN_FRONTEND=noninteractive

# Mettre à jour les listes de paquets, installer les dépendances système nécessaires,
# configurer et installer les extensions PHP, puis nettoyer.
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Dépendances pour intl
    libicu-dev \
    # Dépendances pour zip
    libzip-dev \
    zip \
    # Dépendances pour pdo_sqlite
    libsqlite3-dev \
    # Dépendances pour curl
    libcurl4-openssl-dev \
    # Dépendances pour mbstring (Oniguruma)
    libonig-dev \
    # Utilitaires
    pkg-config \
    && docker-php-ext-configure intl \
    # On pourrait ajouter des options de configuration pour mbstring ici si nécessaire,
    # mais généralement, les valeurs par défaut avec oniguruma sont bonnes.
    # Exemple: --enable-mbstring --with-onig
    && docker-php-ext-install -j$(nproc) intl curl mbstring zip pdo pdo_sqlite \
    # Nettoyage pour réduire la taille de l'image
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier tous les fichiers de l'application dans l'image
COPY . /var/www/html/

# S'assurer que le serveur Apache (www-data) peut écrire dans le dossier data
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

# Apache écoute sur le port 80 par défaut.
EXPOSE 80