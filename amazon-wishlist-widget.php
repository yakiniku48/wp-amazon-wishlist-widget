<?php
/*
Plugin Name: Amazon Wishlist Widget
Plugin URI: https://github.com/yakiniku48/WordPress-Amazon-Wishlist-Widget
Description: Just an Amazon Wishlist Widget.
Author: Hideyuki Motoo
Author URI: http://motoo.net/
Version: 0.2.1
*/

class WP_AW_Widget extends WP_Widget {
	
	protected $locale = array(
		'CA' => 'amazon.ca',
		'CN' => 'amazon.cn',
		'DE' => 'amazon.de',
		'ES' => 'amazon.es',
		'FR' => 'amazon.fr',
		//'IN' => '',
		'IT' => 'amazon.it',
		'JP' => 'amazon.co.jp',
		'UK' => 'amazon.co.uk',
		'US' => 'amazon.com'
	);
	
	public function __construct() {
		parent::__construct(
			'amazon_wishlist_widget',	//id
			'Amazon Wishlist Widget',	//title
			array( 'description' => 'Just an Amazon Wishlist Widget' )
		);
		add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
	}
	
	//Sidebar
	public function widget( $args, $instance ) {
		
		$multipage_start = 0;
		$multipage_count = 10;
		$wishlist = array();
		
		$title = apply_filters( 'widget_body', $instance['title'] );
		$tracking_id = apply_filters( 'widget_body', $instance['tracking'] );
		$wishlist_id = apply_filters( 'widget_body', $instance['wishlist'] );
		$locale = apply_filters( 'widget_body', $instance['locale'] );
		
		$last_modified = (int) get_option( '_plugin_amazon_wishlist_widget_time_' . $wishlist_id );
		if (time() - $last_modified > 60 * 5) {	//every 5min
			do {
				$json = $this->get_wishlist( $wishlist_id, $locale, $multipage_start, $multipage_count );
				if (empty($json->results)) break;
				$wishlist = array_merge( $wishlist, $json->results );
				$multipage_start += $multipage_count;
			} while ( $json->NumRecords > count($wishlist) );
			update_option( '_plugin_amazon_wishlist_widget_data_' . $wishlist_id, $wishlist );
			update_option( '_plugin_amazon_wishlist_widget_time_' . $wishlist_id, time() );
		} else {
			$wishlist = get_option( '_plugin_amazon_wishlist_widget_data_' . $wishlist_id );
		}

		echo $args['before_widget'];
		
		if ($title) {
		?>
		<h1 class="widget-title"><?php echo _e($title.':'); ?></h1>
		<?php
		}
		?>
		<ul>
		<?php
		foreach ( $wishlist as $item)
		{
			$href = rtrim($item->DetailPageURL).'/?tag='.(($tracking_id) ? $tracking_id : 'yakiniku48-22');
			?>
			<li>
				<dl>
				<dt><a href="<?php echo $href; ?>" target="_blank"><?php echo $item->Title; ?></a></dt>
				<dd>
				<a href="<?php echo $href; ?>" target="_blank"><img src="<?php echo $item->ThumbImageUrl; ?>"></a>
				<?php echo $item->Subtitle; ?>
				<span class="amazon-price"><?php echo $item->Price; ?></span>
				</dd>
			</li>
			<?php
		}
		?>
		</ul>
		<?php
		echo $args['after_widget'];
	}

	//Admin panel
	public function form( $instance ) {
		
		$title = esc_attr($instance['title']);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">
			<?php echo _e('Title:'); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>">
		</p>
		
		<?php
		$wishlist = esc_attr($instance['wishlist']);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('wishlist'); ?>">
			<?php echo _e('Wishlist ID:'); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id('wishlist'); ?>" name="<?php echo $this->get_field_name('wishlist'); ?>" type="text" value="<?php echo $wishlist; ?>">
		</p>
		
		<?php
		$tracking = esc_attr($instance['tracking']);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('tracking'); ?>">
			<?php echo _e('Associate ID:'); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id('tracking'); ?>" name="<?php echo $this->get_field_name('tracking'); ?>" type="text" value="<?php echo $tracking; ?>">
		</p>
		<?php

		$locale = ($instance['locale']) ? esc_attr($instance['locale']) : 'US';
		?>
		<p>
			<label for="<?php echo $this->get_field_id('locale'); ?>">
			<?php echo _e('Market Place:'); ?>
			</label>
			<select class="widefat" id="<?php echo $this->get_field_id('locale'); ?>" name="<?php echo $this->get_field_name('locale'); ?>">
		<?php
		foreach (array_keys($this->locale) as $slate) {
			$selected = ($locale == $slate) ? 'selected="selected"' : '';
			?>
			<option value="<?php echo $slate; ?>" <?php echo $selected; ?>><?php echo $slate; ?></option>
			<?php
		}
		?>
			</select>
		</p>
		<?php
	}
	public function update( $new_instance, $old_instance ) {
		delete_option( '_plugin_amazon_wishlist_widget_time_' . $new_instance['wishlist'] );
		return $new_instance;
	}

	protected function get_wishlist( $list_id, $locale, $multipage_start, $multipage_count ) {
		
		$query .= 'http://ws.'.$this->locale[$locale].'/widgets/q';
		$query .= '?Operation=GetResults';
		$query .= '&ListId='.$list_id;
		$query .= '&multipageStart='.$multipage_start;
		$query .= '&InstanceId=0';
		$query .= '&multipageCount='.$multipage_count;
		$query .= '&Sort=DateAdded';
		$query .= '&TemplateId=8004';
		$query .= '&ServiceVersion=20070822';
		$query .= '&MarketPlace='.$locale;
		
		$jsonp = @file_get_contents( $query );
		
		if (! $jsonp) return false;
		
		if ( preg_match( '|^[\s\w]+\((.+)\)|', $jsonp, $matches ) )
		{
			$json = $matches[1];
			$json = preg_replace( '/([{,]+)(\s*)([^"]\w+?)\s*:/','$1"$3":', $json );
			return json_decode( $json );
		}
		return FALSE;
	}
	
	public function wp_enqueue_scripts() {
		$src = plugins_url( 'style.css', __FILE__ );
		wp_enqueue_style( 'amazon_wishlist_widget', $src );
	}
}

add_action( 'widgets_init', function() {
	register_widget( 'WP_AW_Widget' );
});