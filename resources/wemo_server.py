#!/usr/bin/env python
# -*- coding: utf-8 -*-
import os
import sys
import logging
import argparse
import subprocess

import pywemo

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

    

    def handle(self):
        self.logger.debug('start handle()')


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