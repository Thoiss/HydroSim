# Slim, officieel Node-image. De bot heeft GEEN dependencies, dus geen npm install.
FROM node:20-slim

WORKDIR /app
COPY discord.mjs send.mjs heartbeat.mjs relay.mjs ./

# De relay luistert standaard op deze poort (documentatie).
EXPOSE 8099

# Draai als non-root voor extra isolatie.
USER node

# Het token wordt via --env-file meegegeven, niet in de image gebakken.
# Standaard: één alert sturen. Override de entrypoint voor de andere modi:
#   heartbeat: docker run --rm --env-file .env --entrypoint node hydrosim-alertbot heartbeat.mjs
#   relay:     docker run --rm --env-file .env -p 8099:8099 -e RELAY_HOST=0.0.0.0 --entrypoint node hydrosim-alertbot relay.mjs
ENTRYPOINT ["node", "send.mjs"]
