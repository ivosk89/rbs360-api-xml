<?php

namespace ivosk89\api_xml;

use SimpleXMLElement;

/**
 * Вспомогательный класс для работы с XML API программы RBS360
 * Документация API: https://developers.rbs360.ru/integraciya-api-xml/.
 */
class Xml_api
{
    /**
     *    property string $url - хранение адреса сервера с XRM
     *    property string $login - хранение логина авторизации сервера с XRM
     *    property string $pass - хранение пароля авторизации сервера с XRM
     *    property string $sess_id - хранение хэша сессии после авторизации сервера с XRM.
     */
    private $url;

    private $login;

    private $pass;

    private $sess_id;

    private static $instance;

    /**
     * Xml_api constructor.
     */
    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Создание подключения и получение sess_id.
     *
     * @param $url
     * @param $login
     * @param $pass
     *
     * @return static
     */
    public static function getInstance($url, $login, $pass)
    {
        if (!self::$instance) {
            self::$instance = new static();
            self::$instance->url = $url;
            self::$instance->login = $login;
            self::$instance->pass = $pass;
            self::$instance->_auth();
        }

        return self::$instance;
    }

    /**
     * @return string
     */
    public function head()
    {
        return "<?xml version='1.0' encoding='utf8' ?><request>";
    }

    /**
     * @return string
     */
    public function footer()
    {
        return '</request>';
    }

    /**
     * Создание запроса на авторизацию и отправка его на сервер, в случае успеха установка свойства sess_id.
     */
    private function _auth()
    {
        $request = $this->head()
            ."<action type='auth' uid='80085'>
			<login>".$this->login.'</login>
			<password>'.$this->pass.'</password>
			</action>'
            .$this->footer();

        if ($response_parse = $this->send($this->url, 'POST', ['xml' => $request])) {
            $this->sess_id = $response_parse->action->sess_id;
        }
    }

    /**
     *    отправка xml запроса на сервер
     *
     * @param $url
     * @param string $method
     * @param array  $args
     * @param array  $cookie
     *
     * @return SimpleXMLElement
     */
    public function send($url, $method = 'GET', $args = [], $cookie = [])
    {
        $ch = curl_init();
        $query_string = '';
        foreach ($args as $key => $val) {
            $query_string .= $key.'='.$val.'&';
        }

        if ($method == 'GET') {
            $url .= '?'.$query_string;
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $cookies = '';

        foreach ($cookie as $key => $val) {
            $cookies .= "$key=".$val.';';
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, $url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
        }

        $resp = curl_exec($ch);

        //так как ответ не всегда приходит валидным, а как несколько xml объектов
        //делаем его валидным объединяя все в один объект
        /*<?xml version="1.0" encoding="utf8" ?>*/
        // <response>
        //   <action type="add" uid="80085">
        //     <status>true</status>
        //     <id>4967</id>
        //   </action>
        // </response>
        /*<?xml version="1.0" encoding="utf8" ?>*/
        // <response>
        //   <action type="add" uid="80085">
        //     <status>true</status>
        //     <id>4968</id>
        //   </action>
        // </response>
        $resp = str_replace(['<?xml version="1.0" encoding="utf8" ?>', '<response>', '</response>'], '', $resp);

        $resp = '<?xml version="1.0" encoding="utf8" ?><response>'.$resp.'</response>';

        return new SimpleXMLElement($resp);
    }

    /**
     * Получение ID сессии.
     *
     * @return mixed
     */
    public function getSessionId()
    {
        return $this->sess_id;
    }

    /**
     *    создание запроса на получение данных.
     *
     * @param $structure
     * @param array $fields
     * @param array $filters
     * @param array $limits
     * @param array $orders
     *
     * @return SimpleXMLElement
     */
    public function select($structure, $fields = [], $filters = [], $limits = [], $orders = [])
    {
        $request = $this->head()
            ."<action type='list' uid='80085'>
			<structure name='".$structure."'>".'
			'.$this->_prepareFields($fields).'
			'.$this->_prepareFilters($filters).'
			'.$this->_prepareOrders($orders).'
			'.$this->_prepareLimits($limits).'     								
			</structure>
			</action>'
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     *    преобразование массива полей в xml.
     *
     * @param $fields
     *
     * @return string
     */
    private function _prepareFields($fields)
    {
        $fields_xml = '<fields>';
        foreach ($fields as $field => $value) {
            if (is_numeric($field)) {
                $field = $value;
                $value = '';
            }
            $fields_xml .= '
				<'.$field.'>'.$value.'</'.$field.'>';
        }
        $fields_xml .= '</fields>';

        return $fields_xml;
    }

    /**
     *    преобразование массива фильтров в xml.
     *
     * @param $filters
     *
     * @return string
     */
    private function _prepareFilters($filters)
    {
        $filters_xml = '<filters>';

        foreach ($filters as $filter) {
            $filters_xml .= '<filter>
				<field>'.$filter['field'].'</field>
				<operation>'.$filter['operation'].'</operation>
				<value>'.$filter['value'].'</value>
				</filter>';
        }

        $filters_xml .= '</filters>';

        return $filters_xml;
    }

    /**
     * @param $orders
     *
     * @return string
     */
    private function _prepareOrders($orders)
    {
        $orders_xml = '<orders>';

        foreach ($orders as $order) {
            $orders_xml .= '<order>
				<field>'.$order['field'].'</field>
				<type>'.$order['type'].'</type>
				</order>';
        }
        $orders_xml .= '</orders>';

        return $orders_xml;
    }

    /**
     * @param $limits
     *
     * @return string
     */
    private function _prepareLimits($limits)
    {
        $filters_xml = '';

        if (count($limits)) {
            $filters_xml .= '<limit>
				<first>'.$limits[0].'</first>
				<number>'.$limits[1].'</number>
				</limit>';
        }

        return $filters_xml;
    }

    /**
     *    создание запроса на добавление данных.
     *
     * @param $structure
     * @param array $fields
     *
     * @return SimpleXMLElement
     */
    public function add($structure, $fields = [])
    {
        $request = $this->head()
            ."<action type='add' uid='80085'>
			<structure name='".$structure."'>".'
			'.$this->_prepareFields($fields).'     								
			</structure>
			</action>'
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     * Массовое добавление записей.
     *
     * @param $structure
     * @param $records
     *
     * @return SimpleXMLElement
     */
    public function massAdd($structure, $records)
    {
        $request = $this->head()
            ."<action type='add' uid='80085'>";

        foreach ($records as $record) {
            $request .= "<structure name='".$structure."'>".'
			    '.$this->_prepareFields($record).'     								
			</structure>';
        }

        $request .= '</action>'
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     * @param $structure
     * @param $id
     * @param array $fields
     *
     * @return SimpleXMLElement
     */
    public function fileAdd($structure, $id, $fields = [])
    {
        $request = $this->head()
            ."<action type='fileAdd' uid='80085'>
			<structure name='".$structure."' id='".$id."'>".$this->_prepareFields($fields).'</structure>
			</action>'
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     * @param $id
     *
     * @return SimpleXMLElement
     */
    public function paymentCalc($id)
    {
        $request = $this->head()
            ."<action type='paymentCalc' uid='80085'>
			<structure id='".$id."'></structure>
			</action>"
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     *    создание запроса на редактирование данных.
     *
     * @param $structure
     * @param array $fields
     * @param array $filters
     *
     * @return SimpleXMLElement
     */
    public function update($structure, $fields = [], $filters = [])
    {
        $request = $this->head()
            ."<action type='edit' uid='80085'>
			<structure name='".$structure."'>".'
			'.$this->_prepareFields($fields).'
			'.$this->_prepareFilters($filters).'     								
			</structure>
			</action>'
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     * Массовое обновление записей.
     *
     * @param $structure
     * @param $records
     *
     * @return SimpleXMLElement
     */
    public function massUpdate($structure, $records)
    {
        $request = $this->head()
            ."<action type='edit' uid='80085'>";
        foreach ($records as $record) {
            $request .= "<structure name='".$structure."'>"
                .$this->_prepareFields($record['fields'])
                .$this->_prepareFilters($record['filters'])
                .'</structure>';
        }

        $request .= '</action>'
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }

    /**
     * Удаление элемента из CRM.
     *
     * @param $structure
     * @param $id
     *
     * @return SimpleXMLElement
     */
    public function delete($structure, $id)
    {
        $request = $this->head()
            ."<action type='wipe' uid='80085'>
			<structure name='".$structure."' id='".$id."'>     								
			</structure>
			</action>"
            .$this->footer();

        return $this->send($this->url, 'POST', ['xml' => $request], ['sess_id' => $this->sess_id]);
    }
}
