<?php


require 'load-kernel.php';


$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);



// Include Product Importer Class
require_once('./shell/classes/ProductImporter.php');

$arg = getopt("f:i:");


$filename = isset($arg['f']) ? $arg['f'] : 'products-import/products.csv';
$image_path = isset($arg['i']) ? $arg['i'] : 'products-import/products/';

$importer = new ProductImporter($filename, $image_path);
$importer->run();


