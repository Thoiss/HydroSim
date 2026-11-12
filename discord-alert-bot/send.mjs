// HydroSim alert-bot — stuurt één Discord-embed naar een kanaal (CLI).
// Dependency-vrij (Node 18+). Gedeelde logica staat in discord.mjs.
//
// Alert aanleveren via:
//   1. Losse env-vars: ALERT_SEVERITY, ALERT_TITLE, ALERT_MESSAGE (of CLI-arg), ALERT_SOURCE, ALERT_TECHNIQUE, ALERT_DASHBOARD_URL
//   2. Of een volledige JSON in ALERT_JSON

import { buildEmbed, resolveMention, postMessage } from "./discord.mjs";

const token = process.env.DISCORD_BOT_TOKEN;
const channelId = process.env.DISCORD_CHANNEL_ID;

if (!token || !channelId) {
  console.error("Ontbrekende configuratie. Zet DISCORD_BOT_TOKEN en DISCORD_CHANNEL_ID (zie .env.example).");
  process.exit(1);
}

function getAlert() {
  if (process.env.ALERT_JSON) {
    try {
      return JSON.parse(process.env.ALERT_JSON);
    } catch (err) {
      console.error(`ALERT_JSON is geen geldige JSON: ${err.message}`);
      process.exit(1);
    }
  }
  const cliMessage = process.argv.slice(2).join(" ");
  return {
    severity: process.env.ALERT_SEVERITY || "info",
    title: process.env.ALERT_TITLE || "HydroSim monitoring",
    message: cliMessage || process.env.ALERT_MESSAGE || "Testbericht met embed ✅",
    source: process.env.ALERT_SOURCE || null,
    technique: process.env.ALERT_TECHNIQUE || null,
    dashboardUrl: process.env.ALERT_DASHBOARD_URL || null,
  };
}

const alert = getAlert();
const severity = String(alert.severity || "info").toLowerCase();
const embed = buildEmbed(alert);
const { content, allowedMentions, label } = resolveMention(severity);

try {
  const r = await postMessage({ token, channelId, content, embeds: [embed], allowedMentions });
  if (r.ok) {
    console.log(`Embed verzonden ✅ (severity: ${severity}, ${label})`);
  } else {
    console.error(`Versturen mislukt (HTTP ${r.status}): ${r.body}`);
    process.exit(1);
  }
} catch (err) {
  console.error(`Netwerk-/uitvoeringsfout: ${err.message}`);
  process.exit(1);
}
