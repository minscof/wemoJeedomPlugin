touch /tmp/dependancy_wemo_in_progress
echo 0 > /tmp/dependancy_wemo_in_progress
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"
sudo apt-get update  -y -q
echo 30 > /tmp/dependancy_wemo_in_progress
sudo apt-get install -y python-pip python-dev
echo 50 > /tmp/dependancy_wemo_in_progress
sudo pip install ouimeaux==0.7.9
echo 70 > /tmp/dependancy_wemo_in_progress
sudo mkdir /var/www
sudo chmod -R 755 /var/www
sudo chown -R www-data:www-data /var/www
echo 100 > /tmp/dependancy_wemo_in_progress
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
rm /tmp/dependancy_wemo_in_progress