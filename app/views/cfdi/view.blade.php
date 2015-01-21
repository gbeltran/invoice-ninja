@extends('header')

@section('content') 

<div class="row">
  <div class="col-md-12">  
    <div class="panel panel-default">
      <div class="panel-body">         
        <div class="in-bold">
            @if (!$invoice)
                {{ Trans('texts.select') }} {{ Trans('texts.address') }}:
            @endif
        </div>
        <div class="in-thin">
            @if ($invoice)
            <div class="row">
                <div class="col-md-4">  
                     <img src="{{ asset('images/totalinvoices.png') }}" class="in-image"/>  
                </div>
                <div class="col-md-4">  
                    <a href="{{ $invoice->pdf }}" target="_blank">PDF</a>
                </div>
                <div class="col-md-4">  
                    <a href="{{ $invoice->xml }}" target="_blank">XML</a>
                </div>
                
            </div>
            @else
            
            {{ Form::open(['url' => route('cfdiPost',array($public)), 'class' => 'removeLogoForm']) }}	
            
            <p>{{Form::select('address',$address,null,null)}}</p>                                    
             
             <p><button type="submit" class="btn btn-primary" data-dismiss="modal">{{ trans('texts.askfor') }}</button></p> 
             
            {{ Form::close() }}
            
            @endif
            
        </div>
      </div>
    </div>
  </div>
</div>
	
    
	

@stop