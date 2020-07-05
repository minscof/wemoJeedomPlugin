#!/usr/bin/env python3
'''wemo controller'''
# -*- coding: utf-8 -*-
import sys
import logging
import json

import urllib.request
import urllib.error
import urllib.parse
from urllib.error import URLError, HTTPError
import socketserver
from datetime import datetime
import pywemo

__version__ = '0.94'
logging.basicConfig(level=logging.DEBUG,
                    format='%(asctime)-15s - %(name)s: %(message)s')
LOGGER = logging.getLogger('wemo_server')

DEBUG = False

reqlog = logging.getLogger("urllib3.connectionpool")
reqlog.disabled = not DEBUG

reqlog = logging.getLogger("pywemo.discovery")
reqlog.disabled = not DEBUG

reqlog = logging.getLogger("pywemo.subscribe")
reqlog.disabled = not DEBUG

reqlog = logging.getLogger("pywemo.ssdp")
reqlog.disabled = not DEBUG

reqlog = logging.getLogger("pywemo.ouimeaux_device")
reqlog.disabled = not DEBUG

reqlog = logging.getLogger("pywemo.ouimeaux_device.insight")
reqlog.disabled = not DEBUG

reqlog = logging.getLogger("pywemo.ouimeaux_device.api.service")
reqlog.disabled = not DEBUG

# CALLBACK_URL=${1}
CALLBACK_URL_SAMPLE = "http://localhost?payload="

if len(sys.argv) > 1:
    CALLBACK_URL = sys.argv[1]
else:
    CALLBACK_URL = CALLBACK_URL_SAMPLE

# listen PORT=${2}
if len(sys.argv) > 2:
    PORT = int(sys.argv[2])
    HOST, PORT = "localhost", int(sys.argv[2])
else:
    PORT = 5000
    HOST, PORT = "localhost", 5000

# LOGLEVEL=${3}
LOGLEVEL = "info"
if len(sys.argv) > 3:
    LOGLEVEL = sys.argv[3]

if LOGLEVEL == "debug":
    LOGLEVEL = logging.DEBUG
elif LOGLEVEL == "info":
    LOGLEVEL = logging.INFO
elif LOGLEVEL == "warning":
    LOGLEVEL = logging.WARNING
elif LOGLEVEL == "error":
    LOGLEVEL = logging.ERROR

LOGGER.setLevel(LOGLEVEL)

LOGGER.info('Program starting version %s', __version__)

LOGGER.debug('LOGLEVEL %s', LOGLEVEL)

def _status(state):
    return 1 if state == 1 else 0

def _standby(state):
    return 1 if state == 8 else 0

def parse_insight_params(params):
    """Parse the Insight parameters."""
    #global LOGGER
    #LOGGER.debug('______parse %s',params)
    (
        state,  # 0 if off, 1 if on, 8 if on but load is off
        lastchange,
        onfor,  # seconds
        ontoday,  # seconds
        ontotal,  # seconds
        timeperiod,  # pylint: disable=unused-variable
        wifipower,
        currentmw,
        todaymw,
        totalmw
    ) = params.split('|')
    state = int(state)
    return {'status': _status(state),
            'standby': _standby(state),
            'lastchange': datetime.fromtimestamp(int(lastchange)),
            'onfor': int(onfor),
            'ontoday': int(ontoday),
            'ontotal': int(ontotal),
            'wifiPower': int(wifipower),
            'todaymw': int(float(todaymw)),
            'totalmw': int(float(totalmw)),
            'currentpower': int(float(currentmw))}


def event(self, _type, value):
    '''processing unsollicited received event from callback'''
    #global LOGGER
    #LOGGER.info('event argument = %s',locals().keys())
    try:
        LOGGER.debug('event for device %s with type = %s value %s',
                     self.serialnumber, _type, value)
        if _type == 'BinaryState':
            params = {}
            serialnumber = self.serialnumber
            if self.model_name == "Insight":
                self.update_insight_params()
                params = dict(self.insight_params)
                params['status'] = _status(int(params['state']))
                params['standby'] = _standby(int(params['state']))
                del params['state']
            else:
                params["status"] = self.get_state()
            params["logicalAddress"] = serialnumber
            payload = json.dumps(params, sort_keys=True, default=str)
            LOGGER.info("event: %s", payload)
            if CALLBACK_URL == CALLBACK_URL_SAMPLE:
                LOGGER.warning("event notification disable - callback_url %s not completed",
                               CALLBACK_URL)
            else:
                urllib.request.urlopen(
                    CALLBACK_URL + urllib.parse.quote(payload)).read()
    except HTTPError as error:
        # do something
        LOGGER.warning('HTTP error code: %s , reason: %s', error.code, error.reason)
    except URLError as error:
        # do something
        LOGGER.warning('URL = %s - error: %s', CALLBACK_URL, error.reason)
    except:  # pylint: disable=bare-except
        LOGGER.warning(
            'bug in event for device %s  with type = %s value %s', self.serialnumber, _type, value)


DEVICES = pywemo.discover_devices()
SUBSCRIPTION_REGISTRY = pywemo.SubscriptionRegistry()
AUXILIARY_LIST = []
SUBSCRIPTION_REGISTRY.start()

for device in DEVICES:
    if device.serialnumber not in AUXILIARY_LIST:
        AUXILIARY_LIST.append(device.serialnumber)
        SUBSCRIPTION_REGISTRY.register(device)
        SUBSCRIPTION_REGISTRY.on(device, 'BinaryState', event)
        #SUBSCRIPTION_REGISTRY.on(device, 'EnergyPerUnitCost', event)
        LOGGER.debug(
            "add device %s to BinaryState event subscription", device.serialnumber)
        print(AUXILIARY_LIST)
    else:
        LOGGER.debug(
            "device %s already added to BinaryState event subscription", device.serialnumber)


class ApiRequestHandler(socketserver.BaseRequestHandler):
    '''processing received orders'''

    def __init__(self, request, client_address, server1):
        # initialization.
        self.logger = logging.getLogger('ApiRequestHandler')
        # self.logger.debug('__init__')
        socketserver.BaseRequestHandler.__init__(
            self, request, client_address, server1)

    def start_response(self, code, content_type, data):
        '''sending back response'''
        self.logger.debug(
            'start_response() code = %s payload = %s', code, data)
        code = "HTTP/1.1 " + code + '\r\n'
        self.request.send(code.encode())
        response_headers = {
            'Content-Type': content_type + '; encoding=utf8',
            'Content-Length': len(data),
            'Connection': 'close',
        }
        response_headers_raw = ''.join('%s: %s\n' % (
            k, v) for k, v in response_headers.items())
        self.request.send(response_headers_raw.encode())
        self.request.send(b'\n')
        return self.request.send(data.encode())

    def handle(self):
        #self.logger.debug('start handle()')
        global DEVICES, SUBSCRIPTION_REGISTRY, AUXILIARY_LIST

        data = str(self.request.recv(1024), "utf-8").split('\n')[0]

        lst = data.split()
        stringcount = len(lst)
        #self.logger.debug('split len->"%s"', stringcount)
        if stringcount > 1:
            data = urllib.parse.unquote(urllib.parse.unquote(lst[1]))
        else:
            data = urllib.parse.unquote(urllib.parse.unquote(lst[1]))

        self.logger.debug('recv()->"%s"', data)
        cmd = 'unkown'
        data = data.split("?")
        #self.logger.debug('after split data -> %s', data)
        cmd = data[0]
        #self.logger.debug('cmd -> %s', cmd)
        cmd = cmd.split("/")[1]
        #self.logger.debug('cmd -> %s', cmd)
        arg = ''
        if len(data) > 1:
            arg = data[1]

        #self.logger.debug('arg ->%s', arg)
        if arg:
            options = arg.split('&')
            #key = options[0].rpartition('=')[0]
            value = urllib.parse.unquote(options[0].rpartition('=')[2])
            #if len(options) == 2:
                #key2 = options[1].rpartition('=')[0]
                #value2 = urllib.parse.unquote(options[1].rpartition('=')[2])

        # self.logger.debug('cmd ->%s arg=%s key=%s value=%s key2=%s value2=%s',
        #                  cmd, arg, key, value, key2, value2)

        if not cmd:
            content_type = "text/html"
            self.start_response(
                '200 OK', content_type, '<h1>Welcome. Try a command ex : scan, stop, start.</h1>')
            return

        if cmd == 'scan':
            DEVICES = pywemo.discover_devices()
            payload = '['
            separator = ''
            for device1 in DEVICES:
                if device1.serialnumber not in AUXILIARY_LIST:
                    AUXILIARY_LIST.append(device1.serialnumber)
                    SUBSCRIPTION_REGISTRY.register(device1)
                    SUBSCRIPTION_REGISTRY.on(device1, 'BinaryState', event)
                    SUBSCRIPTION_REGISTRY.on(
                        device1, 'EnergyPerUnitCost', event)
                    LOGGER.debug(
                        "add device %s to BinaryState event subscription", device1.serialnumber)
                    print(AUXILIARY_LIST)
                else:
                    LOGGER.debug(
                        "device %s already added to BinaryState event subscription",
                        device1.serialnumber)
                params = {}
                params['name'] = device1.name
                LOGGER.info("name = %s", params['name'])
                params['host'] = device1.host
                LOGGER.info("host = %s", params['host'])
                params['serialNumber'] = device1.serialnumber
                LOGGER.info("serialnumber = %s", params['serialNumber'])
                params['modelName'] = device1.model_name
                LOGGER.info("modelName = %s", params['modelName'])
                params['model'] = device1.model
                LOGGER.info("model = %s", params['model'])
                state = device1.get_state(True)
                LOGGER.info('state = %s', str(state))
                payload += separator
                payload += json.dumps(params, sort_keys=True, default=str)
                separator = ','
            payload += ']'

            self.logger.debug(' scan data ->%s', payload)

            content_type = "text/javascript"
            self.start_response('200 OK', content_type, payload)
            return

        if cmd == 'toggle':
            # key = address value = serialnumber
            for device1 in DEVICES:
                if device1.serialnumber == value:
                    device1.toggle()
                    params = {}
                    serialnumber = device1.serialnumber
                    if device1.model_name == "Insight":
                        device1.update_insight_params()
                        params = dict(device1.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device1.get_state()
                    params["logicalAddress"] = serialnumber
                    payload = json.dumps(params, sort_keys=True, default=str)
                    content_type = "text/javascript"
                    self.start_response('200 OK', content_type, payload)
                    return
            # pas trouvé tout à 0
            payload = '{"status": 0, "standby": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, payload)
            return

        if cmd == 'on':
            # key = address value = serialnumber
            for device1 in DEVICES:
                if device1.serialnumber == value:
                    device1.on()
                    params = {}
                    serialnumber = device1.serialnumber
                    if device1.model_name == "Insight":
                        device1.update_insight_params()
                        params = dict(device1.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device1.get_state()
                    params["logicalAddress"] = serialnumber
                    payload = json.dumps(params, sort_keys=True, default=str)
                    content_type = "text/javascript"
                    self.start_response('200 OK', content_type, payload)
                    return
            # pas trouvé tout à 0
            payload = '{"status": 0, "standby": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, payload)
            return

        if cmd == 'off':
            # key = address value = serialnumber
            for device1 in DEVICES:
                if device1.serialnumber == value:
                    device1.off()
                    params = {}
                    serialnumber = device1.serialnumber
                    if device1.model_name == "Insight":
                        device1.update_insight_params()
                        params = dict(device1.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device1.get_state()
                    params["logicalAddress"] = serialnumber
                    payload = json.dumps(params, sort_keys=True, default=str)
                    content_type = "text/javascript"
                    self.start_response('200 OK', content_type, payload)
                    return
            # pas trouvé tout à 0
            payload = '{"status": 0, "standby": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, payload)
            return

        if cmd == 'refresh':
            for device1 in DEVICES:
                if device1.serialnumber == value:
                    device1.update_binary_state()
                    params = {}
                    serialnumber = device1.serialnumber
                    if device1.model_name == "Insight":
                        device1.update_insight_params()
                        params = dict(device1.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device1.get_state(True)
                    params["logicalAddress"] = serialnumber
                    payload = json.dumps(params, sort_keys=True, default=str)
                    content_type = "text/javascript"
                    self.start_response('200 OK', content_type, payload)
                    return
            # pas trouvé tout à 0
            payload = '{"status": 0, "standby": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, payload)
            return

        if cmd.startswith('stop') or cmd == 'stop':
            print('stop server requested')
            SUBSCRIPTION_REGISTRY.stop()
            server.server_close()
            server.shutdown()
            sys.exit(1)

        if cmd == 'ping':
            content_type = "text/html"
            self.start_response('200 OK', content_type, "ping")
            return

        self.logger.debug('cmd %s not yet implemented', cmd)
        payload = '{"error": cmd not implemented}'
        content_type = "text/javascript"
        self.start_response('404 Not Found', content_type, payload)
        return


class ApiServer(socketserver.TCPServer):
    '''listen http request to serve'''

    def __init__(self, server_address, handler_class=ApiRequestHandler):
        self.logger = logging.getLogger('ApiServer')
        self.logger.setLevel(LOGLEVEL)
        # self.logger.debug('__init__')
        socketserver.TCPServer.allow_reuse_address = True
        socketserver.TCPServer.__init__(self, server_address, handler_class)

    def server_activate(self):
        self.logger.debug('server_activate')
        return socketserver.TCPServer.server_activate(self)

    def serve_forever(self, poll_interval=0.5):
        self.logger.debug('waiting for request from api')
        self.logger.info('Handling api requests, press <Ctrl-C> to quit')
        return socketserver.TCPServer.serve_forever(self, poll_interval)

    def handle_request(self):
        # self.logger.debug('handle_request')
        return socketserver.TCPServer.handle_request(self)

    def verify_request(self, request, client_address):
        #self.logger.debug('verify_request(%s, %s)', request, client_address)
        return socketserver.TCPServer.verify_request(
            self, request, client_address,
        )

    def process_request(self, request, client_address):
        self.logger.debug('process_request(%s, %s)', request, client_address)
        return socketserver.TCPServer.process_request(
            self, request, client_address,
        )

    def server_close(self):
        self.logger.debug('server_close')
        return socketserver.TCPServer.server_close(self)

    def finish_request(self, request, client_address):
        #self.logger.debug('finish_request(%s, %s)', request, client_address)
        return socketserver.TCPServer.finish_request(
            self, request, client_address,
        )

    def shutdown(self):
        self.logger.debug('shutdown()')


try:
    address = (HOST, PORT)
    server = ApiServer(address, ApiRequestHandler)
    ip, port = server.server_address

    LOGGER.info('Listen on %s:%s', ip, port)
    server.serve_forever()
    LOGGER.info('Server ended')
except (KeyboardInterrupt, SystemExit):
    SUBSCRIPTION_REGISTRY.stop()
    server.server_close()
    server.shutdown()
    LOGGER.info('Server ended via ctrl+C')
    sys.exit(0)

if __name__ == "__main__":
    from pprint import pprint

    pprint("Wemo_server starting..")
