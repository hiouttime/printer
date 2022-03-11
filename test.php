<?php
include("ipp.class.php");

$printer = new IPPServer;
var_dump($printer->attrs);
$printer->operate();