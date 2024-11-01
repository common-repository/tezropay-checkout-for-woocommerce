<?php

class TEZRO_Configuration { 
   private $network;

   function __construct($network = null) {
    if($network == 'test' || $network == null):
        $this->network = $this->TEZRO_getApiHostDev();
    else:
        $this->network = $this->TEZRO_getApiHostProd();
    endif;
}

function TEZRO_generateHash($data) {
    return base64_encode(hash_hmac('sha512', $data, ""));
}

function TEZRO_checkHash($data,$hash_key) {
    if(hash_equals($hash_key,hash_hmac('sha512', $data, ""))){
        return true;
    };
    return false;
}

function TEZRO_getNetwork() {
    return $this->network;
}

public function TEZRO_getApiHostDev()
{
    return 'test.openapi.tezro.com/api/v1';
}

public function TEZRO_getApiHostProd()
{
    return 'openapi.tezro.com/api/v1';
}

public function TEZRO_getApiPort()
{
    return 443;
}

public function TEZRO_getInvoiceURL(){
    return $this->network.'/orders';
}


} 
?>
