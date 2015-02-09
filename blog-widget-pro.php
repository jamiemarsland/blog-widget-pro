<?php
/**
 * Plugin Name: Blog Widget Pro
 * Plugin URI: http://pootlepress.com/
 * Description: Displays a loop of posts in a widget with pro features
 * Version: 0.7
 * Author: Shramee
 * Author URI: http://shramee.com/
 * Tested up to: 4.1
 *
 * Text Domain: blg_wid
 */

$blg_wid_plugin_dir = plugins_url( '', __FILE__ );
require_once plugin_dir_path(__FILE__) . 'inc/functions.php';

/**
 * Display a loop of posts.
 * Class Blog_Widget
 */
class Blog_Widget_Pro extends WP_Widget{
	function __construct() {
		parent::__construct(
				'Blog_Widget_Pro',
				__( 'Blog Widget Pro', 'blg_wid' ),
				array(
						'description' => __( 'Displays a posts from your Blog professionally.', 'blg_wid' ),
				)
		);
	}
	
	/**
	 * Renders the widget on the frontend
	 * @param array $args
	 * @param array $instance
	 */
	function widget( $args, $instance ) {
		if( empty( $instance['template'] ) ) return;
		if( is_admin() ) return;
	
		$template = $instance['template'];
		$query_args = $instance;
		unset($query_args['template']);
		unset($query_args['additional']);
		unset($query_args['sticky']);
		unset($query_args['title']);
	
		isset($instance['additional']) ? $query_args = wp_parse_args($instance['additional']) : "";
	
		global $wp_rewrite;
	
		if( $wp_rewrite->using_permalinks() ) {
	
			if( get_query_var('paged') ) {
				// When the widget appears on a sub page.
				$query_args['paged'] = get_query_var('paged');
			}
			elseif( strpos( $_SERVER['REQUEST_URI'], '/page/' ) !== false ) {
				// When the widget appears on the home page.
				preg_match('/\/page\/([0-9]+)\//', $_SERVER['REQUEST_URI'], $matches);
				if(!empty($matches[1])) $query_args['paged'] = intval($matches[1]);
				else $query_args['paged'] = 1;
			}
			else $query_args['paged'] = 1;
		}
		else {
			// Get current page number when we're not using permalinks
			$query_args['paged'] = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
		}
	
		switch($instance['sticky']){
			case 'ignore' :
				$query_args['ignore_sticky_posts'] = 1;
				break;
			case 'only' :
				$query_args['post__in'] = get_option( 'sticky_posts' );
				break;
			case 'exclude' :
				$query_args['post__not_in'] = get_option( 'sticky_posts' );
				break;
		}
	
		// Exclude the current post to prevent possible infinite loop
	
		global $siteorigin_panels_current_post;
	
		if( !empty($siteorigin_panels_current_post) ){
			if(!empty($query_args['post__not_in'])){
				$query_args['post__not_in'][] = $siteorigin_panels_current_post;
			}
			else {
				$query_args['post__not_in'] = array( $siteorigin_panels_current_post );
			}
		}
	
		if( !empty($query_args['post__in']) && !is_array($query_args['post__in']) ) {
			$query_args['post__in'] = explode(',', $query_args['post__in']);
			$query_args['post__in'] = array_map('intval', $query_args['post__in']);
		}

		$good_cats = array();
		for ($i = 0; $i < $instance['num_cats']; $i++){
			if(isset($instance['category'.$i])){
				$good_cats[] = $instance['category'.$i];
			}
		}
		if($good_cats){
			$query_args['cat'] = implode(',', $good_cats);
		}

		// Create the query
		$the_query = new WP_Query($query_args);
		echo $args['before_widget'];
	
		// Filter the title
		$instance['title'] = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		if ( !empty( $instance['title'] ) ) {
			echo $args['before_title'] . $instance['title'] . $args['after_title'];
		}
		
		//Our Awesome stuff
		function blg_wid_add_continue_reading_link(){
			
		}
		
		//CSS to make our awesome new options functional
		$id = $this->id;
		add_filter( 'siteorigin_panels_filter_content_enabled', array( 'SiteOrigin_Panels_Widgets_PostLoop', 'remove_content_filter' ) );
		
		print_awesome_r($good_cats);
		print_awesome_r($instance);
		global $more; $old_more = $more; $more = empty($instance['more']);
	
			while( $the_query->have_posts() ) {
				$the_query->the_post();
				echo "<article class='blog_widget_post'>";
				$instance['show_post_titles'] ? blog_widget_title() : null;
				$instance['show_image'] ? blog_widget_post_img() : null;
				$instance['all_meta'] ? blog_widget_post_meta() : null;
				$instance['show_excerpt'] ? blog_widget_post_excerpt() : null;
				$instance['more'] ? blog_widget_post_more_link() : null;
				$instance['all_meta'] ? blog_widget_post_comments() : null;
				echo "</article>";
			}

		$more = $old_more;
		remove_filter( 'siteorigin_panels_filter_content_enabled', array( 'SiteOrigin_Panels_Widgets_PostLoop', 'remove_content_filter' ) );
		echo $args['after_widget'];
	
		// Reset everything
		wp_reset_query();
	}
	
	/**
	 * @return bool
	 */
	static function remove_content_filter(){
		return false;
	}
	
	/**
	 * Update the widget
	 *
	 * @param array $new
	 * @param array $old
	 * @return array
	 */
	function update($new, $old){
		$new['more'] = !empty( $new['more'] );
		//Required to save the value from checkbox
		$new['show_excerpt'] = !empty( $new['show_excerpt'] );
		$new['show_post_titles'] = !empty( $new['show_post_titles'] );
		$new['show_image'] = !empty( $new['show_image'] );
		$new['all_meta'] = !empty( $new['all_meta'] );
		$good_cats = array();
		for ($i = 0; $i < $old['num_cats']; $i++){
			if(!isset($instance['category'.$i])){
				$new['category'.$i] = false;
			}
		}
		return $new;
	}

	/**
	 * Display the form for the post loop.
	 *
	 * @param array $instance
	 * @return string|void
	 */
	function form( $instance ) {
		$instance = wp_parse_args($instance, array(
				'title' => '',
				'template' => 'loop.php',
	
				// Query args
				'post_type' => 'post',
				'posts_per_page' => '',
	
				'order' => 'DESC',
				'orderby' => 'date',
	
				'sticky' => '',
	
				'additional' => '',
				'more' => false,
				
				//New additions
				'all_meta' => true,
				'show_excerpt' => true,
				'show_post_titles' => true,
				'show_continue_reading' => true,
				'show_image' => true,
		));
	
			// Get all the loop template files
			$post_types = get_post_types(array('public' => true));
			$post_types = array_values($post_types);
			$post_types = array_diff($post_types, array('attachment', 'revision', 'nav_menu_item'));
			$args = array(
				'orderby' => 'name',
				'order' => 'ASC'
			);
			$categories = get_categories($args);
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Title', 'blg_wid' ) ?></label>
				<input type="text" class="widefat" name="<?php echo $this->get_field_name( 'title' ) ?>" id="<?php echo $this->get_field_id( 'title' ) ?>" value="<?php echo esc_attr( $instance['title'] ) ?>">
				<input type="hidden" id="<?php echo $this->get_field_id( 'template' ) ?>" name="<?php echo $this->get_field_name( 'template' ) ?>" value="loop.php">
				<input type="hidden" id="<?php echo $this->get_field_id( 'post_type' ) ?>" name="<?php echo $this->get_field_name( 'post_type' ) ?>" value="<?php echo esc_attr($instance['post_type']) ?>" value="post"></select>
				<input type="hidden" id="<?php echo $this->get_field_id( 'num_cats' ) ?>" name="<?php echo $this->get_field_name( 'num_cats' ) ?>" value="<?php echo count($categories); ?>" value="post"></select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('posts_per_page') ?>"><?php _e('Posts Per Page', 'blg_wid') ?></label>
				<input type="text" class="small-text" id="<?php echo $this->get_field_id( 'posts_per_page' ) ?>" name="<?php echo $this->get_field_name( 'posts_per_page' ) ?>" value="<?php echo esc_attr($instance['posts_per_page']) ?>" />
			</p>
	
			<p>
				<label <?php echo $this->get_field_id('orderby') ?>><?php _e('Order By', 'blg_wid') ?></label>
				<select id="<?php echo $this->get_field_id( 'orderby' ) ?>" name="<?php echo $this->get_field_name( 'orderby' ) ?>" value="<?php echo esc_attr($instance['orderby']) ?>">
					<option value="none" <?php selected($instance['orderby'], 'none') ?>><?php esc_html_e('None', 'blg_wid') ?></option>
					<option value="ID" <?php selected($instance['orderby'], 'ID') ?>><?php esc_html_e('Post ID', 'blg_wid') ?></option>
					<option value="author" <?php selected($instance['orderby'], 'author') ?>><?php esc_html_e('Author', 'blg_wid') ?></option>
					<option value="name" <?php selected($instance['orderby'], 'name') ?>><?php esc_html_e('Name', 'blg_wid') ?></option>
					<option value="name" <?php selected($instance['orderby'], 'name') ?>><?php esc_html_e('Name', 'blg_wid') ?></option>
					<option value="date" <?php selected($instance['orderby'], 'date') ?>><?php esc_html_e('Date', 'blg_wid') ?></option>
					<option value="modified" <?php selected($instance['orderby'], 'modified') ?>><?php esc_html_e('Modified', 'blg_wid') ?></option>
					<option value="parent" <?php selected($instance['orderby'], 'parent') ?>><?php esc_html_e('Parent', 'blg_wid') ?></option>
					<option value="rand" <?php selected($instance['orderby'], 'rand') ?>><?php esc_html_e('Random', 'blg_wid') ?></option>
					<option value="comment_count" <?php selected($instance['orderby'], 'comment_count') ?>><?php esc_html_e('Comment Count', 'blg_wid') ?></option>
					<option value="menu_order" <?php selected($instance['orderby'], 'menu_order') ?>><?php esc_html_e('Menu Order', 'blg_wid') ?></option>
					<option value="menu_order" <?php selected($instance['orderby'], 'menu_order') ?>><?php esc_html_e('Menu Order', 'blg_wid') ?></option>
					<option value="post__in" <?php selected($instance['orderby'], 'post__in') ?>><?php esc_html_e('Post In Order', 'blg_wid') ?></option>
				</select>
			</p>
	
			<p>
				<label for="<?php echo $this->get_field_id('order') ?>"><?php _e('Order', 'blg_wid') ?></label>
				<select id="<?php echo $this->get_field_id( 'order' ) ?>" name="<?php echo $this->get_field_name( 'order' ) ?>" value="<?php echo esc_attr($instance['order']) ?>">
					<option value="DESC" <?php selected($instance['order'], 'DESC') ?>><?php esc_html_e('Descending', 'blg_wid') ?></option>
					<option value="ASC" <?php selected($instance['order'], 'ASC') ?>><?php esc_html_e('Ascending', 'blg_wid') ?></option>
				</select>
			</p>
	
			<p>
				<label for="<?php echo $this->get_field_id('sticky') ?>"><?php _e('Sticky Posts', 'blg_wid') ?></label>
				<select id="<?php echo $this->get_field_id( 'sticky' ) ?>" name="<?php echo $this->get_field_name( 'sticky' ) ?>" value="<?php echo esc_attr($instance['sticky']) ?>">
					<option value="" <?php selected($instance['sticky'], '') ?>><?php esc_html_e('Default', 'blg_wid') ?></option>
					<option value="ignore" <?php selected($instance['sticky'], 'ignore') ?>><?php esc_html_e('Ignore Sticky', 'blg_wid') ?></option>
					<option value="exclude" <?php selected($instance['sticky'], 'exclude') ?>><?php esc_html_e('Exclude Sticky', 'blg_wid') ?></option>
					<option value="only" <?php selected($instance['sticky'], 'only') ?>><?php esc_html_e('Only Sticky', 'blg_wid') ?></option>
				</select>
			</p>
	
			<p>
				<label for="<?php echo $this->get_field_id('more') ?>"><?php _e('More Link ', 'blg_wid') ?></label>
				<input type="checkbox" class="widefat" id="<?php echo $this->get_field_id( 'more' ) ?>" name="<?php echo $this->get_field_name( 'more' ) ?>" <?php checked( $instance['more'] ) ?> /><br/>
				<small><?php _e('If the template supports it, cut posts and display the more link.', 'blg_wid') ?></small>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id('show_image') ?>"><?php _e('Show featured image ', 'blg_wid') ?></label>
				<input type="checkbox" class="widefat" id="<?php echo $this->get_field_id( 'show_image' ) ?>" name="<?php echo $this->get_field_name( 'show_image' ) ?>" <?php checked( $instance['show_image'] ) ?> /><br/>
				<small><?php _e('Shows the posts\' featured images', 'blg_wid') ?></small>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id('show_post_titles') ?>"><?php _e('Show post title ', 'blg_wid') ?></label>
				<input type="checkbox" class="widefat" id="<?php echo $this->get_field_id( 'show_post_titles' ) ?>" name="<?php echo $this->get_field_name( 'show_post_titles' ) ?>" <?php checked( $instance['show_post_titles'] ) ?> /><br/>
				<small><?php _e('Show/Hide the post title', 'blg_wid') ?></small>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id('all_meta') ?>"><?php _e('Show Post Meta ', 'blg_wid') ?></label>
				<input type="checkbox" class="widefat" id="<?php echo $this->get_field_id( 'all_meta' ) ?>" name="<?php echo $this->get_field_name( 'all_meta' ) ?>" <?php checked( $instance['all_meta'] ) ?> /><br/>
				<small><?php _e('Show the date post was published', 'blg_wid') ?></small>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id('show_excerpt') ?>"><?php _e('Show excerpt ', 'blg_wid') ?></label>
				<input type="checkbox" class="widefat" id="<?php echo $this->get_field_id( 'show_excerpt' ) ?>" name="<?php echo $this->get_field_name( 'show_excerpt' ) ?>" <?php checked( $instance['show_excerpt'] ) ?> /><br/>
				<small><?php _e('Show excerpt', 'blg_wid') ?></small>
			</p>
			
			<p>
				<label for="<?php echo $this->get_field_id('categories') ?>"><?php _e('Show Posts from (categories) ', 'blg_wid') ?></label>
				<?php 
				$cats = $categories;
				for ($i = 0; $i < count($categories); $i++){
					if(!isset($instance['category'.$i])){
						$instance['category'.$i] = true;
					}
					?>
						<label style="margin:5px;display:block;" for="<?php echo $this->get_field_name( 'category'.$i ) ?>">
							<?php _e("{$cats[$i]->cat_name}({$cats[$i]->count} posts)", 'blg_wid') ?>
							<input type="checkbox" class="widefat" id="<?php echo $this->get_field_id( 'category'.$i ) ?>" name="<?php echo $this->get_field_name( 'category'.$i ) ?>" value="<?php echo $cats[$i]->term_id ?>" <?php checked( $instance['category'.$i] ) ?> />
						</label>
					<?php 				
				}
				?>
			</p>
			<?php
		}
		
}

function Blog_Widget_Pro_register() {
	register_widget( 'Blog_Widget_Pro' );
}
add_action( 'widgets_init', 'Blog_Widget_Pro_register' );

