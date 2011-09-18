<?php
class Place_model extends CI_Model {
	
	function __construct() {
        parent::__construct();
    }

	function get_place($id) {
		$this->db->select('*')->from('place')->where('id', $id)->limit(1);
		$query = $this->db->get();
		$result = $query->result_array();
		$query->free_result();
		return $result;
	}
	
	function get_places($page, $count) {
		$this->db->select('*')->from('place');
		$page = ( is_numeric($page) && $page > 0 ) ? $page : 0;
		$count = ( is_numeric($count) && $count > 0 ) ? $count : 0;
		if ( $count > 0 ) {
			$this->db->limit($page*$count, $count);
		}
		$query = $this->db->get();
		$result = $query->result_array();
		$query->free_result();
		return $result;	
	}
	
	function get_place_pictures($id, $page, $count) {
		$this->db->select('*');
		$this->db->from('picture')->where('id_place', $id);
		$page = ( is_numeric($page) && $page > 0 ) ? $page : 0;
		$count = ( is_numeric($count) && $count > 0 ) ? $count : 0;
		if ( $count > 0 ) {
			$this->db->limit($page*$count, $count);
		}
		$query = $this->db->get();
		$result = $query->result_array();
		$query->free_result();
		return $result;
	}
		
	function get_places_around_place($id, $distance) {
		$sqlQuery = "CALL around_place(?, ?)";
		$query = $this->db->query($sqlQuery, array($id, $distance));
		$result = $query->result_array();
		$query->free_result();
		return $result;
	}
	
	function get_places_around_location($lat, $lon, $distance) {
		$sqlQuery = "CALL around_location(?, ?, ?)";
		$query = $this->db->query($sqlQuery, array($lat, $lon, $distance));
		$result = $query->result_array();
		$query->free_result();
		return $result;
	}
}