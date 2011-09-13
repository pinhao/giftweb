<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';
require APPPATH.'/libraries/GIFTLib.php';

class gift extends REST_Controller {
	
	private $GIFTLib;
	
	function __construct() {
		parent::__construct();
		$this->GIFTLib = new GIFTLib("127.0.0.1", 12789, "anonymous");
	}
	
	/** api web methods **/
	
	/* function to test connectivity */
	function ping_get() {
		$this->response(array('ping' => 'pong'), 200);
	}
	
	/* get place details */
	function place_details_get() {
		//check for place id
		//retrive place details from db
	}
	
	/* get list of pictures for place */
	function place_pictures_get() {
		//check for place id
		//retrive place pictures from db
	}
	
	/* get list of places arround coordinates */
	function place_arround_get() {
		//check for coordinates and radius
		//retrive from db with stored procedure
	}
	
	function place_query_post() {
		//check for picture, coordinates, bearing
		//handle picture upload
		//get places arround coordinates and corresponding collections
		//query gift
		//check results for certainty
		//make picture place association for approval
		//return results
	}
	
	function collections_get() {
		$collections = $this->GIFTLib->getCollections();
		if ( !empty($collections) ) {
			$response = $this->build_response('collection', $collections);
			$this->response($response, 200);
		} else {
			$this->response(NULL, 400);
		}
	}
	
	function algorithms_get() {
		$algorithms = $this->GIFTLib->getAlgorithms();
		if ( !empty($algorithms) ) {
			$response = $this->build_response('algorithm', $algorithms);
			$this->response($response, 200);
		} else {
			$this->response(NULL, 400);
		}
	}
	
	function images_get() {
		$images = $this->GIFTLib->getImageSet();
		if ( !empty($images) ) {
			$response = $this->build_response('images', $images);
			$this->response($response, 200);
		} else {
			$this->response(NULL, 400);
		}				
	}
	
	function sessionid_get() {
		$this->response($this->GIFTLib->getSessionId(), 200);
	}
	
	/** internal methods **/
	
	private function build_response($type, $items) {
		$response = array();
		$response['response'] = array();
		$response['response']['type'] = $type;
		$response['response']['count'] = count($items);
		$response['response']['items'] = $items;
		return $response;
	}

}
