<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'/libraries/REST_Controller.php';
require APPPATH.'/libraries/GIFTLib.php';

class cibr extends REST_Controller {
	
	private $GIFTLib;
	private $rootWebPath;
	
	function __construct() {
		parent::__construct();
		
		$this->load->model('Place_model');
		$this->load->model('Picture_model');
		
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
	function place_get() {
		$this->validateSessionId();
		if ( $this->input->get_post('id') === FALSE ) {
			$this->errorResponse(array(array('msg'=>'Missing argument id')));
		}
		$place = $this->Place_model->get_place($this->input->get_post('id'));
		$response = $this->build_response('place', $place);
		$this->response($response, 200);
	}
	
	function places_get() {
		$this->validateSessionId();
		$page = $this->input->get_post('page');
		$count = $this->input->get_post('count');
		$place = $this->Place_model->get_places($page, $count);
		$response = $this->build_response('place', $place);
		$this->response($response, 200);		
	}
	
	/* get list of pictures for place */
	function place_pictures_get() {
		$this->validateSessionId();
		$id = $this->input->get_post('id');
		$page = $this->input->get_post('page');
		$count = $this->input->get_post('count');
		if ( $id === FALSE ) {
			$this->errorResponse(array(array('msg'=>'Missing argument id')));
		}
		$pictures = $this->Place_model->get_place_pictures($id, $page, $count);
		$pictures = $this->translate_sql_pictures_to_url($pictures);
		$response = $this->build_response('pictures', $pictures);
		$this->response($response, 200);
	}
	
	/* get list of places around coordinates */
	function places_around_location_get() {
		$this->validateSessionId();
		$lat = $this->input->get_post('lat');
		$lon = $this->input->get_post('lon');
		$distance = $this->input->get_post('distance');	
		if ( $lat === FALSE || $lon === FALSE || $distance === FALSE ) {
			$this->errorResponse(array(array('msg'=>'Missing arguments')));
		}
		$places = $this->Place_model->get_places_around_location($lat, $lon, $distance);
		$response = $this->build_response('place', $places);
		$this->response($response, 200);
	}
	
	function place_with_picture_post() {
		$this->validateSessionId();

		//handle args
		$arguments = array('lat', 'lon', 'distance');
		$argumentsFound = 0;
		foreach ( $arguments as $argument) {
			if ( $this->input->get_post($argument) !== FALSE ) {
				$argumentsFound++;
			}
		}
		if ( $argumentsFound > 0 && $argumentsFound != count($arguments) ) {
			$this->errorResponse(array(array('msg'=>'Missing arguments')));
		}
				
		//handle picture upload
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
		
		//query gift
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
			$images = $this->GIFTLib->getImageSet($collections[0], NULL, $uploadData['full_path'], intval($count), 0.7);
		} else {
			$images = $this->GIFTLib->getImageSet($collections[0], NULL, $uploadData['full_path'], 10, 0.7);
		}
		if ( $images === FALSE ) {
			$this->errorResponse($this->GIFTLib->getLastErrors());
		}
		
		//handle results
		$result = $this->extract_filenames_and_similarity_from_gift_image_array($images);
		$filenames = $result['filenames'];
		$similarity = $result['similarity'];
		if ( count($argumentsFound) == 3 ) {
			// we have location data
			$lat = $this->input->get_post('lat');
			$lon = $this->input->get_post('lon');
			$distance = $this->input->get_post('distance');
			$places = $this->Place_model->get_places_around_location($lat, $lon, $distance);
			$ids = $this->extract_placeids_from_places_array($places);
			$pictures = $this->Picture_model->get_pictures_with_filenames_and_idplaces($filenames, $ids);
		} else {
			 // no location data
			$pictures = $this->Picture_model->get_pictures_with_filenames($filenames);
		}
		
		// find most likely
		$mostLikely = $this->match_most_likely_picture($similarity, $pictures);
		
		$place = array();
		if ( $mostLikely['match'] !== NULL ) {
			$place = $this->Place_model->get_place($mostLikely['match']['id_place']);
		}
		
		//return results
		$response = $this->build_response('place', $place);
		$this->response($response, 200);		
	}
	
	/** internal methods **/
	
	private function match_most_likely_picture($similarity, $pictures) {
		$result = array();
		$result['match'] = NULL;
		$result['matchSimilarity'] = 0.0;
		foreach($pictures as $picture) {
			$file = $picture['file'];
			if ( isset($similarity[$file]) && $similarity[$file] > $result['matchSimilarity'] ) {
				$result['matchSimilarity'] = $similarity[$file];
				$result['match'] = $picture;
				if ( $result['matchSimilarity'] == 1.0 )
					break;
			}
		}
		return $result;
	}
	
	private function extract_filenames_and_similarity_from_gift_image_array($images) {
		$filePathPatern = "#^file:/#";
		$result = array();
		$result['filenames'] = array();
		$result['similarity'] = array();
		foreach($images as $image) {
			$path = preg_replace($filePathPatern, '', $image['image-location']);
			if ( is_file($path) ) {
				$bname = basename($path);
				$result['filenames'][] = $bname;
				$result['similarity'][$bname] = $image['calculated-similarity'];
			}
		}
		return $result;
	}
	
	private function extract_placeids_from_places_array($places) {
		$ids = array();
		foreach($places as $place) {
			if ( isset($place['id']) ) {
				$ids[] = $place['id'];
			}
		}
		return $ids;
	}
		
	private function translate_gift_image_array_paths_to_url($images) {
		$baseurl = $this->config->item('base_url');
		$filePathPatern = "#^(file:)?$this->rootWebPath#";
		foreach($images as $k => $image) {
			$images[$k]['image-location'] = preg_replace($filePathPatern, $baseurl, $image['image-location']);
			$images[$k]['thumbnail-location'] = preg_replace($filePathPatern, $baseurl, $image['thumbnail-location']);
		}
		return $images;
	}
	
	private function translate_sql_pictures_to_url($pictures) {
		$baseurl = $this->config->item('base_url');
		$filePathPatern = "#^(file:)?$this->rootWebPath#";
		foreach($pictures as $k => $picture) {
			$pictures[$k]['url'] = preg_replace($filePathPatern, $baseurl, $picture['file_path'].$picture['file']);
			$pictures[$k]['thumb_url'] = preg_replace($filePathPatern, $baseurl, $picture['thumb_path'].$picture['thumb']);
			unset($pictures[$k]['file_path']);
			unset($pictures[$k]['file']);
			unset($pictures[$k]['thumb_path']);
			unset($pictures[$k]['thumb']);
		}
		return $pictures;
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