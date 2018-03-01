FROM php:7.2-apache-stretch
RUN \
    apt-get update && \
    apt-get install -y git && \
    rm -rf /var/lib/apt/lists/*
RUN git clone https://github.com/brendangregg/FlameGraph /opt/flamegraph
COPY config/php.ini /usr/local/etc/php/
COPY src/* /var/www/html/
