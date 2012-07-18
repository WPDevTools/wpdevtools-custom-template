<?php
/*
Plugin Name: WPDevTools Custom Content Templates
Plugin URI: http://wpdevtools.com/plugins/custom-content/
Description: Provides a template engine for custom content types without the need for theme support files
Author: Christopher Frazier, David Sutoyo
Version: 0.1
Author URI: http://wpdevtools.com/
*/

require_once dirname( __FILE__ ) . '/lib/wpdt-core/shortcodes.php';

/**
 * variable description
 *
 * @var variable type
 */
$wpdt_script_queue = array();


/**
 * WPDT_CustomTemplate
 *
 * Core functionality for the Custom Content Template plugin
 * 
 * @author Christopher Frazier, David Sutoyo
 */
class WPDT_CustomTemplate
{

	/**
	 * Grabs a template page and substitutes custom content values into the template
	 *
	 * @author Christopher Frazier
	 */
	public function filter_content ()
	{

		if (!is_admin()) {

			global $wp_query;
			global $wpdt_script_queue;
			
			if(isset($wp_query->posts) && is_array($wp_query->posts)) {
			
				foreach ($wp_query->posts as &$post) {
	
					$template_query = new WP_Query(array(
						'post_type' => 'custom_template',
						'post_status' => 'publish',
						'name' => '_' . $post->post_type
					));
					
					if ($template_query->have_posts() && locate_template('single-' . $post->post_type . '.php') == '') {
	
						$post_values = get_object_vars($post);
						
						$post_values['permalink'] = get_permalink($post->ID);
	
	
						// Grab the meta content
						$post_custom = get_post_custom($post->ID);
	
						// Unserialize attachment custom meta
						if (array_key_exists('_wp_attachment_metadata', $post_custom)) {
							$post_custom = unserialize($custom['_wp_attachment_metadata'][0]);
						}
	
						// Merge all of the collected data into one array
						$post_values = array_merge($post_values, $post_custom);
	
						// Clean up the data to make sure everything is a simple data type
						foreach ($post_values as $post_values_key => $post_values_value) {
							// Process array data
							if (is_array($post_values_value)) {
								$post_values[$post_values_key] = join($post_values_value, ",");
							}
							
							// Remove private variables
							if (strpos($post_values_key, '_') === 0) { unset($post_values[$post_values_key]); }
						}
						
						$template_meta = get_post_custom($template_query->post->ID);
						if ($template_meta['disable_wpautop'][0] == 'true') {
							remove_filter('the_content', 'wpautop');
						}
						
						// Enqueue the CSS and JS scripts for this content type
						$wpdt_script_queue[$post->post_type] = "<style>\n" . $template_meta['css'][0] . "\n</style>\n";
						$wpdt_script_queue[$post->post_type] .= "\n<script type=\"text/javascript\">\n" . $template_meta['js'][0] . "\n</script>\n";

						$post->post_content = WPDT_Shortcodes::replace_template_tags($template_query->post->post_content, $post_values);
						$post->post_excerpt = WPDT_Shortcodes::replace_template_tags($template_query->post->post_excerpt, $post_values);
						
					}
				}
			}

		}

	}

	/**
	 * Outputs enqueued template scripts
	 *
	 * @author Christopher Frazier
	 */
	public function write_template_scripts()
	{
		global $wpdt_script_queue;
		foreach ($wpdt_script_queue as $script) {
			echo $script;
		}
	}

	/**
	 * Adds system support for the template content type
	 *
	 * @author Christopher Frazier
	 */
	public function register_template_type() 
	{
		$labels = array(
			'name' => _x('Templates', 'post type general name'),
			'singular_name' => _x('Template', 'post type singular name'),
			'add_new' => _x('Add New', 'portfolio item'),
			'add_new_item' => __('Add New Template'),
			'edit_item' => __('Edit Custom Template'),
			'new_item' => __('New Template'),
			'view_item' => __('View Template'),
			'search_items' => __('Search Templates'),
			'not_found' =>  __('Nothing found'),
			'not_found_in_trash' => __('Nothing found in Trash'),
			'parent_item_colon' => ''
		);
 
		$args = array(
			'description' => 'Templates for displaying custom content data on a page.',
			'labels' => $labels,
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => true,
			'query_var' => true,
			'has_archive' => false,
			'show_in_nav_menus' => false,
			'can_export' => true,
			'rewrite' => true,
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => 66,
			'menu_icon' => plugins_url( 'images/application-blog.png' , __FILE__ ),
			'supports' => array('title','editor','excerpt')
		  ); 
 
		register_post_type( 'custom_template' , $args );
	}


	/**
	 * Calls CSS and JS scripts for admin tools
	 *
	 * @author Christopher Frazier
	 */
	public function enqueue_scripts () 
	{
		global $post;
		global $pagenow;
		
		if (is_admin() && (($pagenow == 'post.php' && get_post_type($post->ID) == 'custom_template') || ($pagenow == 'post-new.php' && $_REQUEST['post_type'] == 'custom_template'))) {
			// Set up the code coloring for the template admin
			wp_enqueue_script("codemirror", plugins_url('/lib/codemirror2/lib/codemirror.js', __FILE__));
			wp_enqueue_style("codemirror", plugins_url('/lib/codemirror2/lib/codemirror.css', __FILE__));

			wp_enqueue_script("codemirror-mode-javascript", plugins_url('/lib/codemirror2/mode/javascript/javascript.js', __FILE__));
			wp_enqueue_script("codemirror-mode-css", plugins_url('/lib/codemirror2/mode/css/css.js', __FILE__));
			wp_enqueue_style("codemirror-theme-default", plugins_url('/lib/codemirror2/theme/elegant.css', __FILE__));

			wp_enqueue_script("custom_template_admin", plugins_url('/admin.js', __FILE__), false, 'jquery');

		} else {

			// Just in case the template doesn't actually use jQuery
			wp_enqueue_script("jquery");

		}
	}


	/**
	 * Adds a meta box for the Additional Template Settings section of the template admin
	 *
	 * @author Christopher Frazier
	 */
	public function init_admin(){
		add_meta_box(
			"custom_template_css_meta", 
			"Template CSS", 
			array('WPDT_CustomTemplate','show_css_meta'), 
			"custom_template", 
			"normal", 
			"low"
		);
		add_meta_box(
			"custom_template_js_meta", 
			"Template Javascript", 
			array('WPDT_CustomTemplate','show_js_meta'), 
			"custom_template", 
			"normal", 
			"low"
		);
		add_meta_box(
			"custom_template_type_meta", 
			"Content Types", 
			array('WPDT_CustomTemplate','show_type_meta'), 
			"custom_template", 
			"side", 
			"core"
		);
		add_meta_box(
			"custom_template_admin_support", 
			"Advanced Settings", 
			array('WPDT_CustomTemplate','show_support'), 
			"custom_template", 
			"side", 
			"low"
		);

	}


	/**
	 * Displays the fields for the Additional Template Settings section of the template admin
	 *
	 * @author Christopher Frazier
	 */
	public function show_css_meta() {

		global $post;
		$custom = get_post_custom($post->ID);
	?>
		<div class="widget" style="background-color: #fff;"><textarea style="width: 100%;" rows="8" name="custom_css" id="custom_css"><?php echo $custom["css"][0]; ?></textarea></div>
		<p><strong>Note:</strong> <em>If you find your CSS is not working correctly, make sure your theme correctly calls the wp_head() function.</em></p>

	<?php
	}


	/**
	 * Displays the fields for the Additional Template Settings section of the template admin
	 *
	 * @author Christopher Frazier
	 */
	public function show_js_meta() {

		global $post;
		$custom = get_post_custom($post->ID);
	?>
		<div class="widget" style="background-color: #fff;"><textarea style="width: 100%;" rows="8" name="custom_js" id="custom_js"><?php echo $custom["js"][0]; ?></textarea></div>
		<p><strong>Note:</strong> <em>If you find your Javascript is not working correctly, make sure your theme correctly calls the wp_head() function.</em></p>

	<?php
	}


	/**
	 * Provides a GUI selector for the content type to apply this template
	 *
	 * @author Christopher Frazier
	 */
	public function show_type_meta() {

		global $post;

		$post_types = get_post_types(array(
			'public'   => true,
			'_builtin' => false
		), 'objects');

		$option_html = '';
		
		$has_content_types = false;
		
		foreach($post_types as $post_type) {

			// Set the content type availability boolean
			$has_content_types = true;

			// Create a clean option attributes string
			$option_attrs = '';

			// Check to see if the template is already assigned to a content type
			if ($post->post_name == '_' . $post_type->name) {

				$option_attrs = 'selected="true"';

			} else {

				// Check to see if the current option is available
				$template_query = new WP_Query(array(
					'name' => '_' . $post_type->name,
					'post_type' => 'custom_template'
				));

				if ($template_query->have_posts()) { $option_attrs = ' disabled="true"'; }

			}
			$option_html .= "\n<option value=\"_$post_type->name\" $option_attrs>$post_type->label</option>";
		}
	?>

<?php if ($has_content_types) : ?>

		<div class="misc-pub-section">
			<p>To enable this template, you must select a content type below.  If another template is already applied to a content type, you will be unable to select that content type.</p>
		</div>
		<div class="misc-pub-section misc-pub-section-last">
			<p><label for="content_type">Apply template to:</label>
			<select id="content_type" name="content_type">
				<option value="template-<?php echo $post->ID ?>">Unassigned</option>
			<?php echo $option_html; ?>
			</select></p>
		</div>

<?php else : ?>

		<div class="misc-pub-section misc-pub-section-last">
			<p><em>It looks like you don't have any custom content types set up.  To use the Custom Content Templates Plugin you will need some custom content types defined.</em></p>
		</div>

<?php endif; ?>

	<?php
	}


	/**
	 * Displays the fields for the Additional Template Settings section of the template admin
	 *
	 * @author Christopher Frazier
	 */
	public function show_support() {

		global $post;
		$custom = get_post_custom($post->ID);
		$disable_wpautop = '';
		if ($custom['disable_wpautop'][0] == 'true') { $disable_wpautop = 'checked="true"'; }
	?>
		<div class="misc-pub-section misc-pub-section-last">
			<p><input type="checkbox" name="disable_wpautop" id="disable_wpautop" value="true" <?php echo $disable_wpautop; ?>/> <label for="disable_wpautop">Disable automatic HTML cleanup</label></p>
		</div>
	<?php
	}


	/**
	 * Saves the fields for the Additional Template Settings section of the template admin
	 *
	 * @author Christopher Frazier
	 */
	public function save_meta(){
		global $post;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			// Do nothing?
		} else {
			if (get_post_type($post->ID) == 'custom_template') {
				update_post_meta($post->ID, "css", $_POST["custom_css"]);
				update_post_meta($post->ID, "js", $_POST["custom_js"]);
				update_post_meta($post->ID, "disable_wpautop", $_POST["disable_wpautop"]);
			}
		}
	}

	/**
	 * Sets the post_name variable based on the content type selection
	 *
	 * @author Christopher Frazier
	 */
	public function save_name($post_name){
		global $post;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			// Do nothing?
		} else {
			if (get_post_type($post->ID) == 'custom_template') {
				return $_POST["content_type"];
			} else {
				return $post_name;
			}
		}
	}
}

// WordPress Hook - Main Plugin Filter
add_action('wp', array('WPDT_CustomTemplate','filter_content'),9);
add_action('wp_head', array('WPDT_CustomTemplate','write_template_scripts'));

// WordPress Hooks - Template and Content Type Admin Tools
add_action('init', array('WPDT_CustomTemplate','register_template_type'));
add_action('admin_print_styles', array('WPDT_CustomTemplate','enqueue_scripts'));
add_action('admin_init', array('WPDT_CustomTemplate','init_admin'));
add_action('save_post', array('WPDT_CustomTemplate','save_meta'));
add_filter('name_save_pre', array('WPDT_CustomTemplate','save_name'));
