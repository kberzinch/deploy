echo "Starting deployment script..."
cd /var/github/$1/
git pull
git archive master | tar -x -C /var/www/$1
echo "Done!"