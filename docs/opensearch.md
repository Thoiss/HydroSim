# OpenSearch
OpenSearch is an open-source dashboard that we use to [automatically ingest](https://opensearch.org/docs/latest/observing-your-data/log-ingestion/) multiple log files to monitor Apache, Scada-LTS, and EPANET logs.

## Screenshots
For example, the ScadaLTS access log (`scadalts/tomcat_log/localhost_access_log*.txt`) is read and then visualized in our OpenSearch dashboard through a log ingestion workflow.

- An access log file from a web server

![](https://github.com/user-attachments/assets/4616222a-3eeb-4fc8-a8e8-b2597e3ab44a)

- The ingested log file visualization in OpenSearch Dashboard

![](https://github.com/user-attachments/assets/54c1bb90-0231-4e7e-845c-13a46008ab59)

![](https://github.com/user-attachments/assets/d1907fe0-043b-4e40-afd2-7fc72525f5fd)



## Docker configuration
It comprises of the following containers:
- `opensearch-node` - The OpenSearch core
- `opensearch-dashboards` - The OpenSearch web interface

For log ingestion it also requires:
- `fluent-bit` - Log collector that collects log data and sends it to Data Prepper
- `data-prepper` - Receives and transforms the log data into a structure format and indexes it OpenSearch

### Data flow diagram
This diagram shows the log ingestion workflow before it can be visualized in the OpenSearch Dashboard.

![](https://opensearch.org/docs/latest/images/la.png)

### Credentials
The credentials for the OpenSearch dashboard are set in `opensearch/opensearch.env`, in our case `admin:Patat123!`. Note that when setting one it requires at least a lowercase character, an uppercase character, a number and a symbol, otherwise OpenSearch will refuse to run.

## Index pattern
Before the log files can be explored in OpenSearch Dashboard they need a data view (index pattern). Create the following:

- `apache*` with time field `@timestamp`
- `epanet_logs*` with time field `@timestamp`

![](https://github.com/user-attachments/assets/21962c37-8774-4c5b-a84e-af197924fb68)

## Log ingestion workflow
The log file paths are defined in the `docker-compose.yaml`. For example the `/usr/local/tomcat/logs` folder in the scadalts container is mapped to `./scadalts/tomcat_log` on the host machine, which then allows the fluent-bit container to ingest the ScadaLTS access logs into the log pipeline.
```yml
  scadalts:
    container_name: scadalts
    volumes:
      - ./scadalts/tomcat_log:/usr/local/tomcat/logs:rw
```
```yml
  fluent-bit:
    container_name: fluent-bit
    volumes:
      - ./scadalts/tomcat_log:/var/log/scadalts
      - ./epanet/app:/var/log/epanet:ro
```

### Pipelines
Data Prepper exposes separate HTTP ports for each pipeline:

- `log-pipeline` on port `2021` with path `/log/ingest` (Scada-LTS access logs, index `apache_logs`)
- `epanet-pipeline` on port `2022` with path `/log/epanet`

The pipelines are defined in `opensearch/log_pipeline.yaml` and use `@timestamp` for time-based filtering in Dashboards. EPANET log timestamps are parsed from the application log format.

## Testing
When visiting ScadaLTS on http://127.0.0.1:8081/Scada-LTS/ the access log will be updated and should be automatically ingested to the OpenSearch dashboard.

This can also be manually tested by adding a line to the log file, which should show in the dashboard when refreshing it. This will require first elevating to root with `sudo su` or otherwise editing with `sudo nano`, since the log files are supposed to be read-only.
```
echo '63.173.168.120 - - [04/Nov/2021:15:07:25 -0500] "GET /search/tag/list HTTP/1.0" 200 5003' >> ./scadalts/tomcat_log/localhost_access_log.$(date +%F).txt
```

The OpenSearch API also allows querying raw documents which can be used to troubleshoot the connection.
```sh
curl -X GET -u 'admin:Patat123!' -k 'https://localhost:9200/apache_logs/_search?pretty&size=1'
```
Which should return the following JSON response.
```json
{
  "took" : 966,
  "timed_out" : false,
  "_shards" : {
    "total" : 1,
    "successful" : 1,
    "skipped" : 0,
    "failed" : 0
  },
  "hits" : {
    "total" : {
      "value" : 114,
      "relation" : "eq"
    },
    "max_score" : 1.0,
    "hits" : [
      {
        "_index" : "apache_logs",
        "_id" : "-i6brZQBbBx_deTvrVwS",
        "_score" : 1.0,
        "_source" : {
          "date" : 1.738079443556357E9,
          "log" : "172.25.0.1 - - [28/Jan/2025:15:50:43 +0000] \"GET / HTTP/1.1\" 302 286 \"-\" \"Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:134.0) Gecko/20100101 Firefox/134.0\"",
          "request" : "/",
          "auth" : "-",
          "ident" : "-",
          "response" : "302",
          "bytes" : "286",
          "clientip" : "172.25.0.1",
          "verb" : "GET",
          "httpversion" : "1.1",
          "timestamp" : "28/Jan/2025:15:50:43 +0000"
        }
      }
    ]
  }
}
```

## Logging Scada-LTS

The scadalts container log files are in `/usr/local/tomcat/logs` and are created by user and date, for example `localhost_access_log.2025-02-12.txt` which would require us writing a script to properly implement automatic log file ingestion.

![](https://github.com/user-attachments/assets/4af86778-5e30-491f-a9b8-f8c3facccd14)

## Logging EPANET
EPANET writes logs to `epanet/app/epanet.log` inside the container. This file is mounted into fluent-bit at `/var/log/epanet/epanet.log` and shipped to the `epanet_logs` index via the epanet pipeline.

```sh
curl -X GET -u 'admin:Patat123!' -k 'https://localhost:9200/epanet_logs/_search?pretty&size=1'
```
