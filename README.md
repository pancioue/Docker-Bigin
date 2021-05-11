Container Volume
============
用來將資料綁到本地端

named volume(指定volume)
------------
指定一個Volume給container使用

```
docker volume create todo-db
```
可以下`docker volume ls`查看

用法:
```
docker run -dp 3000:3000 -v todo-db:/etc/todos getting-started
```
查看volume儲存路徑
```
docker volume inspect todo-db
```
* 實際在wsl2底下的路徑`\\wsl$\docker-desktop-data\version-pack-data\community\docker\volumes`


Using Bind Mounts
-------------
```
docker run -dp 3000:3000 \
    -w /app -v "$(pwd):/app" \
    node:12-alpine \
    sh -c "yarn install && yarn run dev"
```

* `-w /app` - sets the "working directory" or the current directory that the command will run from  
  指的是container裡的working path，可以打開container CLI查看，會發現打開就是/app路徑

* `-v "$(pwd):/app"` - bind mount the current directory from the host in the container into the /app directory  

當使用bind mounts時修改檔案
主作業系統、VM(docker host)、container都會同時修改
可以輸入`wsl -d docker-desktop`進入docker host



php + mysql
================
__Dockerfile__
```
FROM php:7.2-apache
RUN docker-php-ext-install pdo_mysql
COPY ./ /var/www/html/
```
Supported extensions: https://github.com/mlocati/docker-php-extension-installer

__docker-compose.yml__
```
version: '3.3'

services:
    http:
        build: ./ # '.'和'./'都可以
        ports:
            - "8081:80"
        volumes:
            - ./:/var/www/html # '.'和'./'都可以

    mysql:
        image: mysql:5.7
        volumes:
            - php-mysql-data:/var/lib/mysql
        environment:
            MYSQL_ROOT_PASSWORD: secret
            MYSQL_DATABASE: app
    
    phpmyadmin:
        image: phpmyadmin/phpmyadmin
        ports:
            - "8082:80"
        environment:
            PMA_HOST: mysql
            PMA_PORT: 3306

volumes:
    php-mysql-data:
```

之後可以下
```
docker-compose -p [project-name] up -d
```
* docker-compose 組成multi-container applications時，會自動產生network，`docker network ls`可以查看，
* 不同container之間是透過network溝通的
* 使用`docker volume ls`查看會發現`php-mysql-data`被自動加上network前綴
* `version`: Compose API Version，V2與V3語法略有不同，不加視同V1，V1基本已棄用，建議使用最新版即可

*test db connect:*
```
$servername = "mysql"; # 注意要填入上面的mysq service name
$username = "root";
$password = "secret";
$dbname = "app";

try {
  $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
  // set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  echo "Connected successfully";
} catch(PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
```


Laravel
================
Manul(當成一般VM安裝)
----------
```
docker pull php:7.2-apache
```
啟動container
```
docker run -d --name my-web php
```
進入container
```
docker exec -it [container id] bash
```
當成一般VM安裝
```
apt-get update

apt-get install -y git nano
```

install composer:
```
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
mv composer.phar /usr/local/bin/composer
```
install laravel:
```
composer create-project laravel/laravel my-app
```
修改權限:
```
cd my-app
chmod -R 777 storage
```
修改 apache2:
```
nano /etc/apache2/sites-enabled/000-default.conf
```
000-default.conf
```
DocumentRoot /var/www/html/my-app
```

打包成image
```
docker commit [container ID] [image name]
```
* docker commit不會打包volumes


docker-compose
----------------
在本機host先把laravel裝好
```
composer create-project --prefer-dist laravel/laravel my-lara
```
__docker-compose.yml__
```
version: '3.8'

services:
    laravel:
        image: php:7.2-apache
        volumes:
            - ./my-lara:/var/www/html
        ports:
            - "8001:80"
```

nginx + php
=============
__docker-compose.yml__
```
version: "3.8"

services:
    nginx:
        image: nginx:latest
        ports:
            - 8080:80
        volumes:
            - ./conf.d:/etc/nginx/conf.d
        networks:
            - net-ng
    
    php:
        image: php:7.4.3-fpm
        volumes:
            - ./np:/www
        networks:
            - net-ng

networks: 
    net-ng:
        external: true # 用已存在的network，不填會自動產生
```

__conf.d/nginx.conf__
```
server {
    listen       80;
    server_name  docker-np.local;

    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;

    location / {
        root /usr/share/nginx/html;
        index index.php;
    }

    error_page 500 502 503 504  /50x.html;
    location = /50x.html {
        root   /usr/share/nginx/html;
    }

    location ~ \.php$ {
        fastcgi_pass   php:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  /www/$fastcgi_script_name;
        include        fastcgi_params;
    }
}
```

__np/index.php__
```
<?php
echo phpinfo();
?>
```

因為有下`external: true`，手動產生network，不然會報錯。若沒加系統會自動生成
```
docker network create net-ng
```

啟動 container
```
docker-compose -p nginx-php up -d
```

新增host網域
```
127.0.0.1       docker-np.local
```

test:  
http://docker-lnmp.local:8080/index.php

LNMP + Laravel
=================
建立Laravel專案
```
composer create-project --prefer-dist laravel/laravel src
```

目錄結構:
```
lnmp-laravel
|___ src
|    |   app/
|    |   public/
|    |   .env
|    |   ...
|
|___ conf.d
|    |   nginx.conf
|
|___ mariadb
|
|___ php
     |   Dockerfile
```

__docker-compose.yml__
```
version: "3.8"

services:
    nginx:
        image: nginx:1.20.0
        ports:
            - 80:80
        volumes:
            - ./conf.d:/etc/nginx/conf.d
        networks:
            - net-lnmp
    
    php:
        build:
            context: ./php
        volumes:
            - ./src:/www
        networks:
            - net-lnmp
  
    db:
        image: mariadb:10.6.0
        environment:
            MYSQL_ROOT_PASSWORD: password
            MYSQL_DATABASE: laravel
        volumes:
            - ./mariadb:/var/lib/mysql 
        networks:
            - net-lnmp

    phpmyadmin:
        image: phpmyadmin:5.1.0
        ports:
            - 8080:80
        environment:
            PMA_HOST: db
            PMA_PORT: 3306
        networks:
            - net-lnmp

networks: 
    net-lnmp:
        external: true
```

__php/Dockerfile__
```
FROM php:7.4.3-fpm

RUN docker-php-ext-install pdo_mysql
```

__conf.d/nginx.conf__
```
server {
    listen       80;
    server_name  docker-lnmp.local;

    root    /www/public/; # 此為php container的路徑，並非nginx的
    index   index.php index.html

    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   php:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }
}
```
* `try_files`: 依順序找尋檔案，並實現了url rewrite

__code/.env__
```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=password
```
* `DB_HOST`可換成IP
* `docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' [container ID]` 可查詢ip

啟動docker-compose
```
docker network create net-lnmp
docker-compose up -d
```