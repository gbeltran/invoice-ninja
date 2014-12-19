<?php

class Cfdi extends Eloquent
{
    protected $connection ="mysql";
    protected $table = 'cfdi';
    
    public static function getLink($id){
        $url = route('cfdi', array($id));
        return "<a href='{$url}'>Get</a>";
    }
    
    public static function setJson($publicId, $invoice){
        $json = array(
            'bill'  => array(
                'items'         => Cfdi::setItems($publicId),
                'receptor'      => Cfdi::setReceptor($invoice),
                'transmitter'   => Cfdi::setTransmitter(),
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

    public static  function setTransmitter(){
        $transmitter = array(
            'private_key'   => INVOICE_API_APISECRET,
            'public_key'   => INVOICE_API_APIPUBLIC
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
        $account = DB::table('contacts')->where('user_id', '=', $address->client->id)->first();
        $email = $account->email;
        $phone = $account->phone;
        $receptor = array(
            'name'              => $address->client->name,
            'alias'             => $address->client->name,
            'rfc'               => $address->client->rfc,
            'logo'              => '',
            'phone'             => $phone,
            'address'           => array(
                'id'            =>  $address->client->id,
                'street'        =>  $address->client->address1,
                'exterior'      =>  $address->client->address2,
                'interior'      =>  0,
                'state_id'      =>  $address->client->state,
                'city_id'       =>  $address->client->city,
                'zipcode_id'    =>  $address->client->postal_code,
                'email'         =>  $email,
                'contact_name'  =>  $address->client->name,
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
                $new->save();
                return $new;
            }
    }
    

}
