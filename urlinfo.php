<?php
/*
    $accessKeyId - Key,

    $secretAccessKey - Secret,

    $site - Valid Site Url,

    $startDate - Start date for results. A date within the last 4 years in format YYYYMMDD,

    $range - Number of days to return. Note that the response document may contain fewer results than this maximum if data is not available. Default value is '31'. Maximum value is '31',

    $history - Result in the Array as following Format
                    Array
                        (
                            [Date] => 2018-05-04
                            [Rank] => 1
                            [PageViews] => Array
                                (
                                    [PerMillion] => 105100
                                    [PerUser] => 7.83
                                )

                            [Reach] => Array
                                (
                                    [PerMillion] => 497400
                                )

                        )

*/
$accessKeyId = 'AKIAJ2NS2ZSYTH2IEH7A';
$secretAccessKey = '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';
$site = 'https://google.com';
$startDate = 20180501;
$range = 5;
$urlInfo = new UrlInfo($accessKeyId, $secretAccessKey, $site,$startDate,$range);
$history = $urlInfo->getUrlInfo();
echo "<pre>";
print_r($history);



/**
 * Makes a request to AWIS for site info.
 */
class UrlInfo {

    protected static $ActionName        = 'TrafficHistory';
    protected static $ResponseGroupName = 'History';
    protected static $ServiceHost      = 'awis.amazonaws.com';
    protected static $ServiceEndpoint  = 'awis.us-west-1.amazonaws.com';
    protected static $Range         = 10;
    protected static $StartDate          = 20180501;
    protected static $SigVersion        = '2';
    protected static $HashAlgorithm     = 'HmacSHA256';
    protected static $ServiceURI = "/api";
    protected static $ServiceRegion = "us-west-1";
    protected static $ServiceName = "awis";


    public function UrlInfo($accessKeyId, $secretAccessKey, $site,$startDate,$range) {
        $this->accessKeyId = $accessKeyId;
        $this->secretAccessKey = $secretAccessKey;
        $this->site = $site;
        Self::$Range = $range;
        Self::$StartDate = $startDate;
        $now = time();
        $this->amzDate = gmdate("Ymd\THis\Z", $now);
        $this->dateStamp = gmdate("Ymd", $now);

    }

    /**
     * Get site info from AWIS.
     */
    public function getUrlInfo() {
        $canonicalQuery = $this->buildQueryParams();
        $canonicalHeaders =  $this->buildHeaders(true);
        $signedHeaders = $this->buildHeaders(false);
        $payloadHash = hash('sha256', "");
        $canonicalRequest = "GET" . "\n" . self::$ServiceURI . "\n" . $canonicalQuery . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = $this->dateStamp . "/" . self::$ServiceRegion . "/" . self::$ServiceName . "/" . "aws4_request";
        $stringToSign = $algorithm . "\n" .  $this->amzDate . "\n" .  $credentialScope . "\n" .  hash('sha256', $canonicalRequest);
        $signingKey = $this->getSignatureKey();
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorizationHeader = $algorithm . ' ' . 'Credential=' . $this->accessKeyId . '/' . $credentialScope . ', ' .  'SignedHeaders=' . $signedHeaders . ', ' . 'Signature=' . $signature;

        $url = 'https://' . self::$ServiceHost . self::$ServiceURI . '?' . $canonicalQuery;
        $ret = self::makeRequest($url, $authorizationHeader);
        // echo "\nResults for " . $this->site .":\n\n";
        // echo $ret;
        // die();
        return self::parseResponse($ret);
    }

    protected function sign($key, $msg) {
        return hash_hmac('sha256', $msg, $key, true);
    }

    protected function getSignatureKey() {
        $kSecret = 'AWS4' . $this->secretAccessKey;
        $kDate = $this->sign($kSecret, $this->dateStamp);
        $kRegion = $this->sign($kDate, self::$ServiceRegion);
        $kService = $this->sign($kRegion, self::$ServiceName);
        $kSigning = $this->sign($kService, 'aws4_request');
        return $kSigning;
    }

    /**
     * Builds headers for the request to AWIS.
     * @return String headers for the request
     */
    protected function buildHeaders($list) {
        $params = array(
            'host'            => self::$ServiceEndpoint,
            'x-amz-date'      => $this->amzDate
        );
        ksort($params);
        $keyvalue = array();
        foreach($params as $k => $v) {
            if ($list)
              $keyvalue[] = $k . ':' . $v;
            else {
              $keyvalue[] = $k;
            }
        }
        return ($list) ? implode("\n",$keyvalue) . "\n" : implode(';',$keyvalue) ;
    }

    /**
     * Builds query parameters for the request to AWIS.
     * Parameter names will be in alphabetical order and
     * parameter values will be urlencoded per RFC 3986.
     * @return String query parameters for the request
     */
    protected function buildQueryParams() {
        $params = array(
            'Action'            => self::$ActionName,
            'Range'             => self::$Range,
            'ResponseGroup'     => self::$ResponseGroupName,
            'Start'             => self::$StartDate,
            'Url'               => $this->site
        );
        ksort($params);
        $keyvalue = array();
        foreach($params as $k => $v) {
            $keyvalue[] = $k . '=' . rawurlencode($v);
        }
        return implode('&',$keyvalue);
    }

    /**
     * Makes request to AWIS
     * @param String $url   URL to make request to
     * @param String authorizationHeader  Authorization string
     * @return String       Result of request
     */
    protected function makeRequest($url, $authorizationHeader) {
        // echo "\nMaking request to:\n$url\n";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Accept: application/xml',
          'Content-Type: application/xml',
          'X-Amz-Date: ' . $this->amzDate,
          'Authorization: ' . $authorizationHeader
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        // echo "<pre>";
        // print_r($ch);
        // die($ch);
        return $result;
    }

    /**
     * Parses XML response from AWIS and displays selected data
     * @param String $response    xml response from AWIS
     */
    public static function parseResponse($response) {
        // echo $response;
        // die();
        $result = [];
        $xml = new SimpleXMLElement($response,LIBXML_ERR_ERROR,false,'http://awis.amazonaws.com/doc/2005-07-11');
        $data = $xml->Response->TrafficHistoryResult->Alexa->TrafficHistory->HistoricalData->Data;
        // echo $data;
        // die();
        if(!empty($data)){
            foreach ($data as $d) {
                // echo $d;
                // die();
                $temp = [];
                $temp['Date'] = (string) $d->Date;
                $temp['Rank'] = (string) $d->Rank;
                $temp['PageViews']['PerMillion'] = (string) $d->PageViews->PerMillion;
                $temp['PageViews']['PerUser'] = (string) $d->PageViews->PerUser;
                $temp['Reach']['PerMillion'] = (string) $d->Reach->PerMillion;
                array_push($result,$temp);

            }
        }
        return $result;
    }

}



?>
