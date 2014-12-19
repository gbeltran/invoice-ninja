<?php

class CfdiController extends Controller {

    public function getCfdi($publicId)
    {
        $invoice = Invoice::scope($publicId)
                ->withTrashed()
                ->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')
                ->firstOrFail();
        
        $cfdi = DB::table('cfdi')->where('invoice_id', '=', $publicId)->first();
        $data = array(
          'invoice'    => $cfdi,
           'public'     => $publicId,
            'address'   => array($invoice->client->user_id => 
                "{$invoice->client->name} | {$invoice->client->address1} {$invoice->client->address2} {$invoice->client->city} {$invoice->client->state} {$invoice->client->country->name} | RFC: {$invoice->client->rfc}")
           
        );
		
//        echo '<pre>';print_r($invoice->client);echo '</pre>';
        return View::make('cfdi.view', $data);         
    }
    
    public function postCfdi($publicId)
    {
        if(Input::get('_token')){
            
            $invoice = Invoice::scope($publicId)
                ->withTrashed()
                ->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')
                ->firstOrFail();
            
                
                $json =  json_encode(Cfdi::setJson($publicId, $invoice));
                $url = INVOICE_API_TIMBRAR;
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
		
                if ($response->code == 0){
                    $files = $response->files;
                    $upd = (object) array('xml'=> $files->xml,'pdf'=> $files->pdf, 'cancel_id' => $response->another, 'sale_id'=> $publicId);
                    Cfdi::saveCFDI($upd);
                    Session::flash('message', trans('texts.cfdifilescreated'));
                }
                else{
                    Session::flash('error', trans('texts.cfdifileserror').'. '. $response->code);	
                }
                
                return  Redirect::route('cfdi', array($publicId));
        }
    }
}
?>