<?php 

require_once 'includes/class.base.php';
require_once 'includes/class.media.php';

/**
 * @package Favicon Rotator
 * @author Archetyped
 */
class FaviconRotator extends FVRT_Base {
	
	/**
	 * Admin Page hook
	 * @var string
	 */
	var $page;
	
	/**
	 * Plugin options
	 * @var array
	 */
	var $options = null;
	
	/**
	 * Plugin Options key
	 * @var string
	 */
	var $opt_key = 'options';
	
	/**
	 * Key to use for icons array in options
	 * @var string
	 */
	var $opt_icons = 'icons';
	
	/**
	 * Save action
	 * @var string
	 */
	var $action_save = 'action_save';
	
	/**
	 * Path to admin contextual help file
	 * @var string
	 */
	var $file_admin_help = 'resources/admin_help.html';
	
	/*-** Instance objects **-*/
	
	/**
	 * Media instance
	 * @var FVRT_Media
	 */
	var $media;
	
	/*-** Initialization **-*/

	function FaviconRotator() {
		$this->__construct();
	}

	function __construct() {
		parent::__construct();
		$this->opt_key = $this->add_prefix($this->opt_key);
		$this->action_save = $this->add_prefix($this->action_save);
		$this->media =& new FVRT_Media();
		$this->register_hooks();
	}
	
	function register_hooks() {
		/*-** Admin **-*/
		
		//Menu
		add_action('admin_menu', $this->m('admin_menu'));
		//Plugins page
		add_filter('plugin_action_links_' . $this->util->get_plugin_base_name(), $this->m('admin_plugin_action_links'), 10, 4);

		/*-** Main **-*/
		
		//Template
		add_action('wp_head', $this->m('display_icon'));
		
		//Instance setup
		$this->media->register_hooks();
	}
	
	/*-** Helpers **-*/
	
	/**
	 * Retrieve options array (or specific option if specified)
	 * @param $key Option to retrieve
	 * @return mixed Full options array or value of specific key (Default: empty array)
	 */
	function get_options($key = null) {
		//Retrieve options entry from DB
		$ret = $this->options;
		if ( is_null($ret) ) {
			$ret = get_option($this->opt_key);
			if ( false === $ret )
				$ret = array();
			$this->options = $ret;
		}
				
		if ( !is_null($key) && isset($ret[$key]) )
			$ret = $ret[$key];
		return $ret;
	}
	
	function set_option($key, $val) {
		//Make sure options array initialized
		$this->get_options();
		//Set option value
		$this->options[$key] = $val;
		//Save updated options to DB
		update_option($this->opt_key, $this->options);
	}
	
	/*-** Media **-*/
	
	/**
	 * Retrieve Post IDs of favicons
	 * @return array Icon IDs
	 */
	function get_icon_ids() {
		//Get array of post IDs for icons
		$icons = $this->get_options($this->opt_icons);
		return $icons;
	}
	
	function get_icon_ids_list() {
		return implode(',', $this->get_icon_ids());
	}
	
	/**
	 * Retrieve icons saved in options menu
	 * @return array Media attachment objects
	 */
	function get_icons() {
		$icons = array();
		//Get icon ids
		$ids = $this->get_icon_ids();
		//Retrieve attachment objects
		if ( !empty($ids) ) {
			$icons = get_posts(array('post_type' => 'attachment', 'include' => $ids));
		}
		
		//Fix icon option if invalid icons were passed
		if ( count($icons) != count($ids) ) {
			$ids = array();
			foreach ( $icons as $icon ) {
				$ids[] = $icon->ID;
			}
			//Save to DB
			$this->save_icons($ids);
		}
		return $icons; 
	}
	
	/**
	 * Save list of selected icons to DB
	 * @param array $ids (optional) Array of icon IDs
	 */
	function save_icons($ids = null) {
		//Check if valid icon IDs are passed to function
		if ( !is_null($ids) ) {
			if ( !is_array($ids) ) {
				 $ids = ( is_int($ids) ) ? array($ids) : null;
			}
		}
		
		//Get icon IDs from form submission
		if ( is_null($ids) && isset($_POST['fv_ids']) && check_admin_referer($this->action_save) ) {
			$ids = explode(',', $_POST['fv_ids']);
		}
		//Save to DB
		if ( is_array($ids) ) {
			//Validate values
			$changed = false;
			foreach ( $ids as $key => $id ) {
				if ( !intval($id) ) {
					unset($ids[$key]);
					$changed = true;
				}
			}
			if ( $changed )
				$ids = array_values($ids);
			$this->set_option($this->opt_icons, $ids);
		}
	}
	
	/**
	 * Build favicon element for output in template
	 */
	function display_icon() {
		//Get icons
		$icons = $icons_orig = $this->get_icon_ids();
		$icon = null;
		//Retrieve icon data
		while ( is_null($icon) && count($icons) > 0 ) {
			//Select one from random
			$idx = ( count($icons) > 1 ) ? array_rand($icons) : 0;
			$icon_id = $icons[$idx];
			$icon = $this->media->get_icon_src($icon_id);
			if ( !$icon || ( is_string($icon) && 'ico' != substr($icon, strrpos($icon, '.') + 1) ) ) {
				//Reset variable to NULL
				$icon = null;
				//Remove icon from list (no longer valid)
				unset($icons[$idx]);
				array_values($icons);
			}
			//Load image src (for image attachments)
			if ( is_array($icon) )
				$icon = $icon[0];
		}
		
		//Display icon
		if ( !is_null($icon) ) {
			?>
			<link rel="shortcut icon" href="<?php echo esc_attr($icon); ?>" />
			<?php
		}
		
		//Update icons array (if necessary)
		if ( $icons !== $icons_orig )
			$this->save_icons($icons);
	}
	
	/*-** Admin **-*/
	
	/**
	 * Adds custom links below plugin on plugin listing page
	 * @param $actions
	 * @param $plugin_file
	 * @param $plugin_data
	 * @param $context
	 */
	function admin_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
		//Add link to settings (only if active)
		if ( is_plugin_active($this->util->get_plugin_base_name()) ) {
			$settings = __('Settings');
			$settings_url = add_query_arg('page', dirname($this->util->get_plugin_base_name()), admin_url('themes.php'));
			array_unshift($actions, '<a href="' . $settings_url . '" title="' . $settings . '">' . $settings . '</a>');
		}
		return $actions;
	}
	
	/**
	 * Get ID of settings section on admin page
	 * @return string ID of settings section
	 */
	function admin_get_settings_section() {
		return $this->add_prefix('settings');
	}
	
	/**
	 * Adds admin submenu item to Appearance menu 
	 */
	function admin_menu() {
		$this->page = $p = add_theme_page(__('Favicon'), __('Favicon'), 'edit_theme_options', $this->util->get_plugin_base(), $this->m('admin_page'));
		//Head
		add_action("admin_print_scripts-$p", $this->m('admin_scripts'));
		add_action("admin_print_styles-$p", $this->m('admin_styles'));
		add_action("admin_head-$p", $this->m('admin_help'));
	}
	
	
	/**
	 * Defines content for admin page
	 */
	function admin_page() {
		if ( ! current_user_can('edit_theme_options') )
			wp_die(__('You do not have permission to customize favicons.'));
			
		//Get saved icons
		if ( isset($_POST['fv_submit']) )
			$this->save_icons();
		$icons = $this->get_icons();
		?>
	
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e('Favicon Rotator'); ?> <a href="<?php echo $this->media->get_upload_iframe_src('image'); ?>" class="button add-new-h2 thickbox" title="<?php esc_attr_e('Add Icon'); ?>"><?php echo esc_html_x('Add new', 'file')?></a></h2>
		<div id="fv_list_container">
			<p id="fv_no_icons"<?php if ( $icons ) echo ' style="display: none;"'?>>No favicons set</p>
			<ul id="fv_list">
			<?php foreach ( $icons as $icon ) : //List icons
				$icon_src = wp_get_attachment_image_src($icon->ID, $this->icon_size);
				$icon_src = $icon_src[0];
				$src = wp_get_attachment_image_src($icon->ID, 'full');
				$src = $src[0]; 
			?>
				<li class="fv_item">
					<div>
						<img class="icon" src="<?php echo $icon_src; ?>" />
						<div class="details">
							<div class="name"><?php echo basename($src); ?></div>
							<div class="options">
								<a href="#" id="fv_id_<?php echo esc_attr($icon->ID); ?>" class="remove">Remove</a>
							</div>
						</div>
					</div>
				</li>
			<?php endforeach; //End icon listing ?>
			</ul>
			<div style="display: none">
				<li id="fv_item_temp" class="fv_item">
					<div>
						<img class="icon" src="" />
						<div class="details">
							<div class="name"></div>
							<div class="options">
								<a href="#" class="remove">Remove</a>
							</div>
						</div>
					</div>
				</li>
			</div>
		</div>
		<form method="post" action="<?php echo esc_attr($_SERVER['REQUEST_URI']); ?>">
			<input type="hidden" id="fv_ids" name="fv_ids" value="<?php echo esc_attr($this->get_icon_ids_list()); ?>" />
			<?php wp_nonce_field($this->action_save); ?>
			<p class="submit"><input type="submit" class="button-primary" name="fv_submit" value="<?php esc_attr_e( 'Save Changes' ); ?>" /></p>
		</form>
	</div>
	<?php 
	}
	
	/**
	 * Adds JS to Admin page
	 */
	function admin_scripts() {
		wp_enqueue_script('media-upload');
		$h_admin = $this->add_prefix('admin_script');
		$h_media = $this->add_prefix('media');
		wp_enqueue_script($h_admin, $this->util->get_file_url('js/admin.js'), array('jquery', 'thickbox'));
		wp_enqueue_script($h_media, $this->util->get_file_url('js/media.js'), array('jquery', $h_admin));
	}
	
	/**
	 * Adds CSS to Admin page
	 */
	function admin_styles() {
		add_thickbox();
		wp_enqueue_style($this->add_prefix('admin_styles'), $this->util->get_file_url('css/admin_styles.css'));
	}
	
	/**
	 * Add contextual help to admin page
	 */
	function admin_help() {
		$help = file_get_contents(dirname(__FILE__) . '/resources/admin_help.html');
		add_contextual_help($this->page, $help);
	}
}