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
            'factura'  => array(
                'articulos'         => Cfdi::setItems($publicId),
                'receptor'      => Cfdi::setReceptor($invoice),
                'opciones'       => Cfdi::setOptions(),
                'totales'        => Cfdi::setTotals($invoice)
        ),
            'parametros'=> Cfdi::setParametros());
	
        return $json;
    }

    public static function setParametros()
    {
        $parametros= array('archivos' => Cfdi::setArchivos());
        return $parametros;
    }

    public static function setArchivos()
    {
        $archivos=array('pdf'=>true);
        return $archivos;
    }

    public static function setItems($publicId)
    {
        $items = array();
	$invoice=Invoice::where('public_id','=',$publicId)->where('account_id','=',Auth::user()->account_id)->first();
	
        $invoice_items = InvoiceItem::where('invoice_id','=',$invoice->id)->get();        

        foreach ($invoice_items as $key => $item) {
            $item->total = $item->cost * $item->qty;
            $single = array(
                'id'            => $item->product_key,
                'descripcion'   => $item->notes,
                'precio'            => number_format($item->cost, 2, '.', ''),
                'cantidad'           => number_format($item->qty, 0, '.', ''),
                'total'         => number_format($item->total, 2, '.', '')
            );
            array_push($items, $single);               
        }

        return $items;            
    }



    public static  function setOptions(){
        $options = array(
            'tipo_factura'   => 'Egreso',
            'moneda'     => 'MXN',
            'pago'    => 'Transferencia',
            'forma_pago'      => 'Pago en una sola exhibicion'
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
            'monto'    => number_format($amount, 2, '.', ''),
            'agregado' => number_format($sale->tax_rate, 2, '.', ''),
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
            'nombre'              => $contact[0]->first_name . ' '. $contact[0]->last_name,
            'rfc'               => $address->client->rfc,
            'telefono'             => $contact[0]->phone,
            'direccion'           => array(
                'calle'        =>  $address->client->address1,
                'exterior'      =>  $address->client->address2,
                'interior'      =>  0,
                'estado'      =>  $address->client->state,
                'ciudad'       =>  $address->client->city,
                'codigo_postal'    =>  $address->client->postal_code,
                'correo'         =>  $contact[0]->email,
                'nombre_contacto'  =>  $contact[0]->first_name . ' '. $contact[0]->last_name,
                'colonia'        =>  $address->client->suburb
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
                $link = "<a href='/clients/{$id}/pdf' target='_blank'>PDF </a> | <a href='/clients/{$id}/xml' target='_blank'>XML </a> | <a href='#' onclick='cancelCfdi($id)'>Cancelar </a>";
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
        $file = file_get_contents($files->pdf);
        file_put_contents(public_path().'/cfdi.pdf', $file);
        $file = file_get_contents($files->xml);
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

        try {
            $json = json_encode(Cfdi::setJson($publicId, $invoice));

            $url = INVOICE_API_TIMBRAR;
            $data = array('datos' => $json);
            $time = date('c');
            $llave_privada = Invoice::transformarLlave(INVOICE_API_TIMBRAR, 'post', $time);
            $options = array(
                'http' => array(
                    'ignore_errors' => true,
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                        "llave_privada: " . $llave_privada . "\r\n" .
                        "llave_publica: " . INVOICE_API_APIPUBLIC . "\r\n" .
                        "timestamp: " . $time . "\r\n",

                    'method' => 'POST',
                    'content' => http_build_query($data),
                ),
            );

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $response = json_decode($result);

        } catch (Exception $exc) {
            $response = $exc->getTraceAsString();
        }


        return $response;

    }


    /**
     * @param $uuid -- folio fiscal
     * @param $tipo -- Si es XML o PDF
     * @param $account -- Para obtener en el futuro las llaves
     * @return string
     */
    public static function getFile($uuid, $tipo,$account)
    {
        //Fecha en formato ISO 8601
        $time=date('c');

        //Url para obtener el XML/PDF
        $url=INVOICE_API_TIMBRAR.'/'.$uuid.'/'.$tipo;

        //Ruta raw para el concatenado en el HMAC
        if($tipo=='xml')
            $raw_url=INVOICE_API_XML;
        else
            $raw_url=INVOICE_API_PDF;

        //transformacion de la llave privada
        $llave_privada=Invoice::transformarLlave($raw_url,'get',$time);

        //Parametros del request
        $parametros = array(
            'http' => array(
                'ignore_errors' => true,
                'header' => "llave_publica: " . INVOICE_API_APIPUBLIC . " \r\n" .
                    "llave_privada: " . $llave_privada . " \r\n" .
                    "timestamp: " . $time . " \r\n",
                'method' => 'GET',
            ),
        );

        $context = stream_context_create($parametros);


        $result = file_get_contents($url, false, $context);
        return $result;
    }
    

}
