<!-- This file is used to markup the administration form of the widget. -->

<!-- Custom  Title Field -->
<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title',"cbxpoll"); ?></label>

	<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
</p>
<p>
<?php
$poll_display_methods = cbxpollHelper::cbxpoll_display_options();

?>
	<label for="<?php echo $this->get_field_id( 'chart_type' ); ?>"><?php _e( 'Chart Type',"cbxpoll"); ?></label>
	<select class="widefat" id="<?php echo $this->get_field_id( 'chart_type' ); ?>" name="<?php echo $this->get_field_name( 'chart_type' ); ?>">
		<?php
		 foreach($poll_display_methods as $key => $method){
			 echo '<option value="'.$key.'" '.selected($chart_type, $key, false).'>'.$method['title'].'</option>';
		 }
		?>
	</select>
</p>
<p>
	<label for="<?php echo $this->get_field_id( 'poll_id' ); ?>"><?php _e( 'Select Poll',"cbxpoll"); ?></label>
	<select class="widefat" id="<?php echo $this->get_field_id( 'poll_id' ); ?>" name="<?php echo $this->get_field_name( 'poll_id' ); ?>">
		<?php
		$args = array(
			'post_type'      => 'cbxpoll',
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		$my_query = new WP_Query($args);
		// var_dump($my_query);

		while ($my_query->have_posts()) : $my_query->the_post();
			$post_id = get_the_ID();
			echo  '<option value="'.$post_id.'" '.selected($post_id, $poll_id, false).'>'.sprintf(__('Poll ID: %d - %s', 'cbxpoll'), $post_id, get_the_title()) . '</a></option>';
		endwhile;
		wp_reset_query(); //needed

		?>
	</select>
</p>
<input type="hidden" id="<?php echo $this->get_field_id( 'submit' ); ?>" name="<?php echo $this->get_field_name( 'submit' ); ?>" value="1" />
<?php
do_action('cbxpollsinglewidget_form_admin', $instance, $this)
?>