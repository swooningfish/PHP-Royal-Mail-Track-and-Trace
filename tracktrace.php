<?php
//Royal Mail Track and Trace page scraper
//Rory Oldershaw 2013
//https://github.com/roryoldershaw

//Set some varialbes
$delivered = 0;
$signature = 0;
$deliveredID;
$signatureID;
$arr = array();

//set POST variables
$trackingNumber = "FI107380836GB";

$url = 'http://www.royalmail.com/trackdetails';

$postData = array(
'tracking_number' => $trackingNumber, 
'op' => "Track item",
'form_id' => "bt_tracked_track_trace_form"
);
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


$dom = new DomDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
$x = new DomXPath($dom);
$rows = $x->query('//*[@id="bt-tracked-track-trace-form"]/div/div/div/div[1]/table/tbody/tr');
        
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
        		if(preg_match('/^DELIVERED/', $status)){$delivered = 1; $deliveredID = $i;}
				if(preg_match('/^Signature/', $status)){$signature = 1; $signatureID = $i;}
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
        
if($signature == 1){
$arr = array('signatureURL' => 'http://www.royalmail.com/track-trace/pod-render/'.$trackingNumber.'') + $arr;
}else{
$arr = array('signatureURL' => '') + $arr;
}

$arr = array('trackingNumber' => $trackingNumber,'delivered' => $delivered, 'deliveredID' => $deliveredID, 'signature' => $signature) + $arr;

echo "<pre>";
print_r($arr);
echo "</pre>";
