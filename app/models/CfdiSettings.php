<?php

class CfdiSettings extends Eloquent
{
    protected $connection ="mysql";
    protected $table = 'cfdi_settings';
    
   

    public static function saveSettings($cfdi)
    {
        $data = CfdiSettings::first();
        if(sizeof($data)>0){
            $data->apisecret = $cfdi->apisecret;
            $data->apipublic = $cfdi->apipublic;
            $data->posturl = $cfdi->posturl;
            $data->cancelurl = $cfdi->cancelurl;
            $data->save();
            return $data;
        }else{			
            $new = new CfdiSettings();
            $new->apisecret = $cfdi->apisecret;
            $new->apipublic = $cfdi->apipublic;
            $new->posturl = $cfdi->posturl;
            $new->cancelurl = $cfdi->cancelurl;
            $new->save();
            return $new;
        }
    }
}
