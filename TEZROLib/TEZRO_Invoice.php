<?php

class TEZRO_Invoice
{

    public function __construct($item)
    {
        $this->item = $item;

    }

    

    public function TEZRO_checkInvoiceStatus($orderID, $hashKey, $KeyID)
    {
        $post_fields = ($this->item->item_params);
        // $this->endpoint
        $url = 'https://openapi.tezro.com/api/v1/orders/' . $orderID;
        $fields = array(
            'headers' => array(
                'KeyID' => $KeyID,
                'Timestamp' => round(microtime(true) * 1000),
                'Content-Type' => 'application/json',
                'Accept'  => 'application/json',
                'Algorithm' => 'SHA512',
                'X-Tezro-Signature' => $hashKey
            ),
            'method'  => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'sslverify' => false,
            'blocking' => true
        );
        $result = wp_remote_post( $url, $fields);
        if ( is_wp_error($result) ) {
             $error_message = $result->get_error_message();
             return "Something went wrong: " . $error_message;
        } else {
            return wp_remote_retrieve_body($result);
        }
    }

    public function TEZRO_createInvoice($KeyID)
    {
        $post_fields = json_encode($this->item->item_params);

        $pluginInfo = $this->item->item_params->extension_version;
        // $this->endpoint
        $url = 'https://openapi.tezro.com/api/v1/orders/init';
        $fields = array(
            'headers' => array(
                'KeyID' => $KeyID,
                'Timestamp' => round(microtime(true) * 1000),
                'Content-Type' => 'application/json',
                'Accept'  => 'application/json'
            ),
            'method'      => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'sslverify' => false,
            'body' => $post_fields,
            'data_format' => 'body'
        );
        $result = wp_remote_post( $url, $fields);
        if ( is_wp_error($result) ) {
             $error_message = $result->get_error_message();
             $this->invoiceData = "Something went wrong: " . $error_message;
        } else {
             $this->invoiceData = wp_remote_retrieve_body($result);
        }

    }

    public function TEZRO_getInvoiceData()
    {
        return $this->invoiceData;
    }

    public function TEZRO_getInvoiceURL()
    {
        $data = json_decode($this->invoiceData);
        return $data->link;
    }
}
