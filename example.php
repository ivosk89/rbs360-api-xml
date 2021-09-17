<?
	/**
		* Пример работы с классом XML_API PHP
		* Документация API: https://developers.rbs360.ru/integraciya-api-xml/
	*/
	require_once("xrm_xml_api.php");
	
	$gorod = "Москва";
	$type = 1; //тип заявки (см. перечисления в RBS360)
	
	// Описание заявки
	$message_to_crm = "Описание заявки!";
	
	// авторизация
	$xml_instance = new Xml_api("http://домен-срм.ру/api/xml", "логин пользователя", "пароль пользователя");
	
	// cоздание контрагента
	$xrm_create_login = $xml_instance->add("references.companies", array(
	"date"=>date("Y-m-d",time()),
	"email"=>$email,
	"name"=>$name,
	"phone"=>$phone,
	"city"=>$gorod,
	"visible"=>1,
	"manager"=>68,
	"property_type"=>1,
	"type"=>1,
	"client_type"=>1,
	"learned_company"=>3
	
	));
	
	$xrm_company_id = $xrm_create_login->action->id;
	
	if($xrm_company_id>0){
		
		// cоздание контакного лица
		$xrm_insert_contact = $xml_instance->add("references.contacts", array("owner"=>$xrm_company_id,
		"name"=>$name,
		"phone"=>$phone,
		"email"=>$email,
		"visible"=>1
		));
		$xrm_contact_id = $xrm_insert_contact->action->id;
		
		// cоздание юр лица			
		$xrm_insert_details = $xml_instance->add("references.companies_details", array(
		"owner"=>$xrm_company_id,
		"name"=>$name,
		"visible"=>1
		));
		
		// создание заявки	
		$xrm_insert_relationship = $xml_instance->add("documents.tender_simple", array(
		"owner"=>$xrm_company_id,
		
		"date"=>date("Y-m-d H:i:s",time()),
		"type"=>$type,
		"status"=>1,
		"description"=>"<![CDATA[$message_to_crm]]>",
		"visible"=>1,
		"responsible"=>1,
		"priority"=>3		
		));
		
	}	
?>