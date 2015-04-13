<?php

require_once(FUEL_PATH . '/libraries/Fuel_base_controller.php');

class Candidate extends Fuel_base_controller {

    function __construct() {
        parent::__construct(FALSE);
        //Load Libraries
        $this->load->module_library(LOGIN_FOLDER, 'Login_auth');
        //Check if logged in
        $this->login_auth->is_logged();
    }

    function index() {
        $this->login_auth->has_permission(array(
            'module' => 'candidates',
            'permission' => 'list',
        ));
        
        $this->fuel->pages->render('list', array(), array('view_module' => 'prime_candidates'));
    }
    
    function get_candidates() {
        $column_no = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : '';
        $sort = isset($_POST['order']) ? strval($_POST['columns'][$column_no]['data']) : 'id';
        $order = isset($_POST['order']) ? strval($_POST['order'][0]['dir']) : 'desc';
        $where = array();

        $page = isset($_POST['start']) ?
                intval($_POST['start']) : 0;
        $rows = isset($_POST['length'])
                ? intval($_POST['length']) : 10;

        if (! empty($_POST['search']['value'])) {
            $where = 'candidate_firstname LIKE "%'.$_POST['search']['value'].'%" 
                OR candidate_lastname LIKE "%'.$_POST['search']['value'].'%" 
                OR candidate_skill_title LIKE "%'.$_POST['search']['value'].'%"';
        }

        $join_educ =  '(SELECT * 
            FROM candidate_education ce
            JOIN lt_degrees lt ON lt.degree_id = candidate_edu_degree_id
            ORDER BY id DESC) ce';
        
        $join_skills =  '(SELECT * 
            FROM candidate_skills cs
            ORDER BY candidate_skill_proficiency DESC) cs';
        
        $join_works =  '(SELECT * 
            FROM candidate_work_experiences cw
            ORDER BY id DESC) cw';

        $select = "c.id,
            u2.user_fname,
            u2.user_lname,
            indus.industry_name,
            c.candidate_job_order_id,
            j.job_order_position,
            c.candidate_firstname,
            c.candidate_lastname,
            ce.degree_title,
            cs.candidate_skill_title,
            cw.candidate_work_experience_job_title,
            c.candidate_jp_industries,
            c.candidate_status,
            c.candidate_jp_expected_salary";
        //Work around remove298
        $this->db->_protect_identifiers = false;
        $this->db->select($select, FALSE);
        $this->db->join($join_educ, 'ce.candidate_edu_candidate_id = c.id');
        $this->db->join($join_skills, 'cs.candidate_skill_candidate_id = c.id');
        $this->db->join($join_works, 'cw.candidate_work_experience_candidate_id = c.id');
        $this->db->join('job_order AS j', 'j.id = c.candidate_job_order_id');
        $this->db->join('lt_industry AS indus', 'indus.industry_id = c.candidate_jp_industries', 'LEFT');
        $this->db->join('assign_groups AS ag', 'ag.user_id="'.$this->session->userdata('user_id').'"');
        $this->db->join('assign_groups AS ag2', 'ag2.user_id = c.created_by AND ag2.group_id = ag.group_id');
        $this->db->join('users AS u1', 'u1.id = c.created_by');
        $this->db->join('users AS u2', 'u2.id = c.updated_by');
        $this->db->group_by(array("c.id"));
        //Get total record
        $rec_count = $this->db->get_where('candidates c');
        //End get total record
        $this->db->select($select, FALSE);
        $this->db->join($join_educ, 'ce.candidate_edu_candidate_id = c.id');
        $this->db->join($join_skills, 'cs.candidate_skill_candidate_id = c.id');
        $this->db->join($join_works, 'cw.candidate_work_experience_candidate_id = c.id');
        $this->db->join('lt_industry AS indus', 'indus.industry_id = c.candidate_jp_industries', 'LEFT');
        $this->db->join('job_order AS j', 'j.id = c.candidate_job_order_id');
        $this->db->join('assign_groups AS ag', 'ag.user_id="'.$this->session->userdata('user_id').'"');
        $this->db->join('assign_groups AS ag2', 'ag2.user_id = c.created_by AND ag2.group_id = ag.group_id');
        $this->db->join('users AS u1', 'u1.id = c.created_by');
        $this->db->join('users AS u2', 'u2.id = c.updated_by');
        $this->db->order_by($sort, $order);
        $this->db->limit($rows, $page);
        $this->db->group_by(array("c.id"));
        $rec = $this->db->get_where('candidates c', $where);

        if ($rec->num_rows() > 0) {
            $data['draw'] = $_POST['draw'];
            $data['recordsTotal'] = $rec_count->num_rows();
            $data['recordsFiltered'] = $rec_count->num_rows();
            $data['data'] = json_array_formatter($rec->result_array());

            echo json_encode($data);
            return;
        }

        $data['draw'] = $_POST['draw'];
        $data['recordsTotal'] = 0;
        $data['recordsFiltered'] = 0;
        $data['data'] = array();
        echo json_encode($data);
        return;
    }

    function get_job_posts() {
        $column_no = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : '';
        $sort = isset($_POST['order']) ? strval($_POST['columns'][$column_no]['data']) : 'id';
        $order = isset($_POST['order']) ? strval($_POST['order'][0]['dir']) : 'desc';
        $where = array();

        $page = isset($_POST['start']) ?
                intval($_POST['start']) : 0;
        $rows = isset($_POST['length'])
                ? intval($_POST['length']) : 10;
        
        //Path to PHASE 1 API
        $url = "http://primemanpower.com.ph/index.php/api/job_post/jobs";

        $post_data = array (
            "select" => "id, title, published, job_order_id",
            "order" => $sort,
            "sort" => $order,
        );

        if (! empty($_POST['search']['value'])) {
            $post_data['where'] = 'title LIKE "%'.$_POST['search']['value'].'%"';
        }

        $job_posts = curl_post(array('url' => $url, 'post_data' => $post_data));
        $job_posts = json_decode($job_posts, TRUE);
        $rec_count = $job_posts['rows'];

        $post_data = array (
            "select" => $post_data['select'],
            "order" => $sort,
            "sort" => $order,
            "page" => $page,
            "rows" => $rows,
        );

        if (! empty($_POST['search']['value'])) {
            $post_data['where'] = 'title LIKE "%'.$_POST['search']['value'].'%"';
        }

        $job_posts = curl_post(array('url' => $url, 'post_data' => $post_data));
        $job_posts = json_decode($job_posts, TRUE);

        if ($rec_count > 0) {
            $data['draw'] = $_POST['draw'];
            $data['recordsTotal'] = $rec_count;
            $data['recordsFiltered'] = $rec_count;
            $data['data'] = json_array_formatter($job_posts['data']);

            echo json_encode($data);
            return;
        }

        $data['draw'] = $_POST['draw'];
        $data['recordsTotal'] = 0;
        $data['recordsFiltered'] = 0;
        $data['data'] = array();
        echo json_encode($data);
        return;
    }

    function move_candidate() {
        header('Content-Type: application/json');

        $applicant = $this->db->get_where(PRIME_CANDIDATES, array(
            'candidate_job_order_id' => $_POST['candidate_job_order_id'],
            'candidate_job_applicant_id' => $_POST['candidate_job_applicant_id'],
        ));

        if($applicant->num_rows() > 0 ) {
            echo json_encode(FALSE);
            return;
        }

        //Path to PHASE 1 API
        $url = "http://localhost/manpower/index.php/api/candidate/download_data";

        $post_data = array (
            "id" => $_POST['candidate_job_applicant_id'],
        );

        $candidate_data = curl_post(array('url' => $url, 'post_data' => $post_data));
        $candidate_data = json_decode($candidate_data, TRUE);
        $c = $candidate_data['data'][0]['applicant'];
        
        $_data = array(
            'candidate_job_order_id' => $_POST['candidate_job_order_id'],
            'candidate_job_applicant_id' => $_POST['candidate_job_applicant_id'],
            'candidate_firstname' => $c['firstname'],
            'candidate_lastname' => $c['lastname'],
            'candidate_middlename' => $c['middlename'],
            'candidate_address' => $c['street_address'].' '.$c['city'].' '.$c['province'],
            'candidate_contact_no' => $c['contact_no'],
            'candidate_email' => $c['email_address'],
            'candidate_resume' => $c['attachfile'],
            'candidate_status' => 'Active',
            'created_by' => $this->session->userdata('user_id'),
            'date_created' => date('Y-m-d H:i:s'),
            'updated_by' => $this->session->userdata('user_id'),
            'date_updated' => date('Y-m-d H:i:s'),
        );

        //add
        $this->db->insert(PRIME_CANDIDATES, $_data);
        $candidate_id = $this->db->insert_id();
        //$url = "http://primemanpower.com.ph/index.php/api/job_post/download_resume/" . $c['attachfile'];
        $url = "http://localhost/manpower/index.php/api/job_post/download_resume/" . $c['attachfile'];
        $data = file_get_contents($url);
        file_put_contents(PRIME_CANDIDATES_PATH.'resumes/'.$c['attachfile'], $data);

        foreach ($c['skills'] as $s) {
            $_data = array(
                'candidate_skill_candidate_id' => $candidate_id,
                'candidate_skill_title' => $s['title'],
                'candidate_skill_proficiency' => $s['proficiency'],
                'candidate_skill_yrs_exp' => $s['yrs_exp'],
            );

            $this->db->insert('candidate_skills', $_data);
        }

        foreach ($c['works'] as $w) {
            $_data = array(
                'candidate_work_experience_candidate_id' => $candidate_id,
                'candidate_work_experience_job_title' => $w['job_title'],
                'candidate_work_experience_company' => $w['company'],
                'candidate_work_experience_location' => $w['location'],
                'candidate_work_experience_industry' => $w['industry'],
                'candidate_work_experience_contact_no' => $w['contact_no'],
                'candidate_work_experience_start_date' => date('Y-m-d', strtotime($w['start_date'])),
                'candidate_work_experience_end_date' => date('Y-m-d', strtotime($w['end_date'])),
                'candidate_work_experience_job_description' => $w['job_description'],
            );

            $this->db->insert('candidate_work_experiences', $_data);
        }

        foreach ($c['educations'] as $e) {
            $_data = array(
                'candidate_edu_candidate_id' => $candidate_id,
                'candidate_edu_degree_id' => $e['degree_id'],
                'candidate_edu_degree_others' => $e['degree_others'],
                'candidate_edu_school' => $e['school'],
                'candidate_edu_country_id' => $e['country_id'],
                'candidate_edu_city_phil_city_id' => $e['city_id'],
                'candidate_edu_province_phil_province_id' => $e['province_id'],
                'candidate_edu_start_date' => date('Y-m-d', strtotime($e['start_date'])),
                'candidate_edu_end_date' => date('Y-m-d', strtotime($e['end_date'])),
            );

            $this->db->insert('candidate_education', $_data);
        }
        
        echo json_encode(TRUE);
    }
    
    function get_job_post_applicants() {
        $column_no = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : '';
        $sort = isset($_POST['order']) ? strval($_POST['columns'][$column_no]['data']) : 'id';
        $order = isset($_POST['order']) ? strval($_POST['order'][0]['dir']) : 'desc';
        $where = array();

        $page = isset($_POST['start']) ?
                intval($_POST['start']) : 0;
        $rows = isset($_POST['length'])
                ? intval($_POST['length']) : 10;
        
        //Path to PHASE 1 API
        $url = "http://primemanpower.com.ph/index.php/api/job_post/jobs";
        
        $post_data = array (
            "select" => "id",
            "order" => 'id',
            "sort" => 'desc',
            "where" => 'job_order_id = "'.$_POST['job_order_no'].'"',
        );
        
        //Get job post ID base from job order no.
        $job_post = curl_post(array('url' => $url, 'post_data' => $post_data));
        $job_post = json_decode($job_post, TRUE);
        $rec_count = $job_post['rows'];
        if ($rec_count == 0) {
            $job_post_id = 0;
        } else{
            $job_post_id = $job_post['data'][0]['id'];
        }
        
        //Getting Applicants
        //Path to PHASE 1 API
        $url = "http://primemanpower.com.ph/index.php/api/job_post/job_applicants";
        $where = 'job_post_id = "'.$job_post_id.'" ';
        
        $post_data = array (
            "select" => "id, firstname, lastname, email_address, contact_no, contact_no2",
            "order" => $sort,
            "sort" => $order,
            "where" => $where,
        );

        if (! empty($_POST['search']['value'])) {
            $where .= 'title LIKE "%'.$_POST['search']['value'].'%"';
            $post_data['where'] = $where;
        }

        $job_applicants = curl_post(array('url' => $url, 'post_data' => $post_data));
        $job_applicants = json_decode($job_applicants, TRUE);
        $rec_count = $job_applicants['rows'];

        $post_data = array (
            "select" => $post_data['select'],
            "order" => $sort,
            "sort" => $order,
            "where" => $where,
            "page" => $page,
            "rows" => $rows,
        );

        if (! empty($_POST['search']['value'])) {
            $where .= 'title LIKE "%'.$_POST['search']['value'].'%"';
            $post_data['where'] = $where;
        }

        $job_applicants = curl_post(array('url' => $url, 'post_data' => $post_data));
        $job_applicants = json_decode($job_applicants, TRUE);

        if ($rec_count > 0) {
            $data['draw'] = $_POST['draw'];
            $data['recordsTotal'] = $rec_count;
            $data['recordsFiltered'] = $rec_count;
            $data['data'] = json_array_formatter($job_applicants['data']);

            echo json_encode($data);
            return;
        }

        $data['draw'] = $_POST['draw'];
        $data['recordsTotal'] = 0;
        $data['recordsFiltered'] = 0;
        $data['data'] = array();
        echo json_encode($data);
        return;
    }

    function add() {
        $this->login_auth->has_permission(array(
            'module' => 'candidates',
            'permission' => 'add',
        ));

        $data = array();
        $data['save_path'] = site_url('primemanpower-candidates/save');
        $data['countries'] = array();
        $data['degrees'] = array();
        $data['phil_city'] = array();
        $data['phil_prv'] = array();
        $data['candidate_jp_position_type'] = array(
            'Contract/Project',
            'Permanent',
            'Either',
        );
        $data['candidate_jp_prefered_location'] = array(
            'Abroad',
            'Local',
            'Both',
        );
        $data['candidate_jp_relocate_philippines'] = array(
            'Yes',
            'No',
        );
        $data['candidate_jp_relocate_abroad'] = array(
            'Yes',
            'No',
        );
        
        $lt_country = $this->db->get('lt_country');
        if($lt_country->num_rows() > 0 ) {
            $data['countries'] = $lt_country->result();
        }
        
        $degrees = $this->db->get('lt_degrees');
        if($degrees->num_rows() > 0 ) {
            $data['degrees'] = $degrees->result();
        }

        $phil_city = $this->db->get('lt_phil_city');
        if($phil_city->num_rows() > 0 ) {
            $data['phil_city'] = $phil_city->result();
        }

        $phil_prv = $this->db->get('lt_phil_province');
        if($phil_prv->num_rows() > 0 ) {
            $data['phil_prv'] = $phil_prv->result();
        }

        $this->fuel->pages->render('add', $data, array('view_module' => 'prime_candidates'));
    }

    function assign_job_order($id, $assigned_id, $assign_id) {
        //New = assign_id
        //Old = Assigned_id

        $this->login_auth->has_permission(array(
            'module' => 'job_orders',
            'permission' => 'edit',
        ));
        
        //Path to PHASE 1 API
        $url = "http://primemanpower.com.ph/index.php/api/job_post/assign_job_order";

        $post_data = array (
            "id" => $id,
            "job_order_id" => $assigned_id,
            "new_job_order_id" => $assign_id,
            "new" => 'no',
        );

        if (empty($assigned_id)) {
            $post_data['new'] = 'yes';
        }

        curl_post(array('url' => $url, 'post_data' => $post_data));
        
        if ($this->input->is_ajax_request()) {
            echo json_decode(TRUE);
            return;
        } else {
            show_404();
        }
    }


    function save() {

        if (count($_POST) == 0) {
            return show_404();
        }

        $this->load->library('form_validation');
        
        $this->form_validation->set_rules('candidate_firstname', 'Firstname', 'required');
        $this->form_validation->set_rules('candidate_lastname', 'Lastname', 'required');
        $this->form_validation->set_rules('candidate_address', 'Address', 'required');
        $this->form_validation->set_rules('candidate_contact_no', 'Contact No', 'numeric');
        $this->form_validation->set_rules('candidate_email', 'E-mail', 'required|valid_email');
        $this->form_validation->set_rules('candidate_birthday', 'Birthday', 'required');
        $this->form_validation->set_rules('candidate_jp_industries', 'Industries', 'required');
        $this->form_validation->set_rules('candidate_jp_expected_salary', 'Salary', 'required|numeric');
        $this->form_validation->set_rules('candidate_jp_start_date', 'Start Date', 'required');

        if (!empty($_POST['id'])) {
            return $this->_update();
        }

        return $this->_add();
    }

    function edit($id) {
        $this->login_auth->has_permission(array(
            'module' => 'candidates',
            'permission' => 'edit',
        ));

        $data = array();
        $data['save_path'] = site_url('primemanpower-candidates/save');
        $data['countries'] = array();
        $data['degrees'] = array();
        $data['phil_city'] = array();
        $data['phil_prv'] = array();
        $data['industry'] = array();
        $data['candidate_job_status'] = array();
        $data['candidate_jp_position_type'] = array(
            'Contract/Project',
            'Permanent',
            'Either',
        );
        $data['candidate_jp_prefered_location'] = array(
            'Abroad',
            'Local',
            'Both',
        );
        $data['candidate_jp_relocate_philippines'] = array(
            'Yes',
            'No',
        );
        $data['candidate_jp_relocate_abroad'] = array(
            'Yes',
            'No',
        );

        $data['status'] = array(
            'Active',
            'Deleted',
        );
        
        $rec = $this->db->get_where(PRIME_CANDIDATES, array('id' => $id));

        //Works and etc.
        $rec2 = $this->db->get_where('candidate_education', array('candidate_edu_candidate_id' => $id));
        $rec3 = $this->db->get_where('candidate_skills', array('candidate_skill_candidate_id' => $id));
        $rec4 = $this->db->get_where('candidate_work_experiences', array('candidate_work_experience_candidate_id' => $id));

        if ($rec->num_rows() > 0) {
            $data['candidate'] = $rec->row();
            $data['candidate_education'] = $rec2->result();
            $data['candidate_skills'] = $rec3->result();
            $data['candidate_work_experience'] = $rec4->result();
            
            $lt_country = $this->db->get('lt_country');
            if($lt_country->num_rows() > 0 ) {
                $data['countries'] = $lt_country->result();
            }

            $degrees = $this->db->get('lt_degrees');
            if($degrees->num_rows() > 0 ) {
                $data['degrees'] = $degrees->result();
            }

            $phil_city = $this->db->get('lt_phil_city');
            if($phil_city->num_rows() > 0 ) {
                $data['phil_city'] = $phil_city->result();
            }

            $phil_prv = $this->db->get('lt_phil_province');
            if($phil_prv->num_rows() > 0 ) {
                $data['phil_prv'] = $phil_prv->result();
            }

            $lt_industry = $this->db->get('lt_industry');
            if($lt_industry->num_rows() > 0 ) {
                $data['industry'] = $lt_industry->result();
            }

            $lt_candidate_status = $this->db->get('lt_candidate_status');
            if($lt_candidate_status->num_rows() > 0 ) {
                $data['candidate_job_status'] = $lt_candidate_status->result();
            }

        } else {
            return show_404();
        }

        $this->fuel->pages->render('edit', $data, array('view_module' => 'prime_candidates'));
    }

    function _add() {
        
        if ($this->form_validation->run() == FALSE) {
            $error_messages = $this->form_validation->error_array();
            $data['error'] = $error_messages;
            echo json_encode($data);
        } else {
            $_data = array(
                'candidate_job_applicant_id' => 0,
                'candidate_firstname' => $_POST['candidate_firstname'],
                'candidate_lastname' => $_POST['candidate_lastname'],
                'candidate_middlename' => $_POST['candidate_middlename'],
                'candidate_address' => $_POST['candidate_address'],
                'candidate_contact_no' => $_POST['candidate_contact_no'],
                'candidate_email' => $_POST['candidate_email'],
                'candidate_birthday' => date('Y-m-d', strtotime($_POST['candidate_birthday'])),
                'candidate_jp_position_type' => $_POST['candidate_jp_position_type'],
                'candidate_jp_prefered_location' => $_POST['candidate_jp_prefered_location'],
                'candidate_jp_prefered_areas' => $_POST['candidate_jp_prefered_areas'],
                'candidate_jp_relocate_philippines' => $_POST['candidate_jp_relocate_philippines'],
                'candidate_jp_relocate_abroad' => $_POST['candidate_jp_relocate_abroad'],
                'candidate_jp_industries' => $_POST['candidate_jp_industries'],
                'candidate_jp_expected_salary' => $_POST['candidate_jp_expected_salary'],
                'candidate_jp_start_date' => date('Y-m-d', strtotime($_POST['candidate_jp_start_date'])),
                'candidate_date_endorsed' => date('Y-m-d H:i:s'),
                'candidate_status' => 'Active',
                'created_by' => $this->session->userdata('user_id'),
                'date_created' => date('Y-m-d H:i:s'),
            );
            
            //add
            $this->db->insert(PRIME_CANDIDATES, $_data);
            $candidate_id = $this->db->insert_id();

            foreach ($_POST['candidate_skill_title'] as $key => $skill_title) {
                $_data = array(
                    'candidate_skill_candidate_id' => $candidate_id,
                    'candidate_skill_title' => $skill_title,
                    'candidate_skill_proficiency' => $_POST['candidate_skill_proficiency'][$key],
                    'candidate_skill_yrs_exp' => $_POST['candidate_skill_yrs_exp'][$key],
                );

                $this->db->insert('candidate_skills', $_data);
            }

            foreach ($_POST['candidate_work_experience_job_title'] as $key => $job_title) {
                $_data = array(
                    'candidate_work_experience_candidate_id' => $candidate_id,
                    'candidate_work_experience_job_title' => $job_title,
                    'candidate_work_experience_company' => $_POST['candidate_work_experience_company'][$key],
                    'candidate_work_experience_location' => $_POST['candidate_work_experience_location'][$key],
                    'candidate_work_experience_industry' => $_POST['candidate_work_experience_industry'][$key],
                    'candidate_work_experience_contact_no' => $_POST['candidate_work_experience_contact_no'][$key],
                    'candidate_work_experience_start_date' => date('Y-m-d', strtotime($_POST['candidate_work_experience_start_date'][$key])),
                    'candidate_work_experience_end_date' => date('Y-m-d', strtotime($_POST['candidate_work_experience_end_date'][$key])),
                    'candidate_work_experience_job_description' => $_POST['candidate_work_experience_job_description'][$key],
                );

                $this->db->insert('candidate_work_experiences', $_data);
            }

            foreach ($_POST['candidate_edu_degree_id'] as $key => $candidate_edu_degree_id) {
                $_data = array(
                    'candidate_edu_candidate_id' => $candidate_id,
                    'candidate_edu_degree_id' => $candidate_edu_degree_id,
                    'candidate_edu_degree_others' => $_POST['candidate_edu_degree_others'][$key],
                    'candidate_edu_school' => $_POST['candidate_edu_school'][$key],
                    'candidate_edu_country_id' => $_POST['candidate_edu_country_id'][$key],
                    'candidate_edu_city_phil_city_id' => $_POST['candidate_edu_city_phil_city_id'][$key],
                    'candidate_edu_province_phil_province_id' => $_POST['candidate_edu_province_phil_province_id'][$key],
                    'candidate_edu_start_date' => date('Y-m-d', strtotime($_POST['candidate_edu_start_date'][$key])),
                    'candidate_edu_start_date' => date('Y-m-d', strtotime($_POST['candidate_edu_start_date'][$key])),
                );

                $this->db->insert('candidate_education', $_data);
            }

            $data['error'] = array();
            echo json_encode($data);
        }
    }

    function _update() {
        if ($this->form_validation->run() == FALSE) {
            $error_messages = $this->form_validation->error_array();

            $data['error'] = $error_messages;
            echo json_encode($data);
        } else {
            $_data = array(
                'candidate_firstname' => $_POST['candidate_firstname'],
                'candidate_lastname' => $_POST['candidate_lastname'],
                'candidate_middlename' => $_POST['candidate_middlename'],
                'candidate_address' => $_POST['candidate_address'],
                'candidate_contact_no' => $_POST['candidate_contact_no'],
                'candidate_email' => $_POST['candidate_email'],
                'candidate_birthday' => date('Y-m-d', strtotime($_POST['candidate_birthday'])),
                'candidate_job_status' => $_POST['candidate_job_status'],
                'candidate_jp_position_type' => $_POST['candidate_jp_position_type'],
                'candidate_jp_prefered_location' => $_POST['candidate_jp_prefered_location'],
                'candidate_jp_prefered_areas' => $_POST['candidate_jp_prefered_areas'],
                'candidate_jp_relocate_philippines' => $_POST['candidate_jp_relocate_philippines'],
                'candidate_jp_relocate_abroad' => $_POST['candidate_jp_relocate_abroad'],
                'candidate_jp_industries' => $_POST['candidate_jp_industries'],
                'candidate_jp_expected_salary' => $_POST['candidate_jp_expected_salary'],
                'candidate_jp_start_date' => date('Y-m-d', strtotime($_POST['candidate_jp_start_date'])),
                'candidate_status' => $_POST['candidate_status'],
                'updated_by' => $this->session->userdata('user_id'),
                'date_updated' => date('Y-m-d H:i:s'),
            );

            //Updating
            $this->db->where('id', $_POST['id']);
            $this->db->update(PRIME_CANDIDATES, $_data);

            //Empty tables
            $this->db->delete('candidate_skills', array('candidate_skill_candidate_id' => $_POST['id']));
            $this->db->delete('candidate_work_experiences', array('candidate_work_experience_candidate_id' => $_POST['id']));
            $this->db->delete('candidate_education', array('candidate_edu_candidate_id' => $_POST['id']));

            //Save work, skills and etc.
            foreach ($_POST['candidate_skill_title'] as $key => $skill_title) {
                $_data = array(
                    'candidate_skill_candidate_id' => $_POST['id'],
                    'candidate_skill_title' => $skill_title,
                    'candidate_skill_proficiency' => $_POST['candidate_skill_proficiency'][$key],
                    'candidate_skill_yrs_exp' => $_POST['candidate_skill_yrs_exp'][$key],
                );

                $this->db->insert('candidate_skills', $_data);
            }

            foreach ($_POST['candidate_work_experience_job_title'] as $key => $job_title) {
                $_data = array(
                    'candidate_work_experience_candidate_id' => $_POST['id'],
                    'candidate_work_experience_job_title' => $job_title,
                    'candidate_work_experience_company' => $_POST['candidate_work_experience_company'][$key],
                    'candidate_work_experience_location' => $_POST['candidate_work_experience_location'][$key],
                    'candidate_work_experience_industry' => $_POST['candidate_work_experience_industry'][$key],
                    'candidate_work_experience_contact_no' => $_POST['candidate_work_experience_contact_no'][$key],
                    'candidate_work_experience_start_date' => date('Y-m-d', strtotime($_POST['candidate_work_experience_start_date'][$key])),
                    'candidate_work_experience_end_date' => date('Y-m-d', strtotime($_POST['candidate_work_experience_end_date'][$key])),
                    'candidate_work_experience_job_description' => $_POST['candidate_work_experience_job_description'][$key],
                );

                $this->db->insert('candidate_work_experiences', $_data);
            }

            foreach ($_POST['candidate_edu_degree_id'] as $key => $candidate_edu_degree_id) {
                $_data = array(
                    'candidate_edu_candidate_id' => $_POST['id'],
                    'candidate_edu_degree_id' => $candidate_edu_degree_id,
                    'candidate_edu_degree_others' => $_POST['candidate_edu_degree_others'][$key],
                    'candidate_edu_school' => $_POST['candidate_edu_school'][$key],
                    'candidate_edu_country_id' => $_POST['candidate_edu_country_id'][$key],
                    'candidate_edu_city_phil_city_id' => $_POST['candidate_edu_city_phil_city_id'][$key],
                    'candidate_edu_province_phil_province_id' => $_POST['candidate_edu_province_phil_province_id'][$key],
                    'candidate_edu_start_date' => date('Y-m-d', strtotime($_POST['candidate_edu_start_date'][$key])),
                    'candidate_edu_end_date' => date('Y-m-d', strtotime($_POST['candidate_edu_end_date'][$key])),
                );

                $this->db->insert('candidate_education', $_data);
            }

            $data['error'] = array();
            echo json_encode($data);
        }
    }

    function delete($id) {
        $this->login_auth->has_permission(array(
            'module' => 'candidates',
            'permission' => 'delete',
        ));

        $_data = array(
            'candidate_status' => 'Deleted',
            'updated_by' => $this->session->userdata('user_id'),
            'date_updated' => date('Y-m-d H:i:s'),
        );

        //Updating
        $this->db->where('id', $id);
        $this->db->update(PRIME_CANDIDATES, $_data);
        
        if ($this->input->is_ajax_request()) {
            echo json_decode(TRUE);
            return;
        } else {
            show_404();
        }
    }

    function _upload_attachment() {
        $config['upload_path'] = FCPATH. '/files/client_contract/';
        $config['allowed_types'] = '*';
        $config['max_size'] = '11000';
        $config['encrypt_name'] = TRUE;

        $this->load->library('upload', $config);

        //If has some error
        if (!$this->upload->do_upload('client_contract_file')) {
            $data['errors'] = $this->upload->display_errors();
            return $data;
        } else {
            $data['errors'] = array();
            $data['response'] = $this->upload->data();
            return $data;
        }
    }

}
