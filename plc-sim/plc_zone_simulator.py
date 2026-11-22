#!/usr/bin/env python3
"""
HydroSim — PLC-zone activiteitssimulator (NORMAAL BEDRIJF / baseline)

Laat de pompen over de zones in een geloofwaardig duty-cycle-patroon draaien,
als *legitieme* werking: gestaffeld, met een maximum aantal pompen tegelijk aan.
Bedoeld om een levend dashboard + een meetbaar normaalpatroon te geven.

Mechanisme: OpenPLC-uitgangscoils zijn programma-gestuurd (de PLC zet een
geschreven coil elke scan ~50 ms terug). Daarom 'houdt' de simulator een pomp
AAN door de coil continu te herschrijven (hameren) zolang die in 'on'-staat is.
Een pomp UIT = simpelweg niet meer schrijven (het programma zet 'm terug op 0).

Draait als losse container op plc_network (zie docker-compose.snippet.yml) of
via:  docker exec epanet python3 plc_zone_simulator.py
"""

import os
import random
import time
from pymodbus.client import ModbusTcpClient

# ---- Configuratie (pas desgewenst aan via env) -----------------------------
PORT = int(os.getenv("PLC_PORT", "502"))
MAX_CONCURRENT = int(os.getenv("MAX_CONCURRENT", "3"))   # max pompen tegelijk AAN (baseline)
ON_MIN, ON_MAX = int(os.getenv("ON_MIN", "60")), int(os.getenv("ON_MAX", "240"))      # on-duur (s)
OFF_MIN, OFF_MAX = int(os.getenv("OFF_MIN", "180")), int(os.getenv("OFF_MAX", "600"))  # off-duur (s)
WRITE_INTERVAL = float(os.getenv("WRITE_INTERVAL", "0.025"))  # hameren (s); < PLC-scan (~50 ms)

# Pompenkaart: host = PLC-zone-container, coil = Modbus-coiladres (_Cmd) dat EPANET leest.
# gate_coil = coil van de hoofdklep (_Cmd) die de pomp vrijgeeft (interlock). Zolang die
# dicht is, zet het PLC-programma het pomp-commando elke scan terug → _Run blijft 0.
# Zone0: Valve0_Cmd = %QX2.1 = coil 17 geeft álle 4 de pompen vrij (bevestigd in zone_0.st).
# Zone1-4: interlock nog te bepalen uit zone_1..4.st → voorlopig None (pomp-commando only).
PUMPS = [
    {"name": "z0-pump1", "host": "plc-zone0", "coil": 4, "gate_coil": 17},
    {"name": "z0-pump2", "host": "plc-zone0", "coil": 5, "gate_coil": 17},
    {"name": "z0-pump3", "host": "plc-zone0", "coil": 6, "gate_coil": 17},
    {"name": "z0-pump4", "host": "plc-zone0", "coil": 7, "gate_coil": 17},
    {"name": "z1-pump1", "host": "plc-zone1", "coil": 1, "gate_coil": 3},
    {"name": "z2-pump1", "host": "plc-zone2", "coil": 1, "gate_coil": 3},
    {"name": "z3-pump1", "host": "plc-zone3", "coil": 1, "gate_coil": 3},
    {"name": "z4-pump1", "host": "plc-zone4", "coil": 1, "gate_coil": 3},
]
# ---------------------------------------------------------------------------


def log(msg):
    print(f"[{time.strftime('%Y-%m-%dT%H:%M:%S')}] {msg}", flush=True)


def get_client(clients, host):
    """Persistente Modbus-client per zone-host; reconnect gebeurt via pop() bij een schrijffout."""
    c = clients.get(host)
    if c is None:
        c = ModbusTcpClient(host, port=PORT, timeout=2)
        c.connect()
        clients[host] = c
    return c


def main():
    random.seed()
    clients = {}
    gate_state = {}  # (host, gate_coil) -> laatst geschreven open/dicht, voor change-logging
    now = time.time()

    # Elke pomp start UIT, met een korte gestaffelde eerste 'on'-tijd (spreiding ~30 s),
    # zodat er snel activiteit is; de concurrency-cap voorkomt dat alles tegelijk aangaat.
    for p in PUMPS:
        p["state"] = "off"
        p["next_toggle"] = now + random.uniform(0, 30)

    log(f"Simulator gestart — {len(PUMPS)} pompen, max {MAX_CONCURRENT} tegelijk AAN, "
        f"on {ON_MIN}-{ON_MAX}s / off {OFF_MIN}-{OFF_MAX}s")

    while True:
        now = time.time()
        on_count = sum(1 for p in PUMPS if p["state"] == "on")

        for p in PUMPS:
            if now < p["next_toggle"]:
                continue
            if p["state"] == "off":
                # Pomp wil AAN — alleen als we onder de concurrency-cap zitten.
                if on_count < MAX_CONCURRENT:
                    p["state"] = "on"
                    on_count += 1
                    p["next_toggle"] = now + random.uniform(ON_MIN, ON_MAX)
                    log(f"PUMP AAN  {p['name']} (zone {p['host']}, coil {p['coil']}) "
                        f"— {on_count}/{MAX_CONCURRENT} actief")
                else:
                    # Cap bereikt — kort opnieuw proberen.
                    p["next_toggle"] = now + random.uniform(10, 40)
            else:
                # Pomp gaat UIT (stoppen met schrijven → programma zet terug op 0).
                p["state"] = "off"
                on_count -= 1
                p["next_toggle"] = now + random.uniform(OFF_MIN, OFF_MAX)
                log(f"PUMP UIT  {p['name']} — {on_count}/{MAX_CONCURRENT} actief")

        # Interlock-bewust: bepaal per hoofdklep of die open moet (≥1 pomp erachter AAN).
        gates = {}
        for p in PUMPS:
            if p["gate_coil"] is None:
                continue
            key = (p["host"], p["gate_coil"])
            gates[key] = gates.get(key, False) or (p["state"] == "on")

        # Schrijf de hoofdkleppen (open bij pompvraag, anders dicht) + log de wissels.
        for (host, gcoil), should_open in gates.items():
            if gate_state.get((host, gcoil)) != should_open:
                gate_state[(host, gcoil)] = should_open
                log(f"KLEP {'OPEN ' if should_open else 'DICHT'} hoofdklep coil {gcoil} @ {host}")
            try:
                get_client(clients, host).write_coil(gcoil, should_open)
            except Exception as e:  # noqa: BLE001
                log(f"WARN klep-schrijven coil {gcoil}@{host} mislukt: {e}")
                clients.pop(host, None)

        # Hameren: schrijf True naar elke actieve pomp-coil (de gate staat dan open,
        # dus het pomp-commando overleeft de scan en _Run volgt).
        for p in PUMPS:
            if p["state"] == "on":
                try:
                    get_client(clients, p["host"]).write_coil(p["coil"], True)
                except Exception as e:  # noqa: BLE001 — simulator moet blijven lopen
                    log(f"WARN schrijven {p['name']} mislukt: {e}")
                    clients.pop(p["host"], None)  # forceer reconnect volgende ronde

        time.sleep(WRITE_INTERVAL)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log("Simulator gestopt.")
