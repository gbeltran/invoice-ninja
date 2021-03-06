<?php

class Cfdi extends Eloquent
{
    protected $connection ="mysql";
    protected $table = 'cfdi';
    
    public static function getLink($id){
        $url = route('cfdi', array($id));
        return "<a href='{$url}'>Get</a>";
    }
    
    public static function setJson($publicId, $invoice, $api){
        $json = array(
            'bill'  => array(
                'items'         => Cfdi::setItems($publicId),
                'receptor'      => Cfdi::setReceptor($invoice),
                'transmitter'   => Cfdi::setTransmitter($api),
                'options'       => Cfdi::setOptions(),
                'totals'        => Cfdi::setTotals($invoice)
        ));

        return $json;
    }

    public static function setItems($publicId)
    {
        $items = array();
        $invoice_items = InvoiceItem::where('invoice_id','=',$publicId)->get();        

        foreach ($invoice_items as $key => $item) {
            $item->total = $item->cost * $item->qty;
            $single = array(
                'id'            => $item->product_key,
                'description'   => $item->notes,
                'uc'            => number_format($item->cost, 2, '.', ''),
                'qty'           => number_format($item->qty, 0, '.', ''),
                'total'         => number_format($item->total, 2, '.', '')
            );
            array_push($items, $single);               
        }

        return $items;            
    }

    public static  function setTransmitter($api){        
        $transmitter = array(
            'private_key'   => $api->apisecret,
            'public_key'   => $api->apipublic
        );

        return $transmitter;
    }

    public static  function setOptions(){
        $options = array(
            'voucher'   => 'Egreso',
            'money'     => 'MXN',
            'method'    => 'Transferencia Bancaria',
            'kind'      => 'Pago en una sola exhibicion'
        );

        return $options;
    }

    public static  function setTotals($sale){
        $items = $sale->invoice_items;
        $amount = 0;
        foreach ($items as $key => $value) {
                   $amount += $value->qty *  $value->cost; 
        }
        $iva = ($sale->tax_rate * $amount) / 100;
        
        $totals = array(
            'amount'    => number_format($amount, 2, '.', ''),
            'aggregate' => number_format($sale->tax_rate, 2, '.', ''),
            'iva'       => number_format($iva, 2, '.', ''),
            'isr'       => number_format(0, 2, '.', ''),
            'riva'      => number_format(0, 2, '.', ''),
            'total'     => number_format($sale->amount, 2, '.', '')
        );
        
        return $totals;
    }

    

    public static function setReceptor($address)
    {
        $contact = $address->client->contacts;
        $receptor = array(
            'name'              => $contact[0]->first_name . ' '. $contact[0]->last_name,
            'alias'             => $contact[0]->first_name . ' '. $contact[0]->last_name,
            'rfc'               => $address->client->rfc,
            'logo'              => '',
            'phone'             => $contact[0]->phone,
            'address'           => array(
                'id'            =>  $address->client->id,
                'street'        =>  $address->client->address1,
                'exterior'      =>  $address->client->address2,
                'interior'      =>  0,
                'state_id'      =>  $address->client->state,
                'city_id'       =>  $address->client->city,
                'zipcode_id'    =>  $address->client->postal_code,
                'email'         =>  $contact[0]->email,
                'contact_name'  =>  $contact[0]->first_name . ' '. $contact[0]->last_name,
                'colony'        =>  $address->client->suburb
            )
        );

        return $receptor;
    }

    public static function saveCfdi($cfdi)
    {
        $data = Cfdi::where('invoice_id','=', $cfdi->sale_id)->first();
        if(sizeof($data)>0){
            $data->pdf = $cfdi->pdf;
            $data->xml = $cfdi->xml;
            $data->cancel = $cfdi->cancel_id;
            $data->invoice_id = $cfdi->sale_id;
            $data->save();
            return $data;
        }else{			
            $new = new Cfdi;
            $new->pdf = $cfdi->pdf;
            $new->xml = $cfdi->xml;
            $new->cancel = $cfdi->cancel_id;
            $new->invoice_id = $cfdi->sale_id;
            $new->flag = 0;
            $new->save();
            return $new;
        }
    }
    
    public static function cfdiTable($id){
        $data = Cfdi::where('invoice_id','=', $id)->first();
        if(sizeof($data)>0){
            if($data->flag == 1){
                return 'Cancelada';
                
            }else{
                $link = "<a href='{$data->pdf}' target='_blank'>PDF </a> | <a href='{$data->xml}' target='_blank'>XML </a> | <a href='#' onclick='cancelCfdi($id)'>Cancelar </a>";
            }
            return $link;
        }
        return '-';
    }
    
    public static function cancelCfdi($publicId, $api)
    {
        $cfdi = Cfdi::where('invoice_id','=', $publicId)->first();
        
        $url = $api->cancelurl;
        $json =  array(
            'public_key'    =>  $api->apipublic, 
            'private_key'   =>  $api->apisecret,
            'uid'           =>  $cfdi->cancel,
        );   
        
        $data = array('json' => json_encode($json));
            
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ),
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);  
        $response = json_decode($result);

        if ($response->code == 0){
            $cfdi->flag = 1;
             $cfdi->save();             
        }   
         
        return $response;
     }
    
    public static function emailCfdi($files, $invoice)
    {
        $invoice->invoice_status_id = INVOICE_STATUS_SENT;
        $invoice->save();
        $file = file_get_contents("http://".substr($files->pdf,2));
        file_put_contents(public_path().'/cfdi.pdf', $file);
        $file = file_get_contents("http://".substr($files->xml,2));
        file_put_contents(public_path().'/cfdi.xml', $file);

        $contact = $invoice->client->contacts;
        $array = array(
            'email' => $contact[0]->email,
            'clientName' => $contact[0]->first_name . ' '. $contact[0]->last_name,
            'invoiceAmount' => $invoice->amount,
            'entityType' => 'invoice',
            'pdf' => $files->pdf,
            'xml' => $files->xml,
            'p_pfd' => public_path().'/cfdi.pdf',
            'p_xml' => public_path().'/cfdi.xml',
        );

        Mail::send('emails.cfdi_html', $array, function($message) use ($array)
        {
            $message->to($array['email'])->subject('Archivos CFDI');
            $message->attach($array['p_pfd']);
            $message->attach($array['p_xml']);
        });

        unlink(public_path().'/cfdi.pdf');
        unlink(public_path().'/cfdi.xml');
    }
    public static function sendCfdi($publicId, $invoice)
    {            
        $api = CfdiSettings::first();
        if (sizeof($api)>0){            
            try {
                $json =  json_encode(Cfdi::setJson($publicId, $invoice, $api));

                $url = $api->posturl;
                $data = array('post-json' => $json);

                $options = array(
                    'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'content' => http_build_query($data),
                    ),
                );

                $context  = stream_context_create($options);
                $result = file_get_contents($url, false, $context);            
                $response = json_decode($result);

            } catch (Exception $exc) {
                $response = $exc->getTraceAsString();
            }
        }
        
        return $response;                
        
    }
    

}
