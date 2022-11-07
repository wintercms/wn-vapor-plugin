FROM laravelphp/vapor:php81

COPY . /var/task
COPY ./php.ini /usr/local/etc/php/conf.d/overrides.ini
