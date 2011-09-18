<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Google_maps extends CI_Controller {

	function __construct()
	{
		parent::__construct();
		$this->load->model('Place_model');
	}

	function index()
	{
		$page = $this->input->get_post('page');
		$count = $this->input->get_post('count');
		$data['markers'] = $this->Place_model->get_places($page, $count);
		$data['center']['latitude'] = 40.63108;
		$data['center']['longitude'] = -8.65862;
		$data['distance'] = -1;
		$this->load->view('google_maps', $data);
	}
	
	function around_place()
	{
		$id = $this->input->get_post('id');
		$distance = $this->input->get_post('distance');
		$data['markers'] = $this->Place_model->get_places_around_place($id, $distance);
		if ( !isset($data['markers'][0]) ) {
			$data['center']['latitude'] = 40.63108;
			$data['center']['longitude'] = -8.65862;
		} else {
			$data['center']['latitude'] = $data['markers'][0]['latitude'];
			$data['center']['longitude'] = $data['markers'][0]['longitude'];
		}
		$data['distance'] = ($distance !== FALSE) ? $distance : -1;
		$this->load->view('google_maps', $data);
	}
	
	function around_location()
	{
		$lat = $this->input->get_post('lat');
		$lon = $this->input->get_post('lon');
		$distance = $this->input->get_post('distance');
		$data['markers'] = $this->Place_model->get_places_around_location($lat, $lon, $distance);
		$data['center']['latitude'] = $lat;
		$data['center']['longitude'] = $lon;
		$data['distance'] = $distance;
		$this->load->view('google_maps', $data);		
	}
}
