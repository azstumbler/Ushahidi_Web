<?php defined('SYSPATH') or die('No direct script access.');
/**
 * This controller is used to list/ view and edit reports
 */

class Reports_Controller extends Main_Controller {

    function __construct()
    {
        parent::__construct();	
    }

    /**
     * Displays all reports.
     */
    public function index() 
	{
		$this->template->header->this_page = 'reports';
		$this->template->content = new View('reports');
		
		// Pagination
		$pagination = new Pagination(array(
			'query_string'    => 'page',
			'items_per_page' => (int) Kohana::config('settings.items_per_page'),
			'total_items'    => ORM::factory('incident')->where('incident_active', '1')->count_all()
		));

		$incidents = ORM::factory('incident')->where('incident_active', '1')->orderby('incident_date', 'desc')->find_all((int) Kohana::config('settings.items_per_page'), $pagination->sql_offset);
		
		$this->template->content->incidents = $this->_get_incidentlisting($incidents);
		$this->template->content->pagination = $pagination;
		$this->template->content->pagination_stats = "(Showing " . (($pagination->sql_offset/(int) Kohana::config('settings.items_per_page')) + 1)
		 	. " of " . ceil($pagination->total_items/(int) Kohana::config('settings.items_per_page')) . " pages)";	
	}
    
    /**
	 * Submits a new report.
	 */
	public function submit()
	{
		$this->template->header->this_page = 'reports_submit';
		$this->template->content = new View('reports_submit');
		
		// setup and initialize form field names
		$form = array
	    (
			'incident_title'      => '',
	        'incident_description'    => '',
	        'incident_date'  => '',
	        'incident_hour'      => '',
			'incident_minute'      => '',
			'incident_ampm' => '',
			'latitude' => '',
			'longitude' => '',
			'location_name' => '',
			'country_id' => '',
			'incident_category' => array(),
			'incident_news' => array(),
			'incident_video' => array(),
			'incident_photo' => array(),
			'person_first' => '',
			'person_last' => '',
			'person_email' => ''
	    );
		//  copy the form as errors, so the errors will be stored with keys corresponding to the form field names
	    $errors = $form;
		$form_error = FALSE;
		
		
		// check, has the form been submitted, if so, setup validation
	    if ($_POST)
	    {
            // Instantiate Validation, use $post, so we don't overwrite $_POST fields with our own things
			$post = Validation::factory(array_merge($_POST,$_FILES));
			
	         //  Add some filters
	        $post->pre_filter('trim', TRUE);

	        // Add some rules, the input field, followed by a list of checks, carried out in order
			$post->add_rules('incident_title','required', 'length[3,200]');
			$post->add_rules('incident_description','required');
			$post->add_rules('incident_date','required','date_mmddyyyy');
			$post->add_rules('incident_hour','required','between[1,12]');
			$post->add_rules('incident_minute','required','between[0,59]');
			if ($_POST['incident_ampm'] != "am" && $_POST['incident_ampm'] != "pm")
			{
				$post->add_error('incident_ampm','values');
	        }
			$post->add_rules('latitude','required','between[-90,90]');		// Validate for maximum and minimum latitude values
			$post->add_rules('longitude','required','between[-180,180]');	// Validate for maximum and minimum longitude values
			$post->add_rules('location_name','required', 'length[3,200]');
			$post->add_rules('incident_category.*','required','numeric');
			
            
			// Validate only the fields that are filled in	
	        if (!empty($_POST['incident_news']))
			{
	        	foreach ($_POST['incident_news'] as $key => $url) {
					if (!empty($url) AND !(bool) filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
					{
						$post->add_error('incident_news','url');
					}
	        	}
	        }
			
			// Validate only the fields that are filled in
	        if (!empty($_POST['incident_video']))
			{
	        	foreach ($_POST['incident_video'] as $key => $url) {
					if (!empty($url) AND !(bool) filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED))
					{
						$post->add_error('incident_video','url');
					}
	        	}
	        }
	
			// Validate photo uploads
			$post->add_rules('incident_photo', 'upload::valid', 'upload::type[gif,jpg,png]', 'upload::size[2M]');
			
			
			// Validate Personal Information
			if (!empty($_POST['person_first']))
			{
				$post->add_rules('person_first', 'length[3,100]');
			}
			
			if (!empty($_POST['person_last']))
			{
				$post->add_rules('person_last', 'length[3,100]');
			}
			
			if (!empty($_POST['person_email']))
			{
				$post->add_rules('person_email', 'email', 'length[3,100]');
			}
			
			// Test to see if things passed the rule checks
	        if ($post->validate())
	        {
				// STEP 1: SAVE LOCATION
				$location = new Location_Model();
				$location->location_name = $post->location_name;
				$location->latitude = $post->latitude;
				$location->longitude = $post->longitude;
				$location->location_date = date("Y-m-d H:i:s",time());
				$location->save();
				
				// STEP 2: SAVE INCIDENT
				$incident = new Incident_Model();
				$incident->location_id = $location->id;
				$incident->user_id = 0;
				$incident->incident_title = $post->incident_title;
				$incident->incident_description = $post->incident_description;
				
				$incident_date=split("/",$post->incident_date);
				// where the $_POST['date'] is a value posted by form in mm/dd/yyyy format
					$incident_date=$incident_date[2]."-".$incident_date[0]."-".$incident_date[1];
					
				$incident_time = $post->incident_hour . ":" . $post->incident_hour . ":00 " . $post->incident_ampm;
				$incident->incident_date = $incident_date . " " . $incident_time;
				$incident->incident_dateadd = date("Y-m-d H:i:s",time());
				$incident->save();
				
				// STEP 3: SAVE CATEGORIES
				foreach($post->incident_category as $item)
				{
					$incident_category = new Incident_Category_Model();
					$incident_category->incident_id = $incident->id;
					$incident_category->category_id = $item;
					$incident_category->save();
				}
				
				// STEP 4: SAVE MEDIA
				// a. News
				foreach($post->incident_news as $item)
				{
					if(!empty($item))
					{
						$news = new Media_Model();
						$news->location_id = $location->id;
						$news->incident_id = $incident->id;
						$news->media_type = 4;		// News
						$news->media_link = $item;
						$news->media_date = date("Y-m-d H:i:s",time());
						$news->save();
					}
				}
				
				// b. Video
				foreach($post->incident_video as $item)
				{
					if(!empty($item))
					{
						$video = new Media_Model();
						$video->location_id = $location->id;
						$video->incident_id = $incident->id;
						$video->media_type = 2;		// Video
						$video->media_link = $item;
						$video->media_date = date("Y-m-d H:i:s",time());
						$video->save();
					}
				}
				
				// c. Photos
				$filenames = upload::save('incident_photo');
				$i = 1;
				foreach ($filenames as $filename) {
					$new_filename = $incident->id . "_" . $i . "_" . time();
					
					// Resize original file... make sure its max 408px wide
					Image::factory($filename)->resize(408,248,Image::AUTO)
						->save(Kohana::config('upload.directory', TRUE) . $new_filename . ".jpg");
					
					// Create thumbnail
					Image::factory($filename)->resize(70,41,Image::HEIGHT)
						->save(Kohana::config('upload.directory', TRUE) . $new_filename . "_t.jpg");
					
					// Remove the temporary file
					unlink($filename);
					
					// Save to DB
					$photo = new Media_Model();
					$photo->location_id = $location->id;
					$photo->incident_id = $incident->id;
					$photo->media_type = 1; // Images
					$photo->media_link = $new_filename . ".jpg";
					$photo->media_thumb = $new_filename . "_t.jpg";
					$photo->media_date = date("Y-m-d H:i:s",time());
					$photo->save();
					$i++;
				}				
				
				
				// STEP 5: SAVE PERSONAL INFORMATION
	            $person = new Incident_Person_Model();
				$person->location_id = $location->id;
				$person->incident_id = $incident->id;
				$person->person_first = $post->person_first;
				$person->person_last = $post->person_last;
				$person->person_email = $post->person_email;
				$person->person_date = date("Y-m-d H:i:s",time());
				$person->save();
				
				url::redirect(url::base() . 'reports/thanks');
	            
	        }
	
            // No! We have validation errors, we need to show the form again, with the errors
	        else   
			{
	            // repopulate the form fields
	            $form = arr::overwrite($form, $post->as_array());

	            // populate the error fields, if any
	            $errors = arr::overwrite($errors, $post->errors('report'));
				$form_error = TRUE;
	        }
	    }		
		else
		{
			$form['latitude'] = Kohana::config('settings.default_lat');
			$form['longitude'] = Kohana::config('settings.default_lon');
		}
		
		// Retrieve Country Cities
		$default_country = Kohana::config('settings.default_country');
		$this->template->content->cities = $this->_get_cities($default_country);
		
		$this->template->content->form = $form;
		$this->template->content->errors = $errors;
		$this->template->content->form_error = $form_error;
		$this->template->content->categories = $this->_get_categories($form['incident_category']);
		
		// Javascript Header
		$this->template->header->map_enabled = TRUE;
        $this->template->header->datepicker_enabled = TRUE;
		$this->template->header->js = new View('reports_submit_js');
		$this->template->header->js->default_map = Kohana::config('settings.default_map');
		$this->template->header->js->default_zoom = Kohana::config('settings.default_zoom');
		$this->template->header->js->latitude = $form['latitude'];
		$this->template->header->js->longitude = $form['longitude'];
	}
	
	 /**
     * Displays a report.
     * @param boolean $id If id is supplied, a report with that id will be
     * retrieved.
     */
	public function view( $id = false )
	{
		$this->template->header->this_page = 'reports';
		$this->template->content = new View('reports_view');
		
        if ( !$id )
        {
            url::redirect('main');
        }
        else
        {
            $incident = ORM::factory('incident', $id);
			
            if ( $incident->id == 0 )	// Not Found
            {
                url::redirect('main');
            }

			// Comment Post?
			// setup and initialize form field names
			$form = array
		    (
		        'comment_author'      => '',
				'comment_description'      => '',
		        'comment_email'    => '',
		        'comment_ip'  => '',
				'captcha'  => ''
		    );
			$captcha = Captcha::factory(); 
			$errors = $form;
			$form_error = FALSE;
			
			// check, has the form been submitted, if so, setup validation
		    if ($_POST)
		    {
	            // Instantiate Validation, use $post, so we don't overwrite $_POST fields with our own things
				$post = Validation::factory($_POST);

		         //  Add some filters
		        $post->pre_filter('trim', TRUE);
		
				// Add some rules, the input field, followed by a list of checks, carried out in order
				$post->add_rules('comment_author','required', 'length[3,100]');
				$post->add_rules('comment_description','required');
				$post->add_rules('comment_email','required','email', 'length[4,100]');
				$post->add_rules('captcha', 'required', 'Captcha::valid');
				
				// Test to see if things passed the rule checks
		        if ($post->validate())
		        {
	                // Yes! everything is valid
					$comment = new Comment_Model();
					$comment->incident_id = $id;
					$comment->comment_author = $post->comment_author;
					$comment->comment_description = $post->comment_description;
					$comment->comment_email = $post->comment_email;
					$comment->comment_ip = $_SERVER['REMOTE_ADDR'];
					$comment->comment_date = date("Y-m-d H:i:s",time());
					$comment->comment_active = 1;		// Activate comment for now
					$comment->save();
					
					// Redirect
					url::redirect('reports/view/' . $id);
				}

	            // No! We have validation errors, we need to show the form again, with the errors
		        else   
				{
		            // repopulate the form fields
		            $form = arr::overwrite($form, $post->as_array());

		            // populate the error fields, if any
		            $errors = arr::overwrite($errors, $post->errors('comments'));
					$form_error = TRUE;
		        }
			}
			
            $this->template->content->incident_id = $incident->id;
			$this->template->content->incident_title = $incident->incident_title;
            $this->template->content->incident_description = nl2br($incident->incident_description);
			$this->template->content->incident_rating = $incident->incident_rating;
            $this->template->content->incident_location = $incident->location->location_name;
            $this->template->content->incident_latitude = $incident->location->latitude;
            $this->template->content->incident_longitude = $incident->location->longitude;
			
            $this->template->content->incident_date = date('M j Y', strtotime($incident->incident_date));
            $this->template->content->incident_time = date('H:i', strtotime($incident->incident_date));
			
            // Retrieve Categories
            $incident_category = "";
            foreach($incident->incident_category as $category) 
            { 
                $incident_category .= "<a href=\"#\">" . $category->category->category_title . "</a>&nbsp;&nbsp;&nbsp;";
            }
			$this->template->content->incident_category = $incident_category;
			
            // Retrieve Media
            $incident_news = array();
            $incident_video = array();
            $incident_photo = array();
            
            //XXX: Replace magic numbers
            foreach($incident->media as $media) 
            {
                if ($media->media_type == 4)
                {
                    $incident_news[] = $media->media_link;
                }
                elseif ($media->media_type == 2)
                {
                    $incident_video[] = $media->media_link;
                }
                elseif ($media->media_type == 1)
                {
                    $incident_photo[] = $media->media_link;
                }
            }
			
            if ( $incident->incident_verified == 1 )
            {
                $this->template->content->incident_verified = "<p><strong class=\"green\">YES</strong></p>";
            }
            else
            {
                $this->template->content->incident_verified = "<p><strong class=\"red\">NO</strong></p>";
            }

			// Retrieve Comments (Additional Information)
			$this->template->content->incident_comments = $this->_get_comments($id);
        }
		
		// Add Neighbors
		$this->template->content->incident_neighbors = $this->_get_neighbors(
				$incident->location->latitude, 
				$incident->location->longitude);
		
		// Javascript Header
		$this->template->header->map_enabled = TRUE;
		$this->template->header->js = new View('reports_view_js');
		$this->template->header->js->incident_id = $incident->id;
		$this->template->header->js->default_map = Kohana::config('settings.default_map');
		$this->template->header->js->default_zoom = Kohana::config('settings.default_zoom');
		$this->template->header->js->latitude = $incident->location->latitude;
		$this->template->header->js->longitude = $incident->location->longitude;
		$this->template->header->js->incident_photos = $incident_photo;
		// Pack the javascript using the javascriptpacker helper
		
		$myPacker = new javascriptpacker($this->template->header->js , 'Normal', false, false);
		$this->template->header->js = $myPacker->pack();
		
		// Forms
		$this->template->content->form = $form;
		$this->template->content->captcha = $captcha;
	    $this->template->content->errors = $errors;
		$this->template->content->form_error = $form_error;
	}
	
	
	/**
     * Report Thanks Page
     */
    function thanks ()
    {
        $this->template->header->this_page = 'reports_submit';
        $this->template->content = new View('reports_submit_thanks');
    }
	
		
	/**
     * Report Rating.
     * @param boolean $id If id is supplied, a rating will be applied to selected report
     */
	public function rating( $id = false )
	{
		$this->template = "";
		$this->auto_render = FALSE;
		
		if ( !$id )
        {
			echo json_encode(array("status"=>"error", "message"=>"ERROR!"));
		}
		else
		{
			if ( !empty($_POST['action']) && !empty($_POST['type']) ) {
				$action = $_POST['action'];
				$type = $_POST['type'];
				
				// Is this an ADD(+1) or SUBTRACT(-1)?
				if ($action == 'add') {
					$action = 1;
				}
				elseif ($action == 'subtract') {
					$action = -1;
				}
				else {
					$action = 0;
				}
				
				if (!empty($action) && ($type == 'original' || $type == 'comment'))
				{
					// Has this IP Address rated this post before?
					if ($type == 'original') {
						$previous = ORM::factory('rating')->where('incident_id',$id)->where('rating_ip',$_SERVER['REMOTE_ADDR'])->find();
					}
					elseif ($type == 'comment') {
						$previous = ORM::factory('rating')->where('comment_id',$id)->where('rating_ip',$_SERVER['REMOTE_ADDR'])->find();
					}
					
					$rating = new Rating_Model($previous->id);	// If previous exits... update previous vote
					// Are we rating the original post or the comments?
					if ($type == 'original') {
						$rating->incident_id = $id;
					}
					elseif ($type == 'comment') {
						$rating->comment_id = $id;
					}

					$rating->rating = $action;
					$rating->rating_ip = $_SERVER['REMOTE_ADDR'];
					$rating->rating_date = date("Y-m-d H:i:s",time());
					$rating->save();
					
					// Get total rating and send back to json
					$total_rating = $this->_get_rating($id, $type);
					
					echo json_encode(array("status"=>"saved", "message"=>"SAVED!", "rating"=>$total_rating));
				}
				else
				{
					echo json_encode(array("status"=>"error1", "message"=>"ERROR!"));
				}
			}
			else
			{
				echo json_encode(array("status"=>"error2", "message"=>"ERROR!"));
			}
		}
	}
	
	
	/**
     * Report Listing
     */
	public function _get_incidentlisting($incidents)
	{
		$html = "";
		foreach ($incidents as $incident)
		{
			$incident_id = $incident->id;
			$incident_title = $incident->incident_title;
			$incident_description = $incident->incident_description;
				// Trim to 150 characters without cutting words
				if ((strlen($incident_description) > 150) && (strlen($incident_description) > 1)) {
					$whitespaceposition = strpos($incident_description," ",145)-1;
					$incident_description = substr($incident_description, 0, $whitespaceposition);
				}
			$incident_date = date('Y-m-d', strtotime($incident->incident_date));
			$incident_location = $incident->location->location_name;
			$incident_verified = $incident->incident_verified;
				if ($incident_verified)
				{
					$incident_verified = "<span class=\"report_yes\">YES</span>";
				}
				else
				{
					$incident_verified = "<span class=\"report_no\">NO</span>";
				}
			
			$html .=	"<div class=\"report_row1\">";
            $html .=	"	<div class=\"report_thumb report_col1\">";
            $html .=	"    	&nbsp;";
            $html .=	"    </div>";
            $html .=	"    <div class=\"report_details report_col2\">";
            $html .=	"    	<h3><a href=\"" . url::base() . "reports/view/" . $incident_id . "\">" . $incident_title . "</a></h3>";
            $html .=	$incident_description . " ...";
            $html .=	"  	</div>";
            $html .=	"    <div class=\"report_date report_col3\">";
            $html .=	$incident_date;
            $html .=	"    </div>";
            $html .=	"    <div class=\"report_location report_col4\">";
            $html .=	$incident_location;
            $html .=	"    </div>";
            $html .=	"    <div class=\"report_status report_col5\">";
            $html .=	$incident_verified;
            $html .=	"    </div>";
            $html .=	"</div>";
		}
		return $html;
	}
	
	
    /*
	* Retrieves Cities
	*/
	private function _get_cities()
	{
		$cities = ORM::factory('city')->orderby('city', 'asc')->find_all();
		$city_select = array('' => 'Select A City');
		foreach ($cities as $city) {
			$city_select[$city->city_lon .  "," . $city->city_lat] = $city->city;
		}
		return $city_select;
	}
    

    //XXX: Move form html code to viewer	
	private function _get_categories($selected_categories)
	{
		// Count categories to determine column length
		$categories_total = ORM::factory('category')->where('category_visible', '1')->count_all();
        $this->template->content->categories_total = $categories_total;

		$categories = array();
		foreach (ORM::factory('category')->where('category_visible', '1')->find_all() as $category)
		{
			// Create a list of all categories
			$categories[$category->id] = array($category->category_title, $category->category_color);
		}

        //format categories for 2 column display
        $this_col = 1; // First column
        $max_col = round($categories_total/2); // Maximum number of columns
        $html= "";
        foreach ($categories as $category => $category_extra)
        {
            $category_title = $category_extra[0];
            $category_color = $category_extra[1];
            if ($this_col == 1) 
                $html.="<ul>";
        
            if (!empty($selected_categories) 
                && in_array($category, $selected_categories)) {
                $category_checked = TRUE;
            }
            else
            {
                $category_checked = FALSE;
            }
                                                                            
            $html.="\n<li><label>";
            $html.=form::checkbox('incident_category[]', $category, $category_checked, ' class="check-box"');
            $html.="$category_title";
            $html.="</label></li>";
       
            if ($this_col == $max_col) 
                $html.="\n</ul>\n";
      
            if ($this_col < $max_col)
            {
                $this_col++;
            } 
            else 
            {
                $this_col = 1;
            }
        }
        return $html;
	}
	
	
	/*
	* Retrieves Comments
	*/
	private function _get_comments($id)
	{
		if ($id)
		{
			$html = "";
			foreach(ORM::factory('comment')->where('incident_id',$id)->where('comment_active','1')->orderby('comment_date', 'asc')->find_all() as $comment)
			{
				$html .= "<div class=\"discussion-box\">";
				$html .= "<p><strong>" . $comment->comment_author . "</strong>&nbsp;(" . date('M j Y', strtotime($comment->comment_date)) . ")</p>";
				$html .= "<p>" . $comment->comment_description . "</p>";
				$html .= "<div class=\"report_rating\">";
				$html .= "	<div>";
				$html .= "	Credibility:&nbsp;";
				$html .= "	<a href=\"javascript:rating('" . $comment->id . "','add','comment','cloader_" . $comment->id . "')\"><img id=\"cup_" . $comment->id . "\" src=\"" . url::base() . 'media/img/' . "up.png\" alt=\"UP\" title=\"UP\" border=\"0\" /></a>&nbsp;";
				$html .= "	<a href=\"javascript:rating('" . $comment->id . "','subtract','comment','cloader_" . $comment->id . "')\"><img id=\"cdown_" . $comment->id . "\" src=\"" . url::base() . 'media/img/' . "down.png\" alt=\"DOWN\" title=\"DOWN\" border=\"0\" /></a>&nbsp;";
				$html .= "	</div>";
				$html .= "	<div class=\"rating_value\" id=\"crating_" . $comment->id . "\">" . $comment->comment_rating . "</div>";
				$html .= "	<div id=\"cloader_" . $comment->id . "\" class=\"rating_loading\" ></div>";
				$html .= "</div>";
				$html .= "</div>";
			}
			
			return $html;
		}
	}
	
	
	/*
	* Retrieves Total Rating For Specific Post
	* Also Updates The Incident & Comment Tables (Ratings Column)
	*/
	private function _get_rating($id = false, $type = NULL)
	{
		if (!empty($id) && ($type == 'original' || $type == 'comment'))
		{
			if ($type == 'original') {
				$which_count = 'incident_id';
			} elseif ($type == 'comment') {
				$which_count = 'comment_id';
			}
			else {
				return 0;
			}
			
			$total_rating = 0;
			// Get All Ratings and Sum them up
			foreach(ORM::factory('rating')->where($which_count,$id)->find_all() as $rating)
			{
				$total_rating += $rating->rating;
			}
			
			// Update Counts
			if ($type == 'original') {
				$incident = ORM::factory('incident', $id);
				if ($incident->loaded==true)
				{
					$incident->incident_rating = $total_rating;
					$incident->save();
				}
			} elseif ($type == 'comment') {
				$comment = ORM::factory('comment', $id);
				if ($comment->loaded==true)
				{
					$comment->comment_rating = $total_rating;
					$comment->save();
				}
			}
			
			return $total_rating;
			
		} else {
			return 0;
		}

	}
	
	
	/*
	* Retrieves Neighboring Incidents
	*/
	private function _get_neighbors($latitude = 0, $longitude = 0)
	{
		$proximity = new Proximity();
		$proximity->Proximity($latitude, $longitude, 100);		// Within 100 Miles ( or Kms ;-) )
		
		// Generate query from proximity calculator
		$radius_query = " location.latitude >= '" . $proximity->MinLatitude() . "' 
			AND location.latitude <= '" . $proximity->MaxLatitude() . "' 
			AND location.longitude >= '" . $proximity->MinLongitude() . "'
			AND location.longitude <= '" . $proximity->MaxLongitude() . "'
			AND incident_active = 1";
		$neighbors = ORM::factory('incident')
			->join('location', 'incident.location_id', 'location.id','INNER')
            ->select('incident.*')
			->where($radius_query)
			->limit('5')
			->find_all();
		
		$html = "";
		foreach($neighbors as $neighbor)
		{
			$html .= "	<li>";
	        $html .= "      <ul>";
	        $html .= "        <li class=\"w-01\"><a href=\"" . url::base() . 
				"reports/view/" . $neighbor->id . "\">" . $neighbor->incident_title . "</a></li>";
	        $html .= "        <li class=\"w-02\">" . $neighbor->location->location_name . "</li>";
	        $html .= "        <li class=\"w-03\">" . date('M j Y', strtotime($neighbor->incident_date)) . "</li>";
	        $html .= "      </ul>";
	        $html .= "    </li>";
		}
		return $html;
	}


} // End Main

