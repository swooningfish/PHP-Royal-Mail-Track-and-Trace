<?php
//Royal Mail Track and Trace page scraper
//Rory Oldershaw 2013
//https://github.com/roryoldershaw
//This program is free software: you can redistribute it and/or modify
//it under the terms of the GNU General Public License as published by
//the Free Software Foundation, either version 3 of the License, or
//(at your option) any later version.

//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

//You should have received a copy of the GNU General Public License
//along with this program.  If not, see <http://www.gnu.org/licenses/>.

//For a demo you can use FI107380836GB
if(isset($_GET['tn'])){
	$output = TrackTraceQuery($trackingNumber = $_GET['tn']);
}elseif(isset($_POST['tn'])){
	$output = TrackTraceQuery($_POST['tn']);
}else{
	$output = array('trackingNumber' => null, 'response' => 0, 'errorMsg' => 'No tracking number provided.');
}

//Echo the output
echo json_encode($output, JSON_UNESCAPED_SLASHES);

function CurlPost($url, $postData){
	//start cURL
	$ch = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_POST, true);
	curl_setopt ($ch, CURLOPT_POSTFIELDS, $postData);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	$html = curl_exec($ch);

	//close cURL
	curl_close($ch);

	return $html;
}

function TrackTraceQuery($trackingNumber){
	$delivered = 0;
	$signature = 0;
	$deliveredID; //The arrayID of the record mentioning that the parcel has been delivered.
	$signatureID; //The arrayID of the record mentioning a signature has been taken.
	$trackRecords = array(); //Array for storing the track records
	$output = array(); //Array for the final output

	//Make cURL request for tracking details
	$trackdetails = CurlPost('http://www.royalmail.com/trackdetails', array(
		'tracking_number' => $trackingNumber, 
		'op' => "Track item",
		'form_id' => "bt_tracked_track_trace_form"
	));
	
	//Start the page scraping
	$dom = new DomDocument();
	libxml_use_internal_errors(true);
	$dom->loadHTML($trackdetails);
	$x = new DomXPath($dom);
	$rows = $x->query('//*[@id="bt-tracked-track-trace-form"]/div/div/div/div[1]/table/tbody/tr');

	if($rows->length == 0){
		//No data returned
		$error = $x->query('//*[@id="bt-tracked-track-trace-form"]/div/div/div/div[1]/div[5]/text()');
		$output = array('trackingNumber' => $trackingNumber, 'response' => 0, 'errorMsg' => $error->item(0)->nodeValue);
	}else{
		//Loops to build arrays with records.
		$i = 0;
		foreach ($rows as $row) {
			$td = $x->query('td', $row);
			$i2=0;	
			foreach ($td as $value) {
				switch($i2){
					case 0:
						$date = $value->nodeValue;
						break;
					case 1:
						$time = $value->nodeValue;
						break;
					case 2:
						$status = $value->nodeValue;
						if(preg_match('/delivered/i', $status)){$delivered = 1; $deliveredID = $i;}
						if(preg_match('/signature/i', $status)){$signature = 1; $signatureID = $i;}
					break;
					case 3:
						$trackPoint = $value->nodeValue;
					break;
				}
				$i2++;
			}
			array_push($trackRecords, array("id" => $i ,"date" => $date, "time" => $time, "status" => $status, "trackPoint" => $trackPoint));
			$i++;
		}

		//Adding some extras to the output
		if($signature == 1){
			//It's much more awkward to get the printed name
			//We basically have to simulate user input step by step as you would do it on the website.
			$tokenHTML = CurlPost('http://www.royalmail.com/track-trace', null);
			$tokenDOM = new DomDocument();
			$tokenDOM->loadHTML($tokenHTML);
			$x = new DomXPath($tokenDOM);
			$tnt_token = $x->query('//input[@type="hidden" and @name="tnt_time_token" and position() = 1]/@value');
			$tnt_time_token = $tnt_token->item(0)->value;
			$form_build = $x->query('//*[@id="track-trace-request-form"]/div/div/div[2]/div/input[@name="form_build_id"]/@value');
			$form_build_id = $form_build->item(0)->value;


			$tokenHTML = CurlPost('http://www.royalmail.com/track-trace', array(
				'track_id' => $trackingNumber, 
				'op' => "Track",
				'tnt_time_token' => $tnt_time_token,
				'form_build_id' => $form_build_id,
				'form_id' => "track_trace_request_form"
			));
			$tokenDOM = new DomDocument();
			$tokenDOM->loadHTML($tokenHTML);
			$x = new DomXPath($tokenDOM);
			$tnt_token = $x->query('//input[@type="hidden" and @name="tnt_time_token" and position() = 1]/@value');
			$tnt_time_token = $tnt_token->item(0)->value;
			$form_build = $x->query('//*[@id="track-trace-request-form"]/div/div/div[2]/div/input[@name="form_build_id"]/@value');
			$form_build_id = $form_build->item(0)->value;

			//Make cURL request for signature details
			$signby = CurlPost('http://www.royalmail.com/track-trace', array(
				'track_id' => $trackingNumber, 
				'op' => "View Proof of Delivery",
				'tnt_time_token' => $tnt_time_token,
				'form_build_id' => $form_build_id,
				'form_id' => "track_trace_request_form"
			));
			//Get the name
			$dom = new DomDocument();
			$dom->loadHTML($signby);
			$x = new DomXPath($dom);
			$signby = $x->query('//*[@id="track-trace-request-form"]/div/div/div[2]/div/div[1]/div/p[1]/span[1]/text()');
		
			//Gen Signature URL
			$output = array('signatureURL' => 'http://www.royalmail.com/track-trace/pod-render/'.$trackingNumber.'', 'printedName' => str_replace(" ", '', $signby->item(0)->nodeValue)) + $output;
		}

		$output = array('trackingNumber' => $trackingNumber, 'response' => 1, 'delivered' => $delivered, 'deliveredID' => $deliveredID, 'signature' => $signature, 'signatureID' => $signatureID) + $output + array('trackRecords' => $trackRecords);
	}
	return $output;
}