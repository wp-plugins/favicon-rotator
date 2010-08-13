<?php
require_once 'class.base.php';

/**
 * Core properties/methods for Media management
 * @package Favicon Rotator
 * @subpackage Media
 * @author Archetyped
 */
class FVRT_Media extends FVRT_Base {
	/**
	 * Prefix for all instance variables that should be prefixed
	 * @var string
	 */
	var $prefix_var = 'var_';
	
	/**
	 * Query var used to set field media is being selected for
	 * Prefix added upon instantiation
	 * @var string
	 */
	var $var_type = 'icon';
	
	/**
	 * Query var used to set media upload action
	 * Prefix added upon instantiation
	 * @var string
	 */
	var $var_action = 'action';
	
	/**
	 * ID of variable used to submit selected icon
	 * Prefix added upon instantiation
	 * @var unknown_type
	 */
	var $var_setmedia = 'setmedia';
	
	/**
	 * Mime types for favicon
	 * @var array
	 */
	var $mime_types = array('png', 'gif', 'jpg', 'x-icon');
	
	/**
	 * Arguments for upload URL building
	 * @var array
	 */
	var $upload_url_args;
	
	/**
	 * Legacy Constructor
	 */
	function FVRT_Media() {
		$this->__construct();
	}
	
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->init_vars(); 
	}
	
	/* Methods */
	
	function register_hooks() {
		//Register handler for custom media requests
		add_action('media_upload_' . $this->add_prefix('icon'), $this->m('upload_icon'));
		//Display 'Set as...' button in media item box
		add_filter('attachment_fields_to_edit', $this->m('attachment_fields_to_edit'), 11, 2);
		
		//Modify tabs in upload popup for fields
		add_filter('media_upload_tabs', $this->m('field_upload_tabs'));
		
		//Restrict file types in upload file dialog
		add_filter('upload_file_glob', $this->m('upload_file_types'));
	}
	
	/**
	 * Initialize value of instance variables
	 */
	function init_vars() {
		//Get object variables
		$vars = get_object_vars($this);
		foreach ( $vars as $var => $val ) {
			if ( strpos($var, $this->prefix_var) === 0 )
				$this->{$var} = $this->add_prefix($val);
		}
	}
	
	
	/**
	 * Sets acceptable file types for uploading
	 * Also sets File dialog description (hacky but works)
	 * @param string $types
	 * @return string Modified file types value & file dialog description
	 */
	function upload_file_types($types) {
		if ( $this->is_custom_media() ) {
			$filetypes = '*.' . implode(';*.', array('png', 'gif', 'jpg', 'ico'));
			$types = esc_js($filetypes) . '",file_types_description: "' . esc_js(__('Favicon files'));
		}
		return $types;
	}
	
	/**
	 * Modifies media upload URL to work with plugin attachments
	 * @param string $url Full admin URL
	 * @param string $path Path part of URL
	 * @return string Modified media upload URL
	 */
	function media_upload_url($url) {
		if ( strpos($url, 'media-upload.php') === 0 ) {
			$url = add_query_arg(array($this->var_action => 1, 'type' => $this->var_type, 'tab' => 'type'), $url);
		}
		return $url;
	}
	
	/**
	 * Handles upload/selecting of an icon
	 */
	function upload_icon() {
		$errors = array();
		$id = 0;
		
		//Process media selection
		if ( isset($_POST[$this->var_setmedia]) ) {
			/* Send image data to main post edit form and close popup */
			//Get Attachment ID
			$field_var = $this->add_prefix('field');
			$args = new stdClass();
			$args->id = array_shift( array_keys($_POST[$this->var_setmedia]) );
			$a =& get_post($args->id);
			//Build object of properties to send to parent page
			if ( ! empty($a) && wp_attachment_is_image($a->ID) ) {
				$args->url = wp_get_attachment_url($a->ID);
				$args->name = basename($args->url);
			}
			//Build JS Arguments string
			$arg_string = array();
			foreach ( (array)$args as $key => $val ) {
				$arg_string[] = "'$key':'$val'";
			}
			$arg_string = '{' . implode(',', $arg_string) . '}';
			?>
			<script type="text/javascript">
			/* <![CDATA[ */
			var win = window.dialogArguments || opener || parent || top;
			win.fvrt.media.setIcon(<?php echo $arg_string; ?>);
			/* ]]> */
			</script>
			<?php
			exit;
		}
		
		//Handle HTML upload
		if ( isset($_POST['html-upload']) && !empty($_FILES) ) {
			$id = media_handle_upload('async-upload', $_REQUEST['post_id']);
			//Clear uploaded files
			unset($_FILES);
			if ( is_wp_error($id) ) {
				$errors['upload_error'] = $id;
				$id = false;
			}
		}
		
		//Display default UI
					
		//Determine media type
		$type = ( isset($_REQUEST['type']) ) ? $_REQUEST['type'] : $this->var_type;
		//Determine UI to use (disk or URL upload)
		$upload_form = ( isset($_GET['tab']) && 'type_url' == $_GET['tab'] ) ? 'media_upload_type_url_form' : 'media_upload_type_form';
		//Load UI
		return wp_iframe( $upload_form, $type, $errors, $id );
	}
	
	/**
	 * Modifies array of form fields to display on Attachment edit form
	 * Array items are in the form:
	 * 'key' => array(
	 * 				  'label' => "Label Text",
	 * 				  'value' => Value
	 * 				  )
	 * 
	 * @return array Form fields to display on Attachment edit form 
	 * @param array $form_fields Associative array of Fields to display on form (@see get_attachment_fields_to_edit())
	 * @param object $attachment Attachment post object
	 */
	function attachment_fields_to_edit($form_fields, $attachment) {
		
		if ( $this->is_custom_media() ) {
			$post =& get_post($attachment);
			//Clear all form fields
			$form_fields = array();
			
			//Add "Set as Image" button (if valid attachment type)
			if ( isset($post->post_type) && strpos($post->post_mime_type, 'image/') === 0 ) {
				$set_as = __('Add icon');
				$field = array(
					'input'		=> 'html',
					'html'		=> '<input type="submit" class="button" value="' . $set_as . '" name="' . $this->var_setmedia . '[' . $post->ID . ']" />'
				);
				//Add field ID value as hidden field (if set)
				if ( isset($_REQUEST[$this->var_type]) ) {
					$field = array(
								'input'	=> 'hidden',
								'value'	=> $_REQUEST[$this->var_type]
								);
					$form_fields[$this->var_type] = $field;
				}
			} else {
				$field = array(
					'input' => 'hidden',
					'value' => ''
				);
			}
			$form_fields['buttons'] = $field;
		}
		return $form_fields;
	}
	
	/**
	 * Checks if value represents a valid media item
	 * @param int|object $media Attachment ID or Object to check
	 * @return bool TRUE if item is media, FALSE otherwise
	 */
	function is_media($media) {
		$media =& get_post($media);
		return ( ! empty($media) && 'attachment' == $media->post_type );
	}
	
	/**
	 * Checks whether current media upload/selection request is initiated by the plugin
	 */
	function is_custom_media() {
		$ret = false;
		$action = $this->var_action;
		$upload = false;
		if (isset($_REQUEST[$action]))
			$ret = true;
		else {
			$qs = array();
			$ref = parse_url($_SERVER['HTTP_REFERER']);
			if ( isset($ref['query']) )
				parse_str($ref['query'], $qs);
			if (array_key_exists($action, $qs))
				$ret = true;
		}
		
		return $ret;
	}
	
	/**
	 * Builds URI for media upload iframe
	 * @param string $type Type of media to upload
	 * @return string Upload URI
	 */
	function get_upload_iframe_src($type = 'media', $args = null) {
		//Build filter tag and callback method
		$tag = $type . '_upload_iframe_src';
		$callback =& $this->m('media_upload_url');
		//Load arguments into instance
		$this->load_upload_args($args);
		//Add filter 
		add_filter($tag, $callback);
		//Build Upload URI
		$ret = get_upload_iframe_src($type);
		//Remove filter
		remove_filter($tag, $callback);
		//Clear arguments from instance
		$this->unload_upload_args();
		//Return URI
		return $ret;
	}
	
	/**
	 * Loads upload URL arguments into instance variable
	 * @param array $args Arguments for upload URL
	 */
	function load_upload_args($args) {
		if ( !is_array($args) )
			$args = array();
		$this->upload_url_args = $args;
	}
	
	/**
	 * Clears upload URL arguments from instance variable
	 * @uses load_upload_args()
	 */
	function unload_upload_args() {
		$this->load_upload_args(null);	
	}
	
	/*-** Field-Specific **-*/
	
	/**
	 * Removes URL tab from media upload popup for fields
	 * Fields currently only support media stored @ website
	 * @param array $default_tabs Media upload tabs
	 * @see media_upload_tabs() for full $default_tabs array
	 * @return array Modified tabs array
	 */
	function field_upload_tabs($default_tabs) {
		if ( $this->is_custom_media() )
			unset($default_tabs['type_url']);
		return $default_tabs;
	}
	
	/*-** Post Attachments **-*/
	
	/**
	 * Retrieves matching attachments for post
	 * @param object|int $post Post object or Post ID (Default: current global post)
	 * @param array $args (optional) Associative array of query arguments
	 * @see get_posts() for query arguments
	 * @return array|bool Array of post attachments (FALSE on failure)
	 */
	function post_get_attachments($post = null, $args = '', $filter_special = true) {
		if (!$this->util->check_post($post))
			return false;
		global $wpdb;
		
		//Default arguments
		$defaults = array(
						'post_type'			=>	'attachment',
						'post_parent'		=>	(int) $post->ID,
						'numberposts'		=>	-1
						);
		
		$args = wp_parse_args($args, $defaults);
		
		//Get attachments
		$attachments = get_children($args);
		
		//Filter special attachments
		if ( $filter_special ) {
			$start = '[';
			$end = ']';
			$removed = false;
			foreach ( $attachments as $i => $a ) {
				if ( $start == substr($a->post_title, 0, 1) && $end == substr($a->post_title, -1) ) {
					unset($attachments[$i]);
					$removed = true;
				}
			}
			if ( $removed )
				$attachments = array_values($attachments);
		}
		
		//Return attachments
		return $attachments;
	}
	
	/**
	 * Retrieve the attachment's path
	 * Path = Full URL to attachment - site's base URL
	 * Useful for filesystem operations (e.g. file_exists(), etc.)
	 * @param object|id $post Attachment object or ID
	 * @return string Attachment path
	 */
	function get_attachment_path($post = null) {
		if (!$this->util->check_post($post))
			return '';
		//Get Attachment URL
		$url = wp_get_attachment_url($post->ID);
		//Replace with absolute path
		$path = str_replace(get_bloginfo('wpurl') . '/', ABSPATH, $url);
		return $path;
	}
	
	/**
	 * Retrieves filesize of an attachment
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 * @return int|string Filesize in bytes (@see filesize()) or as formatted string based on parameters
	 */
	function get_attachment_filesize($post = null, $formatted = true) {
		$size = 0;
		if (!$this->util->check_post($post))
			return $size;
		//Get path to attachment
		$path = $this->get_attachment_path($post);
		//Get file size
		if (file_exists($path))
			$size = filesize($path);
		if ($size > 0 && $formatted) {
			$size = (int) $size;
			$label = 'b';
			$format = "%s%s";
			//Format file size
			if ($size >= 1024 && $size < 102400) {
				$label = 'kb';
				$size = intval($size/1024);
			}
			elseif ($size >= 102400) {
				$label = 'mb';
				$size = round(($size/1024)/1024, 1);
			}
			$size = sprintf($format, $size, $label);
		}
		
		return $size;
	}
	
	/**
	 * Prints the attachment's filesize 
	 * @param obj|int $post (optional) Attachment object or ID (uses global $post object if parameter not provided)
	 * @param bool $formatted (optional) Whether or not filesize should be formatted (kb/mb, etc.) (Default: TRUE)
	 */
	function the_attachment_filesize($post = null, $formatted = true) {
		echo $this->get_attachment_filesize($post, $formatted);
	}
	
	/**
	 * Build output for media item
	 * Based on media type and output type parameter
	 * @param int|obj $media Media object or ID
	 * @param string $type (optional) Output type (Default: source URL)
	 * @return string Media output
	 */
	function get_media_output($media, $type = 'url', $attr = array()) {
		$ret = '';
		$media =& get_post($media);
		//Continue processing valid media items
		if ( $this->is_media($media) ) {
			//URL - Same for all attachments
			if ( 'url' == $type ) {
				$ret = wp_get_attachment_url($media->ID);
			} elseif ( 'link' == $type ) {
				$ret = $this->get_link($media, $attr);
			} else {
				//Determine media type
				$mime = get_post_mime_type($media);
				$mime_main = substr($mime, 0, strpos($mime, '/'));
				
				//Pass to handler for media type + output type
				$handler = implode('_', array('get', $mime_main, 'output'));
				if ( method_exists($this, $handler))
					$ret = $this->{$handler}($media, $type, $attr);
				else {
					//Default output if no handler exists
					$ret = $this->get_image_output($media, $type, $attr);
				}
			}
		}
		
		
		return apply_filters($this->add_prefix('get_media_output'), $ret, $media, $type);
	}
	
	/**
	 * Build HTML for displaying media
	 * Output based on media type (image, video, etc.)
	 * @param int|obj $media (Media object or ID)
	 * @return string HTML for media
	 */
	function get_media_html($media) {
		$out = '';
		return $out;
	}
	
	function get_link($media, $attr = array()) {
		$ret = '';
		$media =& get_post($media);
		if ( $this->is_media($media) ) {
			$attr['href'] = wp_get_attachment_url($media->ID);
			$text = ( isset($attr['text']) ) ? $attr['text'] : basename($attr['href']);
			unset($attr['text']);
			//Build attribute string
			$attr = wp_parse_args($attr, array('href' => ''));
			$attr_string = $this->util->build_attribute_string($attr);
			$ret = '<a ' . $attr_string . '>' . $text . '</a>';
		}
		return $ret;
	}
	
	/**
	 * Builds output for image attachments
	 * @param int|obj $media Media object or ID
	 * @param string $type Output type
	 * @return string Image output
	 */
	function get_image_output($media, $type = 'html', $attr = array()) {
		$ret = '';
		$icon = !wp_attachment_is_image($media->ID);
		
		//Get image properties
		$attr = wp_parse_args($attr, array('alt' => trim(strip_tags( $media->post_excerpt ))));
		list($attr['src'], $attribs['width'], $attribs['height']) = wp_get_attachment_image_src($media->ID, '', $icon);
			
		switch ( $type ) {
			case 'html' :
				$attr_str = $this->util->build_attribute_string($attr);
				$ret = '<img ' . $attr_str . ' />';
				break;
		}
		
		return $ret;
	}
	
	/**
	 * Build HTML IMG element of an Image
	 * @param array $image Array of image properties
	 * 	0:	Source URI
	 * 	1:	Width
	 * 	2:	Height
	 * @return string HTML IMG element of specified image
	 */
	function get_image_html($image, $attributes = '') {
		$ret = '';
		if (is_array($image) && count($image) >= 3) {
			//Build attribute string
			if (is_array($attributes)) {
				$attribs = '';
				$attr_format = '%s="%s"';
				foreach ($attributes as $attr => $val) {
					$attribs .= sprintf($attr_format, $attr, attribute_escape($val));
				}
				$attributes = $attribs;
			}
			$format = '<img src="%1$s" width="%2$d" height="%3$d" ' . $attributes . ' />';
			$ret = sprintf($format, $image[0], $image[1], $image[2]);
		}
		return $ret;
	}
}
?>