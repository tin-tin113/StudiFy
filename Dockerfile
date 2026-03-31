FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

COPY . /var/www/html/

# Ensure uploads are writable in container environments
RUN mkdir -p /var/www/html/uploads/attachments /var/www/html/uploads/avatars /var/www/html/uploads/photos \
	&& chown -R www-data:www-data /var/www/html/uploads \
	&& chmod -R 775 /var/www/html/uploads

EXPOSE 80