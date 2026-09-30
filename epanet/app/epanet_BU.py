#!/usr/bin/env python3
import json
import math
import logging
import os
import re
import struct
import time
from pathlib import Path
import logging.handlers

import numpy
import paho.mqtt.client as mqtt
from dotenv import load_dotenv
from epyt import epanet
from pymodbus.client import ModbusTcpClient

load_dotenv()
BROKER = os.getenv("MQTT_BROKER_URL", "")
TOPIC = os.getenv("MQTT_TOPIC", "")

CA = os.getenv("MQTT_CA_CERT", "certs/ca/ca.crt")
KEY = os.getenv("MQTT_CLIENT_KEY", "certs/server/server.key")
CERT = os.getenv("MQTT_CLIENT_CERT", "certs/server/server.crt")
TLS = os.getenv("MQTT_TLS_ENABLED", "true")

DEBUG = os.getenv("DEBUG", False)
LOCALHOST = os.getenv("LOCALHOST", False)
LOG_FILE = os.getenv(
    "LOG_FILE", (Path(__file__).parent.resolve() / "epanet.log").as_posix()
)
LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()

logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    handlers=[
        logging.handlers.RotatingFileHandler(LOG_FILE, maxBytes=10000, backupCount=3)
    ],
)
logger = logging.getLogger(__name__)

ep = epanet((Path(__file__).parent.resolve() / "scenario.inp").as_posix())

# --- CONFIGURATIE ---
zone0 = (
    "plc-zone0" if not DEBUG else ("localhost:5022" if LOCALHOST else "127.0.0.1:5022")
)
zone1 = (
    "plc-zone1" if not DEBUG else ("localhost:5023" if LOCALHOST else "127.0.0.1:5023")
)
zone2 = (
    "plc-zone2" if not DEBUG else ("localhost:5024" if LOCALHOST else "127.0.0.1:5024")
)
zone3 = (
    "plc-zone3" if not DEBUG else ("localhost:5025" if LOCALHOST else "127.0.0.1:5025")
)
zone4 = (
    "plc-zone4" if not DEBUG else ("localhost:5026" if LOCALHOST else "127.0.0.1:5026")
)

# Offset mapping to epanet id
PUMP_MAPPING = {
    zone0: {
        "pump1": 4,
        "pump2": 5,
        "pump3": 6,
        "pump4": 7,
    },
    zone1: {"pump1": 1},
    zone2: {"pump1": 1},
    zone3: {"pump1": 1},
    zone4: {"pump1": 1},
}

VALVE_MAPPING = {
    zone0: {
        "valve0": 17,  # Reservior
        "valve1": 18,  # IN T1
        "valve2": 19,  # OUT T1
        "valve3": 20,  # IN T2
        "valve4": 21,  # OUT T2
        "valve5": 22,  # IN T3
        "valve6": 23,  # OUT T3
        "valve7": 24,  # IN T4
        "valve8": 25,  # OUT T4
    },
    zone1: {"valve1": 3, "valve2": 5},
    zone2: {"valve1": 3, "valve2": 5},
    zone3: {"valve1": 3, "valve2": 5},
    zone4: {"valve1": 3, "valve2": 5},
}

# right adjacent node for flow
JUNCTION_FLOW_NEEDED: list[str] = [
    "z0-junction1",
    "z1-point1",
    "z2-point1",
    "z3-point1",
    "z4-point1",
    # these seem logical but would not work because not all the junctions and pipes have a prefix of the zone they are in
    # "z1-junction1",
    # "z2-junction1",
    # "z3-junction1",
    # "z4-junction1",
]


def setup_clients(zones: list[str]) -> dict[str, ModbusTcpClient]:
    try:
        logger.info("Setting up Modbus clients for zones: %s", zones)
        if not DEBUG:
            clients: dict[str, ModbusTcpClient] = {
                zone: ModbusTcpClient(host=f"plc-{zone.replace('z', 'zone')}", port=502)
                for zone in zones
            }
        else:
            clients: dict[str, ModbusTcpClient] = {
                zone: ModbusTcpClient(
                    host="127.0.0.1" if not LOCALHOST else "localhost",
                    port=5022 + i,
                )
                for i, zone in enumerate(zones)
            }
        for client in clients.values():
            while not client.connect():
                time.sleep(1)
        logger.info("Modbus clients connected")
        return clients
    except Exception as e:
        logger.exception("ERROR in setup_clients: %s", e)
        raise


def get_coil_index(zone, element: str, type_map):
    sanatized_element = re.sub("^z\\w-", "", element)
    if sanatized_element in type_map[zone]:
        return type_map[zone][sanatized_element]
    else:
        return None


def read_plc(zone, client: ModbusTcpClient) -> dict[str, dict]:
    nodes, links = get_zone_items(zone)
    try:
        logger.info("Reading PLC for zone %s", zone)
        if not LOCALHOST and not DEBUG:
            zone_host = client.comm_params.host
        else:
            zone_host = client.comm_params.host + ":" + str(client.comm_params.port)

        nodes = {
            ep.getNodeNameID(node): {"type": ep.getNodeType(node), "index": node}
            for node in nodes
        }

        links = {
            ep.getLinkNameID(link): {"type": ep.getLinkType(link), "index": link}
            for link in links
        }

        rr = client.read_coils(address=0, count=32)
        if not rr.isError():
            # links data
            for element, data in links.items():
                ltype = data["type"]
                if ltype not in ["PUMP", "VALVE", "TCV", "FCV"]:
                    continue

                map_dict = PUMP_MAPPING if ltype == "PUMP" else VALVE_MAPPING

                idx = get_coil_index(zone_host, element, map_dict)

                if idx < len(rr.bits):
                    is_running = rr.bits[idx]
                    links[element].update({"status": 1.0 if is_running else 0.0})

        logger.info(
            "Finished reading PLC for %s (nodes=%d, links=%d)",
            zone_host,
            len(nodes),
            len(links),
        )
        return nodes, links
    except Exception as e:
        logger.exception("Error in read_plc: %s", e)
        raise


def set_nodedata(nodes: dict[str, dict]):
    logger.info("Applying node updates (%d)", len(nodes))
    for name, node in nodes.items():
        try:
            index = node["index"]
            # quality can be set
            # ep.setNodeSourceQuality(index, node["quality"])

            match ep.getNodeType(index):
                case "JUNCTION":
                    # changes the base demand that is multiplied by the demand curve
                    # node_data["demand"] = ep.setNodeBaseDemands(index)
                    pass
                case "TANK":
                    # you can change if the tanks can overflow but this is not implemented and/or researched
                    # ep.setNodeTankCanOverFlow()

                    # you can change the min and max water levels but we don't change these mid simulation for now
                    # ep.setNodeTankMaximumWaterLevel(index,node["max"])
                    # ep.setNodeTankMinimumWaterLevel(index,node["min"])
                    pass
                case "RESERVOIR":
                    pass
                case _:
                    pass

        except Exception as e:
            logger.exception("setting values for node %s returned: %s", name, e)


def set_linkdata(links: dict[str, dict]):
    logger.info("Applying link updates (%d)", len(links))
    for name, link in links.items():
        try:
            index = link["index"]
            # the same as speed = 0 or if the pipe/valve is open of closed
            if isinstance(status_val := link.get("status"), int | float):
                current_status = ep.getLinkStatus(index)

                new_status = 1.0 if status_val > 0.5 else 0.0

                if new_status != current_status:
                    ep.setLinkStatus(index, new_status)

            match ep.getLinkType(index):
                case "PIPE":
                    pass
                case "PUMP":
                    # if power := link.get("power"):
                    #     ep.setLinkPumpPower(index, power)

                    # used to change the rotation speed. 0 = off
                    if speed := link.get("speed"):
                        # we use the status instead of the speed
                        new_status = 1.0 if link.get("status", 0) > 0.5 else 0.0
                        ep.setLinkSettings(index, new_status)
                    pass
                # there are more valve types but only the TCV is used
                case "VALVE" | "TCV" | "FCV":
                    pass
                case _:
                    pass
        except Exception as e:
            logger.exception("setting values for link %s returned: %s", name, e)


def float_to_registers(value):
    return list(struct.unpack(">HH", struct.pack(">f", value)))


def flow_needed(nodes_list: numpy.ndarray[list]):
    for nodes in nodes_list:
        node = str(nodes[1])
        if node in JUNCTION_FLOW_NEEDED:
            return node
    else:
        return False


def write_plc(
    client: ModbusTcpClient, nodes_data: dict[str, dict], links_data: dict[str, dict]
) -> None:
    try:
        if not LOCALHOST and not DEBUG:
            zone_host = client.comm_params.host
        else:
            zone_host = client.comm_params.host + ":" + str(client.comm_params.port)

        logger.info(
            "Writing PLC data for %s (nodes=%d, links=%d)",
            zone_host,
            len(nodes_data),
            len(links_data),
        )
        sensor_mask = 0

        for link, data in links_data.items():
            nodes = ep.getNodesConnectingLinksID(data["index"])

            if node := flow_needed(nodes):
                # set flow 0 when pressure is negetive because the network is disconnected and still calculates the values
                if nodes_data[node]["pressure"] < 0:
                    flow = data["flow"] = 0.0
                else:
                    flow = data["flow"]

                logger.info(
                    f"{zone_host:<14} | {f'{link}-{node}':<12} | {'METER':<6} | {f'FLOW {flow}':<16} | Reg 700"
                )
                try:
                    client.write_registers(address=700, values=float_to_registers(flow))
                except Exception as e:
                    logger.debug(
                        "Failed writing flow registers for %s %s-%s: %s",
                        zone_host,
                        link,
                        node,
                        e,
                    )
                    pass

            if (data_type := data.get("type")) in ["PUMP", "VALVE", "TCV", "FCV"]:
                status = data.get("status")
                if data_type in ["VALVE", "TCV", "FCV"]:
                    text = "OPEN" if status else "DICHT"
                else:
                    text = "AAN" if status else "UIT"

                map_dict = PUMP_MAPPING if data_type == "PUMP" else VALVE_MAPPING
                idx = get_coil_index(zone_host, link, map_dict)

                plc_display = f"{text} (Coil {idx})"
                logger.info(
                    f"{zone_host:<14} | {link:<12} | {data_type:<6} | {text:<16} | {plc_display}, {status}"
                )

        for node, data in nodes_data.items():
            if data.get("type") == "TANK":
                try:
                    tank_num = int("".join(filter(str.isdigit, node))) - 1
                    level = data.get("level")

                    is_low, is_high = level < 5.0, level > 15.0
                    base_bit = tank_num * 2
                    if is_low:
                        sensor_mask |= 1 << base_bit
                    if is_high:
                        sensor_mask |= 1 << (base_bit + 1)
                    try:
                        reg_address = 10 + (tank_num * 2)
                        client.write_registers(
                            address=reg_address,
                            values=float_to_registers(level),
                        )
                        logger.info(
                            f"{zone_host:<14} | {node:<12} | {'TANK':<6} | {f'LEVEL {level}':<16} | Reg {reg_address}"
                        )
                    except Exception as e:
                        logger.debug(
                            "Failed writing tank register for %s %s: %s",
                            zone_host,
                            node,
                            e,
                        )
                        pass
                except Exception as e:
                    logger.debug(
                        "Failed processing tank data for %s %s: %s",
                        zone_host,
                        node,
                        e,
                    )
                    pass

        try:
            client.write_registers(address=0, values=[sensor_mask])
        except Exception as e:
            logger.debug(
                "Failed writing sensor mask for %s: %s",
                zone_host,
                e,
            )
            pass

    except Exception:
        logger.exception("Unexpected error in write_plc")
        pass


def get_zone_items(zone_id: str) -> tuple[list, list]:
    """fucntion to get the items from a zone

    Args:
        zone_id (str): id of the zone u want the items from

    Returns:
        tuple (list, list): returns a tuple of the nodes and links in the zone
    """
    nodes = [
        id
        for id, name in zip(ep.getNodeIndex(), ep.getNodeNameID())
        if name.startswith(zone_id)
    ]
    links = [
        id
        for id, name in zip(ep.getLinkIndex(), ep.getLinkNameID())
        if name.startswith(zone_id)
    ]
    logger.info(
        "Zone %s items resolved (nodes=%d, links=%d)",
        zone_id,
        len(nodes),
        len(links),
    )
    return nodes, links


def get_nodedata(nodes):
    zone_data = {}

    logger.info("Collecting node data for %d nodes", len(nodes))
    for node in nodes:
        node_data = dict()
        name = ep.getNodeNameID(node)
        node_data["index"] = node
        node_data["type"] = ep.getNodeType(node)
        node_data["pressure"] = round(float(ep.getNodePressure(node)), 3)
        node_data["quality"] = round(float(ep.getNodeActualQuality(node)), 3)
        node_data["elevation"] = ep.getNodeElevations(node)
        match ep.getNodeType(node):
            case "JUNCTION":
                node_data["demand"] = round(float(ep.getNodeActualDemand(node)), 3)
                node_data["head"] = round(float(ep.getNodeHydraulicHead(node)), 3)
            case "TANK":
                # h = V / (pi * r^2)
                # V = pi * r^2 * h
                diameter = ep.getNodeTankDiameter(node)
                volume = ep.getNodeTankVolume(node)

                # Bereken oppervlakte van de bodem: pi * r^2
                # (r = diameter / 2)
                area = math.pi * ((diameter / 2) ** 2)

                # Bereken level: Volume / Oppervlakte
                if area > 0:
                    calculated_level = volume / area
                else:
                    calculated_level = 0.0

                node_data["level"] = round(float(calculated_level), 3)
            case "RESERVOIR":
                pass
            case _:
                pass
        # print(name, json.dumps(node_data))
        zone_data[name] = node_data
    return zone_data


def get_linkdata(links):
    zone_data = {}
    logger.info("Collecting link data for %d links", len(links))


    for link in links:
        link_data = dict()
        # variables of every node
        name = ep.getLinkNameID(link)
        link_data["index"] = link
        link_data["type"] = ep.getLinkType(link)
        link_data["quality"] = float(float(ep.getLinkActualQuality(link)))
        link_data["headloss"] = round(float(ep.getLinkHeadloss(link)), 3)
        link_data["flow"] = round(float(ep.getLinkFlows(link)), 3)
        # the same as speed = 0 or if the pipe/valve is open of closed
        link_data["status"] = int(ep.getLinkStatus(link))

        match ep.getLinkType(link):
            case "PIPE":
                link_data["velocity"] = round(float(ep.getLinkVelocity(link)), 3)
                link_data["length"] = int(ep.getLinkLength(link))
                pass
            case "PUMP":
                # determent to be always 1
                link_data["power"] = ep.getLinkPumpPower(link)
                # used to change the rotation speed. 0 = off
                link_data["speed"] = float(ep.getLinkSettings(link))
                link_data["energy"] = round(float(ep.getLinkEnergy(link)), 3)
                link_data["efficiency"] = round(ep.getLinkPumpEfficiency(link), 3)
                link_data["state"] = ep.getLinkPumpState(link)
            # there are more valve types but only the TCV is used
            case "VALVE" | "TCV" | "FCV":
                link_data["velocity"] = round(float(ep.getLinkVelocity(link)), 3)
                pass
            case _:
                pass
        # print(name, json.dumps(link_data))
        zone_data[name] = link_data
    return zone_data

def convert_numpy(obj):

    if isinstance(obj, numpy.ndarray):
        return obj.tolist()

    if isinstance(obj, numpy.generic):
        return obj.item()

    raise TypeError(f"Cannot serialize object of type {type(obj)}")



def main():
    mqtt_client = mqtt.Client(client_id=f"mqtt-{os.urandom(4).hex()}")
    try:
        logger.info("Initializing MQTT client")
        if TLS and CA and KEY and CERT:
            mqtt_client.tls_set(ca_certs=CA, certfile=CERT, keyfile=KEY)
            mqtt_client.tls_insecure_set(True)
            host = BROKER.split("://")[-1]
            mqtt_client.connect(host, 8883)
        else:
            mqtt_client.connect(BROKER.split("://")[-1])
        mqtt_client.loop_start()
        logger.info("MQTT connected")
    except Exception:
        pass

    try:
        zone_ids = ["z0", "z1", "z2", "z3", "z4"]

        logger.info("Starting EPANET simulation")
        clients: dict[str, ModbusTcpClient] = setup_clients(zone_ids)

        ep.setTimeSimulationDuration(24 * 3600)  # bv. 1 dag in seconden
        tstep = 5 * 60
        ep.setTimeHydraulicStep(tstep)
        ep.openHydraulicAnalysis()
        ep.initializeHydraulicAnalysis(0)

        logger.info("Hydraulic analysis initialized (tstep=%s)", tstep)
        while True:
            # this way the duration is set to infinite.
            ep.setTimeSimulationDuration(
                ep.getTimeSimulationDuration() + ep.getTimeHydraulicStep()
            )

            for zone, client in clients.items():
                nodes, links = read_plc(zone, client)
                set_nodedata(nodes)
                set_linkdata(links)

            t = ep.runHydraulicAnalysis()
            tstep = ep.nextHydraulicAnalysisStep()
            logger.info("Hydraulic step complete (t=%s, next_step=%s)", t, tstep)

            for zone, client in clients.items():
                nodes, links = get_zone_items(zone)
                # there is no way as of 11-12-2025 to get the tag value of the links and nodes
                # epyt has a way to add comments with setNodeComment() and setLinkComment()
                nodes_data = get_nodedata(nodes)
                links_data = get_linkdata(links)

                write_plc(client, nodes_data, links_data)
                try:
                    json__data = json.dumps(nodes_data, default=convert_numpy, indent=2)
                    mqtt_client.publish(TOPIC, str(json__data))
                    # logger.info("Convert links_data to json .....")
                    json__data2 = json.dumps(links_data, indent=2)
                    #logger.info("dump: (json__data=%s)", json__data2)
                    mqtt_client.publish(TOPIC, str(json__data2))
                except ValueError as e:
                    logger.info("%s: '%s'", e, TOPIC)

            time.sleep(5)
            if tstep <= 0:
                logger.info("Hydraulic analysis complete")
                break
    except KeyboardInterrupt:
        logger.info("Program interrupted by user")
    except Exception as e:
        logger.exception(
            "Failed to run EPANET simulation due to an unexpected error: %s",
            e,
        )
    finally:
        local_variables = locals()
        if "clients" in local_variables:
            for client in clients.values():
                client.close()

        ep.closeHydraulicAnalysis()
        ep.unload()
        if "mqtt" in local_variables:
            mqtt_client.loop_stop()
            mqtt_client.disconnect()


if __name__ == "__main__":
    main()
