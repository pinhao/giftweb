<?

define('DEFAULT_USERNAME','anonymous');
define('DEFAULT_SESSION','default_session');

class GIFTLib {
	
	protected $mServer;
	protected $mPort;
	protected $mSocket;
	protected $mUsername;
	protected $mSessionId;
	protected $mCollections;
	protected $mAlgorithms;
	protected $mAlgorithmsForCollection;
	protected $mLastErrors;
		
	
	
	function __construct($server, $port, $username = DEFAULT_USERNAME, $sessionId = DEFAULT_SESSION) {
		$this->mServer = $server;
		$this->mPort = $port;
				
		$this->mUsername = $username;
		$this->mSessionId = $sessionId;
		$this->mLastErrors = array();
		
		$this->sRequestHeader = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>'.
								'<!DOCTYPE mrml SYSTEM "http://www.mrml.net/specification/v1_0/MRML_v10.dtd">';
	}
	
	protected function connect() {
		$socket = fsockopen($this->mServer,$this->mPort, $errno, $errstr, 30);
		if (!$socket) {
			$this->mSocket = null;
			$this->mLastErrors = array(array('msg'=>'Unable to create to socket'));
			return FALSE;
		}
		$this->mSocket = $socket;
		return TRUE;
	}
	
	protected function disconnect() {
		if ( $this->mSocket != null ) {
			fclose($this->mSocket);
			$this->mSocket = null;
		}		
	}
	
	protected function request($request) {
		if ( !$this->connect() ) { 
			$this->mLastErrors = array(array('msg'=>'Unable to connect to server'));
			return FALSE;
		}
		
		$request = $this->sRequestHeader.$request;
				
		if ( fwrite($this->mSocket, $request) === FALSE ) {
			$this->mLastErrors = array(array('msg'=>'Error while writing to server'));
			$this->disconnect();
			return FALSE;
		}
		
		$response = "";
		while ( ($buf = fgets($this->mSocket, 8192)) !== FALSE )
			$response .= $buf;
			
		if ( !feof($this->mSocket) ) {
			$this->mLastErrors = array(array('msg'=>'End of File error'));
			$this->disconnect();
			return FALSE;
		}
			    
		$this->disconnect();
		return $response;
	}
	
	protected function didFindErrorsInResponse($mrml) {
		$this->mLastErrors = array();
		$errorsFound = FALSE;
		if ( empty($mrml) )
			return TRUE;
		foreach ($mrml->xpath('//error') as $error) {
			$errorMessage = (string)$error['message'];
			$this->mLastErrors[] = array('msg' => $errorMessage);
			$errorsFound = TRUE;
		}
		return $errorsFound;
	}
	
	public function getLastErrors() {
		return $this->mLastErrors;
	}
	
	public function getCollections() {
	    if ( !isset($this->mCollections) ) {
			$request = "<mrml><get-collections/></mrml>";
			$response = $this->request($request);
			if ( $response === FALSE )
				return FALSE;
			$mrml = new SimpleXMLElement($response);
			$this->mCollections = array();
			if ( $this->didFindErrorsInResponse($mrml) )
				return FALSE;
			foreach ($mrml->xpath('//collection-list/collection') as $collection) {
				$collectionName = (string)$collection['collection-name'];
				$collectionId = (string)$collection['collection-id'];
				$collectionImageCount = (int)$collection['cui-number-of-images'];
				$this->mCollections[] = array('id'=>$collectionId, 'name'=>$collectionName, 'count'=>$collectionImageCount);
			}
		}
		return $this->mCollections;
	}
	
	public function getAlgorithms($collection = NULL) {
		if ( !isset($this->mAlgorithms) ||  $this->mAlgorithmsForCollection != $collection ) {
			$this->mAlgorithmsForCollection = $collection;
    		// gift ignores collection-id value bug?
        	$request = "<mrml><get-algorithms collection-id=\"$collection\"/></mrml>";
        	$response = $this->request($request);
			if ( $response === FALSE )
				return FALSE;
        	$mrml = new SimpleXMLElement($response);
			$this->mAlgorithms = array();
			if ( $this->didFindErrorsInResponse($mrml) )
				return FALSE;			
        	foreach ($mrml->xpath('//algorithm-list/algorithm') as $algorithm) {
        		$algorithmName = (string)$algorithm['algorithm-name'];
        		$algorithmId = (string)$algorithm['algorithm-id'];
				$algorithmType = (string)$algorithm['algorithm-type'];
				$collectionId = (string)$algorithm['collection-id'];
        		$this->mAlgorithms[] = array('collection'=>$collectionId, 'id'=>$algorithmId, 'name'=>$algorithmName, 'type'=>$algorithmType);
        	}
        }
        return $this->mAlgorithms;
    }
	
	public function getSessionId($noCache = FALSE) {
		if ( $this->mSessionId == DEFAULT_SESSION || $noCache === TRUE) {
			// user-name e session-name just for looks
			$request = "<mrml session-id=\"dummy_session_identifier\">".
						"<open-session user-name=\"$this->mUsername\" session-name=\"$this->mSessionId\" />".
						"</mrml>";
        	$response = $this->request($request);
			if ( $response === FALSE )
				return FALSE;
        	$mrml = new SimpleXMLElement($response);
			if ( $this->didFindErrorsInResponse($mrml) )
				return FALSE;
        	$session = $mrml->xpath('//acknowledge-session-op');
			if ( !isset($session[0]) || !isset($session[0]['session-id']) ) {
				$this->mLastErrors = array(array('msg'=>'Unable to create session'));
				return FALSE;
			}
			
        	$this->mSessionId = (int)$session[0]['session-id'];
		}
		return $this->mSessionId;
	}
	
	public function setSessionId($sessionId) {
			$requestFormat = '<mrml session-id="%s"><get-server-properties/></mrml>';
			$request = sprintf($requestFormat, $sessionId);
			$response = $this->request($request);
			if ( $response === FALSE )
				return FALSE;
			$mrml = new SimpleXMLElement($response);
			if ( $this->didFindErrorsInResponse($mrml) )
				return FALSE;
			$this->mSessionId = $sessionId;
			return TRUE;	
	}
	
	public function getImageSet($collection = NULL, $algorithm = NULL, $inputFilePath = NULL, $resultSize = 10, $minSimilarity = 0.0) {
		
		// default to first collection
		if ( $collection === NULL ) {
			if ( $this->getCollections() === FALSE )
				return FALSE;
			if ( !isset($this->mCollections) || !isset($this->mCollections[0]) ) {
				$this->mLastErrors = array(array('msg'=>'No collection found'));
				return FALSE;
			}
			$collection = $this->mCollections[0];
		}
		
		// default to first algorithm
		if ( $algorithm === NULL ) {
			if ( $this->getAlgorithms() === FALSE )
				return FALSE;
			if ( !isset($this->mAlgorithms) || !isset($this->mAlgorithms[0]) ) {
				$this->mLastErrors = array(array('msg'=>'No algorithms found'));
				return FALSE;
			}			
			$algorithm = $this->mAlgorithms[0];
		}
		
		// check sessionId
		if ( $this->getSessionId() === FALSE ) {
			
		}
		
		// validate arguments
		if ( $collection === NULL || $algorithm === NULL || $resultSize < 0 || $minSimilarity < 0.0 || $minSimilarity > 1.0) {
			return FALSE;
		}
		
		if ( $resultSize == 0 )
			return array();
		
		$fileQuery = '';
		if ( $inputFilePath !== NULL ) {
			$inputFilePath = realpath($inputFilePath);
			if ( is_file($inputFilePath) === TRUE ) {
				$fileQueryFormat = '<user-relevance-element-list>'.
							'<user-relevance-element image-location="file:%s" thumbnail-location=""  user-relevance="1"/>'.
							'</user-relevance-element-list>';
				$fileQuery = sprintf($fileQueryFormat, $inputFilePath);
							
			}
		}
		
		$requestFormat = '<mrml session-id="%1$s">'.
					'<configure-session session-id="%1$s">'.
					'<algorithm algorithm-id="%2$s" algorithm-type="%3$s" collection-id="%4$s"></algorithm>'.
					'</configure-session>'.
					'<query-step session-id="%1$s" result-size="%5$d" algorithm-id="%2$s" collection="%4$s">%6$s</query-step>'.
					'</mrml>';
		
		$request = sprintf($requestFormat, $this->mSessionId, $algorithm['id'], $algorithm['type'], $collection['id'], $resultSize, $fileQuery);
		$response = $this->request($request);
		if ( $response === FALSE )
			return FALSE;
		$mrml = new SimpleXMLElement($response);
		$imageSet = array();
		if ( $this->didFindErrorsInResponse($mrml) )
			return FALSE;
    	foreach ($mrml->xpath('//query-result-element-list/query-result-element') as $image) {
    		$similarity = (string)$image['calculated-similarity'];
			if ( $similarity >= $minSimilarity ) {
    			$location = (string)$image['image-location'];
				$thumbnail = (string)$image['thumbnail-location'];
    			$imageSet[] = array('calculated-similarity'=>$similarity, 'image-location'=>$location, 'thumbnail-location'=>$thumbnail);
			}
    	}		
		return $imageSet;
	}
	
}