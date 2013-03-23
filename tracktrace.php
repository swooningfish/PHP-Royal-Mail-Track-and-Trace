<?php
//Royal Mail Track and Trace page scraper
//Rory Oldershaw 2013
//https://github.com/roryoldershaw

//Set some variables
$delivered = 0;
$signature = 0;
$deliveredID; //The arrayID of the record mentioning that the parcel has been delivered.
$signatureID; //The arrayID of the record mentioning a signature has been taken.
$arr = array(); //Array for storing the results

$trackingNumber = "FI107380836GB";

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

//Make cURL request for tracking details
$trackdetails = CurlPost('http://www.royalmail.com/trackdetails',
array(
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
    array_push($arr, array("date" => $date, "time" => $time, "status" => $status, "trackPoint" => $trackPoint));
    $i++;	
}

//Adding some extras to the output
if($signature == 1){
	//Gen Signature URL
	$arr = array('signatureURL' => 'http://www.royalmail.com/track-trace/pod-render/'.$trackingNumber.'') + $arr;
}else{
	$arr = array('signatureURL' => '') + $arr;
}

$arr = array('trackingNumber' => $trackingNumber,'delivered' => $delivered, 'deliveredID' => $deliveredID, 'signature' => $signature, 'signatureID' => $signatureID) + $arr;

//Echo out result
echo "<pre>";
print_r($arr);
echo "</pre>";
