<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';
require APPPATH.'/libraries/GIFTLib.php';

class cibr extends REST_Controller {
	
	private $GIFTLib;
	private $rootWebPath;
	
	function __construct() {
		parent::__construct();
		
		//$this->load->model('Location_model');
		//$this->load->model('Place_model');
		//$this->load->model('Picture_model');
		
		$this->config->load('gift');
		$host = $this->config->item('gift_host');
		$port = $this->config->item('gift_port');
		$this->GIFTLib = new GIFTLib($host, $port);
		
		$this->rootWebPath = rtrim(realpath('.'), '/').'/';
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
	
	/** internal methods **/
	
	private function translate_path_to_url($images) {
		$baseurl = $this->config->item('base_url');
		$filePathPatern = "#^(file:)?$this->rootWebPath#";
		foreach($images as $k => $image) {
			$images[$k]['image-location'] = preg_replace($filePathPatern, $baseurl, $image['image-location']);
			$images[$k]['thumbnail-location'] = preg_replace($filePathPatern, $baseurl, $image['thumbnail-location']);
		}
		return $images;
	}
	
	private function validateSessionId() {
		if ( ($sid = $this->input->get_post('session-id')) !== FALSE) { 
			if ( $this->GIFTLib->setSessionId($sid) === FALSE ) {
				$this->errorResponse($this->GIFTLib->getLastErrors());
			}
		}
	}
	
	private function errorResponse( $msgs = NULL, $code = 400) {
		$this->response($this->build_response('error', $msgs), $code);
	}
	
	private function build_response($type, $items) {
		$response = array();
		$response['response'] = array();
		$response['response']['session-id'] = $this->GIFTLib->getSessionId();
		if ( !empty($type) && !empty($items) ) {
			$response['response']['type'] = $type;
			$response['response']['count'] = count($items);
			$response['response']['items'] = $items;
		}
		return $response;
	}
}