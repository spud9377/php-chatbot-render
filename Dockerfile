# Dockerfile
# Utiliser une image PHP officielle avec Apache
FROM php:8.2-apache

# Installer les extensions PHP nécessaires
# - curl: pour les appels API OpenAI
# - mbstring: pour la manipulation de chaînes multi-octets (bonne pratique)
# - zip: si vous aviez besoin de gérer des archives zip
# - pdo et pdo_sqlite: Bien que vous utilisiez JSON, si jamais vous voulez SQLite, c'est déjà là.
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl curl mbstring zip pdo pdo_sqlite

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier tous les fichiers de l'application dans l'image
# Le "." source est le contexte de build (la racine de votre projet)
# La destination est WORKDIR dans l'image
COPY . /var/www/html/

# S'assurer que le serveur Apache (www-data) peut écrire dans le dossier data
# Le dossier data sera monté depuis un disque persistant sur Render,
# mais il est bon de s'assurer des permissions de base dans l'image.
# La commande chown sur le disque monté sera plus importante.
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

# Apache écoute sur le port 80 par défaut, donc pas besoin d'EXPOSE explicitement
# sauf si vous voulez être très clair.
EXPOSE 80

# La commande par défaut de php:8.x-apache est 'apache2-foreground',
# donc pas besoin de la redéfinir sauf si vous avez des besoins spécifiques.