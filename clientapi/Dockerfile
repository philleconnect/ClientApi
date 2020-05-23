FROM debian:buster-slim

MAINTAINER Johannes Kreutz <philleconnect@jkm-marburg.de>

ENV VERSION "1"

USER root

RUN apt-get update && apt-get install -y --no-install-recommends nginx php-fpm php-mysql wakeonlan

COPY php/* /var/www/html/
COPY default /etc/nginx/sites-enabled/

RUN mkdir /etc/clientapi

RUN echo "www-data ALL=NOPASSWD: /usr/bin/wakeonlan" >> /etc/sudoers

EXPOSE 443

COPY entrypoint.sh /
RUN chmod +x /entrypoint.sh

CMD ["./entrypoint.sh"]
