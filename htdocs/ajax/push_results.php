<?php
/*
 * @iLabAfrica
 * Pushes results back to sanitas
 */
include("../includes/db_lib.php");

$test_id = $_REQUEST['test_id'];
$api_key = "0QLUIWX3R";
$sanitas_inbound_url = "http://192.168.1.9:8888/sanitas/bliss/notify?api_key=".$api_key;
$error_log_path ="/var/www/BLIS/htdocs/logs/blis.api.error.log";


$lab_numbers = API::getTestLabNoToPush();


foreach ($lab_numbers as $lab_no){
	$lab_request_no = $lab_no['labNo'];
	$result_ent = $lab_no['result'];
	$test = Test::getByExternalLabno($lab_request_no);
	$specimen_id = $test->specimenId;
	$specimen = get_specimen_by_id($specimen_id);
	$patient = get_patient_by_id($specimen->patientId);
	
	#Test Result (Strip unecessary characters)
	$result = str_replace("<br>","",$test->decodeResult());
	$result = str_replace("&nbsp;","",$result);
	$result = str_replace("<b>","",$result);
	$result = str_replace("</b>","",$result);
	#Time Stamp
	$time_stamp = date("Y-m-d H:i:s");
	#user
	$emr_user_id = get_emr_user_id($_SESSION['user_id']);
	if ($emr_user_id ==null)$emr_user_id="59";
	
	$user_name = get_username_by_id($_SESSION['user_id']);
	
	if ($lab_no['system_id'] == "sanitas")
	{
		$json_string ='{"labNo": '.$test->external_lab_no.',"requestingClinician": '.$emr_user_id.',"result": '.$result.'}';
		
		/*
		 * Send POST request with HttpRequest
		 */
		$r = new HttpRequest($sanitas_inbound_url, HttpRequest::METH_POST);
		$r->addPostFields(array('labResult' => $json_string));
		if($specimen->external_lab_no!=NULL){
			try {
				
				$response = $r->send()->getBody();
				
			} catch (HttpException $ex) {
				
				error_log("\n".$time_stamp.": HTTP Exception: ======>".$ex, 3, $error_log_path);
				
			}
		}
	
		if($response=="Test updated"){
			
			API::updateExternalLabRequestSentStatus($lab_no['labNo'], 1);
			
		}else if($response!="Test updated"){
			
				error_log("\n".$time_stamp.": HTTP Response Exception: ======>".$ex, 3, $error_log_path);
				
		}
	}
	else if ($lab_no['system_id'] == "medboss"){
		$server = '192.168.6.4';
		//$server = '192.168.184.121:1432';
		// Connect to MSSQL
		$link = mssql_connect($server, 'kapsabetadmin', 'kapsabet');
		echo "<html><body>";
		if (!$link)
		{
			error_log("\n".$time_stamp.": MSSQL Connection Error: ======>".mssql_get_last_message(), 3, $error_log_path);
		
		}
		
		if (!mssql_select_db('[Kapsabet]', $link)){
			
			error_log("\n".$time_stamp.": MSSQL Database Selection Error: ======>".mssql_get_last_message(), 3, $error_log_path);
		
		}
		$lab_request_no = intval($lab_request_no);
		$query = mssql_query("INSERT INTO 
				BlissLabResults (RequestID,OfferedBy,DateOffered, TimeOffered, TestResults) 
				VALUES ('$lab_request_no','$user_name','$time_stamp','$time_stamp','$result_ent')
				");
		
		if (!$query) {
			
			error_log("\n".$time_stamp.": MSSQL Query Error: ======>".mssql_get_last_message(), 3, $error_log_path);
			
		}else {
			
			API::updateExternalLabRequestSentStatus($lab_request_no, 1);

		}

		mssql_close($link);
	}
}

/*
 * Sent POST request as json with curl
*/
/*$ch = 	curl_init($Sanitas_inbound_url);
 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Content-Length: ' . strlen($data_string))
);
$result = curl_exec($ch);

error_log("\nHTTP rsponse ================>".$result, 3, "/home/royrutto/Desktop/my.error.log");
*/

?>