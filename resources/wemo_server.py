#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
import sys
import logging
import argparse
#import subprocess
import json

import pywemo
import urllib.request
import urllib.error
import urllib.parse
import socketserver
from datetime import datetime

__version__ = '0.93'
logging.basicConfig(level=logging.DEBUG,
                    format='%(asctime)-15s - %(name)s: %(message)s')
logger = logging.getLogger('wemo_server')
logger.info('Program starting version %s', __version__)

reqlog = logging.getLogger("urllib3.connectionpool")
reqlog.disabled = True

NOOP = lambda *x: None


#level = logging.DEBUG
# if getattr(args, 'debug', False):
#    level = logging.DEBUG
# logging.basicConfig(level=level)

# callbackUrl=${1}
if len(sys.argv) > 1:
    callbackUrl = sys.argv[1]
else:
    callbackUrl = "http://localhost?payload="

#logger.debug('callback Url %s', callbackUrl)

# listen PORT=${2}
if len(sys.argv) > 2:
    PORT = int(sys.argv[2])
    HOST, PORT = "localhost", int(sys.argv[2])
else:
    PORT = 5000
    HOST, PORT = "localhost", 5000

# loglevel=${3}
if len(sys.argv) > 3:
    loglevel = sys.argv[3]
else:
    loglevel = "info"

logger.debug('loglevel %s', loglevel)

#time_start = time()
#print('Server started at ', strftime("%a, %d %b %Y %H:%M:%S +0000", localtime(time_start)), 'listening on port ', PORT)
#logger.info('Server started at %s listening on port %s',strftime("%a, %d %b %Y %H:%M:%S +0000", localtime(time_start)), PORT)


def _status(state):
    return 1 if state == 1 else 0


def _standby(state):
    return 1 if state == 8 else 0


def parse_insight_params(params):
    """Parse the Insight parameters."""
    #global logger
    #logger.debug('______parse %s',params)
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
    global logger
    #logger.info('event argument = %s',locals().keys())
    try:
        logger.info('event for device %s with type = %s value %s',
                    self.serialnumber, _type, value)
        #logger.info("$$$$$$ $$ device = %s", type(self))
        if _type == 'BinaryState':
            params = {}
            serialnumber = self.serialnumber
            if self.model_name == "Insight":
                params = dict(self.insight_params)
                params['status'] = _status(int(params['state']))
                params['standby'] = _standby(int(params['state']))
                del params['state']
            else:
                params["status"] = self.get_state()
            params["logicalAddress"] = serialnumber
            payload = json.dumps(params, sort_keys=True, default=str)
            logger.debug("json dumps payload = %s", payload)
            urllib.request.urlopen(
                callbackUrl + urllib.parse.quote(payload)).read()
    except:
        logger.warning(
            'bug in event for device  with type = %s value %s', _type, value)


devices = pywemo.discover_devices()

SUBSCRIPTION_REGISTRY = pywemo.SubscriptionRegistry()
SUBSCRIPTION_REGISTRY.start()

for device in devices:
    '''
    state = device.get_state(True)
    logger.info('state = %s', str(state))
    serialnumber = device.serialnumber
    logger.info("serialnumber = %s", serialnumber)
    params = {}
    if device.model_name == "Insight":
        params = dict(device.insight_params)
        params['status'] = _status(int(params['state']))
        params['standby'] = _standby(int(params['state']))
        del params['state']
    else:
        params["status"] = device.get_state()
    params["logicalAddress"] = serialnumber
    payload = json.dumps(params, sort_keys=True, default=str)
    logger.debug("json dumps payload = %s", payload)
    urllib.request.urlopen(callbackUrl + urllib.parse.quote(payload)).read()
    '''
    SUBSCRIPTION_REGISTRY.register(device)
    SUBSCRIPTION_REGISTRY.on(device, 'BinaryState', event)
    #SUBSCRIPTION_REGISTRY.on(device, 'EnergyPerUnitCost', event)


class apiRequestHandler(socketserver.BaseRequestHandler):
    def __init__(self, request, client_address, server):
        # initialization.
        self.logger = logging.getLogger('apiRequestHandler')
        #self.logger.debug('__init__')
        socketserver.BaseRequestHandler.__init__(
            self, request, client_address, server)

    def start_response(self, code, contentType, data):
        self.logger.debug(
            'start_response() code = %s payload = %s', code, data)
        code = "HTTP/1.1 " + code + '\r\n'
        self.request.send(code.encode())
        response_headers = {
            'Content-Type': contentType + '; encoding=utf8',
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
        global devices

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
        key = ''
        value = ''
        key2 = ''
        value2 = ''
        if arg:
            options = arg.split('&')
            key = options[0].rpartition('=')[0]
            value = urllib.parse.unquote(options[0].rpartition('=')[2])
            if len(options) == 2:
                key2 = options[1].rpartition('=')[0]
                value2 = urllib.parse.unquote(options[1].rpartition('=')[2])

        #print('DEBUG = cmd=', cmd, ' arg ', arg, ' key ', key, ' value ', value, ' key2 ', key2, ' value2 ', value2)
        #self.logger.debug('cmd ->%s arg=%s key=%s value=%s key2=%s value2=%s',
        #                  cmd, arg, key, value, key2, value2)

        if not cmd:
            content_type = "text/html"
            self.start_response(
                '200 OK', content_type, '<h1>Welcome. Try a command ex : scan, stop, start.</h1>')
            return

        if cmd == 'scan':
            devices = pywemo.discover_devices()
            payload = '['
            separator = ''
            for device in devices:
                params = {}
                params['name'] = device.name
                logger.info("name = %s", params['name'])
                params['host'] = device.host
                logger.info("host = %s", params['host'])
                params['serialNumber'] = device.serialnumber
                logger.info("serialnumber = %s", params['serialNumber'])
                params['modelName'] = device.model_name
                logger.info("modelName = %s", params['modelName'])
                params['model'] = device.model
                logger.info("model = %s", params['model'])
                state = device.get_state(True)
                logger.info('state = %s', str(state))
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
            for device in devices:
                if device.serialnumber == value:
                    device.toggle()
                    params = {}
                    serialnumber = device.serialnumber
                    if device.model_name == "Insight":
                        params = dict(device.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device.get_state()
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
            for device in devices:
                if device.serialnumber == value:
                    device.on()
                    params = {}
                    serialnumber = device.serialnumber
                    if device.model_name == "Insight":
                        params = dict(device.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device.get_state()
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
            for device in devices:
                if device.serialnumber == value:
                    device.off()
                    params = {}
                    serialnumber = device.serialnumber
                    if device.model_name == "Insight":
                        params = dict(device.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device.get_state()
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
            for device in devices:
                if device.serialnumber == value:
                    device.update_binary_state()
                    params = {}
                    serialnumber = device.serialnumber
                    if device.model_name == "Insight":
                        device.update_insight_params()
                        params = dict(device.insight_params)
                        params['status'] = _status(int(params['state']))
                        params['standby'] = _standby(int(params['state']))
                        del params['state']
                    else:
                        params["status"] = device.get_state(True)
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
            server.server_close()
            os._exit(1)

        if cmd == 'ping':
            content_type = "text/html"
            self.start_response('200 OK', content_type, "ping")
            return

        self.logger.debug('cmd %s not yet implemented', cmd)
        payload = '{"error": cmd not implemented}'
        content_type = "text/javascript"
        self.start_response('404 Not Found', content_type, payload)
        return


class apiServer(socketserver.TCPServer):
    def __init__(self, server_address, handler_class=apiRequestHandler):
        self.logger = logging.getLogger('apiServer')
        #self.logger.debug('__init__')
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

    def close_request(self, request_address):
        #self.logger.debug('close_request(%s)', request_address)
        return socketserver.TCPServer.close_request(
            self, request_address,
        )

    def shutdown(self):
        self.logger.debug('shutdown()')


try:
    address = (HOST, PORT)
    server = apiServer(address, apiRequestHandler)
    ip, port = server.server_address

    logger.info('Listen on %s:%s', ip, port)
    server.serve_forever()
    logger.info('Server ended')
except (KeyboardInterrupt, SystemExit):
    logger.info('Server ended via ctrl+C')
    os._exit(0)