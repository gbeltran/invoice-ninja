@extends('accounts.nav')

@section('content')	
	@parent	

        {{ Former::open()->addClass('col-md-8 col-md-offset-2 warn-on-exit')->rules(array(
  		'apisecret' => 'required',
  		'apipublic' => 'required',
  		'posturl' => 'required',
  		'cancelurl' => 'required'
	)) }}	
        
	{{ Former::legend('CFDI Settings') }}
        
        {{ Former::text('apisecret')->label('API Secret')->value($apisecret) }}	
        {{ Former::text('apipublic')->label('API Public')->value($apipublic) }}	
        {{ Former::text('posturl')->label('Post URL')->value($posturl) }}	
        {{ Former::text('cancelurl')->label('Cancel URL')->value($cancelurl) }}	
		
        {{ Former::actions( Button::lg_success_submit('Save')->append_with_icon('floppy-disk') ) }}
	
	{{ Former::close() }}



@stop