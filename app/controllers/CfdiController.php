<?php

class CfdiController extends Controller {

    public function settings()
    {
//        return View::make('cfdi.settings'); 
        echo 'd';
    }
    
    public function settingsPost($publicId)
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