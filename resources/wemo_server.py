#!/usr/bin/env python
# -*- coding: utf-8 -*-
import os
import sys
import logging
import argparse
import subprocess

from ouimeaux.discovery import UPnPLoopbackException
from ouimeaux.environment import Environment
from ouimeaux.config import in_home, WemoConfiguration
from ouimeaux.utils import matcher

__version__='0.9'

reqlog = logging.getLogger("requests.packages.urllib3.connectionpool")
reqlog.disabled = True

NOOP = lambda *x: None
from socketio.server import SocketIOServer
from ouimeaux.server import app, initialize
from ouimeaux.utils import matcher
from ouimeaux.signals import receiver, statechange, devicefound

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


#while 1:
@receiver(statechange)
def handler(sender, **kwargs):
        if kwargs.get('state') :
            value=1
        else :
            value=0
        #print jeeWemo
        subprocess.Popen(['/usr/bin/php',jeeWemo,'serialnumber='+str(sender.serialnumber),'state='+str(value)])
        print "{} state is {state}".format(sender.serialnumber, state="on" if kwargs.get('state') else "off")
        
       
initialize()

try:
    # TODO: Move this to configuration
    listen = '127.0.0.1:5000'
    try:
        host, port = listen.split(':')
    except Exception:
        print "Invalid bind address configuration:", listen
        sys.exit(1)
    SocketIOServer((host, int(port)), app,
                   policy_server=False,
                   namespace="socket.io").serve_forever()
except (KeyboardInterrupt, SystemExit):
    sys.exit(0)