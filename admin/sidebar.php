<div class="wrap">

    <div id="icon-options-general" class="icon32"></div>
    <?php echo '<h2>' . __( 'CBX Poll Options', 'cbxpoll' ) . '</h2>';?>

    <div id="poststuff">

        <div id="post-body" class="metabox-holder columns-2">

            <!-- main content -->
            <div id="post-body-content">

                <div class="meta-box-sortables ui-sortable">

                    <div class="postbox">

                        <?php //echo '<h3>' . __( 'Responsive Poll Settings', 'cbxpoll' ) . '</h3>';?>

                        <div class="inside">
                            <?php
                            $settings_api->show_navigation();
                            $settings_api->show_forms();
                            ?>
                        </div> <!-- .inside -->

                    </div> <!-- .postbox -->

                </div> <!-- .meta-box-sortables .ui-sortable -->

            </div> <!-- post-body-content -->

            <!-- sidebar -->
            <div id="postbox-container-1" class="postbox-container">

                <div class="meta-box-sortables">

                    <div class="postbox">                       
                        <div class="inside">
                            <div class="postbox">
                                <h3>Plugin Info</h3>

                                <div class="inside">
                                    <p>Name : <?php echo  'CBX Poll'; ?> <?php echo 'v' . CBX_POLL_PLUGIN_VERSION; ?></p>
                                    <p>Author :
                                        <a href="http://codeboxr.com/product/cbx-poll-for-wordpress/" target="_blank">Codeboxr Team</a>
                                    </p>
                                    <p>Email : <a href="mailto:info@codeboxr.com" target="_blank">info@codeboxr.com</a></p>

                                    <p>Contact : <a href="http://codeboxr.com/contact-us.html" target="_blank">Contact Us</a></p>
                                </div>
                            </div>

                            <div class="postbox">
                                <h3><?php _e('Help & Supports','cbxpoll'); ?></h3>
                                <div class="inside">
                                    <p>Support: <a href="http://codeboxr.com/contact-us" target="_blank">Contact Us</a></p>
                                    <p><i class="icon-envelope"></i> <a href="mailto:info@codeboxr.com">info@codeboxr.com</a></p>
                                    <p><i class="icon-phone"></i> <a href="tel:008801717308615">+8801717308615</a></p>
                                </div>
                            </div>
	                        <div class="postbox">
		                        <h3><?php _e('Codeboxr Updates','cbxpoll'); ?></h3>
		                        <div class="inside">
			                        <?php

			                        include_once(ABSPATH . WPINC . '/feed.php');
			                        if (function_exists('fetch_feed')) {
				                        $feed = fetch_feed('http://codeboxr.com/products/feed/?product_cat=wpplugins');
				                        // $feed = fetch_feed('http://feeds.feedburner.com/wpboxr'); // this is the external website's RSS feed URL
				                        if (!is_wp_error($feed)) : $feed->init();
					                        $feed->set_output_encoding('UTF-8'); // this is the encoding parameter, and can be left unchanged in almost every case
					                        $feed->handle_content_type(); // this double-checks the encoding type
					                        $feed->set_cache_duration(21600); // 21,600 seconds is six hours
					                        $limit = $feed->get_item_quantity(6); // fetches the 18 most recent RSS feed stories
					                        $items = $feed->get_items(0, $limit); // this sets the limit and array for parsing the feed

					                        $blocks = array_slice($items, 0, 6); // Items zero through six will be displayed here
					                        echo '<ul>';
					                        foreach ($blocks as $block) {
						                        $url = $block->get_permalink();
						                        echo '<li><a target="_blank" href="' . $url . '">';
						                        echo '<strong>' . $block->get_title() . '</strong></a></li>';
					                        }//end foreach
					                        echo '</ul>';


				                        endif;
			                        }
			                        ?>
		                        </div>
	                        </div>

	                        <div class="postbox">
		                        <h3><?php _e('Codeboxr on facebook','cbxpoll') ?></h3>
		                        <div class="inside">
			                        <iframe src="//www.facebook.com/plugins/likebox.php?href=http%3A%2F%2Fwww.facebook.com%2Fcodeboxr&amp;width=260&amp;height=258&amp;show_faces=true&amp;colorscheme=light&amp;stream=false&amp;border_color&amp;header=false&amp;appId=558248797526834" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:260px; height:258px;" allowTransparency="true"></iframe>
		                        </div>
	                        </div>
                        </div> <!-- .meta-box-sortables -->


                    </div> <!-- .postbox -->
                </div> <!-- .meta-box-sortables -->
            </div> <!-- #postbox-container-1 .postbox-container -->
        </div> <!-- #post-body .metabox-holder .columns-2 -->
    </div> <!-- #poststuff -->
</div> <!-- .wrap -->





