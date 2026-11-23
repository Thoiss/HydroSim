##! HydroSim — Zeek site-config voor Modbus-monitoring
##! Draait met host-capture op de plc_network-bridge (zie docker-compose.snippet.yaml).

# Log als JSON met ISO8601-tijd, zodat Data Prepper / OpenSearch er @timestamp uit haalt.
redef LogAscii::use_json = T;
redef LogAscii::json_timestamps = JSON::TS_ISO8601;

# Schrijf naar de gemounte map.
redef Log::default_logdir = "/logs";

# De Modbus-analyzer (base/protocols/modbus) is standaard geladen en logt modbus.log
# voor TCP/502-verkeer: tijd, uid, bron/doel-host+poort, en de functiecode (bv.
# WRITE_SINGLE_COIL / WRITE_MULTIPLE_COILS). Dat is genoeg om schrijf-commando's en
# hun herkomst te detecteren — niets extra nodig.
#
# Optioneel (gedetailleerde coil-adressen/waarden): laad het track-memmap beleid.
# @load policy/protocols/modbus/track-memmap
