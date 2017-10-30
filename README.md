# tileproxy
Simple PHP/memcache Tile Proxy

In our case, we have different users showing most of the time the same map rectangle. This simple tileproxy acts as a proxy between the user and the original tileserver an improves the loading speed for the first time the user visit the page.

## Package Requirements
* a running memcached Server on Port 11211.
* installed memcache PECL extension.

## Package Installation on Ubuntu
`sudo apt-get install php-memcache memcached (php-memcache without ..d)`

## Installation
* Install the needed packages.
* Start the memcached server (if not already done)
* Copy the tileproxy.php where you want to have it. 
