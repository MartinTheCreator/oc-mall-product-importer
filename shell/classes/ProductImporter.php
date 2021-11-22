<?php

use Offline\Mall\Models\Product;
use Offline\Mall\Models\ProductFile;
use Offline\Mall\Models\ProductFileGrant;
use Offline\Mall\Models\ProductPrice;
use Offline\Mall\Models\Property;
use Offline\Mall\Models\PropertyGroup;
use Offline\Mall\Models\PropertyGroupProperty;
use Offline\Mall\Models\PropertyValue;
use Offline\Mall\Models\Variant;
use Offline\Mall\Models\Category;
use Offline\Mall\Models\CustomFieldOption;
use Offline\Mall\Models\Currency;
use Offline\Mall\Models\Price;
use Offline\Mall\Models\ImageSet;
use Offline\Mall\Models\Brand;

class ProductImporter{
    public $products;
    //public $mainCategory;
    private $file_path;
    private $image_folder;

    private static $MAIN_CATEGORY = 2;

    function __construct($path, $image_folder){
        $this->products = array();
        // Fetch the main category since there are the main category properties
        //$this->mainCategory = Category::select('id', 'slug')->find(ProductImporter::$MAIN_CATEGORY);
        $this->file_path = storage_path('app/' . $path);
        $this->image_folder = $image_folder;

    }
    private function setProductDetails($pModel, $product){
        $pModel->name = $product['name'];
        $pModel->slug = \Str::slug($pModel->name);
        $pModel->published = true;

        $pModel->stock = 1;
        $pModel->allow_out_of_stock_purchases = true;

        $description = "<p>We also supply large quantities and units.<br>Easily fulfill the form for Request Quote and we will contact you.</p>";
        $pModel->description = $description;
        
        /*
        $pModel->description_short = '';
        */

        $pModel->user_defined_id = $product['sku'];

        $pModel->meta_title = $pModel->name;
        $pModel->meta_description = '';
        $pModel->meta_keywords = '';

        $pModel->additional_descriptions = [];
        $pModel->additional_properties = [];

        $pModel->save();
    }
    private function setProductPrice($pid, $price, $sale){
        $currencies = Currency::select('id', 'is_default', 'rate')->get();

        // If product doesn't have a price
        echo "#${pid} | ";

        if(! is_numeric($price)){
            $price = (int)preg_replace("/[^0-9]/", "", $price);
        }
        if(! is_numeric($sale)){
            $sale = (int)preg_replace("/[^0-9]/", "", $sale);
       }

        if(! is_numeric($price) || ! is_numeric($sale)) return;

        echo "price: ${price} | sale: ${sale}";

        if($sale && $sale < $price){
            foreach($currencies as $curr){
                Price::updateOrCreate([
                    'price_category_id' => 1,
                    'priceable_id'      => $pid,
                    'priceable_type'    => 'mall.product',
                    'currency_id'       => $curr->id,
                ], [
                    'price' => $curr->is_default == 1 ? $price : $price * $curr->rate,
                ]);

                ProductPrice::updateOrCreate([
                    'currency_id' => $curr->id,
                    'product_id'  => $pid,
                    'variant_id'  => null,
                ], [
                    'price' => $curr->is_default == 1 ? $sale : $sale * $curr->rate,
                ]);
            }
        }else{
            foreach($currencies as $curr){
                ProductPrice::updateOrCreate([
                    'currency_id' => $curr->id,
                    'product_id'  => $pid,
                    'variant_id'  => null,
                ], [
                    'price' => $curr->is_default == 1 ? $price : $price * $curr->rate,
                ]);
            }
        }
    }
    private function setVariantPrice($pid, $vid, $price, $sale = null){
        $currencies = Currency::select('id', 'is_default', 'rate')->get();

        if(! is_numeric($price)){
            $price = (int)preg_replace("/[^0-9]/", "", $price);
        }

        if($sale){

            if(! is_numeric($sale)){
                $sale = (int)preg_replace("/[^0-9]/", "", $sale);
            }

            if(! is_numeric($price) || ! is_numeric($sale)) return;

            if($sale && $sale < $price){
                foreach($currencies as $curr){
                    Price::updateOrCreate([
                        'price_category_id' => 1,
                        'priceable_id'      => $vid,
                        'priceable_type'    => 'mall.variant',
                        'currency_id'       => $curr->id,
                    ], [
                        'price' => $curr->is_default == 1 ? $price : $price * $curr->rate,
                    ]);

                    ProductPrice::updateOrCreate([
                        'currency_id' => $curr->id,
                        'product_id'  => $pid,
                        'variant_id'  => $vid,
                    ], [
                        'price' => $curr->is_default == 1 ? $sale : $sale * $curr->rate,
                    ]);
                }
            }else{
                foreach($currencies as $curr){
                    ProductPrice::updateOrCreate([
                        'currency_id' => $curr->id,
                        'product_id'  => $pid,
                        'variant_id'  => $vid,
                    ], [
                        'price' => $curr->is_default == 1 ? $price : $price * $curr->rate,
                    ]);
                }
            }
        }else{
            foreach($currencies as $curr){
                ProductPrice::updateOrCreate([
                    'currency_id' => $curr->id,
                    'product_id'  => $pid,
                    'variant_id'  => $vid,
                ], [
                    'price' => $curr->is_default == 1 ? $price : $price * $curr->rate,
                ]);
            }
        }

        


    }
    private function setProductCategoryProperties($pModel, $product){
        echo "\nSetting Product Properties\n";

        $product_id = $pModel->id;

        $catGroupsProps = Category::find(ProductImporter::$MAIN_CATEGORY)->property_groups()->whereHas('properties', function($q){
            $q->where('use_for_variants', 0);
        })->get();

        foreach($catGroupsProps as $key => $catGroupProp){
            $tmpProps = $catGroupProp->properties;

            foreach ($tmpProps as $prop) {
                $key = $prop['slug'];

                if(isset($product[$key])){
                    PropertyValue::updateOrCreate(
                        ['product_id' => $pModel->id, 'variant_id' => NULL, 'property_id' => $prop->id],
                        [ 'value' => $product[$key]]
                    );
                }
            }
        }

    }
    vate function setAdditionalProperties($productModel, $properties){
        if(count($properties) == 0) return;

        $props = array();
        foreach($properties as $key => $property){
            array_push($props, [ 'name' => $key, 'value' => $property]);
        }

        $productModel->additional_properties = $props;
        $productModel->save();
    }
    private function setBrand($pModel, $brand){
        // If Brand name is not set
        if(! isset($brand)) return;

        if(! Brand::where('slug', \Str::slug($brand))->exists()){
            $brand = Brand::create([
                'name' => $brand,
                'slug' => \Str::slug($brand)
            ]);
        }else{
            $brand = Brand::where('slug', \Str::slug($brand))->first();
        }

        $pModel->brand_id = $brand->id;
        $pModel->save();

    }
    private function setImageSets($pModel){
        // Createing Image Set for the product;
        $path = $this->image_folder;
        $setName = 'set-' . $pModel->slug;
        $images = array();

        if($pModel->
            whereHas('image_sets', function($q) use($setName){
                $q->where('name', $setName);
            })->
            exists()
        ){
            $set = ImageSet::where('name', $setName)->first();
        }else{
            $set = new ImageSet;
            $set->name = $setName;
            $set->is_main_set = 1;
            $set->product_id = $pModel->id;
            $set->save();
        }

        // Clear all the previous images from the set
        if($set->images()->exists()){
            $set->images()->delete();
        }

        // Get a list of all images in the image folder for the products
        $files =  \Storage::files($path);

        $sku = $pModel->user_defined_id;

        // Search trough files and set the images to the product that has filename same as sku (user_defined_id)
        if(count($files) > 0){
            foreach ($files as $f) {
                $img = explode('/', $f);
                //$img = str_replace(' ', '', $img[count($img) - 1]);
                $img = $img[count($img) - 1];
                //$result = preg_match('/' . $sku .'((\(|\s|\-)+.*.\)?)?\.(jpg|png|jpeg)/i', $img);
                $result = preg_match('/' . $sku .'\.(jpg|png|jpeg)/i', $img);

                if($result == 1){
                    $completePath = storage_path('app/' . $path . $img);

                    try{

                        $set->images()->create(['data' => $completePath]);
                        $set->save();

                    }catch (Exception $e){
                        echo 'Image exception: ',  $e->getMessage(), "\n";
                    }
                }
            }
            //echo "\nImages are set for the ImageSet for product ${sku}\n";
        }
    }
    private function setVariantDetails($variantModel, $variant, $mainId){
        $variantModel->name = $variant['name'];
        $variantModel->user_defined_id = $variant['sku'];
        $variantModel->product_id = $mainId;


        $variantModel->allow_out_of_stock_purchases = 1;
        $variantModel->stock = 100;

        $variantModel->save();
    }
    private function setVariantProperties($variantModel, $variant){
        echo "\nSetting Variant Properties\n";

        $catGroupsProps = Category::find(ProductImporter::$MAIN_CATEGORY)->property_groups()->whereHas('properties', function($q){
            $q->where('use_for_variants', 1);
        })->get();

        foreach($catGroupsProps as $key => $catGroupProp){
            $tmpProps = $catGroupProp->properties;

            foreach ($tmpProps as $prop) {
                $key = $prop['slug'];

                if(isset($variant[$key])){
                    PropertyValue::updateOrCreate(
                        ['product_id' => $variantModel->product->id, 'variant_id' => $variantModel->id, 'property_id' => $prop->id],
                        [ 'value' => $variant[$key]]
                    );
                }
            }
        }
        
    }

    private function setVariantMainStaticProperties($variantModel, $properties){
        if(count($properties) == 0)
            return;

        foreach ($properties as $prop => $val) {
            $variantModel[$prop] = $val;
        }

        $variantModel->save();

    }
    private function setVariantImageSets($variant){

       $path = $this->image_folder;
        $setName = $variant->name;
        $images = array();

        if(isset($variant->image_set_id)){
            $set = ImageSet::where('id', $variant->image_set_id)->first();
        }else{
            $set = new ImageSet;
            $set->name = $setName;
            $set->is_main_set = 0;
            $set->product_id = $variant->product->id;
            $set->save();
        }
        $variant->image_set_id = $set->id;
        $variant->save();

        // Clear all the previous images from the set
        if($set->images()->exists()){
            $set->images()->delete();
        }

        // Get a list of all images in the image folder for the products
        $files =  \Storage::files($path);
        $sku = $variant->user_defined_id;

        // Search trough files and set the images to the product that has filename same as sku (user_defined_id)
        if(count($files) > 0){
            foreach ($files as $f) {
                $img = explode('/', $f);
                //$img = str_replace(' ', '', $img[count($img) - 1]);
                $img = $img[count($img) - 1];
                $result = preg_match('/' . $sku .'((\(|\s|\-)+.*.\)?)?\.(jpg|png|jpeg)/i', $img);

                if($result == 1){
                    $completePath = storage_path('app/' . $path . $img);

                    try{

                        $set->images()->create(['data' => $completePath]);
                        $set->save();

                    }catch (Exception $e){
                        echo 'Image exception: ',  $e->getMessage(), "\n";
                    }
                }
            }
        }
    }
    private function setCategory($pModel, $categories){
        echo "\nSetting Categories\n";

        if(! isset($categories) || count($categories) == 0) {
            $main_cat = Category::find(ProductImporter::$MAIN_CATEGORY);

            $pModel->categories()->attach($main_cat->id);
            $pModel->save();


            return ;
        }

        foreach ($categories as $category) {
            $cat = Category::firstOrCreate(
                [
                    'slug' => \Str::slug($category)
                ],
                [
                    'name' => ucfirst($category),
                    'inherit_property_groups' => 1,
                    'parent_id' => ProductImporter::$MAIN_CATEGORY
                ]
            );
            if(! $pModel->categories()->where('category_id', $cat->id)->exists()){
                $pModel->categories()->attach($cat->id);
                $pModel->save();
            }
        }
        
        echo "\nDone\n";
    }
    private function getProductsFromCSV(){
        echo "Getting products from CSV file...\n";

        $products = fopen($this->file_path, 'r');

        // Dump first row
        fgetcsv($products);

        $tempProducts = array();
        $i = 0;

        while(($data = fgetcsv($products)) !== FALSE){

            if($i > 22000) break;

            $p = array();
            $sku = $data[1];

            if(Product::where('user_defined_id', $sku)->has('image_sets')->exists()) continue;

            
            $p['sku'] = $sku;
            $p['catalog_code'] = $data[0];
            $p['cas-rn'] = $data[2];
            $p['name'] = $data[3];
            $p['purity'] = $data[4];

            $p['smiles'] = $data[8];
            $p['inchl-code'] = $data[9];
            $p['inchl-key'] = $data[10];


            $p['molecule-quantity'] = $data[5];
            $p['price'] = $data[6];
            $p['currency'] = $data[7];

            $cats = array();
            if($data[11] && strlen($data[11]))
                array_push($cats, $data[11]);

            if($data[12] && strlen($data[12]))
                array_push($cats, $data[12]);

            if($data[13] && strlen($data[13]))
                array_push($cats, $data[13]);

            if($data[14] && strlen($data[14]))
                array_push($cats, $data[14]);

            $p['categories'] = $cats;

            $similar = array();

            if($data[15] && strlen($data[15]))
                array_push($similar, $data[15]);

            if($data[16] && strlen($data[16]))
                array_push($similar, $data[16]);

            if($data[17] && strlen($data[17]))
                array_push($similar, $data[17]);

            if($data[18] && strlen($data[18]))
                array_push($similar, $data[18]);

            if($data[19] && strlen($data[19]))
                array_push($similar, $data[19]);

            $p['similar'] = $similar;

            $p['shipping-period'] = $data[21];

            $p['molecule-weight'] = $data[26];
            $p['formula'] = $data[27];

            $p['variations'] = array();

            array_push($tempProducts, $p);

            $i++;
        }

        // Unsetting variables and closing file streams
        unset($p);
        fclose($products);
        //

        // Remaking the product list using duplicates as variations
        $sku_codes = array_unique(array_column($tempProducts, 'sku'));
        $csvProducts = array();

        foreach ($tempProducts as $index => $row) {
            $sku_codes = array_unique(array_column($csvProducts, 'sku'));

            if(! in_array($row['sku'], $sku_codes)){
                $p['sku'] = $row['sku'];
                $p['cas-rn'] = $row['cas-rn'];
                $p['name'] = $row['name'];
                $p['purity'] = $row['purity'];

                $p['smiles'] = $row['smiles'];
                $p['inchl-code'] = $row['inchl-code'];
                $p['inchl-key'] = $row['inchl-key'];

                $p['similar'] = $row['similar'];
                $p['categories'] = $row['categories'];
                $p['variations'] = array();
                $p['formula'] = $row['formula'];
                $p['molecule-weight'] = $row['molecule-weight'];
                //$p['description'] = $row['description'];

                $v['sku'] = $row['catalog_code'];
                $v['name'] = $row['name'];
                $v['price'] = $row['price'];
                $v['molecule-quantity'] = $row['molecule-quantity'];
                $v['shipping-period'] = $row['shipping-period'];

                $p['variations'][] = $v;

                $csvProducts[] = $p;

            }else{
                $originalIndex = array_search($row['sku'], $sku_codes);
                $original = $csvProducts[$originalIndex];

                $v['sku'] = $row['catalog_code'];
                $v['name'] = $row['name'];
                $v['price'] = $row['price'];
                $v['molecule-quantity'] = $row['molecule-quantity'];
                $v['shipping-period'] = $row['shipping-period'];

                $original['variations'][] = $v;

                $csvProducts[$originalIndex] = $original;
            }

            unset($tempProducts[$index]);
            
        }
        unset($v);
        unset($p);
        unset($original);
        unset($originalIndex);
        unset($tempProducts);

        $this->products = $csvProducts;
        echo "Done...\n";
    }


    public function run(){

        $this->getProductsFromCSV();

        $row = 0;
        // Inserting Main Product Details
        foreach ($this->products as $key => $product){
            echo "Product: " . $product['sku'] . "\n\n";

            if(strlen($product['sku']) > 0 && Product::where('user_defined_id', $product['sku'])->exists()){
                $pModel = Product::where('user_defined_id', $product['sku'])->first();
            }elseif(strlen($product['name']) > 0 && Product::where('name', $product['name'])->exists()){
                $pModel = Product::where('name', $product['name'])->first();
            }else{
                $pModel = new Product;
            }
            
            // Sets product details
            $this->setProductDetails($pModel, $product);

            // Sets product categories
            $this->setCategory($pModel, $product['categories']);
            
            // Set Image sets
            if($pModel->user_defined_id){
                $this->setImageSets($pModel);
            }

            
            // Set Properties for the main group
            $this->setProductCategoryProperties($pModel, $product);

            $this->setAdditionalProperties($pModel, [ 'dimension' => $product['dimension'], 'dimension_2' => $product['dimension_2']]);

            // Setting product price
            if(! count($product['variations']) > 1 ){
                $this->setProductPrice($pModel->id, $product['price'], $product['sale']);
            }

            // If product has variants
            
            if(count($product['variations']) > 0){
                $pModel->inventory_management_method = 'variant';
                $pModel->save();
                
                foreach ($product['variations'] as $variant) {
                    echo "\nVariant: " . $variant['sku'] . "\n\n";

                    if(isset($variant['sku']) && Variant::where('user_defined_id', $variant['sku'])->exists()){

                        $variantModel = Variant::where('user_defined_id', $variant['sku'])->first();
                        echo "Variant: " . $variant['sku'];
                    }else{
                        $variantModel = new Variant;
                    }

                    // Set Variant Details
                    $this->setVariantDetails($variantModel, $variant, $pModel->id);

                    // Set Variant Price
                    $this->setVariantPrice($pModel->id, $variantModel->id, $variant['price']);

                    // Set Variant Properties
                    $this->setVariantProperties($variantModel, $variant);


                    // Set Variant Main Static Properties
                    $properties = ['availability' => $variant['shipping-period']];
                    $this->setVariantMainStaticProperties($variantModel, $properties);

                    // Set Variant Static Properties
                    $properties = ['Purity' => $variant['purity'], 'Availability' => $variant['shipping-period']];
                    $this->setVariantStaticProperties($variantModel, $properties);

                }

            }
            */

            echo "\nContinuing to next Product----\n";
        }        

        echo "\nRun method has ended.\n";

    }
}