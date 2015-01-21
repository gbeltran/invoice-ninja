<?php

class Invoice extends EntityModel
{
	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function user()
	{
		return $this->belongsTo('User');
	}	

	public function client()
	{
		return $this->belongsTo('Client')->withTrashed();
	}

	public function invoice_items()
	{
		return $this->hasMany('InvoiceItem');
	}

	public function invoice_status()
	{
		return $this->belongsTo('InvoiceStatus');
	}

	public function invoice_cfdi()
	{
		return $this->hasOne('Cfdi');
	}

	public function invoice_design()
	{
		return $this->belongsTo('InvoiceDesign');
	}

	public function invitations()
	{
		return $this->hasMany('Invitation')->orderBy('invitations.contact_id');		
	}

	public function getName()
	{
		return $this->invoice_number;
	}

	public function getLink()
	{
		return link_to('invoices/' . $this->public_id, $this->invoice_number);
	}

	public function getEntityType()
	{
		return $this->is_quote ? ENTITY_QUOTE : ENTITY_INVOICE;
	}	
	
	public function isSent()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_SENT;
	}

	public function isViewed()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_VIEWED;	
	}	

	public function isPaid()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_PAID;	
	}	

	public function hidePrivateFields()
	{
		$this->setVisible([
			'invoice_number', 
			'discount', 
			'is_amount_discount',
			'po_number', 
			'invoice_date', 
			'due_date', 
			'terms', 
			'public_notes', 
			'amount', 
			'balance', 
			'invoice_items', 
			'client', 
			'tax_name', 
			'tax_rate', 
			'account', 
			'invoice_design',
			'invoice_design_id',
			'is_pro',
			'is_quote',
			'custom_value1',
			'custom_value2',
			'custom_taxes1',
			'custom_taxes2']);
		
		$this->client->setVisible([
			'name',
			'id_number',
			'vat_number',
			'address1', 
			'address2', 
			'city', 
			'state', 
			'postal_code', 
			'work_phone', 
			'payment_terms', 
			'contacts', 
			'country', 
			'currency_id',
			'custom_value1',
			'custom_value2']);

		$this->account->setVisible([
			'name',
			'id_number',
			'vat_number',
			'address1', 
			'address2', 
			'city', 
			'state', 
			'postal_code', 
			'work_phone', 
			'work_email', 
			'country', 
			'currency_id',
			'custom_label1',
			'custom_value1',
			'custom_label2',
			'custom_value2',
			'custom_client_label1',
			'custom_client_label2',
			'primary_color',
			'secondary_color',
			'hide_quantity',
			'hide_paid_to_date',
			'custom_invoice_label1',
			'custom_invoice_label2']);		

		foreach ($this->invoice_items as $invoiceItem) 
		{
			$invoiceItem->setVisible([
				'product_key', 
				'notes', 
				'cost', 
				'qty', 
				'tax_name', 
				'tax_rate']);
		}

		foreach ($this->client->contacts as $contact) 
		{
			$contact->setVisible([
				'first_name', 
				'last_name', 
				'email', 
				'phone']);
		}						

		return $this;
	}

	public function shouldSendToday()
	{
		if (!$this->start_date || strtotime($this->start_date) > strtotime('now'))
		{
			return false;
		}

		if ($this->end_date && strtotime($this->end_date) < strtotime('now'))
		{
			return false;
		}

		$dayOfWeekToday = date('w');
		$dayOfWeekStart = date('w', strtotime($this->start_date));

		$dayOfMonthToday = date('j');
		$dayOfMonthStart = date('j', strtotime($this->start_date));
		
		if (!$this->last_sent_date) 
		{
			return true;
		} 
		else 
		{	
			$date1 = new DateTime($this->last_sent_date);
			$date2 = new DateTime();
			$diff = $date2->diff($date1);
			$daysSinceLastSent = $diff->format("%a");
			$monthsSinceLastSent = ($diff->format('%y') * 12) + $diff->format('%m');

			if ($daysSinceLastSent == 0) 
			{
				return false;
			}
		}

		switch ($this->frequency_id)
		{
			case FREQUENCY_WEEKLY:
				return $daysSinceLastSent >= 7;
			case FREQUENCY_TWO_WEEKS:
				return $daysSinceLastSent >= 14;
			case FREQUENCY_FOUR_WEEKS:
				return $daysSinceLastSent >= 28;
			case FREQUENCY_MONTHLY:
				return $monthsSinceLastSent >= 1;
			case FREQUENCY_THREE_MONTHS:
				return $monthsSinceLastSent >= 3;
			case FREQUENCY_SIX_MONTHS:
				return $monthsSinceLastSent >= 6;
			case FREQUENCY_ANNUALLY:
				return $monthsSinceLastSent >= 12;
			default:
				return false;
		}

		return false;
	}

	public static function transformarLlave($url,$metodo, $time)
	{
		//Toma la url como esta descrita el http://sandbox.apisat.mx ej: http://sandbox.apisat.mx/api/1.0/factura/{uuid} tal cual
		$url_parsed=parse_url($url);
		//Concatena la informacion Metodo, Nombre del host, el Path, el Scheme de la ruta http o https el timestamp y la llave publica separadas por @
		$data=$metodo.'@'.$url_parsed['host'].$url_parsed['path'].'@'.$url_parsed['scheme'].'@'.$time.'@'.INVOICE_API_APIPUBLIC;
		
		//Una vez parseada la ruta se procede a realizar el HMAC
		$hash=hash_hmac('sha256',$data,INVOICE_API_APISECRET);

		//Para mayor seguridad al enviar la llave privada se codifica
		$hash=base64_encode($hash);

		return $hash;
	}
}

Invoice::created(function($invoice)
{
	$invoice->account->incrementCounter($invoice->is_quote);
	Activity::createInvoice($invoice);
});

Invoice::updating(function($invoice)
{
	Activity::updateInvoice($invoice);
});

Invoice::deleting(function($invoice)
{
	Activity::archiveInvoice($invoice);
});

Invoice::restoring(function($invoice)
{
	Activity::restoreInvoice($invoice);
});
