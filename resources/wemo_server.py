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

__version__='0.91'
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

level = logging.DEBUG
#if getattr(args, 'debug', False):
#    level = logging.DEBUG
logging.basicConfig(level=level)

'''
def on_switch(switch):
    print "Switch found!", switch.name

def on_motion(motion):
    print "Motion found!", motion.name
'''    

devices = pywemo.discover_devices()

for device in devices:
    state = str(device.get_state(True))
    logger.info('state = %s', state)
    serialnumber = device.serialnumber
    logger.info("serialnumber = %s", serialnumber)
    subprocess.Popen(['/usr/bin/php',jeeWemo,'serialnumber='+serialnumber,'state='+state])
    #print "{} state is {state}".format(sender.serialnumber, state="on" if kwargs.get('state') else "off")
        
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
                serialnumber = device.serialnumber
                logger.info("serialnumber = %s", serialnumber)
                name = device.name
                logger.info("name = %s", name)
                host = device.host
                logger.info("host = %s", host)
                model_name = device.model_name
                logger.info("type = %s", model_name)
                model = device.model
                logger.info("model = %s", model)
                result += separator
                result += json.dumps({'name': name, 'host': host, 'serialnumber': serialnumber, 'model_name': model_name, 'model': model, 'state': state})
                separator = ','
            result += ']'    
            
            # data = '{"1":{"vendor":'+str(equipments[0][0])+'},"2":{"vendor":'+str(equipments[1][0])+'}}'
            #print("DEBUG = data =", data)
            self.logger.debug('result scan data ->%s', result)

            content_type = "text/javascript"
            self.start_response('200 OK', content_type, result)
            return
        
        if cmd == 'state':
            result = '{"state": 0, "standby": 0}'
            content_type = "text/javascript"
            self.start_response('200 OK', content_type, result)
            return

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
    # TODO: Move this to configuration

    HOST = '127.0.0.1'
    PORT = 5000
    address = (HOST, PORT) 
    server = wemoServer(address, jeedomRequestHandler)
    ip, port = server.server_address  # what port was assigned?
    
    logger.info('Server on %s:%s', ip, port)
    server.serve_forever()
    logger.info('Server ended')
except (KeyboardInterrupt, SystemExit):
    logger.info('Server ended via ctrl+C')
    sys.exit(0)