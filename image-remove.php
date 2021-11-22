<?php


return 0;

require 'load-kernel.php';


$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);


use Offline\Mall\Models\Product;
use Offline\Mall\Models\ImageSet;


function deleteImages($products, $limit){

	//echo "Delete images";

	foreach ($products as $product) {
		echo "\n" . $product->id . "\n";

		if($product->image_sets()->exists()){
			$images = $product->image_sets()->get();

			foreach ($images as $key => $image) {
				$image->delete();
			}

			$product->image_sets()->delete();
		}
		unset($images);
		unset($product);
	}
	unset($products);
	$products = Product::has('image_sets')->with('image_sets')->select('id')->take($limit)->get();

	deleteImages($products, $limit);
	echo "\n";
	echo "\n";
}

function deleteImageSets(){
	$images = ImageSet::get();

	foreach ($images as $key => $image) {
		$image->delete();
	}

}

$limit = 5;
$products = Product::has('image_sets')->with('image_sets')->select('id')->take($limit)->get();
deleteImages($products, $limit);
deleteImageSets();

echo "Images are deleted";
