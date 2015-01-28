<?php

use ninja\mailers\ContactMailer as Mailer;
use ninja\repositories\InvoiceRepository;
use ninja\repositories\ClientRepository;
use ninja\repositories\TaxRateRepository;

class InvoiceController extends \BaseController
{

	protected $mailer;
	protected $invoiceRepo;
	protected $clientRepo;
	protected $taxRateRepo;

	public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, TaxRateRepository $taxRateRepo)
	{
		parent::__construct();

		$this->mailer = $mailer;
		$this->invoiceRepo = $invoiceRepo;
		$this->clientRepo = $clientRepo;
		$this->taxRateRepo = $taxRateRepo;
	}


	public function index()
	{

		$data = [
			'title' => trans('texts.invoices'),
			'entityType' => ENTITY_INVOICE,
			'columns' => Utils::trans(['checkbox', 'invoice_number', 'client', 'invoice_date', 'invoice_total', 'balance_due', 'due_date', 'status', 'cfdi', 'action'])
		];

		$recurringInvoices = Invoice::scope()->where('is_recurring', '=', true);

		if (Session::get('show_trash:invoice')) {
			$recurringInvoices->withTrashed();
		}

		if ($recurringInvoices->count() > 0) {
			$data['secEntityType'] = ENTITY_RECURRING_INVOICE;
			$data['secColumns'] = Utils::trans(['checkbox', 'frequency', 'client', 'start_date', 'end_date', 'invoice_total', 'action']);
		}

		return View::make('list', $data);
	}

	public function clientIndex()
	{
		$data = [
			'showClientHeader' => true,
			'hideLogo' => Session::get('white_label'),
			'title' => trans('texts.invoices'),
			'entityType' => ENTITY_INVOICE,
			'columns' => Utils::trans(['invoice_number', 'invoice_date', 'invoice_total', 'balance_due', 'due_date'])
		];

		return View::make('public_list', $data);
	}

	public function getDatatable($clientPublicId = null)
	{
		$accountId = Auth::user()->account_id;
		$search = Input::get('sSearch');

		return $this->invoiceRepo->getDatatable($accountId, $clientPublicId, ENTITY_INVOICE, $search);
	}

	public function getClientDatatable()
	{
		//$accountId = Auth::user()->account_id;
		$search = Input::get('sSearch');
		$invitationKey = Session::get('invitation_key');
		$invitation = Invitation::where('invitation_key', '=', $invitationKey)->first();

		if (!$invitation || $invitation->is_deleted) {
			return [];
		}

		$invoice = $invitation->invoice;

		if (!$invoice || $invoice->is_deleted) {
			return [];
		}

		return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_INVOICE, $search);
	}

	public function getRecurringDatatable($clientPublicId = null)
	{
		$query = $this->invoiceRepo->getRecurringInvoices(Auth::user()->account_id, $clientPublicId, Input::get('sSearch'));
		$table = Datatable::query($query);

		if (!$clientPublicId) {
			$table->addColumn('checkbox', function ($model) {
				return '<input type="checkbox" name="ids[]" value="' . $model->public_id . '" ' . Utils::getEntityRowClass($model) . '>';
			});
		}

		$table->addColumn('frequency', function ($model) {
			return link_to('invoices/' . $model->public_id, $model->frequency);
		});

		if (!$clientPublicId) {
			$table->addColumn('client_name', function ($model) {
				return link_to('clients/' . $model->client_public_id, Utils::getClientDisplayName($model));
			});
		}

		return $table->addColumn('start_date', function ($model) {
			return Utils::fromSqlDate($model->start_date);
		})
			->addColumn('end_date', function ($model) {
				return Utils::fromSqlDate($model->end_date);
			})
			->addColumn('amount', function ($model) {
				return Utils::formatMoney($model->amount, $model->currency_id);
			})
			->addColumn('dropdown', function ($model) {
				if ($model->is_deleted) {
					return '<div style="height:38px"/>';
				}

				$str = '<div class="btn-group tr-action" style="visibility:hidden;">
                        <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                            ' . trans('texts.select') . ' <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu" role="menu">';

				if (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
					$str .= '<li><a href="' . URL::to('invoices/' . $model->public_id . '/edit') . '">' . trans('texts.edit_invoice') . '</a></li>
										    <li class="divider"></li>
										    <li><a href="javascript:archiveEntity(' . $model->public_id . ')">' . trans('texts.archive_invoice') . '</a></li>
										    <li><a href="javascript:deleteEntity(' . $model->public_id . ')">' . trans('texts.delete_invoice') . '</a></li>';
				} else {
					$str .= '<li><a href="javascript:restoreEntity(' . $model->public_id . ')">' . trans('texts.restore_invoice') . '</a></li>';
				}

				return $str . '</ul>
                    </div>';


			})
			->make();
	}


	public function view($invitationKey)
	{
		$invitation = Invitation::where('invitation_key', '=', $invitationKey)->firstOrFail();

		$invoice = $invitation->invoice;

		if (!$invoice || $invoice->is_deleted) {
			return View::make('invoices.deleted');
		}

		if ($invoice->is_quote && $invoice->quote_invoice_id) {
			$invoice = Invoice::scope($invoice->quote_invoice_id, $invoice->account_id)->firstOrFail();

			if (!$invoice || $invoice->is_deleted) {
				return View::make('invoices.deleted');
			}
		}

		$invoice->load('user', 'invoice_items', 'invoice_design', 'account.country', 'client.contacts', 'client.country');

		$client = $invoice->client;

		if (!$client || $client->is_deleted) {
			return View::make('invoices.deleted');
		}

		if (!Session::has($invitationKey) && (!Auth::check() || Auth::user()->account_id != $invoice->account_id)) {
			Activity::viewInvoice($invitation);
			Event::fire('invoice.viewed', $invoice);
		}

		Session::set($invitationKey, true);
		Session::set('invitation_key', $invitationKey);
		Session::set('white_label', $client->account->isWhiteLabel());

		$client->account->loadLocalizationSettings();

		$invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
		$invoice->due_date = Utils::fromSqlDate($invoice->due_date);
		$invoice->is_pro = $client->account->isPro();

		$contact = $invitation->contact;
		$contact->setVisible([
			'first_name',
			'last_name',
			'email',
			'phone']);

		$data = array(
			'showClientHeader' => true,
			'showBreadcrumbs' => false,
			'hideLogo' => $client->account->isWhiteLabel(),
			'invoice' => $invoice->hidePrivateFields(),
			'invitation' => $invitation,
			'invoiceLabels' => $client->account->getInvoiceLabels(),
			'contact' => $contact
		);

		return View::make('invoices.view', $data);
	}

	public function edit($publicId, $clone = false)
	{
		$user_id = Auth::user()->id;
		//echo '<pre>';print_R(Auth::user());echo  '</pre>';
//		$invoice = Invoice::scope($publicId)->withTrashed()->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')->firstOrFail();
		$invoice = Invoice::where('public_id', $publicId)->where('account_id', Auth::user()->account_id)->withTrashed()->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')->firstOrFail();


		$entityType = $invoice->getEntityType();
		$contactIds = DB::table('invitations')
			->join('contacts', 'contacts.id', '=', 'invitations.contact_id')
			->where('invitations.invoice_id', '=', $invoice->id)
			->where('invitations.account_id', '=', Auth::user()->account_id)
			->where('invitations.deleted_at', '=', null)
			->select('contacts.public_id')->lists('public_id');

		if ($clone) {
			$invoice->id = null;
			$invoice->invoice_number = Auth::user()->account->getNextInvoiceNumber($invoice->is_quote);
			$invoice->balance = $invoice->amount;
			$invoice->invoice_status_id = 0;
			$invoice->invoice_date = date_create()->format('Y-m-d');
			$method = 'POST';
			$url = "{$entityType}s";
		} else {
			Utils::trackViewed($invoice->invoice_number . ' - ' . $invoice->client->getDisplayName(), $invoice->getEntityType());
			$method = 'PUT';
			$url = "{$entityType}s/{$publicId}";
		}

		$invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
		$invoice->due_date = Utils::fromSqlDate($invoice->due_date);
		$invoice->start_date = Utils::fromSqlDate($invoice->start_date);
		$invoice->end_date = Utils::fromSqlDate($invoice->end_date);
		$invoice->is_pro = Auth::user()->isPro();

		$data = array(
			'entityType' => $entityType,
			'showBreadcrumbs' => $clone,
			'account' => $invoice->account,
			'invoice' => $invoice,
			'data' => false,
			'method' => $method,
			'invitationContactIds' => $contactIds,
			'url' => $url,
			'title' => trans("texts.edit_{$entityType}"),
			'client' => $invoice->client);
		$data = array_merge($data, self::getViewModel());

		// Set the invitation link on the client's contacts
		if (!$clone) {
			$clients = $data['clients'];
			foreach ($clients as $client) {
				if ($client->id == $invoice->client->id) {
					foreach ($invoice->invitations as $invitation) {
						foreach ($client->contacts as $contact) {
							if ($invitation->contact_id == $contact->id) {
								$contact->invitation_link = $invitation->getLink();
							}
						}
					}
					break;
				}
			}
		}

//                echo '<pre>';print_R($data);

		$_cfdi = Cfdi::where('invoice_id', '=', $invoice->id)->first();
		$data['cfdi'] = false;
		if (sizeof($_cfdi) > 0) {
			$data['cfdi'] = $_cfdi['flag'] == 1 ? true : false;
			return View::make('invoices.edit_view', $data);
		}
//                
		return View::make('invoices.edit', $data);
	}

	public function create($clientPublicId = 0)
	{
		$client = null;
		$invoiceNumber = Auth::user()->account->getNextInvoiceNumber();
		$account = Account::with('country')->findOrFail(Auth::user()->account_id);

		if ($clientPublicId) {
			$client = Client::scope($clientPublicId)->firstOrFail();
		}

		$data = array(
			'entityType' => ENTITY_INVOICE,
			'account' => $account,
			'invoice' => null,
			'data' => Input::old('data'),
			'invoiceNumber' => $invoiceNumber,
			'method' => 'POST',
			'url' => 'invoices',
			'title' => trans('texts.new_invoice'),
			'client' => $client);
		$data = array_merge($data, self::getViewModel());

		return View::make('invoices.edit', $data);
	}

	private static function getViewModel()
	{
		$recurringHelp = '';
		foreach (preg_split("/((\r?\n)|(\r\n?))/", trans('texts.recurring_help')) as $line) {
			$parts = explode("=>", $line);
			if (count($parts) > 1) {
				$line = $parts[0] . ' => ' . Utils::processVariables($parts[0]);
				$recurringHelp .= '<li>' . strip_tags($line) . '</li>';
			} else {
				$recurringHelp .= $line;
			}
		}

		return [
			'account' => Auth::user()->account,
			'products' => Product::scope()->orderBy('id')->get(array('product_key', 'notes', 'cost', 'qty')),
			'countries' => Country::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'clients' => Client::scope()->with('contacts', 'country')->orderBy('name')->get(),
			'taxRates' => TaxRate::scope()->orderBy('name')->get(),
			'currencies' => Currency::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'sizes' => Size::remember(DEFAULT_QUERY_CACHE)->orderBy('id')->get(),
			'paymentTerms' => PaymentTerm::remember(DEFAULT_QUERY_CACHE)->orderBy('num_days')->get(['name', 'num_days']),
			'industries' => Industry::remember(DEFAULT_QUERY_CACHE)->orderBy('name')->get(),
			'invoiceDesigns' => InvoiceDesign::remember(DEFAULT_QUERY_CACHE, 'invoice_designs_cache_' . Auth::user()->maxInvoiceDesignId())
				->where('id', '<=', Auth::user()->maxInvoiceDesignId())->orderBy('id')->get(),
			'frequencies' => array(
				1 => 'Weekly',
				2 => 'Two weeks',
				3 => 'Four weeks',
				4 => 'Monthly',
				5 => 'Three months',
				6 => 'Six months',
				7 => 'Annually'
			),
			'recurringHelp' => $recurringHelp
		];
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		return InvoiceController::save();
	}

	private function save($publicId = null)
	{
		$action = Input::get('action');
		$entityType = Input::get('entityType');
		if ($publicId):
			$invoice = Invoice::where('public_id', '=', $publicId)->where('account_id', '=', Auth::user()->account_id)->first();
			$_cfdi = Cfdi::where('invoice_id', '=', $invoice->id)->first();
			$all = Input::all();
			if ((isset($all['_formType'])) && ($all['_formType'] != 'cfdi'))
				$this->CFDI($publicId, $all['_formType']);

			if (sizeof($_cfdi) > 0) {
				$url = "{$entityType}s/" . $publicId . '/edit';
				return Redirect::to($url);
			}
		endif;

		if (in_array($action, ['archive', 'delete', 'mark', 'restore'])) {
			return InvoiceController::bulk($entityType);
		}

		$input = json_decode(Input::get('data'));
		$invoice = $input->invoice;

		if ($errors = $this->invoiceRepo->getErrors($invoice)) {
			Session::flash('error', trans('texts.invoice_error'));

			return Redirect::to("{$entityType}s/create")
				->withInput()->withErrors($errors);
		} else {
			$this->taxRateRepo->save($input->tax_rates);

			$clientData = (array)$invoice->client;
			$client = $this->clientRepo->save($invoice->client->public_id, $clientData);

			$invoiceData = (array)$invoice;
			$invoiceData['client_id'] = $client->id;
			$invoice = $this->invoiceRepo->save($publicId, $invoiceData, $entityType);

			$account = Auth::user()->account;
			if ($account->invoice_taxes != $input->invoice_taxes
				|| $account->invoice_item_taxes != $input->invoice_item_taxes
				|| $account->invoice_design_id != $input->invoice->invoice_design_id
			) {
				$account->invoice_taxes = $input->invoice_taxes;
				$account->invoice_item_taxes = $input->invoice_item_taxes;
				$account->invoice_design_id = $input->invoice->invoice_design_id;
				$account->save();
			}

			$client->load('contacts');
			$sendInvoiceIds = [];

			foreach ($client->contacts as $contact) {
				if ($contact->send_invoice || count($client->contacts) == 1) {
					$sendInvoiceIds[] = $contact->id;
				}
			}

			foreach ($client->contacts as $contact) {
				$invitation = Invitation::scope()->whereContactId($contact->id)->whereInvoiceId($invoice->id)->first();

				if (in_array($contact->id, $sendInvoiceIds) && !$invitation) {
					$invitation = Invitation::createNew();
					$invitation->invoice_id = $invoice->id;
					$invitation->contact_id = $contact->id;
					$invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
					$invitation->save();
				} else if (!in_array($contact->id, $sendInvoiceIds) && $invitation) {
					$invitation->delete();
				}
			}

			$message = trans($publicId ? "texts.updated_{$entityType}" : "texts.created_{$entityType}");
			if ($input->invoice->client->public_id == '-1') {
				$message = $message . ' ' . trans('texts.and_created_client');

				$url = URL::to('clients/' . $client->public_id);
				Utils::trackViewed($client->getDisplayName(), ENTITY_CLIENT, $url);
			}

			if ($action == 'clone') {
				return $this->cloneInvoice($publicId);
			} else if ($action == 'convert') {
				return $this->convertQuote($publicId);
			} else if ($action == 'email') {
				if (Auth::user()->confirmed && !Auth::user()->isDemo()) {
					$message = trans("texts.emailed_{$entityType}");
					$this->mailer->sendInvoice($invoice);
					Session::flash('message', $message);
				} else {
					$errorMessage = trans(Auth::user()->registered ? 'texts.confirmation_required' : 'texts.registration_required');
					Session::flash('error', $errorMessage);
					Session::flash('message', $message);
				}
			} else {
				Session::flash('message', $message);
			}
			if ($publicId == null)
				$publicId = $invoice->public_id;

			$all = Input::all();
			if (isset($all['_formType']))
				$this->CFDI($publicId, $all['_formType']);

			$url = "{$entityType}s/" . $invoice->public_id . '/edit';
			return Redirect::to($url);
			return;
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int $id
	 * @return Response
	 */
	public function show($publicId)
	{
		Session::reflash();

		return Redirect::to('invoices/' . $publicId . '/edit');
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int $id
	 * @return Response
	 */
	public function update($publicId)
	{
		return InvoiceController::save($publicId);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int $id
	 * @return Response
	 */
	public function bulk($entityType = ENTITY_INVOICE)
	{
		$action = Input::get('action');
		$statusId = Input::get('statusId', INVOICE_STATUS_SENT);
		$ids = Input::get('id') ? Input::get('id') : Input::get('ids');
		$count = $this->invoiceRepo->bulk($ids, $action, $statusId);

		if ($count > 0) {
			$key = $action == 'mark' ? "updated_{$entityType}" : "{$action}d_{$entityType}";
			$message = Utils::pluralize($key, $count);
			Session::flash('message', $message);
		}

		if ($action == 'restore' && $count == 1) {
			return Redirect::to("{$entityType}s/" . $ids[0]);
		} else {
			return Redirect::to("{$entityType}s");
		}
	}

	public function convertQuote($publicId)
	{
		$invoice = Invoice::with('invoice_items')->scope($publicId)->firstOrFail();
		$clone = $this->invoiceRepo->cloneInvoice($invoice, $invoice->id);

		Session::flash('message', trans('texts.converted_to_invoice'));
		return Redirect::to('invoices/' . $clone->public_id);
	}

	public function cloneInvoice($publicId)
	{
		/*
		$invoice = Invoice::with('invoice_items')->scope($publicId)->firstOrFail();   
		$clone = $this->invoiceRepo->cloneInvoice($invoice);
		$entityType = $invoice->getEntityType();

		Session::flash('message', trans('texts.cloned_invoice'));
		return Redirect::to("{$entityType}s/" . $clone->public_id);
		*/

		return self::edit($publicId, true);
	}

	public function cancelCfdi($publicId)
	{

		$invoice = Invoice::find($publicId);

		if (count($invoice)) {
			$uuid = $invoice->invoice_cfdi()->first()->cancel;
			$time = date('c');
			$url = INVOICE_API_CANCELAR;
			$llave_privada = Invoice::transformarLlave($url, 'delete', $time);
			$rfc = $invoice->client()->first()->rfc;
			$parametros = array(
				'http' => array(
					'ignore_errors' => true,
					'header' => "llave_publica: " . INVOICE_API_APIPUBLIC . " \r\n" .
						"llave_privada: " . $llave_privada . " \r\n" .
						"timestamp: " . $time . " \r\n" .
						"rfc: " . $rfc . " \r\n",
					'method' => 'DELETE',
				),
			);
			$context = stream_context_create($parametros);

			$result = file_get_contents(INVOICE_API_TIMBRAR . '/' . $uuid, false, $context);

			$result = json_decode($result);

			if ($result->codigo == 201) {
				Session::flash('message', 'CFDI Cancelado');
				$cfdi = Cfdi::where('invoice_id', '=', $invoice->id)->first();
				$cfdi->flag = 1;
				$cfdi->save();
			} else
				Session::flash('error', $result->mensaje);


			return Redirect::back();
		} else {
		}

	}

	public function CFDI($publicId, $type)
	{
		if ($type == 'cfdi') {

			$invoice = Invoice::scope($publicId)->where('account_id', '=', Auth::user()->account_id)
				->withTrashed()
				->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')
				->firstOrFail();

			$api = CfdiSettings::first();
			if (sizeof($api) > 0) {
				try {
					$response = Cfdi::sendCfdi($publicId, $invoice);

				} catch (Exception $e) {
					Session::flash('error', $e->getMessage());
				}
				if ($response->codigo == 200) {
					$files = $response->archivos;
					$upd = (object)array('xml' => $files->xml, 'pdf' => $files->pdf, 'cancel_id' => $response->uuid, 'sale_id' => $invoice->id);
					Cfdi::saveCFDI($upd);

					try {
						Cfdi::emailCfdi($files, $invoice);
					} catch (Exception $exc) {
						Session::flash('error', $exc->getTraceAsString());
					}
					Session::flash('message', trans('texts.cfdifilescreated'));
				} else {
					Session::flash('error', trans('texts.cfdifileserror') . '.<br> #<strong>' . $response->codigo . '</strong>: <i>' . $response->mensaje . '</i>.');
				}
			} else {
				Session::flash('error', trans('texts.apisettingserror'));
			}
		} else {
			$invoice = Invoice::where('public_id', '=', $publicId)->where('account_id', '=', Auth::user()->account_id)->first();
			if (count($invoice))
				InvoiceController::cancelCfdi($invoice->id);
		}
		return Redirect::back();
	}

	public function requestFile($invoiceId, $tipo)
	{
		try {
			if ($tipo == 'xml' || $tipo == 'pdf') {
				$invoice = Invoice::find($invoiceId);
				if (count($invoice)) {
					$account = Account::where('id', '=', $invoice->account_id)->first();
					$cfdi = Cfdi::where('invoice_id', '=', $invoice->id)->first();
					if (count($cfdi) && count($account)) {
						$response = Cfdi::getFile($cfdi->cancel, $tipo, $account);

						header('Content-Type: application/pdf');
						header('Content-Disposition: inline; filename=' . $cfdi->cancel . $tipo);
						header('Content-Transfer-Encoding: binary');
						header('Expires: 0');
						header('Cache-Control: must-revalidate');
						header('Pragma: public');
						header('Content-Length: ' . strlen($response));

						echo $response;


					} else {
						throw new Exception('No se ha encontrado el cfdi o la cuenta perteneciente', 0);
					}
				} else {
					throw new Exception('Invoice no existe', 0);
				}
			} else {
				throw new Exception('Solo pdf o xml', 0);
			}
		} catch (Exception $e) {
			Session::flash('error', $e->getMessage());
			Redirect::back();
		}
	}

	public static function makePdf()
	{
		echo '<script src="{{ asset(\'js/pdf_viewer.js\') }}" type="text/javascript"></script>';
	}


	public function test()
	{

		$account=Account::where('id','=',Auth::user()->account_id)->first();
		$data=  '{"invoice":{"client":{"public_id":"1","name":"Lala","id_number":"","vat_number":"","work_phone":"","custom_value1":"","custom_value2":"","private_notes":"","address1":"Mira","address2":"200","city":"Tijuana","state":"Baja California","postal_code":"22207","country_id":"484","size_id":"2","industry_id":"3","currency_id":"1","website":"","payment_terms":"7","contacts":[{"public_id":"1","first_name":"Lala","last_name":"Lolo","email":"cdfi@yopmail.com","phone":"1234567890","send_invoice":true,"account_id":"7","user_id":"7","client_id":"6","created_at":"2015-01-21 19:07:45","updated_at":"2015-01-22 20:44:56","deleted_at":null,"is_primary":"1","last_login":null}],"mapping":{"contacts":{}},"country":{"id":"484","name":"Mexico"},"user_id":"7","account_id":"7","created_at":"2015-01-21 19:07:45","updated_at":"2015-01-22 20:44:56","deleted_at":null,"balance":"12500.00","paid_to_date":null,"last_login":null,"is_deleted":"0","rfc":"AAD990814BP7","suburb":"Laracast"},"account":{"id":"7","timezone_id":"6","date_format_id":"7","datetime_format_id":"7","currency_id":"1","created_at":"2015-01-21 18:58:32","updated_at":"2015-01-26 20:53:36","deleted_at":null,"name":"Luis Josue","ip":"187.184.159.189","account_key":"Z6655V2nQDHozlpAxpxVWQEZxPo3h70t","last_login":"2015-01-23 21:59:48","address1":"Casa Blacan","address2":"20500","city":"Tijuana","state":"Baja California","postal_code":"22207","country_id":"484","invoice_terms":null,"email_footer":null,"industry_id":"12","size_id":null,"invoice_taxes":"1","invoice_item_taxes":"0","invoice_design_id":"1","work_phone":"6642182508","work_email":"luis@solucioname.net","language_id":"7","pro_plan_paid":null,"custom_label1":null,"custom_value1":null,"custom_label2":null,"custom_value2":null,"custom_client_label1":null,"custom_client_label2":null,"fill_products":"1","update_products":"1","primary_color":null,"secondary_color":null,"hide_quantity":"0","hide_paid_to_date":"0","custom_invoice_label1":null,"custom_invoice_label2":null,"custom_invoice_taxes1":null,"custom_invoice_taxes2":null,"vat_number":"","invoice_design":null,"invoice_number_prefix":null,"invoice_number_counter":"24","quote_number_prefix":null,"quote_number_counter":"1","share_counter":"1","id_number":"","rfc":"AAD990814BP7","suburb":"Los Lobos"},"id":"","discount":"1","is_amount_discount":"0","frequency_id":"1","terms":"213","set_default_terms":true,"public_notes":"1231","po_number":"0024","invoice_date":"Mon January 26, 2015","invoice_number":"0024","due_date":"Mon February 2, 2015","start_date":"Mon January 26, 2015","end_date":"","tax_name":"IVA","tax_rate":"16","is_recurring":false,"invoice_status_id":0,"invoice_items":[{"product_key":"pokebola","notes":"pokebola","cost":"100.00","qty":"2","tax_name":"IVA","tax_rate":"16","actionsVisible":false,"_tax":{"public_id":"","rate":"16","name":"IVA","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":16,"displayName":"16% IVA"},"tax":{"public_id":"","rate":"16","name":"IVA","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":16,"displayName":"16% IVA"},"prettyQty":2,"prettyCost":"100.00","mapping":{"tax":{}},"wrapped_notes":"pokebola"},{"product_key":"","notes":"","cost":0,"qty":0,"tax_name":"","tax_rate":0,"actionsVisible":false,"_tax":{"public_id":"","rate":0,"name":"","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":"","displayName":""},"tax":{"public_id":"","rate":0,"name":"","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":"","displayName":""},"prettyQty":"","prettyCost":"","mapping":{"tax":{}},"wrapped_notes":""}],"amount":0,"balance":0,"invoice_design_id":"2","custom_value1":"","custom_value2":"","custom_taxes1":false,"custom_taxes2":false,"mapping":{"client":{},"invoice_items":{},"tax":{}},"_tax":{"public_id":"","rate":"16","name":"IVA","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":16,"displayName":"16% IVA"},"tax":{"public_id":"","rate":"16","name":"IVA","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":16,"displayName":"16% IVA"},"wrapped_terms":"213","wrapped_notes":"1231"},"tax_rates":[{"public_id":"","rate":0,"name":"","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":"","displayName":""},{"public_id":"","rate":"16","name":"IVA","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":16,"displayName":"16% IVA"},{"public_id":"","rate":0,"name":"","is_deleted":false,"is_blank":false,"actionsVisible":false,"prettyRate":"","displayName":""}],"invoice_taxes":true,"invoice_item_taxes":true,"mapping":{"invoice":{},"tax_rates":{}},"clientLinkText":"Editar detalles del cliente","taxBackup":false}';
		if(file_exists($account->getLogoPath()))
			$exist=true;
		else
			$exist=false;

		$labels=$account->getInvoiceLabels();
		echo '<script src="'.asset('js/jquery.1.9.1.min.js').'"></script><script src="//cdn.datatables.net/1.10.4/js/jquery.dataTables.min.js"></script><script src="//cdn.datatables.net/tabletools/2.2.3/js/dataTables.tableTools.min.js"></script><script src="'.asset('js/jspdf.source.js').'"></script><script src="'.asset('built.js').'"></script>
		<script src="' . asset('js/pdf_viewer.js') . '" type="text/javascript"></script><script src="' . asset('js/compatibility.js') . '" type="text/javascript"></script><script src="' .  asset('js/makePdf.js') . '"></script>';

		echo '<script>
				var currencies = '.Currency::remember(120)->get().';
				  var currencyMap = {};
				  for (var i=0; i<currencies.length; i++) {
					var currency = currencies[i];
					currencyMap[currency.id] = currency;
				  }


			  window.logoImages = {};

			  logoImages.imageLogo1 = "'.HTML::image_data("images/report_logo1.jpg").'";
			  logoImages.imageLogoWidth1 =120;
			  logoImages.imageLogoHeight1 = 40;

			  logoImages.imageLogo2 = "'.HTML::image_data('images/report_logo2.jpg').'";
			  logoImages.imageLogoWidth2 =325/2;
			  logoImages.imageLogoHeight2 = 81/2;

			  logoImages.imageLogo3 = "'.HTML::image_data('images/report_logo3.jpg').'";
			  logoImages.imageLogoWidth3 =325/2;
			  logoImages.imageLogoHeight3 = 81/2;
			  var exist="'.json_encode($exist).'";
			  exist=exist==="true";



			  var NINJA = NINJA || {};
			  NINJA.primaryColor = "'.$account->primary_color.'";
			  NINJA.secondaryColor = "'. $account->secondary_color.'";

			  NINJA.parseFloat = function(str) {
				if (!str) return \'\';
				str = (str+\'\').replace(/[^0-9/./-]/g, \'\');
				return window.parseFloat(str);
			  }



			  if (exist)
				  if (window.invoice) {
					invoice.image = "'.HTML::image_data($account->getLogoPath()).'";
					invoice.imageWidth = "'.$account->getLogoWidth().'";
					invoice.imageHeight = "'.$account->getLogoHeight().'";
				  }

				  function formatMoney(value, currency_id, hide_symbol) {
    				value = NINJA.parseFloat(value);
				if (!currency_id) currency_id = '. Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY).';
			var currency = currencyMap[currency_id];
			return accounting.formatMoney(value, hide_symbol ? "" : currency.symbol, currency.precision, currency.thousand_separator, currency.decimal_separator);
			}
		  </script>';

			echo '<script>
			var invoiceLabels ='.json_encode($labels).';
			savePdf(' . $data . ');</script>';
	}
}