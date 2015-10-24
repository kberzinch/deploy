echo "Starting deployment script..."
cd /var/github/$1/
git pull
git archive master | tar -x -C /var/www/a$1
echo "Done!"