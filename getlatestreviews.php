<?php 
	$_asin = $_REQUEST["asin"];
	$_reviewByPageOrAll = $_REQUEST["collection"];
	$_urlToProcess = "https://www.amazon.com/review/product/".$_asin;

	/* curl to fetch the amazon HTML */
	function curlRequestResponse($url, $json = true) {
		$str     = ''; 
		$proxy   = array();
		$proxy[] = '95.67.47.94:53281';
		$proxy[] = '41.33.114.84:8080';
		$proxy[] = '14.136.246.173:3128';
		$proxy[] = '194.28.170.234:41258';
		$proxy[] = '59.106.222.74:60088';
		$asin    = $_REQUEST["asin"];
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 999);

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "amzn=PrqXm7zp/xFTG1r/Hf7Pug==&amzn-r=/review/product/$asin");

		if (isset($proxy)) {  
		    $proxy = $proxy[array_rand($proxy)];

		//proxy details
		//curl_setopt($ch, CURLOPT_PROXY, $proxy);
		//curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
		//curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		}
		//curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		//curl_setopt($ch, CURLOPT_ENCODING,  '');
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$response = curl_exec($ch);

		if ($humanReadableError = curl_errno($ch) || $response === false) {
			//echo '<pre>'; print_r($humanReadableError); exit;
			try{
				$httpResponseCode = '';
				if($humanReadableError != 1){
					$error_message = curl_strerror($humanReadableError);
					$httpResponseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$this->session->set_flashdata('msg_error', "cURL error ({$humanReadableError }):\n {$error_message}");
					curl_close($ch);
				}else{
					$this->session->set_flashdata('msg_error', 'magento site no loger exist !');
				}
			}  catch (Exception $e){
				$this->session->set_flashdata('msg_error', $e);
			}
		}
		curl_close($ch);
		return ($json) ? json_decode($response, true) : $response;
	}

	/* get total reviews for n asin */
	
	
	/* this function read the reviews from the requested links */
	function getPageReview($pageUrl) {
		
		$pageResult = array();
		
		$curl_scraped_page = curlRequestResponse($pageUrl, false);
		
		$productReviews= explode('id="cm_cr-review_list"',str_replace('<br/>', '',$curl_scraped_page));
		$productReviewsArray = explode('class="a-section celwidget"', $productReviews[1]);
		unset($productReviewsArray[0]);
		$_reviewArrayHTML = array();
		foreach($productReviewsArray as $_key => $reviewDetail){
			$_removeExtraText = explode('</span></div><div class="a-row review-comments',$reviewDetail);
			$_reviewArrayHTML[] = $_removeExtraText[0];
		}
		
		foreach($productReviewsArray as $key => $review) {
			
			$starsArray=explode('title="',$review);
			$reviewStars = explode('.',$starsArray[1]);

			//$_reviewDetailArray = array();
			
			//if((int)$reviewStars[0] > 3) {
				
				$pageResult[$key]['stars'] = (int)$reviewStars[0];
				
				$dateArray = explode('a-color-secondary review-date">on ', $review);
				$reviewDate = explode('</span>',$dateArray[1]);
				$pageResult[$key]['date'] = $reviewDate[0];
				
				$name = explode('class="a-size-base a-link-normal author"', $review);
				$reviewerName = explode('</a></span><span',$name[count($name)-1]);
				$reviewAuther = explode('">',$reviewerName[0]);
				$pageResult[$key]['auther'] = $reviewAuther[count($reviewAuther)-1];
				
				$summary = explode('data-hook="review-title"', $review);
				$summaryDataRemoveExtraText = explode('</a></div><div',$summary[count($summary)-1]);
				$summaryArray = explode('">',$summaryDataRemoveExtraText[0]);
				$pageResult[$key]['title'] = $summaryArray[count($summaryArray)-1];
				
				$commentHTML = explode('class="a-size-base review-text">', $review);
				$comment = explode('</span></div><div', $commentHTML[1]);
				$pageResult[$key]['review'] = $comment[0];
				
			//}
			
		}
		
		return $pageResult;
		
	}


	/* logic to get reviews */

	$lastPageNumber = 0;
	$_urlForNextPages = "";
	$_reviewsOfProduct = array();
	
	/* send request to multiple time to get review from amazon - prevent robot page because of continues request */
	for($requestNumber=0; $requestNumber<10 ;$requestNumber++) {
		
		/* curl request to read the page html */
		$curl_scraped_page = curlRequestResponse($_urlToProcess, false);
		//echo $_urlToProcess; print_r($curl_scraped_page); exit;
		
		/* get content between the pagination DIV */
		$pagesRemoveBefore = explode('id="cm_cr-pagination_bar"',$curl_scraped_page);
		$pageRemoveAfter = explode('</div>',$pagesRemoveBefore[1]);
		
		/* get the LI array to fetch data from last LI for the last page of review */
		$pageLIsIndexes = explode('data-reftag="cm_cr_arp_d_paging_btm"',$pageRemoveAfter[0]);
		
		/* get last LI - Dont include the NEXT Option LI */
		$lastPageHTML = explode('</a>',$pageLIsIndexes[count($pageLIsIndexes)-1]);
		
		/* last page of review */
		$higherValue = explode('">',$lastPageHTML[0]);
		$lastPageNumber = (int)$higherValue[count($higherValue)-1];
		/* URL for next review pages */
		$_urlForReviewPages = explode('<a href="',$higherValue[count($higherValue)-2]);
		//echo $lastPageNumber."<br>";
		//echo $_urlForReviewPages[count($_urlForReviewPages)-1]; exit;
		$_urlForNextPages = str_ireplace("=".$lastPageNumber, "=", $_urlForReviewPages[count($_urlForReviewPages)-1]);
		if($lastPageNumber > 0){
			break;
		}
	}
	
	$index = ($lastPageNumber > 0) ? $lastPageNumber : 1;
	
	//echo '<br>sku = '.$_asin.'<br>Pages = '.$index.'<br>'.$_urlForNextPages;
	
	for($i=0 ; $i < $index ; $i++) {
		/* send multiple request until not get HTML from amazon */
		for($requestNumber=0 ; $requestNumber < 10 ; $requestNumber++) {
			//if(!empty($_urlForNextPages)){
				$currentPageUrl = ($index < 2) ? $_urlToProcess : "https://www.amazon.com".$_urlForNextPages.($i+1);
				/* read reviews for requested page */
				//echo $currentPageUrl; exit;
				$_newresultpage = getPageReview($currentPageUrl);

				if($_reviewByPageOrAll == "page"){
					$_reviewsOfProduct[$i] = $_newresultpage;	
				}
				if($_reviewByPageOrAll == "all") {
					
					if(!empty($_newresultpage)){
						$_reviewsOfProduct = array_merge($_reviewsOfProduct,$_newresultpage);
					}
				}
				
				/* move to next page using break if reviews found for the requested pages */
				if(!empty($_newresultpage)){ 
					break;
				}
				sleep(5);
			//}
		}
		
	}


function recursive_array_search($needle,$haystack) {
    foreach($haystack as $key=>$value) {
        $current_key=$key;
        if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
            return $current_key;
        }
    }
    return false;
}

$latestReviewArr = array();

for($d=0; $d<=30; $d++)
{
	$checkDate = date('Y-m-d', strtotime("-$d days"));
	$checkDate  = date("F j, Y",strtotime($checkDate));
	if(is_numeric(recursive_array_search($checkDate, $_reviewsOfProduct)))
	{
		$latestReviewArr[] = $checkDate;
	}
	
}



echo count($latestReviewArr);exit;
	//echo json_encode($_reviewsOfProduct); exit;
	//echo "<pre>"; print_r($_reviewsOfProduct); exit;

	//echo count($_reviewsOfProduct);exit;

?>