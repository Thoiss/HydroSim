FROM wordpress:5.8-php7.4-apache

# Install specific Bash version 3.1
RUN sed -i 's|deb.debian.org/debian|archive.debian.org/debian|; s|security.debian.org/debian-security|archive.debian.org/debian-security|' /etc/apt/sources.list && \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive && \
    apt-get update && \
    apt-get install -y wget build-essential && \
    wget http://ftp.gnu.org/gnu/bash/bash-3.1.tar.gz && \
    tar -xzf bash-3.1.tar.gz && \
    cd bash-3.1 && \
    ./configure && \
    make && \
    make install && \
    cd .. && \
    rm -rf bash-3.1 bash-3.1.tar.gz
    
RUN a2enmod cgi

COPY vulnerable.sh /usr/lib/cgi-bin/vulnerable.sh

COPY passwords.txt /

RUN chmod +rx /usr/lib/cgi-bin/vulnerable.sh && \
    chmod -r /passwords.txt

# Expose WordPress on port 80
EXPOSE 80
