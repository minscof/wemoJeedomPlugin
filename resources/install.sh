touch /tmp/dependancy_wemo_in_progress
echo 0 > /tmp/dependancy_wemo_in_progress
echo "Launch install of wemo dependancy"
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"
sudo apt-get update  -y -q
echo 30 > /tmp/dependancy_wemo_in_progress
echo 50 > /tmp/dependancy_wemo_in_progress
sudo pip3 install pywemo
echo 70 > /tmp/dependancy_wemo_in_progress
sudo chown -R www-data:www-data /var/www/html/plugins/wemo
echo 100 > /tmp/dependancy_wemo_in_progress
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
echo "Everything is successfully installed!"
rm /tmp/dependancy_wemo_in_progress