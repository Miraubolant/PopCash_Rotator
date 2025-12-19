FROM php:8.2-apache

# Activer mod_rewrite pour .htaccess
RUN a2enmod rewrite headers

# Configurer Apache pour autoriser .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copier les fichiers de l'application
COPY . /var/www/html/

# Créer le dossier logs avec les bonnes permissions
RUN mkdir -p /var/www/html/logs && \
    chown -R www-data:www-data /var/www/html/logs && \
    chmod 755 /var/www/html/logs

# Exposer le port 80
EXPOSE 80

# Démarrer Apache
CMD ["apache2-foreground"]
