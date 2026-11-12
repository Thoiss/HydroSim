# Operational Technology Simulation System

## Overview

This project integrates EPANET, OpenPLC, and ScadaLTS to create a comprehensive simulation environment for operational technology (OT) systems, with a focus on water networks. This is further expanded with a MQTT-broker to allow a secure connection to physical hardware. This can be used to make a physical model of the simulation environment.

## Components

### Simulation

- #### EPANET
    Simulates water distribution systems, providing a digital twin of the water network.

- #### OpenPLC
    Acts as the programmable logic controller, interfacing with the EPANET simulation to read sensor data and execute control logic.

- #### ScadaLTS
    Provides the human-machine interface (HMI) for visualization and interaction with the simulated system.

- #### Opensearch
    Retrieves logs from ScadaLTS (privilege escalation and RCE) and EPANET.

- #### MQTT-broker
    Allows EPANET to send the state of the simulation in JSON format to any receiving device.

### Physical hardware

- #### Raspberry Pi
    Interprets and translates the state of the simulation so the physical hardware can understand it:
    1. Receives and interprets the simulation state (JSON over MQTT to class objects).
    2. Translates the received state to modbus read/write instructions and sends it to the physical model (Class objects to modbus data).

- #### PLC
    Receives the state of the simulation using modbus instructions, which can be used to physically demonstrate the state of the simulation.

## Features

- Realistic simulation of water network behavior
- Real-time control logic execution
- User-friendly HMI for system monitoring and control
- Physical connection capabilities over MQTT
- Scenario testing and analysis capabilities
- Students training in a safe, simulated environment

## Requirements
- Docker (and Docker compose, see [Docker installation guide](https://docs.docker.com/engine/install/)) 
- OpenSSL certificates (see [MQTT](docs/mqtt.md))

## Installation
```
git clone https://github.com/coecs-hhs/HydroSim
cd HydroSim/mqtt
./generate_all.sh
cd ..
sudo docker compose up -d
```

**Note**: 
If something goes wrong while trying to generate the certificates, check ["Setup & Certificates"](docs/mqtt.md) or run the individual generation scripts one by one manually.

## Documentation
- [Epanet](docs/epanet.md)
- [OpenPLC](docs/openplc.md)
- [OpenSearch](docs/opensearch.md)
- [ScadaLTS](docs/scadalts.md)
- [MQTT broker](docs/mqtt.md)

## Dev container (EPANET debugging)
- Install the VS Code Dev Containers extension and open this folder in VS Code.
- Run “Reopen in Container”; the image (Python 3.12) installs `epyt`, `pymodbus`, `paho-mqtt`, `pyModbusTCP`, and `debugpy` from `.devcontainer/requirements.txt`.
- Use the existing launch config `Python Debugger: Current File with Arguments` to debug `epanet/app/epanet.py` (it defaults to `scenario.inp`).
- Set MQTT/TLS env vars in `.env` if you need non-default connection details during debugging.

## Contributing

Contributions to improve the simulation or add new features are welcome. Please submit pull requests or open issues for discussion.

## Support

For questions or issues, please open an issue in this repository.
