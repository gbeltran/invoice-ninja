<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
    </head>
    <body>

      {{ $clientName }},<p/>

      {{ trans("texts.{$entityType}_message", ['amount' => $invoiceAmount]) }}<p/>      
     
      <a href="{{ $pdf }}" target="_blank">PDF</a> |   <a href="{{ $xml }}" target="_blank">XML</a>
    </body>
</html>
