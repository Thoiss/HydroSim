# HydroSim – Setup handoff (read this first)

Context: HydroSim is a deliberately vulnerable water-network OT simulation for security education.
It currently runs on a single Ubuntu VM on the school server. The owner (Thijs) cannot reach that
server right now and wants a working copy on a new Linux Mint VM (Mint is Ubuntu-based; fine).
A previous Claude Code session analysed the repo (no builds were run). This file hands that over.

**Your job:** do as much of the setup as possible on this VM, then keep the "Manual steps" list
below up to date. Do not commit secrets (API keys, real passwords). Keep the vulnerable services
firewalled to trusted IPs; never expose them to the open internet.

## Architecture (from docker-compose.yaml)
- `epanet` – Python (epyt, pymodbus, paho-mqtt); talks Modbus to the PLCs, publishes state to MQTT (TLS). Mounts `./mqtt/certs`, `./.env`, `./zone-registers.txt`.
- `plc-zone0..4` – OpenPLC (built from `openplc/`, one `.st` program per zone). Web UIs 8082-8086, Modbus 5022-5026.
- `scadalts` (image `scadalts/scadalts:v2.7.5.2`, port 8081) + `database` (mysql-server 8.0.32, host port 3307, root/root).
- `webserver` – WordPress 5.8 + Shellshock CGI (bash 3.1), port 80, `/passwords.txt` is the exercise credential file.
- `opensearch-node`, `opensearch-dashboards` (5601), `data-prepper`, `fluent-bit` – log pipeline for blue-team exercises.
- `mosquitto` – MQTT broker with mutual TLS on 8883.
- `website/` – PHP "labsys wiki" app. **NOT in docker-compose and not documented anywhere.**
- Physical hardware (Raspberry Pi / PLC) code lives on branch `Hardware-Branch`; ignore unless asked.

## Known gaps / fixes to make
1. `website/` has no deployment. Needs PHP+Apache and a MySQL user `webapp`/`waterdb` on `127.0.0.1:3307`, DB `scadalts` (see `website/includes/config.php`, `db.php`). No schema in repo: reconstruct tables from the PHP (`includes/`, `api.php`, `templates/`) and add a compose service. Mark the schema as a best guess until compared with the school VM.
2. `website/chat-proxy.php` has an empty `$API_KEY` (Anthropic). Read it from an env var rather than hardcoding; user supplies the key.
3. `docker-compose.yaml` mounts `./scadalts/db_conf` (missing from repo; root-level `my.cnf` is unused) and `./scadalts/config:/config.zip` (odd mount). Decide on correct handling, test it.
4. OpenSearch passwords differ: compose dashboards uses `Paat123!`, `opensearch/opensearch.env` + docs use `Patat123!`. Make consistent.
5. `.env` is committed despite being gitignored; its MQTT broker address/topic are school-network specific. Update for this VM.
6. `epanet/app/.venv` (hundreds of files) is committed; remove from git and ignore it.
7. `opensearch*`/`data-prepper` images use `:latest`; consider pinning.
8. MQTT certs: `cd mqtt && ./generate_all.sh` (prompts for number of clients). Server cert CN must match this VM's IP/hostname. `mqtt/certs` is gitignored.

## Plan
1. Install Docker + Compose. **On Mint** do not use `lsb_release -cs` in Docker's apt repo line; use `UBUNTU_CODENAME` from `/etc/os-release` (or install `docker.io` + `docker-compose-v2`).
2. Check ports free: 80, 3307, 5022-5026, 5601, 8081-8086, 8883, 9200, 9600, 2021, 2022. Need ~8 GB RAM; OpenSearch may need `vm.max_map_count=262144`.
3. Fix the gaps above, generate certs, `docker compose build && docker compose up -d`, inspect logs per service.
4. Verify: PLC Modbus reachable, EPANET logs show register reads and MQTT publish, ScadaLTS login page loads, WordPress on :80, Shellshock CGI at `/cgi-bin/vulnerable.sh` behaves as the exercise expects, logs land in OpenSearch (`apache_logs`, `epanet_logs` indices).
5. Set up firewall (ufw) limiting exposed ports to trusted IPs.
6. Add/maintain the manual-steps list below.

## Manual steps for the user (keep updated)
- [ ] Import ScadaLTS config: log into http://<vm>:8081/Scada-LTS (default likely admin/admin, unverified) and import `scadalts/HydroSimV7.zip` via the UI (see `docs/scadalts.md`). Check the data sources point at `plc-zone0..4`.
- [ ] Provide an Anthropic API key for the website chatbot (if wanted).
- [ ] Choose passwords for anything reachable beyond the lab (MySQL root, OpenSearch, WordPress).
- [ ] Decide which IPs may reach the VM (firewall).
- [ ] **Tomorrow, from the school VM, copy:**
  - the real `website/` database schema + data and the `webapp` DB user (e.g. `mysqldump`)
  - Apache/PHP config and vhosts for the website
  - the ScadaLTS DB/export if newer than `HydroSimV7.zip`
  - the actual `/passwords.txt` and any other exercise-specific files, cron jobs, users
  - anything on the VM not tracked in git
- [ ] Compare reconstructed website schema/behaviour with the original and fix differences.
- [ ] Confirm the exercise works end to end (Shellshock → credentials → HMI → logs in OpenSearch).
- [ ] Decide whether the Raspberry Pi / physical hardware (branch `Hardware-Branch`) is needed.

## Status log (Mint VM, hostname proxmoxMint, 192.168.0.103)
STACK IS UP (15 containers). Verified: MQTT mutual TLS + EPANET publishing, 5 PLC Modbus ports, ScadaLTS login (:8081/Scada-LTS), wiki (:8088), Shellshock CGI (/cgi-bin/vulnerable.sh), OpenSearch `apache_logs` + `epanet_logs` filling, Dashboards :5601.
Changes made:
- DB = real MySQL datadir copied from the school server (`scadalts/db_data`, gitignored). Its TLS/RSA key files were missing from the copy; they were deleted so MySQL regenerated them (needed for caching_sha2 logins).
- The wiki shares ScadaLTS's `users` table; `website-deploy/schema.sql` guess was wrong and removed. `website` service (:8088) reads DB host/port from env.
- `chat-proxy.php` reads `ANTHROPIC_API_KEY` env. The live server file had a real key hardcoded: rotate it.
- `webserver/Dockerfile`: apt now uses archive.debian.org (bullseye is EOL).
- compose: removed `config:/config.zip` mount; `./my.cnf:/etc/my.cnf:ro` instead of `db_conf`; OpenSearch password standardised to `Patat123!`.
- New MQTT certs for 192.168.0.103 (CA, server, client10); `.env` MQTT_BROKER_URL set to the same.
- `epanet/app/.venv` and `.env` untracked from git index. Nothing committed.
Open: WordPress (:80) redirects to wp-admin/install.php (no WP tables in the DB copy; install or import). ufw firewall not configured (needs sudo). Gap 7 (pin :latest images) not done. `adminer.php` from the server's ~/web is not in the container.

## Update 2026-10-14: school-server data merged
- Server's `hydrosim-dump.sql` was NEWER than the copied `db_data` (has 30 Sep data). The `scadalts` DB was reloaded from the dump (backup of the previous state: `~/hydrosim-backups/scadalts-before-dump-import.sql`). 10 users, 34 data points, 16 activity_logs.
- ScadaLTS export (`ScadaLTS_ExportAll.json`) matches the DB: no import needed. Uploads identical to `scadalts/config/uploads`.
- PLC `openplc.db` / `st_files` from the server match what the repo builds (only timestamps differ): nothing to copy.
- School server layout (from `ports.txt`/Apache config): the wiki ran on the HOST Apache on :80; the WordPress/Shellshock container was not running there. Mirrored here: wiki (`website`) on :80 (+ the server's `adminer.php`, gitignored), WordPress/Shellshock `webserver` moved to :8089. Revert by swapping the two port mappings in docker-compose.yaml (docs/opensearch.md still says the WordPress server is on :80).
- WordPress has no tables in the DB on the server either (never installed there); it shows the install page on :8089.
- Remaining: ufw firewall (needs sudo), `ANTHROPIC_API_KEY` for the chatbot (the old key was hardcoded on the server: rotate it), end-to-end exercise run-through, commit changes (nothing committed yet).
