mysql -u root -p123 -e "DROP DATABASE IF EXISTS ferryman_test"
mysql -u root -p123 -e "CREATE DATABASE ferryman_test"
mysql -u root -p123 ferryman_test < tests/Data/structure.sql

php vendor/bin/phpunit