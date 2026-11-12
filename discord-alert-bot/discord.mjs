// Gedeelde Discord-helpers voor de alert-bot (dependency-vrij, Node 18+).
// Gebruikt door send.mjs (CLI) en relay.mjs (HTTP-endpoint).

export const SEVERITY_COLORS = {
  info: 0x3498db, // blauw
  low: 0x2ecc71, // groen
  medium: 0xf1c40f, // geel
  high: 0xe67e22, // oranje
  critical: 0xe74c3c, // rood
};

const ROLE_ENV = {
  info: "ROLE_INFO",
  low: "ROLE_LOW",
  medium: "ROLE_MEDIUM",
  high: "ROLE_HIGH",
  critical: "ROLE_CRITICAL",
};

const trunc = (s, n) => (typeof s === "string" && s.length > n ? s.slice(0, n - 1) + "…" : s);

// Bouwt een embed uit een alert-object.
export function buildEmbed(alert) {
  const severity = String(alert.severity || "info").toLowerCase();
  const color = SEVERITY_COLORS[severity] ?? SEVERITY_COLORS.info;

  const fields = [{ name: "Severity", value: severity.toUpperCase(), inline: true }];
  if (alert.source) fields.push({ name: "Bron", value: trunc(String(alert.source), 1024), inline: true });
  if (alert.technique) fields.push({ name: "ATT&CK", value: trunc(String(alert.technique), 1024), inline: true });

  const embed = {
    title: trunc(alert.title || "HydroSim monitoring", 256),
    description: trunc(alert.message || "", 4096),
    color,
    fields,
    timestamp: new Date().toISOString(),
    footer: { text: "HydroSim monitoring" },
  };
  if (alert.dashboardUrl) embed.url = alert.dashboardUrl;
  return embed;
}

// Bepaalt content + allowed_mentions op basis van severity en omgeving:
// severity -> rol-mention (spoiler), of @everyone als MENTION_EVERYONE=true, anders geen ping.
export function resolveMention(severity, env = process.env) {
  const sev = String(severity || "info").toLowerCase();
  const spoiler = env.SPOILER_MENTION !== "false";
  const wrap = (s) => (spoiler ? `||${s}||` : s);

  if (env.MENTION_EVERYONE === "true") {
    return { content: wrap("@everyone"), allowedMentions: { parse: ["everyone"] }, label: "@everyone" };
  }
  const roleId = env[ROLE_ENV[sev]] || null;
  if (roleId) {
    return { content: wrap(`<@&${roleId}>`), allowedMentions: { roles: [roleId] }, label: `rol ${roleId}` };
  }
  return { content: undefined, allowedMentions: { parse: [] }, label: "geen ping" };
}

// Verstuurt een bericht naar een kanaal via de Discord REST API.
// Retourneert { ok, status, body } i.p.v. te throwen op een HTTP-fout.
export async function postMessage({ token, channelId, content, embeds, allowedMentions }) {
  const res = await fetch(`https://discord.com/api/v10/channels/${channelId}/messages`, {
    method: "POST",
    headers: { Authorization: `Bot ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({ content, embeds, allowed_mentions: allowedMentions }),
  });
  const body = res.ok ? null : await res.text();
  return { ok: res.ok, status: res.status, body };
}
