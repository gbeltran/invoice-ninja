@extends('master')

@section('head')	

  <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/> 
  <link href="{{ asset('css/style.css') }}" rel="stylesheet" type="text/css"/>    

  <style type="text/css">
		body {
		  padding-top: 40px;
		  padding-bottom: 40px;
		}
       .modal-header {
          border-top-left-radius: 3px;
          border-top-right-radius: 3px;
       }
       .modal-header h4 {
          margin:0;
       }
       .modal-header img {
          float: left; 
          margin-right: 20px;
       }
       .form-signin {
		  max-width: 400px;
		  margin: 0 auto;
          background: #fff;
       }
       p.link a {
          font-size: 11px;
       }
       .form-signin .inner {
		  padding: 20px;
          border-bottom-right-radius: 3px;
          border-bottom-left-radius: 3px;
          border-left: 1px solid #ddd;
          border-right: 1px solid #ddd;
          border-bottom: 1px solid #ddd;
		}
		.form-signin .checkbox {
		  font-weight: normal;
		}
		.form-signin .form-control {
		 margin-bottom: 10px !important;
                 margin-top: 5px !important;
		}
		.form-signin .form-control:focus {
		  z-index: 2;
		}
		
  </style>

@stop

@section('body')
<div class="container">
    {{ Former::open('register')->addClass('form-signin') }}
    <div class="modal-header">
        <h4>Invoice Ninja Account Registration</h4>
    </div>
    <div class="inner">
        <p>
            {{ Form::text('first_name', Input::old('first_name'), array('placeholder' => 'First Name')) }}
            {{ $errors->first('first_name') }}
            {{ Form::text('last_name', Input::old('last_name'), array('placeholder' => 'Last Name')) }}
            {{ $errors->first('last_name') }}
            {{ Form::text('email', Input::old('login_email'), array('placeholder' => 'Email address')) }}
            {{ $errors->first('email') }}
            {{ Form::password('password', array('placeholder' => 'Password')) }}
            {{ $errors->first('password') }}
        </p>

        <p>{{ Button::success_submit('Register', array('class' => 'btn-lg'))->block() }}</p>
        <p class="link">{{ link_to('login', 'Sign in!') }}</p>
    </div>
    {{ Former::close() }}
</div>
@stop