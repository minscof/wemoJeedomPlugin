#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
import sys
import logging
import argparse
import subprocess
import json

import pywemo
import urllib.request, urllib.error, urllib.parse
from datetime import datetime

__version__='0.92'
logging.basicConfig(level=logging.DEBUG,format='%(asctime)-15s - %(name)s: %(message)s')
logger = logging.getLogger('wemo_server')
logger.info('Program starting version %s',__version__)

reqlog = logging.getLogger("urllib3.connectionpool")
reqlog.disabled = True

NOOP = lambda *x: None
import socketserver


if os.path.isfile('/usr/share/nginx/www/jeedom/plugins/wemo/core/php/jeeWemo.php') :
    jeeWemo = '/usr/share/nginx/www/jeedom/plugins/wemo/core/php/jeeWemo.php'
elif os.path.isfile('/var/www/html/plugins/wemo/core/php/jeeWemo.php') :
    jeeWemo = '/var/www/html/plugins/wemo/core/php/jeeWemo.php'
else :
    jeeWemo = '/var/www/plugins/wemo/core/php/jeeWemo.php'

#level = logging.DEBUG
#if getattr(args, 'debug', False):
#    level = logging.DEBUG
#logging.basicConfig(level=level)

if len(sys.argv) > 1:
    PORT = int(sys.argv[1])
    HOST, PORT = "localhost", int(sys.argv[1])
else:
    PORT = 5000
    HOST, PORT = "localhost", 5000

# jeedomIP=${2}
if len(sys.argv) > 2:
    jeedomIP = sys.argv[2]
else:
    jeedomIP = "localhost"

# jeedomApiKey=${3}
if len(sys.argv) > 3:
    jeedomApiKey = sys.argv[3]
else:
    jeedomApiKey = "jeedomApiKey"


jeedomCmd = "http://" + jeedomIP + "/core/api/jeeApi.php?apikey=" + jeedomApiKey + '&type=wemo&value='
logger.info('Jeedom callback cmd %s', jeedomCmd)


#time_start = time()
#print('Server started at ', strftime("%a, %d %b %Y %H:%M:%S +0000", localtime(time_start)), 'listening on port ', PORT)  
#logger.info('Server started at %s listening on port %s',strftime("%a, %d %b %Y %H:%M:%S +0000", localtime(time_start)), PORT)


def _status(state):
    return "1" if state == 1 else "0"

def _standby(state):
    return "1" if state == 8 else "0"

def parse_insight_params(params):
        """Parse the Insight parameters."""
        (
            state,  # 0 if off, 1 if on, 8 if on but load is off
            lastchange,
            onfor,  # seconds
            ontoday,  # seconds
            ontotal,  # seconds
            timeperiod,  # pylint: disable=unused-variable
            wifipower,  # This one is always 41 for me; what is it?
            currentmw,
            todaymw,
            totalmw
        ) = params.split('|')
        return {'status': _status(state),
                'standby' : _standby(state),
                'lastchange': datetime.fromtimestamp(int(lastchange)),
                'onfor': int(onfor),
                'ontoday': int(ontoday),
                'ontotal': int(ontotal),
                'wifipower' : int(wifipower),
                'todaymw': int(float(todaymw)),
                'totalmw': int(float(totalmw)),
                'currentpower': str(int(float(currentmw)))}


def event(self, _type, value):
    global logger
    logger.info('event argument = %s',locals().keys())
    try:
        logger.info('event for device %s with type = %s value %s', self.serialnumber, _type, value)
        if _type == 'BinaryState':
            result = parse_insight_params(value)
            #subprocess.Popen(['/usr/bin/php',jeeWemo,'serialNumber='+self.serialnumber,'state=' + value[0]])
            value = '{"logicalAddress":"' + self.serialnumber + '","status":"' + result['status'] +'"}'
            urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
            value = '{"logicalAddress":"' + self.serialnumber + '","standby":"' + result['standby'] +'"}'
            urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
            value = '{"logicalAddress":"' + self.serialnumber + '","currentPower":"' + result['currentpower'] +'"}'
            urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
    except:
        logger.info('********  bug exception raised in event for device ')
        logger.info('bug in event for device  with type = %s value %s', _type, value)
 

devices = pywemo.discover_devices()

SUBSCRIPTION_REGISTRY = pywemo.SubscriptionRegistry()
SUBSCRIPTION_REGISTRY.start()

for device in devices:
    state = str(device.get_state(True))
    logger.info('state = %s', state)
    serialNumber = device.serialnumber
    logger.info("serialNumber = %s", serialNumber)
    #subprocess.Popen(['/usr/bin/php',jeeWemo,'serialnumber='+serialnumber,'state='+state])
    value = '{"logicalAddress":"' + serialNumber + '","status":"' + _status(state) +'"}'
    urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
    value = '{"logicalAddress":"' + serialNumber + '","standby":"' + _standby(state) +'"}'
    urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
    value = '{"logicalAddress":"' + serialNumber + '","currentPower":"' + str(device.current_power) +'"}'
    urllib.request.urlopen(jeedomCmd + urllib.parse.quote(value)).read()
    SUBSCRIPTION_REGISTRY.register(device)
    SUBSCRIPTION_REGISTRY.on(device, 'BinaryState', event) 
    SUBSCRIPTION_REGISTRY.on(device, 'EnergyPerUnitCost', event)


class jeedomRequestHandler(socketserver.BaseRequestHandler):
    def __init__(self, request, client_address, server):
        # initialization.
        self.logger = logging.getLogger('jeedomRequestHandler')
        self.logger.debug('__init__')
        socketserver.BaseRequestHandler.__init__(self, request, client_address, server)

    def start_response(self, code, contentType, data):
        self.logger.debug('start_response() code = %s data = %s', code, data)
        code = "HTTP/1.1 " + code + '\r\n'
        self.request.send(code.encode())
        response_headers = {
            'Content-Type': contentType +'; encoding=utf8',
            'Content-Length': len(data),
            'Connection': 'close',
        }
        response_headers_raw = ''.join('%s: %s\n' % (k, v) for k, v in response_headers.items())
        self.request.send(response_headers_raw.encode())
        self.request.send(b'\n')
        return self.request.send(data.encode())

    def handle(self):
        self.logger.debug('start handle()')
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
        if len(data)>1:
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
        self.logger.debug('cmd ->%s arg=%s key=%s value=%s key2=%s value2=%s', cmd, arg, key, value, key2, value2 )
        
        if not cmd:
            content_type = "text/html"
            self.start_response('200 OK', content_type, '<h1>Welcome. Try a command ex : scan, stop, start.</h1>')
            return
            
        if cmd == 'scan':
            devices = pywemo.discover_devices()
            result = '['
            separator = ''
            for device in devices:
                state = str(device.get_state(True))
                logger.info('state = %s', state)
                serialNumber = device.serialnumber
                logger.info("serialNumber = %s", serialNumber)
                name = device.name
                logger.info("name = %s", name)
                host = device.host
                logger.info("host = %s", host)
                modelName = device.model_name
                logger.info("modelName = %s", modelName)
                model = device.model
                logger.info("model = %s", model)
                result += separator
                result += json.dumps({'name': name, 'host': host, 'serialNumber': serialNumber, 'modelName': modelName, 'model': model, 'status': _status(state), 'standby': _standby(state)})
                separator = ','
            result += ']'    
            
            # data = '{"1":{"vendor":'+str(equipments[0][0])+'},"2":{"vendor":'+str(equipments[1][0])+'}}'
            #print("DEBUG = data =", data)
            self.logger.debug('result scan data ->%s', result)

            content_type = "text/javascript"
            self.start_response('200 OK', content_type, result)
            return
        
        if cmd == 'blink':
            #key = address value = serialnumber
            for device in devices:
                if device.serialnumber == value :
                    device.update_insight_params()
                    result = '{"status": '+ _status(device.get_state()) +', "standby": '+ _standby(device.get_state()) +', "currentPower": '+ str(device.current_power) +'}'
                    content_type = "text/javascript"
                    self.start_response('200 OK', content_type, result)
                    return
            #pas trouvé tout à 0 
            result = '{"status": 0, "standby": 0, "currentPower": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, result)
            return


        if cmd == 'refresh':
            for device in devices:
                if device.serialnumber == value :
                    device.update_insight_params()
                    result = '{"status": '+ _status(device.get_state()) +', "standby": '+ _standby(device.get_state()) +', "currentPower": '+ str(device.current_power) +', "wifiPower": '+ "41" +'}'
                    content_type = "text/javascript"
                    self.start_response('200 OK', content_type, result)
                    return
            #pas trouvé tout à 0 
            result = '{"status": 0, "standby": 0, "currentPower": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, result)
            return
        
        if cmd.startswith('stop') or cmd == 'stop':
            print('stop server requested')
            #todo close socket
            server.server_close()
            sys.exit()
            # return end_daemon(start_response)
        
        self.logger.debug('cmd %s not yet implemented', cmd)
        result = '{"error": cmd not implemented}'
        content_type = "text/javascript"
        self.start_response('404 Not Found', content_type, result)
        return


class wemoServer(socketserver.TCPServer):
    def __init__(self, server_address, handler_class=jeedomRequestHandler):
        self.logger = logging.getLogger('wemoServer')
        self.logger.debug('__init__')
        socketserver.TCPServer.allow_reuse_address = True
        socketserver.TCPServer.__init__(self, server_address, handler_class)

    def server_activate(self):
        self.logger.debug('server_activate')
        return socketserver.TCPServer.server_activate(self)

    def serve_forever(self, poll_interval=0.5):
        self.logger.debug('waiting for request from jeedom')
        self.logger.info('Handling jeedom requests, press <Ctrl-C> to quit')
        return socketserver.TCPServer.serve_forever(self, poll_interval)

    def handle_request(self):
        #self.logger.debug('handle_request')
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
    server = wemoServer(address, jeedomRequestHandler)
    ip, port = server.server_address 
    
    logger.info('Server on %s:%s', ip, port)
    server.serve_forever()
    logger.info('Server ended')
except (KeyboardInterrupt, SystemExit):
    logger.info('Server ended via ctrl+C')
    sys.exit(0)