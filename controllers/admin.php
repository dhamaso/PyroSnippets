<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * PyroSnippets Admin Controller Class
 *
 * @package  	PyroCMS
 * @subpackage  PyroSnippets
 * @category  	Controller
 * @author  	Adam Fairholm
 */ 
class Admin extends Admin_Controller {

	/**
	 * Section
	 *
	 * @var		string
	 */
	protected $section = 'content';
	
	/**
	 * Valid Snippet Types
	 *
	 * @var		array
	 */
	protected $snippet_types = array(
		'wysiwyg' 	=> 'WYSIWYG',
		'text' 		=> 'Text',
		'html'		=> 'HTML',
		'image'		=>	'Image'
	);

	/**
	 * Construct
	 *
	 * @return	void
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->load->model('snippets/snippets_m');
		
		$this->load->language('snippets');
		$this->load->config('snippets/snippets');

		$this->template->set('statuses', $this->config->item('snippet_statuses'));
		
		$this->template->snippet_types = $this->snippet_types;	
	}

	/**
	 * Index
	 *
	 * Load snippet list.
	 *
	 * @return	void
	 */
	public function index()
	{
		$this->list_snippets();
	}

	/**
	 * List snippets
	 *
	 * @return	void
	 */
	public function list_snippets($offset = 0)
	{	
		// -------------------------------------
		// Get snippets
		// -------------------------------------
		
		$this->template->snippets = $this->snippets_m->get_snippets(Settings::get('records_per_page'), $offset);

		// -------------------------------------
		// Pagination
		// -------------------------------------

		$total_rows = $this->snippets_m->count_all();
		
		$this->template->pagination = create_pagination('admin/snippets/list_snippets', $total_rows);
		
		// -------------------------------------

		$this->template->build('admin/index');
	}
	
	/**
	 * Edit a snippet
	 *
	 * @param 	int $snippet_id
	 * @return	void
	 */
	public function edit_snippet($snippet_id = null)
	{			
		if (is_null($snippet_id))
		{
			show_error('Invalid snippet ID.');
		}

		// -------------------------------------
		// Get snippet data
		// -------------------------------------

		$snippet = $this->snippets_m->get_snippet($snippet_id);

		// -------------------------------------
		// Validation & Setup
		// -------------------------------------
	
		$this->load->library('form_validation');
		
		$this->form_validation->set_rules('content', 'snippets.snippet_content', 'trim');

		$config[0] = array(
			array(
			     'field'   => 'content', 
			     'label'   => 'snippets.snippet_content', 
			     'rules'   => 'trim'
			  )
		);
		
		// Is this required?
		// @todo - make this an option
		$config[0][0]['rules'] .= '|required';

		// -------------------------------------
		// Process Data
		// -------------------------------------
		
		if ($this->form_validation->run())
		{
			if ( ! $this->snippets_m->update_snippet($snippet))
			{
				$this->session->set_flashdata('notice', lang('snippets.update_snippet_error'));	
			}
			else
			{
				$this->session->set_flashdata('success', lang('snippets.update_snippet_success'));
				
				Events::trigger('post_snippet_edit', $snippet_id);
			}
	
			$this->input->post('btnAction') == 'save_exit' ? redirect('admin/snippets') : redirect('admin/snippets/edit_snippet/'.$snippet_id);
		}

		// -------------------------------------
		// Event
		// -------------------------------------
		
		if (method_exists($this->snippets_m->snippets->{$snippet->type}, 'event'))
		{
			$this->snippets_m->snippets->{$snippet->type}->event();
		}

		// -------------------------------------
		
		$this->template->set('snippet', $snippet)->build('admin/edit');
	}
	
	/**
	 * Delete a snippet
	 *
	 * @param 	int $snippet_id
	 * @return	void
	 */
	public function delete_snippet($snippet_id = null)
	{		
		// If you can't admin snippets, you can't delete them
		role_or_die('snippets', 'admin_snippets');

		if ( ! $this->snippets_m->delete_snippet( $snippet_id ))
		{
			$this->session->set_flashdata('notice', lang('snippets.delete_snippet_error'));	
		}
		else
		{
			$this->session->set_flashdata('success', lang('snippets.delete_snippet_success'));
			
			Events::trigger('post_snippet_delete', $snippet_id);
		}

		redirect('admin/snippets');
	}

	/**
	 * Check Slug
	 *
	 * Check slug to make sure it is unique.
	 * Validation callback.
	 *
	 * @param	string $slug slug to be tested
	 * @param	mode $mode 'update' or 'insert'
	 * @return	bool
	 */
	public function _check_slug($slug, $mode)
	{
		$obj = $this->db->where('slug', $slug)->get('snippets');
		
		if ($mode == 'update')
		{
			$threshold = 1;
		}
		else
		{
			$threshold = 0;
		}
		
		if ($obj->num_rows > $threshold)
		{
			$this->form_validation->set_message('_check_slug', lang('snippets.slug_unique'));
		
			return false;
		}
		else
		{
			return true;
		}
	}

}