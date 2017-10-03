<?php
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

    Dieses Programm ist Freie Software: Sie können es unter den Bedingungen
    der GNU General Public License, wie von der Free Software Foundation,
    Version 3 der Lizenz oder (nach Ihrer Wahl) jeder neueren
    veröffentlichten Version, weiterverbreiten und/oder modifizieren.

    Dieses Programm wird in der Hoffnung, dass es nützlich sein wird, aber
    OHNE JEDE GEWÄHRLEISTUNG, bereitgestellt; sogar ohne die implizite
    Gewährleistung der MARKTFÄHIGKEIT oder EIGNUNG FÜR EINEN BESTIMMTEN ZWECK.
    Siehe die GNU General Public License für weitere Details.

    Sie sollten eine Kopie der GNU General Public License zusammen mit diesem
    Programm erhalten haben. Wenn nicht, siehe <http://www.gnu.org/licenses/>.
    
    */
/*
 * A simple Memcached tile Proxy.
 * (Inspired by http://wiki.openstreetmap.org/wiki/ProxySimplePHP)
 * 
 * @author Peter Herter, Switzerland
 * 
 * Needs ...
 * - a running memcached Server on Port 11211.
 * - installed memcache PECL extension.
 * 
 * Installation on Ubuntu:
 * - sudo apt-get install php-memcache memcached (php-memcache without ..d)
 * 
 * Needed Parameters:
 * x,y,z
 * Optional Parameter: 
 *  style=test      => Test Tiles
 *  style=light     => Light Tiles
 *  style=wikimedia => Wikimedia Tiles
 * 
 * Possible Optimizations:
 * - Use Unix Domain Sockets instead of TCP
 * - ...
 */

$ttl = 7 * 24 * 3600; //cache timeout in seconds

//Define the possible styles
//Check the Tile usage policy of the corresponding servers
$serverlist = array();
$serverlist['default']['urls'] = array(
    "http://a.tile.openstreetmap.org/{z}/{x}/{y}.png",
    "http://b.tile.openstreetmap.org/{z}/{x}/{y}.png",
    "http://c.tile.openstreetmap.org/{z}/{x}/{y}.png"
);
$serverlist['light']['urls'] = array(
    "http://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png",
);
$serverlist['wikimedia']['urls'] = array(
    "https://maps.wikimedia.org/osm-intl/{z}/{x}/{y}.png",
);
$serverlist['wikimedia']['zlimit'] = 18;


if (!filter_has_var(INPUT_GET, 'x') ||
        !filter_has_var(INPUT_GET, 'y') ||
        !filter_has_var(INPUT_GET, 'z')) {
    $x = $y = $z = 0;
} else {
    $x = intval(filter_input(INPUT_GET, 'x'));
    $y = intval(filter_input(INPUT_GET, 'y'));
    $z = intval(filter_input(INPUT_GET, 'z'));
}



$style = 'default';
if (filter_has_var(INPUT_GET, 'style') && isset($serverlist[filter_input(INPUT_GET, 'style')])) {
    $style = filter_input(INPUT_GET, 'style');
}

//Build URL and id based on style and x,y,z
$baseurl = $serverlist[$style]['urls'][rand(0, count($serverlist[$style]['urls']) - 1)];
$baseid = "$style/{z}/{x}/{y}";

$url = str_replace('{x}', $x, str_replace('{y}', $y, str_replace('{z}', $z, $baseurl)));
$id = str_replace('{x}', $x, str_replace('{y}', $y, str_replace('{z}', $z, $baseid)));


//Check if we output dummy tiles
if (filter_input(INPUT_GET, 'style') === 'test' || (isset($serverlist[$style]['zlimit']) && $z > $serverlist[$style]['zlimit'])) {
    $text = $id;
    $im = @imagecreatetruecolor(256, 256)
            or die('Cannot Initialize new GD image stream');

    //Background
    imagefilledrectangle($im, 0, 0, 256, 256, imagecolorallocate($im, 240, 240, 240));

    //Border
    imagerectangle($im, 1, 1, 256 - 3, 256 - 3, imagecolorallocate($im, 255, 0, 0));

    //Text
    imagestring($im, 5, 5, 5, $text, imagecolorallocate($im, 0, 0, 0));

    //Output Image
    header('Content-Type: image/png');
    imagepng($im);
    imagedestroy($im);
    exit;
}


//Redirect in case of missing Memcache installation.
if (!class_exists('Memcache')) {
    header('location: ' . $url);
    exit;
}


$memcache = new Memcache();
if (!@$memcache->connect('127.0.0.1', 11211)) {
    //If memcache Server is not available, we redirect to the original URL
    header('location: ' . $url);
    exit;
}

if (filter_has_var(INPUT_GET, 'status')) {
    //On Status Request, we output the Memcache Status
    // (memcache Status is open for everyone. Is this a problem?)
    header('content-type: text/plain');
    print_r($memcache->getextendedstats());
    exit;
}

$img = $memcache->get($id);
if (!$img) {
    //if image not in cache we get one from orignal server
    $img = file_get_contents($url);
    if ($img) {
        $memcache->set($id, $img, 0, $ttl);
    } else {
        //header('location: ' . $url);
        http_response_code(500);
        exit();
    }
}

//Set some Headers
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $ttl) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
header("Cache-Control: public, max-age=" . $ttl);
// for MSIE 5
header("Cache-Control: pre-check=" . $ttl, false);
if(strpos('.jpeg',$url) !== false) {
    header('Content-Type: image/jpg');
} else {
    header('Content-Type: image/png');
}
echo $img;
