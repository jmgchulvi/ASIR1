FROM php:8.2-apache

# Instala la extensión mysqli
RUN docker-php-ext-install mysqli

# Habilita la extensión mysqli (a veces es necesario explícitamente, aunque install a menudo lo hace)
# RUN docker-php-enable mysqli

# Asegúrate de que mod_rewrite esté habilitado (normalmente ya lo está)
RUN a2enmod rewrite
