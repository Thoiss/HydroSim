// HydroSim alert-bot — periodieke heartbeat met end-to-end statuscheck.
// Post met een vast interval een status-embed naar Discord en controleert de héle keten:
//   - OpenSearch bereikbaar/gezond (de bron-kant)
//   - de relay-endpoint bereikbaar (de deur waar OpenSearch op klopt)
//   - Discord-levering (bewezen doordat dit bericht aankomt)
// Dependency-vrij (Node 18+): fetch voor Discord/relay, node:https voor OpenSearch (self-signed cert).
// Draai als langlopend proces of container OP het opensearch-net, zodat de checks de andere
// containers op naam kunnen bereiken.

import https from "node:https";

const token = process.env.DISCORD_BOT_TOKEN;
const channelId = process.env.HEARTBEAT_CHANNEL_ID || process.env.DISCORD_CHANNEL_ID;

if (!token || !channelId) {
  console.error(
    "Ontbrekende configuratie: zet DISCORD_BOT_TOKEN en DISCORD_CHANNEL_ID (of HEARTBEAT_CHANNEL_ID)."
  );
  process.exit(1);
}

const intervalMinutes = Number(process.env.HEARTBEAT_INTERVAL_MINUTES || 60);
if (!Number.isFinite(intervalMinutes) || intervalMinutes <= 0) {
  console.error("HEARTBEAT_INTERVAL_MINUTES moet een positief getal zijn.");
  process.exit(1);
}

const OPENSEARCH_URL = process.env.OPENSEARCH_URL || "https://opensearch-node1:9200";
const OPENSEARCH_USER = process.env.OPENSEARCH_USER || "admin";
const OPENSEARCH_PASSWORD = process.env.OPENSEARCH_PASSWORD || "";
const RELAY_HEALTH_URL = process.env.RELAY_HEALTH_URL || "http://discord-relay:8099/health";

const startedAt = Date.now();

function formatUptime(ms) {
  const s = Math.floor(ms / 1000);
  const d = Math.floor(s / 86400);
  const h = Math.floor((s % 86400) / 3600);
  const m = Math.floor((s % 3600) / 60);
  const parts = [];
  if (d) parts.push(`${d}d`);
  if (h) parts.push(`${h}u`);
  parts.push(`${m}m`);
  return parts.join(" ");
}

// OpenSearch cluster-health via node:https (de lab-stack gebruikt een self-signed cert).
function checkOpenSearch() {
  return new Promise((resolve) => {
    let url;
    try {
      url = new URL(OPENSEARCH_URL + "/_cluster/health");
    } catch {
      return resolve({ ok: false, error: "ongeldige OPENSEARCH_URL" });
    }
    const auth = "Basic " + Buffer.from(`${OPENSEARCH_USER}:${OPENSEARCH_PASSWORD}`).toString("base64");
    const req = https.request(
      {
        hostname: url.hostname,
        port: url.port || 443,
        path: url.pathname,
        method: "GET",
        headers: { Authorization: auth },
        rejectUnauthorized: false, // self-signed cert in de lab-stack
        timeout: 5000,
      },
      (res) => {
        let body = "";
        res.on("data", (c) => (body += c));
        res.on("end", () => {
          try {
            const j = JSON.parse(body);
            resolve({ ok: res.statusCode === 200, status: j.status, nodes: j.number_of_nodes });
          } catch {
            resolve({ ok: false, error: `HTTP ${res.statusCode}` });
          }
        });
      }
    );
    req.on("error", (e) => resolve({ ok: false, error: e.message }));
    req.on("timeout", () => {
      req.destroy();
      resolve({ ok: false, error: "timeout" });
    });
    req.end();
  });
}

// Relay-endpoint bereikbaar?
async function checkRelay() {
  try {
    const res = await fetch(RELAY_HEALTH_URL, { signal: AbortSignal.timeout(5000) });
    return { ok: res.ok, error: res.ok ? null : `HTTP ${res.status}` };
  } catch (e) {
    return { ok: false, error: e.message };
  }
}

async function sendHeartbeat() {
  const [os, relay] = await Promise.all([checkOpenSearch(), checkRelay()]);

  const osIcon = os.ok ? (os.status === "green" ? "🟢" : os.status === "yellow" ? "🟡" : "🔴") : "🔴";
  const osText = os.ok ? `${osIcon} ${os.status} (${os.nodes} node)` : `🔴 onbereikbaar (${os.error || "?"})`;
  const relayText = relay.ok ? "🟢 bereikbaar" : `🔴 onbereikbaar (${relay.error || "?"})`;

  // Alles goed = groen; probleem aan een van beide kanten = oranje.
  const allOk = os.ok && os.status !== "red" && relay.ok;
  const color = allOk ? 0x2ecc71 : 0xe67e22;

  const embed = {
    title: "HydroSim alert-bot — heartbeat",
    description: "Statuscheck van de monitoring-keten (OpenSearch → relay → Discord).",
    color,
    fields: [
      { name: "OpenSearch", value: osText, inline: true },
      { name: "Relay-endpoint", value: relayText, inline: true },
      { name: "Discord", value: "🟢 bereikbaar", inline: true },
      { name: "Uptime", value: formatUptime(Date.now() - startedAt), inline: true },
      { name: "Interval", value: `${intervalMinutes} min`, inline: true },
    ],
    timestamp: new Date().toISOString(),
    footer: { text: "HydroSim monitoring" },
  };

  try {
    const res = await fetch(`https://discord.com/api/v10/channels/${channelId}/messages`, {
      method: "POST",
      headers: { Authorization: `Bot ${token}`, "Content-Type": "application/json" },
      body: JSON.stringify({ embeds: [embed], allowed_mentions: { parse: [] } }),
    });
    const stamp = new Date().toISOString();
    if (res.ok) {
      console.log(`[${stamp}] Heartbeat verzonden ✅  (OpenSearch: ${os.ok ? os.status : "down"}, relay: ${relay.ok ? "ok" : "down"})`);
    } else {
      console.error(`[${stamp}] Heartbeat mislukt (HTTP ${res.status}): ${await res.text()}`);
    }
  } catch (err) {
    console.error(`[${new Date().toISOString()}] Heartbeat netwerkfout: ${err.message}`);
  }
}

console.log(`Heartbeat gestart. Interval: ${intervalMinutes} min. Stop met Ctrl+C.`);
await sendHeartbeat(); // meteen één bij opstarten
setInterval(sendHeartbeat, intervalMinutes * 60 * 1000);
