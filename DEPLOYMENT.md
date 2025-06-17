# Deployment Guide for OVH

## 1. Prepare Your Application

### Update Environment Variables
Create a `.env.local` file with production settings:
```env
APP_ENV=prod
APP_SECRET=your_app_secret_here
DATABASE_URL="mysql://your_db_user:your_db_password@127.0.0.1:3306/your_db_name?serverVersion=8.0.32&charset=utf8mb4"
```

### Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### Clear Cache
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### Build Assets
```bash
npm run build
```

## 2. Server Setup

### Connect to Your OVH Server
```bash
ssh your_username@your_server_ip
```

### Install Required Software
```bash
# Update system
sudo apt update
sudo apt upgrade

# Install PHP and extensions
sudo apt install php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-intl php8.2-gd php8.2-mbstring php8.2-zip

# Install MySQL
sudo apt install mysql-server

# Install Nginx
sudo apt install nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Configure MySQL
```bash
sudo mysql_secure_installation
mysql -u root -p
CREATE DATABASE your_db_name;
CREATE USER 'your_db_user'@'localhost' IDENTIFIED BY 'your_db_password';
GRANT ALL PRIVILEGES ON your_db_name.* TO 'your_db_user'@'localhost';
FLUSH PRIVILEGES;
```

### Configure Nginx
Create a new Nginx configuration file:
```bash
sudo nano /etc/nginx/sites-available/invoice-reader
```

Add the following configuration:
```nginx
server {
    server_name your_domain.com;
    root /var/www/invoice-reader/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/invoice-reader_error.log;
    access_log /var/log/nginx/invoice-reader_access.log;
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/invoice-reader /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 3. Deploy Your Application

### Create Application Directory
```bash
sudo mkdir -p /var/www/invoice-reader
sudo chown -R your_username:www-data /var/www/invoice-reader
```

### Deploy Your Code
From your local machine:
```bash
# Create a deployment script
cat > deploy.sh << 'EOF'
#!/bin/bash
rsync -avz --exclude='.git' --exclude='node_modules' --exclude='var/cache' --exclude='var/log' ./ your_username@your_server_ip:/var/www/invoice-reader/
EOF
chmod +x deploy.sh

# Run the deployment
./deploy.sh
```

### On the Server
```bash
cd /var/www/invoice-reader

# Set proper permissions
sudo chown -R www-data:www-data var/cache var/log
sudo chmod -R 775 var/cache var/log

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear and warm up cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

## 4. SSL Configuration

### Install Certbot
```bash
sudo apt install certbot python3-certbot-nginx
```

### Get SSL Certificate
```bash
sudo certbot --nginx -d your_domain.com
```

## 5. Final Steps

### Set Up Supervisor (for background jobs)
```bash
sudo apt install supervisor
sudo nano /etc/supervisor/conf.d/invoice-reader.conf
```

Add the following configuration:
```ini
[program:invoice-reader-messenger]
command=php /var/www/invoice-reader/bin/console messenger:consume async --time-limit=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
process_name=%(program_name)s_%(process_num)02d
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invoice-reader-messenger:*
```

### Set Up Cron Jobs
```bash
sudo crontab -e
```

Add the following line:
```
* * * * * cd /var/www/invoice-reader && php bin/console messenger:consume async --time-limit=3600
```

## 6. Monitoring and Maintenance

### Check Logs
```bash
tail -f /var/log/nginx/invoice-reader_error.log
tail -f /var/www/invoice-reader/var/log/prod.log
```

### Backup Database
```bash
# Create a backup script
cat > backup.sh << 'EOF'
#!/bin/bash
mysqldump -u your_db_user -p your_db_name > /var/backups/invoice-reader-$(date +%Y%m%d).sql
EOF
chmod +x backup.sh

# Add to crontab
sudo crontab -e
# Add: 0 0 * * * /var/www/invoice-reader/backup.sh
```

## Troubleshooting

1. If you see a 502 Bad Gateway error:
   - Check PHP-FPM status: `sudo systemctl status php8.2-fpm`
   - Check Nginx error logs: `sudo tail -f /var/log/nginx/error.log`

2. If you have permission issues:
   - Check directory permissions: `ls -la /var/www/invoice-reader`
   - Ensure www-data has proper access: `sudo chown -R www-data:www-data /var/www/invoice-reader`

3. If the application is slow:
   - Check PHP-FPM configuration: `sudo nano /etc/php/8.2/fpm/pool.d/www.conf`
   - Adjust memory limits and process manager settings

4. If you can't connect to the database:
   - Check MySQL status: `sudo systemctl status mysql`
   - Verify database credentials in `.env.local`
   - Check MySQL error logs: `sudo tail -f /var/log/mysql/error.log` 