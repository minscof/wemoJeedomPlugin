touch /tmp/dependancy_wemo_in_progress
echo 0 > /tmp/dependancy_wemo_in_progress
echo "Launch install of wemo dependancy"
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"
sudo apt-get update  -y -q
echo 30 > /tmp/dependancy_wemo_in_progress
sudo apt-get install -y python-pip python-dev libffi-dev
echo 50 > /tmp/dependancy_wemo_in_progress
#sudo pip install git+https://github.com/syphoxy/ouimeaux.git
sudo pip install git+https://github.com/iancmcc/ouimeaux.git
echo 70 > /tmp/dependancy_wemo_in_progress
sudo pip install flask==0.8 flask-basicauth flask-restful flask_cors==1.1
sudo pip install Werkzeug==0.16
sudo chown -R www-data:www-data /var/www/html/plugins/wemo
echo 100 > /tmp/dependancy_wemo_in_progress
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
echo "Everything is successfully installed!"
rm /tmp/dependancy_wemo_in_progress