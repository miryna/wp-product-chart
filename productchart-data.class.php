<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
		
class ProductChart_Data{

	/**
	* Plugin settings 
	* @string "private"
	* or @string "remote"
	*/
	private  $_connect;

	/**
	* Plugin settings 
	* @string in case $_connect == "remote"
	* @null in case $_connect == "local"
	*/	
	private  $_remote_host;
	private  $_remote_db;
	private  $_remote_user;
	private  $_remote_pass;
	
	/**
	* Link of the remote DB connection
	* @resource in case $_connect == "remote"
	* @null in case $_connect == "local"
	*/	
	private  $_remote_link;
	
	/**
	* Message on the admin settings page of this plugin
	* @string
	*/
	public  $info_connect;

	/**
	* DB queries
	* @string
	*/	
	protected static $_query_categories;
	protected static $_query_products;	
	
	/**
	* Results of DB queries
	* @object
	*/
	protected $_result_categories;
	protected $_result_products;

	/**
	* @array
	* Each  ID of category (key) being the ID of top category (value)
	*/
	protected $supercat = array();

	/**
	* @array
	* Each  ID of category (key) being the name of category (value)
	*/
	protected $namecat = array();

	/**
	* @array
	* Notes the top categories, which were selling
	*/
	protected $names = array();

	/**
	* @array
	* A two-dimensional array: each Day (key) being a subarray sales in top categories ( ID category (key) => number of sales (value))
	*/
	protected $data = array();


	public function __construct() {
		
		$this->_connect = get_option('pch_connect');
	
		if ($this->_connect == 'remote'){
			
			$this->_remote_host = get_option('pch_host');
			$this->_remote_db = get_option('pch_db');
			$this->_remote_user = get_option('pch_user');
			$this->_remote_pass = get_option('pch_pass');
			
		}
		
		self::$_query_categories = '
		
			SELECT  pg_id, 
						pg_supergroupid,
						pg_nameshort					
				
				FROM productgroups
				
				ORDER BY pg_id DESC
		';
	
		self::$_query_products = '
		
			SELECT  p_id,
					p_productgroupid, 
					DATE(o_creationdate) AS date,
					COUNT(p_productgroupid) AS numsales
					
			FROM products, 
				 orders
			
			WHERE   p_id=o_productid 
					
			GROUP BY p_productgroupid,
					 DATE(o_creationdate)
			
			ORDER BY  DATE(o_creationdate), 
					  p_productgroupid DESC
		'; 
		
		$this->info_connect = 'test';		
	
		add_action( 'init', array( &$this, 'data' ) );

		}	

		
	/**
	* Main function for the data processing of product-chart, returns the final string to pass to the Google Chart
	*
	* no params
	* @return string in success case, false in case of failure
	*
	**/	
	public function data(){
	
		if($this->_connect == 'remote'){
		
			add_action( 'init', array( &$this, 'remotedb_connect' ) );
			add_action( 'init', array( &$this, 'remotedb_result_categories' ) );
			add_action( 'init', array( &$this, 'remotedb_result_products' ) );		
		
			if($this->remotedb_connect()){
				
				$result_cat = $this->remotedb_result_categories();
				$result_prod = $this->remotedb_result_products();				
			}

		}else{

			add_action( 'init', array( &$this, 'db_result_categories' ) );
			add_action( 'init', array( &$this, 'db_result_products' ) );	
		
			$result_cat = $this->db_result_categories();
			$result_prod = $this->db_result_products();

		}

		add_action( 'init', array( &$this, 'data_processing' ) );

		
		if(	$result_cat && $result_prod ){
			
			$this->data_string = $this->data_processing();
			
			return $this->data_string;
			
		}else{
			
			return false;
		}
	
	}

// ======================= LOCAL DB Processing 
	
	/**
	* Get an array of all categories from local DB
	* Get an array of all category names from local DB
	*
	* no params
	* @return bool
	*/	
	public function db_result_categories(){

		global $wpdb;
		
		$this->_result_categories = $wpdb->get_results(self::$_query_categories);
		
			if ( empty($this->_result_categories) ){
				$this->info_connect .= "<br>Query result (categories) is empty!";
				return false;
			}

		$cat = array();
		// Each  ID of category (key) being the ID of parent category (value)
		
		foreach ($this->_result_categories as $ct)
		{
			$ct->pg_id =($ct->pg_id)*1;
			$ct->pg_supergroupid =($ct->pg_supergroupid)*1;
				
			$cat[$ct->pg_id] = $ct->pg_supergroupid;
			$this->namecat[$ct->pg_id] = $ct->pg_nameshort;
		}
		
		foreach($cat as $key => $value){
			
			$k = $key;
			$v = $value;

			while($v){
				if($k == $v){
					$this->supercat[$key] = $v;
					break;
				}
				else{
					$k = $v;
					if ( !$cat[$k] ){
						$this->info_connect .= "<br><b>Error taxonomy categories: <br> Category id = $k has no top category! </b>";
						return false;
					}	
					$v = $cat[$k];
				}
			}
		}

		unset($cat);
	
		return true;
	}
	
	/**
	 * Find the sales for each category for each day (daily sales) from local DB
	 *
	 * no params
	 * @return bool
	 *
	*/
	public function db_result_products(){
		
		global $wpdb;
		
		$this->_result_products = $wpdb->get_results(self::$_query_products);
		
		if ( empty($this->_result_products) ){
			$this->info_connect .= "<br>Query result (products) is empty!";
			return false;
		}
			

		foreach ($this->_result_products as $item) 	
		{
			if(array_key_exists($item->p_productgroupid, $this->supercat)){

				// Fill a two-dimensional array: Day => subarray sales in top categories
				$this->data[$item->date][$this->supercat[$item->p_productgroupid]] += $item->numsales;
				
				// Note the top categories, which were selling
				$this->names[$this->supercat[$item->p_productgroupid]] = true;
			}
		}		
	
		return true;
	}	

	
// ======================= REMOTE DB Processing 

	/**
	* Establish a connection to the remote database
	*
	* no params
	* @return bool
	*/
	public  function remotedb_connect(){

		//This message will display if the following function @mysqli_connect() completed a fatal error 
		$this->info_connect = '<br><b>Fatal error database connection!</b>
		<br>Site up and running in emergency mode.
		<br>Probably, your settings are not correct...
		<br>You should change settings or switch to the local connection.';
	
		//Use IF to prevent fatal error on the html page
		if(!$this->_remote_link = @mysqli_connect($this->_remote_host, $this->_remote_user, $this->_remote_pass, $this->_remote_db) ){
			return false;
		}
		
		$this->info_connect = '<br>Connection is established... ';

		$this->_result_categories = mysqli_query($this->_remote_link,self::$_query_categories);
			
		if (  empty($this->_result_categories) ){

			$this->info_connect .= "<br>Query result (categories) is empty!";
		
			return false;
		}
		
		$this->_result_products = mysqli_query($this->_remote_link,self::$_query_products);
			
		if ( empty($this->_result_products) ){
				
			$this->info_connect .= "<br>Query result (sales) is empty!";

			return false;
		}

		return true;
	
	}

	
	/**
	* Get an array of all the categories from remote DB
	* Get an array of all the category names from remote DB
	*
	* no params
	* @return bool
	*/	
	public  function remotedb_result_categories(){

		$cat = array();
		// Each  ID of category (key) being the ID of parent category (value)
		
		while($ct = @mysqli_fetch_assoc($this->_result_categories))
		{
			$ct['pg_id'] =$ct['pg_id']*1;
			$ct['pg_supergroupid'] =$ct['pg_supergroupid']*1;
				
			$cat[$ct['pg_id']] = $ct['pg_supergroupid'];
			// Fill an array: the ID of category => the name of category
			$this->namecat[$ct['pg_id']] = $ct['pg_nameshort'];
		}
				
		foreach($cat as $key => $value){
			
			$k = $key;
			$v = $value;

			while($v){
				if($k == $v){
					$this->supercat[$key] = $v;
					break;
				}
				else{
					$k = $v;
					if ( !$cat[$k] ){
						$this->info_connect .= "<br><b>Error taxonomy categories: <br> Category id = $k has no top category! </b>";
						return false;
					}	
					$v = $cat[$k];
				}
			}
		}

		unset($cat);

		return true;
			
	}


	/**
	* Find the sales for each category for each day (daily sales) from remote DB
	*
	* no params
	* @return bool	
	**/	
	public  function remotedb_result_products(){

		while($item = @mysqli_fetch_assoc($this->_result_products))
		{
			if(array_key_exists($item['p_productgroupid'], $this->supercat)){

				// Fill a two-dimensional array: Day => subarray sales in top categories 
				$this->data[$item['date']][$this->supercat[$item['p_productgroupid']]] += $item['numsales'];
				
				// Note the top categories, which were selling
				$this->names[$this->supercat[$item['p_productgroupid']]] = true;
			}
		}

		return true;	
	}
	
	
	/**
	* DATA Processing 
	*
	* Process data for the resulting string
	*
	* no params
	* @return string
	**/		
	public function data_processing(){
		
		// Get a uniform top sales subarrays categories for all days:
		// Supplement subarrays top categories in which no sales on a particular day, but were selling on other days
		
		foreach($this->data as $this->data_key => $this->data_value){	

			foreach ( $this->names as $this->names_key => $this->names_value){
				if(!array_key_exists($this->names_key, $this->data_value)){
					
					$this->data[$this->data_key][$this->names_key] = 0;
				}
			}
			ksort($this->data[$this->data_key]);
			
		}
		
		//	Find names for the top categories, which were selling
		
		foreach($this->names as $sale_key => $sale_value){
			
			if(!$sale_value)
				unset ($this->names[$sale_key]);
			
			else
				$this->names[$sale_key] = $this->namecat[$sale_key];
		}
		unset($this->namecat);

		// Sort an array of the names of top categories
		ksort($this->names);

		
		//	Form the final string to pass to the Google Chart

		$this->data_string = '';
		$this->data_string .= "[ 'Category', ";

		foreach($this->names as $name){
			$this->data_string .= "'";
			$this->data_string .= $name;
			$this->data_string .= "', ";	
		}
		
		$this->data_string .= "{ role: 'annotation' } ]," ;

		foreach($this->data as $this->datakey => $this->datavalue){
				$this->data_string .= " [ '";
				$this->data_string .= $this->datakey; 
				$this->data_string .= "', ";
				$this->data_string .= implode(", ", $this->datavalue); 
				$this->data_string .= ", '']," ;
		}

		return $this->data_string;
	
	}

	
	/**
	* get messages about connecting to database to display on the settings page
	*
	* no params
	* @return string
	**/		
	public function info(){
		
		return $this->info_connect;
	}
	
}