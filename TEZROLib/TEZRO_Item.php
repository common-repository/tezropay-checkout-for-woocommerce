<?php

class TEZRO_Item { 
  function __construct($config,$item_params) {
      $this->endpoint = $config->TEZRO_getNetwork();
      $this->item_params = $item_params;
      return $this->TEZRO_getItem();
}

function TEZRO_getItem(){
   $this->invoice_endpoint = $this->endpoint.'/orders/init';
   $this->buyer_transaction_endpoint = $this->endpoint.'/transactions';
   return ($this->item_params);
}

}

?>
