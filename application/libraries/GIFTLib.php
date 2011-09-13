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
		
	private $sRequestTemplate;
	
	
	function __construct($server, $port, $username = DEFAULT_USERNAME, $sessionId = DEFAULT_SESSION) {
		$this->mServer = $server;
		$this->mPort = $port;
				
		$this->mUsername = $username;
		$this->mSessionId = $sessionId;
		
		$this->sRequestHeader = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>
<!DOCTYPE mrml SYSTEM \"http://www.mrml.net/specification/v1_0/MRML_v10.dtd\">
";
	}
	
	protected function connect() {
		$socket = fsockopen($this->mServer,$this->mPort, $errno, $errstr, 30);
		if (!$socket) {
			$this->mSocket = null;
			return false;
		}
		$this->mSocket = $socket;
		return true;
	}
	
	protected function disconnect() {
		if ( $this->mSocket != null ) {
			fclose($this->mSocket);
			$this->mSocket = null;
		}
			
	}
	
	protected function request($request) {
		if ( !$this->connect() ) return "";
		
		$request = $this->sRequestHeader.$request;
				
		fputs($this->mSocket, $request);
		
		$response = "";
		while ( ($buf = fgets($this->mSocket, 1024)) !== false )
			$response .= $buf;
			    
		$this->disconnect();
		
		return $response;
	}
	
	public function getCollections() {
	    if ( !isset($this->mCollections) ) {
			$request = "<mrml><get-collections/></mrml>";
			$response = $this->request($request);
			$mrml = new SimpleXMLElement($response);
			$this->mCollections = array();
			foreach ($mrml->xpath('//collection-list/collection') as $collection) {
				$collectionName = (string)$collection['collection-name'];
				$collectionId = (string)$collection['collection-id'];
				$collectionImageCount = (int)$collection['cui-number-of-images'];
				$this->mCollections[] = array('id'=>$collectionId, 'name'=>$collectionName, 'count'=>$collectionImageCount);
			}
		}
		return $this->mCollections;
	}
	
	public function getAlgorithms($collection = '') {
		if ( !isset($this->mAlgorithms) ||  $this->mAlgorithmsForCollection != $collection ) {
			$this->mAlgorithmsForCollection = $collection;
    		// gift ignores collection-id value bug?
        	$request = "<mrml><get-algorithms collection-id=\"$collection\"/></mrml>";
        	$response = $this->request($request);
        	$mrml = new SimpleXMLElement($response);
			$this->mAlgorithms = array();
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
	
	public function getSessionId() {
		if ( $this->mSessionId == DEFAULT_SESSION ) {
			// user-name e session-name just for looks
			$request = "<mrml session-id=\"dummy_session_identifier\">".
						"<open-session user-name=\"$this->mUsername\" session-name=\"$this->mSessionId\" />".
						"</mrml>";
        	$response = $this->request($request);
        	$mrml = new SimpleXMLElement($response);
        	$session = $mrml->xpath('//acknowledge-session-op');
        	if ( isset($session[0]) )
        		$this->mSessionId = (int)$session[0]['session-id'];
		}
		return $this->mSessionId;
	}
	
	public function getImageSet($collection = '', $algorithm = NULL, $resultSize = 10, $uploadedFile = '') {
		$this->getSessionId();
		$this->getAlgorithms($collection);
		
		if ( $algorithm === NULL && isset($this->mAlgorithms[0]) )
		{
			$algorithm = $this->mAlgorithms[0];
		}
		else
		{
			return array();
		}
		
		$requestFormat = sprintf("<mrml session-id=\"%1$s\">".
					"	<configure-session session-id=\"%1$s\">".
					"		<algorithm algorithm-id=\"%2$s\"".
					"		algorithm-type=\"%3$s\"".
					"		collection-id=\"%4$d\">".
					"		</algorithm>".
					"	</configure-session>".
					"	<query-step session-id=\"%1$s\"".
					"		result-size=\"%5$d\"".
					"		algorithm-id=\"%2$s\"".
					"		collection=\"%6$s\"".
					"	</query-step>"
					"</mrml>";
		$requestFormat = sprintf($format, $this->mSessionId, $algorithm['id'], $algorithm['type'], $collection, $resultSize, $collection);
		$response = $this->request($request);
		$mrml = new SimpleXMLElement($response);
		var_dump($mrml);
		return array();
	}
	
}
	/*
	//get info form server
	function GetInfo() {
	      global $xml,$mrmldtd,$allowUpload;
	      global $server,$port,$name;

	      //simple mrml request 
	      $GetInfo= $xml . $mrmldtd .
	            "<mrml session-id=\"dummy_session_identifier\">
	            <open-session user-name=\"$name\" session-name=\"charmer_default_session\" />
	            <get-collections/><get-algorithms/>
	            </mrml>";

	      //Make the first request
	      $socket=connection($server,$port);
	      $answer=request($socket,$GetInfo);
	      fclose($socket);                                

	      //Create a xml parser, Parse the answer and free the parser
	      $MRML_parser = xml_parser_create();
	      xml_set_element_handler($MRML_parser, "MRMLstart", "MRMLend");    
	      xml_parse($MRML_parser, $answer);   
	      xml_parser_free($MRML_parser);            

	            //show collections, algorithms and ...
	        echo "<center><font color=#ff0000 size=+2>Do not save this page (see <a href=http://viper.unige.ch/demo/noSave.html>why</a>)</font></center><br/><br/>  ";
	      ShowCollection();
	      ShowAlgorithm();
	            ShowNumberReturn();     
	            echo "Algorithm options:<br>";
	            ShowOptionAlgorithm();
	        if ($allowUpload == "yes")
	         ShowAddImage();
	        else 
	         echo "<br/><br/><font color=\"#ff0000\">Image uploading feature disabled</font>";
	            ShowButtons();
	}

}

//***************VARIABLES****************
//global variable
$Depth=0;
$Collection=array();
$NbCollection=-1;
$Algorithm=array();
$NbAlgorithm=-1;
$Property=array();
$NbProperty=-1;
$NbPropertyAlgo=array();
$Parent=array();

//***********SOCKET FUNCTIONS************
//Open a socket
function connection($server,$port) {
	$socket = fsockopen ($server,$port, &$errno, &$errstr, 30);
	if (!$socket)
		return null;
	return $socket;
}

//Make a request to the server and return the answer
function request($socket,$request) {
	fputs ($socket, $request);
	$answer="";
	while (!feof($socket)) {
		$buf = fgets($socket,1);
		$answer .=$buf;
	}
    return $answer;
}

//***********REQUEST FUNCTIONS************
function MakeRandom() {
	//global variables
      global $xml,$mrmldtd;
      global $Algorithm,$NbAlgorithm;     
      global $AlgorithmId,$CollectionId,$SessionId;
      global $server,$port,$Return;

      //find the Algorithm Type
      for ($i=0;$i<=$NbAlgorithm;$i++){
            if ($Algorithm[$i]["ALGORITHM-ID"]==$AlgorithmId) {$AlgorithmType=$Algorithm[$i]["ALGORITHM-TYPE"];}        
      }

      //The MRML request to fetch a random set of images
      $RandomRequest= $xml . $mrmldtd .
            "<mrml session-id=\"$SessionId\">
            <configure-session  session-id=\"$SessionId\">
                <algorithm  algorithm-id=\"$AlgorithmId\"  algorithm-type=\"$AlgorithmType\"  collection-id=\"$CollectionId\" >
                </algorithm>
            </configure-session>
            <query-step  session-id=\"$SessionId\" result-size=\"$Return\" algorithm-id=\"$AlgorithmId\" collection=\"$CollectionId\"/>
            </mrml>";


      //Make the request
      $socket=connection($server,$port);
      $answer=request($socket,$RandomRequest);
      fclose($socket);

      //Create a xml parser, Parse the answer and free the parser
      $MRML_parser = xml_parser_create();
      xml_set_element_handler($MRML_parser, "MRMLstart", "MRMLend");
      xml_parse($MRML_parser, $answer);
      xml_parser_free($MRML_parser);
}

//make a query 
function MakeQuery() {
      //global variables
      global $HTTP_POST_VARS,$HTTP_POST_FILES;
      global $xml,$mrmldtd,$ImagePath,$phppath;
      global $Algorithm,$NbAlgorithm,$ImgHeight,$ImgWidth;     
      global $AlgorithmId,$CollectionId,$SessionId,$OldCollectionId;
      global $server,$port,$Return,$url,$SERVER_NAME,$DOCUMENT_ROOT;
      global $Property,$NbProperty,$NbPropertyAlgo,$OldAlgorithm; 
      global $imageloc,$imagerel,$nbimage,$QueryImgNb,$QueryImgRelLoc;

      //get the upload file name
      $NameImgUpload=$HTTP_POST_FILES['UserImage']['name']; 

      //if there is a upload file...                  
      if ($NameImgUpload!="") {           
            //check if file exist
            if ($HTTP_POST_FILES['UserImage']['tmp_name']=="none") {
                    die("<br><h3>file upload error: $NameImgUpload</h3><br>");
            }
            
            //check file extension
            CheckExtension($NameImgUpload);
            
                  //move the file to $ImagePath directory
            $temp=explode(".",$NameImgUpload);
            $desti1=$ImagePath.$NameImgUpload;
            $desti2 = $ImagePath . $temp[0] . ".jpg";             
            if ($desti1==$desti2) {$desti2=$ImagePath . "Thumb_".$temp[0] . ".jpg";}
                                                            
            if (!move_uploaded_file($HTTP_POST_FILES['UserImage']['tmp_name'],$desti1)) {die ("error in transfert");}
            
            //convert image   to jpg with "convert"
            $command=escapeShellCmd("./convert -geometry $ImgWidthx$ImgHeight $desti1 $desti2");            
            system($command);
      }
      
      
      
      //check if the url and his extension is valid.
        if ($url!="") {             
                if (!@fopen($url,"rb")){
                  die("<br><h3>Bad url: $url</h3><br>");
            }
            
            //check file extension
            CheckExtension($url);               
            
            //copy image from url
            $urltmp=explode('/',$url);
            $urltmp2=explode('.',$urltmp[count($urltmp)-1]);
            $dest1=$ImagePath."tmp.".$urltmp2[1];
            $dest2 = $ImagePath . $urltmp2[0] . ".jpg";                                               
            if (!($fp = fopen($url,"r"))) die("Could not open src");          
            if (!($fp2 = fopen($dest1,"w"))) die("Could not open dest"); 
            while ($contents = fread( $fp,4096)) { 
                  fwrite( $fp2, $contents); 
            } 
            fclose($fp); 
            fclose($fp2); 
            
            //convert image   to jpg
            $command=escapeShellCmd("./convert -geometry $ImgWidthx$ImgHeight $dest1 $dest2");        
            system($command);
        }
            
      //find the right algorithm
      for ($i=0;$i<=$NbAlgorithm;$i++){
            if ($Algorithm[$i]["ALGORITHM-ID"]==$AlgorithmId) {
                  $AlgorithmType=$Algorithm[$i]["ALGORITHM-TYPE"];
                  $algonum=$i;
            }
      }
      
        $nbimage=0;
        $nbrel=0;         
        $nbthumb=0;
        //find image location,relevance and thumbnail location
      while (list ($key, $val) = each ($HTTP_POST_VARS)) {
            if (is_int(strpos($key,"image_"))){               
                  $imageloc[$nbimage]=$val;
                  $nbimage++;
            }     
            if (is_int(strpos($key,"rel_img_"))){           
                  $imagerel[$nbrel]=$val;
                  $nbrel++;               
            }     
            if (is_int(strpos($key,"thumb_img_"))){         
                  $imagethumb[$nbthumb]=$val;
                  $nbthumb++;             
            }
                        
      }
      
      //find the query image
      $QueryImgNb=0;          
      for ($i=0;$i<$nbimage;$i++){
                  if ($imagerel[$i]==-1 || $imagerel[$i]==1) {
                        $QueryImg[$QueryImgNb]=$imageloc[$i];
                        $QueryImgThumb[$QueryImgNb]=$imagethumb[$i];
                        $QueryImgRel[$QueryImgNb]=$imagerel[$i];                    
                        if ($imagerel[$i]==1) {                   
                                $QueryImgRelLoc[$QueryImg[$QueryImgNb]]=1;                                                                      
                        }
                        $QueryImgNb++;                      
                  }
      }

      //The MRML request
      $QueryRequest= $xml . $mrmldtd .
            "<mrml session-id=\"$SessionId\">
            <configure-session  session-id=\"$SessionId\">        
            <algorithm  algorithm-id=\"$AlgorithmId\"  algorithm-type=\"$AlgorithmType\"  collection-id=\"$CollectionId\" ";
            
            if ($OldAlgorithm==$algonum) {            
                  //add the property
                  for ($j=0;$j<$NbPropertyAlgo[$algonum];$j++){   
                        
                        $option="AlgoOption".$j;
                        global $$option;

                        $option2="NameAlgoOption".$j;
                        global $$option2; 
                        $sendname="";
                        if ($$option!="") {$sendname=$$option2;}                    
                                                            
                        if ($$option=="yes") {$sendvalue="false";}
                        if ($$option=="no") {$sendvalue="true";}
                        if ($$option=="value") {
                                $optionb="AlgoOptionb".$j;
                                global $$optionb;
                              $sendvalue=$$optionb;
                        }           
                        if (chop($sendname)!="") $QueryRequest .= $sendname . "=\"".$sendvalue."\" ";
                  }
            }
            
            $QueryRequest .= ">
            </algorithm>
            </configure-session>
                  <query-step  session-id=\"$SessionId\" result-size=\"$Return\" result-cutoff=\"0.0\" collection=\"$CollectionId\" algorithm-id=\"$AlgorithmId\">
                  <mpeg-7-qbe></mpeg-7-qbe>
                  <user-relevance-element-list>";            
                  //add the image location to the request
            for ($i=0;$i<$nbimage;$i++){
                  $QueryRequest .= "\n<user-relevance-element image-location=\"$imageloc[$i]\" thumbnail-location=\"\"  user-relevance=\"$imagerel[$i]\"/>";
            }                 
            //ad the image passed by url
            if ($url!="") {
                  $QueryRequest .= "\n<user-relevance-element image-location=\"$url\" thumbnail-location=\"\"  user-relevance=\"1\"/>";         
                  $QueryImg[$QueryImgNb]=$url;
                  $QueryImgThumb[$QueryImgNb]=$phppath.$dest2;
                  $QueryImgRel[$QueryImgNb]=1;
                  $QueryImgNb++;
            }
            //ad the image passed by file upload
            if ($NameImgUpload!="") {
                    $NameImgUploadLoc= $phppath.$desti1;          
                  $QueryRequest .= "\n<user-relevance-element image-location=\"$NameImgUploadLoc\" thumbnail-location=\"\"  user-relevance=\"1\"/>";        
                  $QueryImg[$QueryImgNb]=$NameImgUploadLoc;
                  $QueryImgThumb[$QueryImgNb]=$phppath.$desti2;
                  $QueryImgRel[$QueryImgNb]=1;
                  $QueryImgNb++;
            }

                  $QueryRequest .= "</user-relevance-element-list></query-step>
            </mrml>";                                             
                  
      //Display QueryImg                  
      ShowQueryImg($QueryImgNb,$QueryImg,$QueryImgThumb,$QueryImgRel);
      
      echo "<br><center><h2>QueryRequest: $QueryRequest</h2></center><br>";         
            
      echo "<center><h2>Result:</h2></center>";       
      

      //Make the request
      $socket=connection($server,$port);
      $answer=request($socket,$QueryRequest);
      fclose($socket);
      
      //Create a xml parser, Parse the answer and free the parser
      $MRML_parser = xml_parser_create();
      xml_set_element_handler($MRML_parser, "MRMLstart", "MRMLend");
      xml_parse($MRML_parser, $answer);
      xml_parser_free($MRML_parser);            

}

//***********PARSING FUNCTIONS************
function MRMLstart($parser, $name, $attrs) {
      global $Collection,$NbCollection;
      global $Algorithm,$NbAlgorithm;
      global $Property,$NbProperty,$NbPropertyAlgo;
      global $SessionId,$Submit,$Depth,$Parent;

      //parse MRML tag
      switch ($name) { 
            case "ACKNOWLEDGE-SESSION-OP":
                  //Get session id
                  if ($SessionId=="") {
                        $SessionId=$attrs["SESSION-ID"];       
                  }
                  
                  break;

            case "COLLECTION":
                  //Get Collection
                        $NbCollection++;
                  while (list ($key, $val) = each ($attrs)) {
                                    $Collection[$NbCollection][$key]=$val;
                  } 
                  break;

            case "ALGORITHM":
                  //Get alorithm
                  if ($attrs["ALGORITHM-TYPE"]!="") {
                              $NbProperty=-1;
                              $Depth=0;                           
                                    $NbAlgorithm++;
                        while (list ($key, $val) = each ($attrs)) { 
                                          $Algorithm[$NbAlgorithm][$key]=$val;
                        } 
                  }
                  break;

            case "PROPERTY-SHEET":
                        //Get alorithm property
                        if ($NbAlgorithm==-1) {$NbAlgorithm=0;}
                        $NbProperty++;
                        $NbPropertyAlgo[$NbAlgorithm]=$NbProperty;                        
                        while (list ($key, $val) = each ($attrs)) { 
                              $Property[$NbAlgorithm][$NbProperty][$key]=$val;                              
                        } 
                        
                        //save Depth and parent from the property
                        $Property[$NbAlgorithm][$NbProperty]["DEPTH"]=$Depth;                   
                        $Property[$NbAlgorithm][$NbProperty]["PARENT"]=$Parent[$Depth];                                                   
                        
                        //increase Depth
                        $Depth++;
                        
                        $Parent[$Depth]=$NbProperty;
                                                                        
                  break;

            case "QUERY-RESULT-ELEMENT":
                  //get image info
                  global $imageloc,$imagerel,$nbimage,$QueryImgRelLoc;              
                  $ImgLoc=$attrs["IMAGE-LOCATION"];
                  $ImageThumb=$attrs["THUMBNAIL-LOCATION"];
                  $Similarity=$attrs["CALCULATED-SIMILARITY"];  
                  //echo "ImgLoc: -$ImgLoc- , ImageThumb: -$ImageThumb- <br>";
                  if ($Submit=="Random") {ShowImage($ImgLoc,$ImageThumb,$Similarity,0);}
                  if ($Submit=="Query" && $QueryImgRelLoc[$ImgLoc]=="") {ShowImage($ImgLoc,$ImageThumb,$Similarity,0);}
                  if ($Submit=="Query" && $QueryImgRelLoc[$ImgLoc]!="") {ShowImage($ImgLoc,$ImageThumb,$Similarity,1);}
 
            break;
      }
}

function MRMLend($parser, $name) {
      global $Depth;    
      switch ($name) { 
            case "PROPERTY-SHEET":        
                  //decrease depth
                  $Depth--;   
                  break;
    }
} */