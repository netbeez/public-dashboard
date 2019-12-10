<?php


// ******************************************************************
// ******************************************************************
// MAKE HTTPS REQUESTS TO SERVER
// ******************************************************************
// ******************************************************************

//abstract, this should never be directly instantiated
abstract class Api_Access {
	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// CONSTANTS >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	const FORMAT = "json";
	const REQUEST_TYPE = "GET"; 


	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// PRIVATE FUNCTIONS >>>>>>>>>>>>>>>>>>>>>>
	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	//builds an array into a path
	private static function build_http_path($string, $current){
		return $string . "/" . $current;
	}

	//creates an entire url
	private static function build_url($url_array, $queries_hash){
		//format URL array into one string
		$path = array_reduce($url_array, array(__CLASS__, "build_http_path"), "");
		//format associative array into HTTP query format
		$query = cr_post($queries_hash);
		//put together the full URL
		$full_url = API_HOST . "/" . self::get_base() . $path . "." . self::FORMAT. "?" . $query;
		return $full_url;
	}

	//required headers for proper api access
	private static function required_headers(){
		//builds required headers
		$header = array(
					"API-VERSION: "   . API_VERSION  ,
						"Authorization: " . API_AUTH_KEY ,
						"Accept: application/json");
		return $header;
	}
	
	// gets the base class of the call function to help construct the API URL
	private static function get_base(){
		return strtolower(get_called_class()); //forces subclasses to be named according to endpoint
	}


	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// PROTECTED FUNCTIONS >>>>>>>>>>>>>>>>>>>>
	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	//actually does the api request and returns the result
	protected static function make_get_request($url_array, $queries_hash = array()){
		//prepare to make the request
		$url = self::build_url($url_array, $queries_hash);

		//make the request
		$ch = curl_init(); 	//open curl
			curl_setopt($ch, CURLOPT_URL, $url); 								// Set so curl_exec returns the result rather than outputs it.
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 					
			curl_setopt($ch, CURLOPT_HTTPHEADER, self::required_headers()); 	// set headers
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, SSL_VERIFY_HOST);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, SSL_VERIFY_PEER);
			curl_setopt($ch, CURLOPT_VERBOSE, true);
							curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
							curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			$response = curl_exec($ch);					 						//make the request
					$curl_errno = curl_errno($ch);
					$curl_error = curl_error($ch);
					if ($curl_errno > 0) {
							echo "cURL Error ($curl_errno): $curl_error";
					}
		curl_close($ch); 	//close curl

		return $response;
	}
	
	//present in many but not all endpoints, protected for easy but optional exposure
	protected static function ids(){
		// set URL variables
		$endpoint = __FUNCTION__;
		// create the URL array
		// the host and namespace (agents) is automatic
		$url = array($endpoint);
		$response = self::make_get_request($url); //make the request
		return $response;
	}
	
	
	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	// PUBLIC FUNCTIONS >>>>>>>>>>>>>>>>>>>>>>>
	// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
	//all endpoints have this

	public static function show($id){
		// URL: <host>/<base_class>/$id.json
		// create the URL array
		// the host and namespace (agents) is automatic
		$url = array($id);
		$response = self::make_get_request($url); //make the request
		return $response;
	}
	
	//all endpoints have this
	public static function index(){
		// URL: <host>/<base_class>s.json
		// create the URL array
		// the host and namespace (agents) is automatic
		$url = array();
		$response = self::make_get_request($url); //make the request
		return $response;
	}
}
	
function cr_post($a,$b=0,$c=0){
	if (!is_array($a) || sizeof($a) == 0) return false;
	foreach ((array)$a as $k=>$v){
			if (!isset($v)) continue;
			if ($c) $k=$b."[]"; elseif (is_int($k)) $k=$b.$k;
			if (is_array($v)||is_object($v)) {
					$r[]=cr_post($v,$k,1);continue;
			}
			$r[]=urlencode($k)."=" .urlencode($v);    
	}
	return implode("&",$r);
}

// ******************************************************************
// ******************************************************************
// ******************************************************************
// MAKE HTTPS REQUESTS TO SPECIFIC ENDPOINTS
// ******************************************************************
// ******************************************************************
// ******************************************************************

	// ******************************************************************
	// AGENTS
	// ******************************************************************
	class Agents extends Api_Access {
		// URL: <host>/agents(.*).json

        public static function ids(){
            // URL: <host>/agents/ids.json?
            return parent::ids();
        }

        public static function names(){
            // URL: <host>/agents/names.json?
						$endpoint = __FUNCTION__;					//endpoint name from function
						$queries_hash = array('agent_ids' => Process_Data::$agent_ids);
            $url = array($endpoint); 					//the host and namespace (class name) is automatic
            $response = parent::make_get_request($url, $queries_hash); //make request
            return $response;
        }

        public static function statuses(){
            // URL: <host>/agents/statuses.json?
						$endpoint = __FUNCTION__;					//endpoint name from function
						$queries_hash = array('agent_ids' => Process_Data::$agent_ids);
            $url = array($endpoint); 					//the host and namespace (class name) is automatic
            $response = parent::make_get_request($url, $queries_hash); //make request
            return $response;
        }

        public static function availabilities($from, $to, $window_size){
            // URL: <host>/agents/availabilities.json?
            $endpoint = __FUNCTION__;					//endpoint name from function
            $queries_hash = array('from' => $from, 'to' => $to, 'window_size' => $window_size, 'agent_ids' => Process_Data::$agent_ids);
            $url = array($endpoint); 					//the host and namespace (class name) is automatic
            $response = parent::make_get_request($url, $queries_hash); //make request
            return $response;
        }
	}
	
	
	// ******************************************************************
	// NB_TARGETS
	// ******************************************************************
	class Nb_Targets extends Api_Access {
		// URL: <host>/nb_targets(.*).json

        public static function names(){
            // URL: <host>/nb_targets/names.json?
						$endpoint = __FUNCTION__;					//endpoint name from function
						$queries_hash = array('nb_target_ids' => Process_Data::$target_ids);
            $url = array($endpoint);  				    //the host and namespace (class name) is automatic
            $response = parent::make_get_request($url, $queries_hash); //make request
            return $response;
        }

		public static function ids(){
			// URL: <host>/nb_targets/ids.json?
			return parent::ids();
		}
	}

    // ******************************************************************
	// ACCESS_POINT_METRICS
	// ******************************************************************
	class Access_Point_Metrics extends Api_Access {
	    // URL: <host>/access_point_metrics(.*).json

        public static function index($from, $to, $agent_id){
            // URL: <host>/<base_class>.json
            // create the URL array
            // the host and namespace (agents) is automatic
            $queries_hash = array('from' => $from, 'to' => $to, 'agent_id' => $agent_id);
            $url = array();
            $response = self::make_get_request($url, $queries_hash); //make the request
            return $response;
        }

        public static function sample($from, $to, $agent_id, $cardinality=100){
            // URL: <host>/nb_alerts/open_alerts_at_intervals.json?
            $endpoint = __FUNCTION__;					//endpoint name from function
            $url = array($endpoint); 					//the host and namespace (class name) is automatic
            $queries_hash = array('from' => $from, 'to' => $to, 'agent_id' => $agent_id, 'cardinality' => $cardinality);

            $response = parent::make_get_request($url, $queries_hash); //make request
            return $response;
        }

	}
	
	
	// ******************************************************************
	// NB_ALERTS
	// ******************************************************************
	class Nb_Alerts extends Api_Access {
		// URL: <host>/nb_alerts(.*).json

		public static function open_alerts_at_intervals($from, $to, $window_size, $optional_queries=array()){
			// URL: <host>/nb_alerts/open_alerts_at_intervals.json?
			$endpoint = __FUNCTION__;					//endpoint name from function
			$url = array($endpoint); 					//the host and namespace (class name) is automatic
            $queries_hash = array('from' => $from, 'to' => $to, 'window_size' => $window_size);
            if(isset($optional_queries)){
                $queries_hash = array_merge($queries_hash, $optional_queries);
            }
			$response = parent::make_get_request($url, $queries_hash); //make request
			return $response;
		}
		
		public static function current_alerts($optional_queries=array()){
			// URL: <host>/nb_alerts/current_alerts.json?
			$endpoint = __FUNCTION__;					//endpoint name from function
			$url = array($endpoint); 					//the host and namespace (class name) is automatic
			$response = parent::make_get_request($url, $optional_queries); //make request
			return $response;
		}
	}

		
	// ******************************************************************
	// SCHEDULED NB TEST RESULTS
	// ******************************************************************
	class Scheduled_Nb_Test_Results extends Api_Access {
		// URL: <host>/scheduled_nb_test_results(.*).json
		// only index and show (inherited)

        public static function index($from, $to, $agent_id, $test_type_id, $group_all_results){
            // URL: <host>/<base_class>.json
            // create the URL array
            // the host and namespace (agents) is automatic
            $queries_hash = array('from' => $from, 'to' => $to, 'agent_id' => $agent_id, 'test_type_id' => $test_type_id, 'group_all_results' => $group_all_results);
            $url = array();
            $response = self::make_get_request($url, $queries_hash); //make the request
            return $response;
        }
	}

    // ******************************************************************
    // NB TEST STATISTICS
    // ******************************************************************
    class Nb_Test_Statistics extends Api_Access {
        // URL: <host>/nb_test_statistics(.*).json
        // only index and show (inherited)

        public static function index($from, $to, $nb_target_id, $test_type_id, $window_size, $grouping){
            // URL: <host>/<base_class>s.json
            // create the URL array
            // the host and namespace (agents) is automatic
            $url = array();
            $queries_hash = array('from' => $from, 'to' => $to, 'nb_target_id' => $nb_target_id, 'test_type_id' => $test_type_id, 'window_size' => $window_size, 'grouping' => $grouping);
						$response = self::make_get_request($url, $queries_hash); //make the request
            return $response;
        }
    }
?>
