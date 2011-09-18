<?php
class Picture_model extends CI_Model {
	
	function __construct() {
        parent::__construct();
    }

	function get_picture_with_id($id) {
		$this->db->select('*')->from('picture');
		$this->db->where('id', $id);
		$query = $this->db->get();
		$result = $query->result_array();
		$query->free_result();
		return $result;
	}

	function get_picture_with_filename($filename) {
		$result = get_pictures_with_filenames_and_idplaces(array($filename));
		if ( count($result) > 1 || !isset($result[0]) )
			return FALSE;
		return $result[0];
	}
	
	function get_pictures_with_filenames($filenames) {
		return get_pictures_with_filenames_and_idplaces($filenames);
	}
	
	function get_pictures_with_filenames_and_idplaces($filenames, $ids = NULL) {
		$this->db->select('*')->from('picture');
		$this->db->where_in('file', $filenames);
		if ( $ids !== NULL && is_array($ids) ) {
			$this->db->where_in('id_place', $ids);
		}
		$query = $this->db->get();
		$result = $query->result_array();
		$query->free_result();
		return $result;	
	}
	
}