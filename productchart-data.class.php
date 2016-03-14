<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
		
class ProductChart_Data{

	//Plugin settings
	private  $_connect;
	private  $_remote_host;
	private  $_remote_db;
	private  $_remote_user;
	private  $_remote_pass;
	
	//Link of the remote DB connection
	private  $_remote_link;
	
	//Message on the settings page
	public  $info_connect;

	//DB queries	
	protected static $_query_categories;
	protected static $_query_products;	

	//results of DB queries	
	protected $_result_categories;
	protected $_result_products;	


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
	* Main function for the data of product-chart
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
			
			$data_string = $this->data_processing();
			
			return $data_string;
			
		}else{
			
			return false;
		}
	
	}

// ======================= LOCAL DB Processing 
	
	/**
	* Get an array of all the categories from local DB
	* Get an array of all the category names from local DB
	*/	
	public function db_result_categories(){

		global $wpdb;
		
		$this->_result_categories = $wpdb->get_results(self::$_query_categories);
		
			if ( empty($this->_result_categories) ){
				$this->info_connect .= "<br>Query result (categories) is empty!";
				return false;
			}

		//  Array: all categories => parents
		$cat = array();
		
		// Array:  all categories => names
		global $namecat;
		$namecat = array();
		
		foreach ($this->_result_categories as $ct)
		{
			$ct->pg_id =($ct->pg_id)*1;
			$ct->pg_supergroupid =($ct->pg_supergroupid)*1;
				
			$cat[$ct->pg_id] = $ct->pg_supergroupid;
			$namecat[$ct->pg_id] = $ct->pg_nameshort;
		}
		
		//	Find the top category for each category

		global $supercat;
		$supercat = array();
				
		foreach($cat as $key => $value){
			
			$k = $key;
			$v = $value;

			while($v){
				if($k == $v){
					global $supercat;
					$supercat[$key] = $v;
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
	**/
	public function db_result_products(){
		
		global $wpdb;
		
		$this->_result_products = $wpdb->get_results(self::$_query_products);
		
		if ( empty($this->_result_products) ){
			$this->info_connect .= "<br>Query result (products) is empty!";
			return false;
		}
			
		global $supercat;
		
		// Array of sales
		global $data;
		$data = array();
		
		// The categories in which were  sales
		global $names;
		$names = array();			
		
		// Fill a two-dimensional array: Day => subarray sales in top categories
		foreach ($this->_result_products as $item) 	
		{
			if(array_key_exists($item->p_productgroupid, $supercat)){
				
				$data[$item->date][$supercat[$item->p_productgroupid]] += $item->numsales;
				
				// Note the top categories, which were selling
				$names[$supercat[$item->p_productgroupid]] = true;
			}
		}		
	
		return true;
	}	

	
// ======================= REMOTE DB Processing 

	/**
	* Establish a connection to the remote database
	*/
	public  function remotedb_connect(){
		
		$this->info_connect = '<br><b>Fatal error database connection!</b>
		<br>Site up and running in emergency mode.
		<br>Probably, your settings are not correct...
		<br>You should change settings or switch to the local connection.';		
	
		//Use if to prevent fatal error
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
	*/	
	public  function remotedb_result_categories(){
	
		//  Array: all categories => parents
		$cat = array();
		
		// Array:  all categories => names
		global $namecat;
		$namecat = array();
	
		while($ct = @mysqli_fetch_assoc($this->_result_categories))
		{
			$ct['pg_id'] =$ct['pg_id']*1;
			$ct['pg_supergroupid'] =$ct['pg_supergroupid']*1;
				
			$cat[$ct['pg_id']] = $ct['pg_supergroupid'];
			$namecat[$ct['pg_id']] = $ct['pg_nameshort'];
		}
		
		/**
		*	Find the top category for each category
		*/
		global $supercat;
		$supercat = array();
				
		foreach($cat as $key => $value){
			
			$k = $key;
			$v = $value;

			while($v){
				if($k == $v){
					global $supercat;
					$supercat[$key] = $v;
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
	**/	
	public  function remotedb_result_products(){

		global $supercat;
		
		// Array of sales
		global $data;
		$data = array();
		
		// The categories in which were  sales
		global $names;
		$names = array();
			
		// Fill a two-dimensional array: Day => subarray sales in top categories 
		while($item = @mysqli_fetch_assoc($this->_result_products)) 	
		{
			if(array_key_exists($item['p_productgroupid'], $supercat)){
				
				$data[$item['date']][$supercat[$item['p_productgroupid']]] += $item['numsales'];
				
				// Note the top categories, which were selling
				$names[$supercat[$item['p_productgroupid']]] = true;
			}
		}

		return true;	
	}
	
	
// ======================= DATA Processing 

	/**
	* Process data for the resulting string
	**/		
	public function data_processing(){
		
		global $namecat;
		global $data;
		global $names;
		
		/**
		* Get a uniform top sales subarrays categories for all days:
		* Supplement subarrays top categories in which no sales on a particular day, but were selling on other days
		*/
		foreach($data as $data_key => $data_value){	

			foreach ( $names as $names_key => $names_value){
				if(!array_key_exists($names_key, $data_value)){
					
					$data[$data_key][$names_key] = 0;
				}
			}
			ksort($data[$data_key]);
			
		}

		
		/**
		*	Find names for the top categories, which were selling
		*/
		foreach($names as $sale_key => $sale_value){
			
			if(!$sale_value)
				unset ($names[$sale_key]);
			
			else
				$names[$sale_key] = $namecat[$sale_key];
		}
		unset($namecat);

		// Sort an array of the names of top categories
		ksort($names);

		
		/**
		*	Form the final string to pass to the Google Chart
		*/	
		$data_string = '';
		$data_string .= "[ 'Category', ";

		foreach($names as $name){
			$data_string .= "'";
			$data_string .= $name;
			$data_string .= "', ";	
		}
		
		$data_string .= "{ role: 'annotation' } ]," ;

		foreach($data as $datakey => $datavalue){
				$data_string .= " [ '";
				$data_string .= $datakey; 
				$data_string .= "', ";
				$data_string .= implode(", ", $datavalue); 
				$data_string .= ", '']," ;
		}

		return $data_string;
	
	}

	
	/**
	* Messages about connecting to the database to display on the settings page
	**/		
	public function info(){
		
		return $this->info_connect;
	}
	
}