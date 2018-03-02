# Flame graph builder

Just a wrapper for [Brendan Gregg's scripts](http://www.brendangregg.com/FlameGraphs/cpuflamegraphs.html#Instructions) for building CPU flame graphs.

### Howto

1. Build an image

        docker build . --tag flame-graph-builder

2. Run the container

        docker run --rm -it -p $external_ip:80:80 flame-graph-builder

3. Upload you perf script

        [root@server ~]# perf script | curl -# -i -X POST http://IP_FROM_PREVIOUS_STEP/ -F perfScript=@- | tee /dev/null
        ######################################################################## 100.0%
        HTTP/1.1 100 Continue

        HTTP/1.1 201 Created
        Date: Thu, 01 Mar 2018 23:52:41 GMT
        Server: Apache/2.4.25 (Debian)
        X-Powered-By: PHP/7.2.2
        Vary: Accept,Accept-Encoding
        Content-Length: 457
        Content-Type: text/plain;charset=UTF-8

        flameGraph: http://IP_FROM_PREVIOUS_STEP/reports/613f7450e5b870e38da466681f13afa70262cd15/flame-graph.svg
        icicleGraph: http://IP_FROM_PREVIOUS_STEP/reports/613f7450e5b870e38da466681f13afa70262cd15/icicle-graph.svg
        perfScript: http://IP_FROM_PREVIOUS_STEP/reports/613f7450e5b870e38da466681f13afa70262cd15/perf-script.txt
        perfFolded: http://IP_FROM_PREVIOUS_STEP/reports/613f7450e5b870e38da466681f13afa70262cd15/perf-folded.txt
        reportId: 613f7450e5b870e38da466681f13afa70262cd15

4. ???

5. Profit!
