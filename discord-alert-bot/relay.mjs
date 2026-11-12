// HydroSim alert-bot — relay.
// Klein HTTP-endpoint dat een alert-JSON ontvangt en als Discord-embed doorstuurt.
// Dit is de interface die OpenSearch Alerting (custom webhook) straks aanroept.
// Dependency-vrij (Node 18+, ingebouwde http-module). Gedeelde logica in discord.mjs.
//
// Endpoints:
//   POST /alert   body = alert-JSON (severity, title, message, source, technique, dashboardUrl)
//                 vereist header  X-Auth-Token: <RELAY_AUTH_TOKEN>
//   GET  /health  -> { status: "ok" }

import http from "node:http";
import { buildEmbed, resolveMention, postMessage } from "./discord.mjs";

const token = process.env.DISCORD_BOT_TOKEN;
const channelId = process.env.DISCORD_CHANNEL_ID;
const authToken = process.env.RELAY_AUTH_TOKEN;

// Standaard alleen lokaal bereikbaar; pas RELAY_HOST bewust aan voor netwerktoegang.
const host = process.env.RELAY_HOST || "127.0.0.1";
const port = Number(process.env.RELAY_PORT || 8099);

if (!token || !channelId) {
  console.error("Ontbrekende configuratie: zet DISCORD_BOT_TOKEN en DISCORD_CHANNEL_ID (zie .env.example).");
  process.exit(1);
}
// Auth-token is verplicht: zonder zou iedereen die de poort bereikt alerts kunnen injecteren.
if (!authToken) {
  console.error("RELAY_AUTH_TOKEN is verplicht. Zet een sterk, geheim token in .env.");
  process.exit(1);
}

const MAX_BODY = 64 * 1024; // 64 KB cap tegen misbruik

function reply(res, status, obj) {
  res.writeHead(status, { "Content-Type": "application/json" });
  res.end(JSON.stringify(obj));
}

// Constante-tijd vergelijking om timing-aanvallen op het token te beperken.
function safeEqual(a, b) {
  if (typeof a !== "string" || typeof b !== "string" || a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

const server = http.createServer((req, res) => {
  if (req.method === "GET" && req.url === "/health") {
    return reply(res, 200, { status: "ok" });
  }
  if (req.method !== "POST" || req.url !== "/alert") {
    return reply(res, 404, { error: "Niet gevonden. Gebruik POST /alert of GET /health." });
  }
  if (!safeEqual(req.headers["x-auth-token"] || "", authToken)) {
    return reply(res, 401, { error: "Ongeldig of ontbrekend X-Auth-Token." });
  }

  let body = "";
  let aborted = false;
  req.on("data", (chunk) => {
    body += chunk;
    if (body.length > MAX_BODY) {
      aborted = true;
      reply(res, 413, { error: "Body te groot." });
      req.destroy();
    }
  });
  req.on("end", async () => {
    if (aborted) return;
    let alert;
    try {
      alert = JSON.parse(body || "{}");
    } catch {
      return reply(res, 400, { error: "Body is geen geldige JSON." });
    }

    const severity = String(alert.severity || "info").toLowerCase();
    const embed = buildEmbed(alert);
    const { content, allowedMentions, label } = resolveMention(severity);

    try {
      const r = await postMessage({ token, channelId, content, embeds: [embed], allowedMentions });
      const stamp = new Date().toISOString();
      if (r.ok) {
        console.log(`[${stamp}] alert doorgestuurd (severity: ${severity}, ${label})`);
        return reply(res, 200, { status: "sent", severity, ping: label });
      }
      console.error(`[${stamp}] Discord weigerde (HTTP ${r.status}): ${r.body}`);
      return reply(res, 502, { error: "Discord weigerde het bericht", status: r.status, detail: r.body });
    } catch (err) {
      console.error(`[${new Date().toISOString()}] fout: ${err.message}`);
      return reply(res, 502, { error: err.message });
    }
  });
});

server.listen(port, host, () => {
  console.log(`Relay luistert op http://${host}:${port}  (POST /alert, GET /health)`);
  console.log("Alleen requests met de juiste X-Auth-Token worden geaccepteerd.");
});
