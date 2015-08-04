<?php
require_once "lib/Dropbox/autoload.php";
use \Dropbox as dbx;
$dbxClient = new dbx\Client(file_get_contents(__DIR__."/tok.txt"), "Ad Hoc CLI");
print_r($dbxClient->getMetadata("/1.txt"));
?>