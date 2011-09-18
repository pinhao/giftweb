<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 
 * GIFT
 *
 * Class for raw interaction with  gift server
 *
 */
require APPPATH.'/libraries/REST_Controller.php';
require APPPATH.'/libraries/GIFTLib.php';

class gift extends REST_Controller {
	
	private $GIFTLib;
	private $rootWebPath;
	
	function __construct() {
		parent::__construct();
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
	
	function collections_get() {
		$this->validateSessionId();
		$collections = $this->GIFTLib->getCollections();
		if ( $collections === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		$response = $this->build_response('collection', $collections);
		$this->response($response, 200);
	}
	
	function algorithms_get() {
		$this->validateSessionId();
		$algorithms = $this->GIFTLib->getAlgorithms();
		if ( $algorithms === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		$response = $this->build_response('algorithm', $algorithms);
		$this->response($response, 200);
	}
	
	function random_images_get() {
		$this->validateSessionId();
		$collections = $this->GIFTLib->getCollections();
		if ( $collections === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		$collectionRandKey = array_rand($collections);
		if ( $collectionRandKey === NULL ) {
			$this->errorResponse(array(array('msg'=>'No collections on server')));
		}
		if ( ($count = $this->input->get('count')) !== FALSE ) {
			$images = $this->GIFTLib->getImageSet($collections[$collectionRandKey], NULL, NULL, intval($count));
		} else {
			$images = $this->GIFTLib->getImageSet($collections[$collectionRandKey]);
		}
		if ( $images === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		$images = $this->translate_path_to_url($images);
		$response = $this->build_response('image', $images);
		$this->response($response, 200);				
	}
	
	function similar_images_post() {
		$uploadConfig = array();
		$uploadConfig['upload_path'] = $this->rootWebPath.'static/uploads/';
		$uploadConfig['allowed_types'] = 'jpg|jpeg';
		$uploadConfig['encrypt_name'] = TRUE;
		$this->load->library('upload', $uploadConfig);
		if ( $this->upload->do_upload() === FALSE ) {
			$this->errorResponse(array(array('msg'=>$this->upload->display_errors('',''))));
		}
		// upload file comes in userfile field encoded in multipart/form-data 
		$uploadData = $this->upload->data();
		if ( empty($uploadData) ) {
			$this->errorResponse(array(array('msg'=>'File upload error')));
		}
		if ( $uploadData['file_type'] != 'image/jpeg' || $uploadData['is_image'] === 0 ) {
			$this->errorResponse(array(array('msg'=>'Image File invalid')));
			if ( is_file($uploadData['full_path']) === TRUE ) {
				unlink($uploadData['full_path']);
			}
		}
		$this->validateSessionId();
		$collections = $this->GIFTLib->getCollections();
		if ( $collections === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		if ( empty($collections) ) {
			$this->errorResponse(array(array('msg'=>'No collections on server')));
		}
		// TODO:in which collection should the query run??
		// HARDCODED to first collection
		if ( ($count = $this->input->post('count')) !== FALSE ) {
			$images = $this->GIFTLib->getImageSet($collections[0], NULL, $uploadData['full_path'], intval($count));
		} else {
			$images = $this->GIFTLib->getImageSet($collections[0], NULL, $uploadData['full_path']);
		}
		if ( $images === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		$images = $this->translate_path_to_url($images);
		$response = $this->build_response('image', $images);
		$this->response($response, 200);
	}
	
	function sessionid_get() {
		$sessionId = $this->GIFTLib->getSessionId();
		if ( $sessionId === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		$response = $this->build_response(NULL, NULL);
		$this->response($response, 200);
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
	
	private function errorResponse( $msgs = NULL, $code = 400) {
		$this->response($this->build_response('error', $msgs), $code);
	}
	
	private function validateSessionId() {
		if ( ($sid = $this->input->get_post('session-id')) !== FALSE) { 
			if ( $this->GIFTLib->setSessionId($sid) === FALSE ) {
				$this->errorResponse($this->GIFTLib->getLastErrors());
			}
		}
	}
	
	private function build_response($type, $items) {
		$response = array();
		$response['response'] = array();
		if ( ($sessionId = $this->GIFTLib->getSessionId()) !== FALSE ) {
			$response['response']['session-id'] = $sessionId;
		}
		if ( !empty($type) ) {
			$response['response']['type'] = $type;
			if ( empty($items) ) {
				$item = array();
			}
			$response['response']['count'] = count($items);
			$response['response']['items'] = $items;
		}
		return $response;
	}

}
