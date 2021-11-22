<?php

use OFFLINE\Mall\Models\User;
use OFFLINE\Mall\Models\Address;
use OFFLINE\Mall\Models\CustomerGroup;
use OFFLINE\Mall\Models\Subscriber;
use OFFLINE\Mall\Models\Customer;
use RainLab\Location\Models\Country;

class UsersImporter{

	private $filePath;
	private $users;

	function __construct($path){
		$this->filePath = storage_path('app/' . $path);
		$this->users = array();
	}


	private function getUsersFromCSV(){
        echo "Getting products from CSV file...\n";

        $fileStream = fopen($this->filePath, 'r');

        // Dump first row
        fgetcsv($fileStream);

        while(($data = fgetcsv($fileStream)) !== FALSE){
        	$user = array();
        	$user['email'] = $data[0];
        	$user['group'] = $data[1];
        	$user['fname'] = $data[2];
        	$user['lname'] = $data[3];
        	$user['is_active'] = $data[4];
        	$user['newsletter'] = $data[31] ?: 0;
        	
        	$address = array();
        	$billingAddress = array();

        	if($data[5] || $data[6] || $data[7] || $data[8]){
        		$billingAddress['fname'] = isset($data[5]) ? $data[5] : '';
        		$billingAddress['mname'] = isset($data[6]) ? $data[6] : '';
        		$billingAddress['lname'] = isset($data[7]) ? $data[7] : '';
        		$billingAddress['street'] = isset($data[8]) ? $data[8] : '';

        		$billingAddress['city'] = isset($data[11]) ? $data[11] : '';
        		$billingAddress['country_code'] = isset($data[13]) ? $data[13] : '';
        		$billingAddress['zip'] = isset($data[14]) ? $data[14] : '';

        		$billingAddress['telephone'] = isset($data[15]) ? $data[15] : '';
        		$billingAddress['company'] = isset($data[16]) ? $data[16] : '';
        	}

        	$address['billing'] = $billingAddress;

        	$shippingAddress = array();

        	if($data[18] || $data[19] || $data[20] || $data[22]){
        		$shippingAddress['fname'] = isset($data[18]) ? $data[18] : '';
        		$shippingAddress['mname'] = isset($data[19]) ? $data[19] : '';
        		$shippingAddress['lname'] = isset($data[20]) ? $data[20] : '';
        		$shippingAddress['street'] = isset($data[22]) ? $data[22] : '';

        		$shippingAddress['city'] = isset($data[25]) ? $data[25] : '';
        		$shippingAddress['country_code'] = isset($data[27]) ? $data[27] : '';
        		$shippingAddress['zip'] = isset($data[28]) ? $data[28] : '';

        		$shippingAddress['telephone'] = isset($data[29]) ? $data[29] : '';
        		$shippingAddress['company'] = isset($data[30]) ? $data[30] : '';
        	}

        	$address['shipping'] = $shippingAddress;

        	$user['addresses'] = $address;

        	$this->users[] = $user;
        }


        echo "Done...\n";
    }

    private function setAddress($customerModel, $address, $addressType){
    	// Address Type: Billing 1, Shipping 2
        echo "\nSetting Address\n";
    	if(! isset($address) || !($addressType > 0 )) return;

		
        $addressModel = Address::firstOrCreate(
			[ 
				'customer_id' => $customerModel->id,
				'lines' => isset($address['street']) ? $address['street'] : '/',
			],
			[
                'name' => $customerModel->firstname . ' ' . $customerModel->lastname,
				'company' => isset($address['company']) ? $address['company'] : '',
				'zip' => isset($address['zip']) ? $address['zip'] : '/',
				'city' => isset($address['city']) ? $address['city'] : '/',
				'country_code' => 0,
                'phone' => isset($address['telephone']) ? $address['telephone'] : '/',
			],
		);

		if(isset($address['country_code'])){
			$addressModel->country_id = Country::where('code', $address['country_code'])->first()->id;
            $addressModel->save();
        }


		if($addressType == 1)
			$customerModel->default_billing_address_id = $addressModel->id;

		if($addressType == 2)
			$customerModel->default_shipping_address_id = $addressModel->id;

        $customerModel->addresses()->add($addressModel);

		$customerModel->save();
        
        //$addressModel->customer()->add($customerModel);

    }

    private function createUsers(){

        //$i = 0;

    	foreach ($this->users as $key => $user) {
            // $i++;
            // if($i > 2) break;

    		$pass = Hash::make(str_random(12));

            echo "\n" . $user['email'] . "\n";

            try{
        		$model = User::firstOrCreate(
        			['email' => $user['email']],
        			[
        				'name' => $user['fname'],
        				'surname' => $user['lname'],
        				'is_activated' => 0,
        				'password' => $pass,
        				'password_confirmation' => $pass,
        			]
        		);

                if($user['is_active'] === 'Yes'){
                    $model->is_activated = 1;
                    $model->save();
                }


        		$customerModel = Customer::firstOrCreate(
        			['user_id' => $model->id],
        			[
        				'firstname' => $user['fname'],
        				'lastname' => $user['lname'],
        			]
        		);

        		$model->customer()->add($customerModel);

                if(isset($user['newsletter'])){
                    $subscriberModel = Subscriber::firstOrCreate(
                        ['user_id' => $model->id],
                        [
                            'status' => $user['newsletter'],
                        ]
                    );
                }

        		if(CustomerGroup::where('name', $user['group'])->exists()){
        			$group = CustomerGroup::where('name', $user['group'])->first();
        			$model->customer_group()->add($group);
        			$model->save();
        		}

        		if(isset( $user['addresses']['billing'] )){
                    echo "\nBilling\n";
        			$this->setAddress($customerModel, $user['addresses']['billing'], 1);

        		}

        		if(isset( $user['addresses']['shipping'] )){
                    echo "\nBilling\n";
        			$this->setAddress($customerModel, $user['addresses']['shipping'], 2);
        		}
            }catch(Exception $e){
                echo "\n" . 'Error' . "\n";
            }
    	}
    }

	function run(){
		$this->getUsersFromCSV();

		$this->createUsers();
	}

}