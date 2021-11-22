<?php

require 'load-kernel.php';


$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);



$path = 'products-export.csv';
$file_path = storage_path('app/' . $path);

$file = fopen($file_path, "w");

if($f === false)
	die("Error operating file ${file_path}");

