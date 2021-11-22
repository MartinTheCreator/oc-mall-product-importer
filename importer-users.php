<?php

return 0;

require 'load-kernel.php';


$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
// Include Product Importer Class
require_once('./shell/classes/UsersImporter.php');

$arg = getopt("f");


$filename = isset($arg['f']) ? $arg['f'] : 'users-import/customers.csv';

$importer = new UsersImporter($filename);

$importer->run();


