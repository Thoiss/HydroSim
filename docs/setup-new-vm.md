# HydroSim: setting up on a new Linux VM

Repeatable procedure used to rebuild the school-server HydroSim environment on a fresh
Linux Mint 22 / Ubuntu 24.04 VM (`proxmoxMint`, 192.168.0.103). HydroSim is a **deliberately
vulnerable** OT lab: keep it on a trusted network and firewall it (step 9).

## 0. Where everything comes from

| What | Source | Notes |
|---|---|---|
| Code, compose file, PLC programs, EPANET model, docs | **GitHub** `https://github.com/coecs-hhs/HydroSim` (branch `main`) | Also contains the `website/` wiki source. The local setup changes listed in step 4 are not on GitHub yet. |
| MySQL database (ScadaLTS + wiki `users`, `pages`, `activity_logs`) | **School server**, `mysqldump` → `hydrosim-dump.sql` | Authoritative. The raw `scadalts/db_data` folder from the rsync copy was older (23 Sep vs 30 Sep data) and had no TLS/RSA key files. |
| ScadaLTS config / uploads | **School server** (ScadaLTS UI export + `scadalts/config/uploads`) | Only needed to cross-check. The dump already contains it. |
| Wiki `adminer.php` | **School server** `/var/www/html/adminer.php` (inside `apache-etc.tgz`) | Gitignored, so not on GitHub. |
| `php.ini` used by the wiki | **School server** `/etc/php/8.3/apache2/php.ini` | Hides warnings and buffers output, which the wiki needs to redirect after login. |
| MQTT certificates | **Generated** on the new VM (`mqtt/generate_all.sh`) | The old ones are tied to the old network address. Do not copy them. |
| Anthropic API key (wiki chatbot) | **You supply it** (`ANTHROPIC_API_KEY`) | The school server had one hardcoded in `chat-proxy.php`: rotate it. |
| Rsync copy of the server's home dir (`~/server-copy`) | **School server** (rsync/tar) | Used for inspection only: bash history, a diff against the repo, and `db_data`. It is not needed to run the lab. |

## 1. Prerequisites

- 4+ CPU recommended (2 works but the five OpenPLC image builds take about 15 min), **10 GB RAM** (about 8 GB needed), **40 GB+ disk**
  (images and build cache use ~25 GB), free ports: 80, 3307, 5022-5026, 5601, 8081-8086, 8089, 8883, 9200, 9600, 2021, 2022.
- Internet access for pulling images.

```bash
sudo apt-get update
sudo apt-get install -y docker.io docker-compose-v2 git sqlite3
sudo usermod -aG docker $USER      # then log out and back in (a new session is required)
docker ps                           # must work without sudo
```
On Mint do not use `lsb_release -cs` in Docker's apt repo line (it returns the Mint
codename); the distro packages above avoid that. `vm.max_map_count` (OpenSearch) is already high on Mint 22;
if not: `sudo sysctl -w vm.max_map_count=262144`.

## 2. Collect the data from the school server

Run on the **school server**. Copy the results to the new VM with `tar` (SMB/NAS backends
reject symlinks; a tar file avoids that) or with `rsync -aHAX` over SSH.

```bash
mkdir -p ~/HydroSimVM_Files && cd ~/HydroSimVM_Files

# 1) Database (authoritative)
sudo docker exec database mysqldump -uroot -proot --all-databases --routines --triggers --events > hydrosim-dump.sql

# 2) Wiki sources and Apache/PHP config (adminer.php and php.ini are needed)
sudo tar -czf apache-etc.tgz /etc/apache2 /etc/php /var/www/html

# 3) Optional cross-checks
sudo docker exec webserver cat /passwords.txt > passwords.txt
sudo docker cp webserver:/var/www/html ./wp-html
for z in 0 1 2 3 4; do mkdir -p plc/zone$z; sudo docker cp plc-zone$z:/docker_persistent/. plc/zone$z/; done
sudo ss -tlnp > ports.txt
```
Note: `docker cp plc-zoneN:/OpenPLC_v3/webserver/openplc.db` copies only a symlink; the real
files are in `/docker_persistent`. Export ScadaLTS from the UI (Administration → Export project) if wanted.

Put everything in `~/HydroSimVM_Files` on the new VM.

## 3. Get the code

```bash
cd ~
git clone https://github.com/coecs-hhs/HydroSim
cd HydroSim
```

## 4. Local changes needed on top of the GitHub repo

These were required to make the stack build and run on a new machine:

1. **`webserver/Dockerfile`**: the base image is Debian bullseye (end-of-life). Add before `apt-get update`:
   ```
   RUN sed -i 's|deb.debian.org/debian|archive.debian.org/debian|; s|security.debian.org/debian-security|archive.debian.org/debian-security|' /etc/apt/sources.list && \
       echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive && \
       apt-get update && apt-get install -y wget build-essential && ...
   ```
2. **`docker-compose.yaml`**:
   - `database`: replace the `./scadalts/db_conf:/etc/mysql:ro` mount with `./my.cnf:/etc/my.cnf:ro`.
   - `scadalts`: remove the `./scadalts/config:/config.zip` mount.
   - `opensearch-dashboards`: `OPENSEARCH_PASSWORD: Patat123!` (was `Paat123!`; must match `opensearch/opensearch.env`).
   - Add a `website` service (PHP wiki), ports `80:80`, networks `intranet` + `database`, env `DB_HOST=database`, `DB_PORT=3306`,
     `ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY:-}`, build context `.` with `website-deploy/Dockerfile`.
   - `webserver` (WordPress + Shellshock): publish `8089:80` (the school server had the wiki on port 80, with this container stopped).
3. **`website/includes/config.php` + `db.php`**: read `DB_HOST` / `DB_PORT` from the environment (defaults `127.0.0.1` / `3307` unchanged);
   the user/password stay `webapp` / `waterdb`.
4. **`website/chat-proxy.php`**: `$API_KEY = getenv('ANTHROPIC_API_KEY') ?: '';`
5. **`website-deploy/Dockerfile`**:
   ```
   FROM php:8.3-apache
   RUN docker-php-ext-install mysqli
   COPY website-deploy/php.ini /usr/local/etc/php/php.ini
   COPY website/ /var/www/html/
   ```
6. **Git hygiene**: `git rm -r --cached epanet/app/.venv .env` (both are gitignored but were committed).

Then add the server-only files:

```bash
cd ~/HydroSim
mkdir -p ~/s && tar -xzf ~/HydroSimVM_Files/apache-etc.tgz -C ~/s --no-same-owner
cp ~/s/var/www/html/adminer.php website/adminer.php          # gitignored
cp ~/s/etc/php/8.3/apache2/php.ini website-deploy/php.ini
```

## 5. MQTT certificates and `.env`

```bash
cd ~/HydroSim/mqtt && chmod +x generate_*.sh
# prompts: CA name, server CN (the VM's IP or hostname), number of clients, client CN
printf 'HydroSimCA\n192.168.0.103\n1\nclient10\n' | ./generate_all.sh
cd ..
sed -i 's/^MQTT_BROKER_URL=.*/MQTT_BROKER_URL=192.168.0.103/' .env     # must match the server cert CN
```
`.env` expects the client cert `client10` (`MQTT_CLIENT_CERT=/certs/client/client10.crt`).

## 6. Build and start

```bash
cd ~/HydroSim
docker compose build          # ~15 min on 2 CPUs (OpenPLC compiles five times)
docker compose up -d database
docker compose ps             # wait until database is (healthy)
```

## 7. Load the database dump

Import only the `scadalts` section of the dump (lines between `-- Current Database: scadalts` and the
trailing `-- Current Database: mysql`). Do **not** replay the `mysql` system database section.

```bash
F=~/HydroSimVM_Files/hydrosim-dump.sql
S=$(grep -n '^-- Current Database: `scadalts`' $F | head -1 | cut -d: -f1)
E=$(( $(grep -n '^-- Current Database: `mysql`' $F | tail -1 | cut -d: -f1) - 1 ))
(sed -n '1,20p' $F; sed -n "${S},${E}p" $F) > /tmp/scadalts-only.sql

docker compose stop scadalts website webserver
docker exec -i database mysql -uroot -proot < /tmp/scadalts-only.sql
docker exec database mysql -uroot -proot -e "CREATE USER IF NOT EXISTS 'webapp'@'%' IDENTIFIED BY 'waterdb'; GRANT ALL ON scadalts.* TO 'webapp'@'%';"
```
The dump loads into an empty datadir; a fresh container creates the `scadalts` database on first start.
If instead you reuse a raw `db_data` folder, delete its stale `*.pem` files first and
`chown -R mysql:mysql` it from the image (`docker run --rm -v $PWD/scadalts/db_data:/d --entrypoint sh mysql/mysql-server:8.0.32 -c 'chown -R mysql:mysql /d'`).
Otherwise `caching_sha2_password` logins fail with "Authentication requires either RSA keys or SSL encryption".

## 8. Start everything and verify

```bash
export ANTHROPIC_API_KEY=...        # optional, wiki chatbot; put it in your shell or a compose .env
docker compose up -d
docker compose ps
```

| Check | Command / URL | Expected |
|---|---|---|
| Wiki | `http://<vm>/` | 200; log in with an account from the dump |
| WordPress + Shellshock | `http://<vm>:8089/cgi-bin/vulnerable.sh` | 200; `curl -A '() { :; }; echo; echo OK' ...` prints `OK` |
| ScadaLTS | `http://<vm>:8081/Scada-LTS/` | login page (default `admin`/`admin`); the root path `/` is 404 by design |
| PLC Modbus | ports 5022-5026 | open |
| PLC web UIs | `http://<vm>:8082`..`:8086` | login page |
| MQTT TLS | `docker logs mosquitto` | `client10` connected, TLSv1.3 |
| EPANET | `docker logs epanet`, `epanet/app/epanet.log` | register reads for `plc-zone0..4` |
| OpenSearch | `curl -sk -u 'admin:Patat123!' https://localhost:9200/apache_logs/_count` | count grows (also `epanet_logs`) |
| Dashboards | `http://<vm>:5601` | 200 |

## 9. Firewall (not automated)

```bash
sudo ufw default deny incoming
sudo ufw allow from <trusted-ip-or-subnet> to any
sudo ufw allow 22/tcp      # keep SSH access before enabling
sudo ufw enable
```
Note: Docker-published ports bypass ufw's normal chains; also restrict with the `DOCKER-USER` chain
or bind the ports to a trusted interface in the compose file.

## 10. Known gotchas

- **Out of disk during build**: `docker builder prune -af` frees build cache (about 7 GB).
- **Docker group not active**: your shell must be a new login session; `sg docker -c "docker ps"` works in the meantime.
- **Wiki shows PHP warnings or login does nothing**: the image needs the server's `php.ini` (step 4/5).
- **WordPress on :8089 shows the install page**: WordPress was never installed on the school server either (no `wp_*` tables).
- **OpenSearch password** must be consistent: `Patat123!` in `opensearch/opensearch.env`, `opensearch/*.yaml`, and the compose file.
- **Reboot**: all services use `restart: always`; if something is down, `docker compose up -d`.
