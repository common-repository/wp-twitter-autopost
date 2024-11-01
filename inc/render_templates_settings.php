<div id="wpt_settings_page" class="postbox-container jcd-wide">
<div class="metabox-holder">
<div class="ui-sortable meta-box-sortables">
<div class="postbox">
<div class="handlediv"><span class="screen-reader-text">Click to toggle</span></div>
<h3 class='hndle'><span>Status Update Settings</span></h3>
<div class="inside wpt-settings">
<form method="post" action="">
		<?php $nonce = wp_nonce_field('wp-twitter-autopost-nonce', '_wpnonce', true, false).wp_referer_field(false);  echo "<div>$nonce</div>"; ?>
		<div>	
			<?php //echo apply_filters('wpt_pick_shortener',''); ?>
			<?php 
				$post_types = get_post_types( array( 'public'=>true ), 'objects' );
				$wpt_settings = get_option('wptp_post_types');
				
					foreach( $post_types as $type ) {
						$name = $type->labels->name;
						$singular = $type->labels->singular_name;
						$slug = $type->name;
						if ( $slug == 'attachment' || $slug == 'nav_menu_item' || $slug == 'revision' ) {
						} else {
							$vowels = array( 'a','e','i','o','u' );
							foreach ( $vowels as $vowel ) {
								if ( strpos($name, $vowel ) === 0 ) { $word = 'an'; break; } else { $word = 'a'; }
							}
					?>
				<div class='wpt_types wpt_<?php echo $slug; ?>' id='wpt_<?php echo $slug; ?>'>
				<?php 
				if ( get_option( 'limit_categories' ) != '0' && $slug == 'post' ) {
					$falseness = get_option( 'jd_twit_cats' );
					$categories = get_option( 'tweet_categories' );
					if ( $falseness == 1 ) { 
						echo "<p>These categories are currently <strong>excluded</strong> by the deprecated WP Twitter Autopost category filters.</p>"; 
					} else {
						echo "<p>These categories are currently <strong>allowed</strong> by the deprecated WP Twitter Autopost category filters.</p>"; 				
					}
					echo "<ul>";
					foreach ( $categories as $cat ) {
						$category = get_the_category_by_ID( $cat );
						echo "<li>$category</li>";
					}
					echo "</ul>";
								
				}
				?>
				<fieldset>
				<h4><?php echo $name ?></h4>
				<div class="clear"></div>
				<div>
					<input type="checkbox" name="wptp_post_types[<?php echo $slug; ?>][post-published-update]" id="<?php echo $slug; ?>-post-published-update" value="1" <?php echo wptwtr_chkbox_processing('wptp_post_types',$slug,'post-published-update')?> />
					<label for="<?php echo $slug; ?>-post-published-update"><strong><?php printf('Update when %1$s %2$s is published',$word, $singular); ?></strong></label> <label for="<?php echo $slug; ?>-post-published-text"><br /><?php printf('Template for new %1$s updates',$name); ?></label><br /><input type="text" class="wpt-template" name="wptp_post_types[<?php echo $slug; ?>][post-published-text]" id="<?php echo $slug; ?>-post-published-text" size="60" maxlength="120" value="<?php if ( isset( $wpt_settings[$slug] ) ) { echo esc_attr( stripslashes( $wpt_settings[$slug]['post-published-text'] ) ); } ?>" />
				</div>
				<p>
					<input type="checkbox" name="wptp_post_types[<?php echo $slug; ?>][post-edited-update]" id="<?php echo $slug; ?>-post-edited-update" value="1" <?php echo wptwtr_chkbox_processing('wptp_post_types',$slug,'post-edited-update')?> />
					<label for="<?php echo $slug; ?>-post-edited-update"><strong><?php printf('Update when %1$s %2$s is edited',$word, $singular); ?></strong></label><br /><label for="<?php echo $slug; ?>-post-edited-text"><?php printf('Template for %1$s editing updates',$name); ?></label><br /><input type="text" class="wpt-template" name="wptp_post_types[<?php echo $slug; ?>][post-edited-text]" id="<?php echo $slug; ?>-post-edited-text" size="60" maxlength="120" value="<?php if ( isset( $wpt_settings[$slug] ) ) { echo esc_attr( stripslashes( $wpt_settings[$slug]['post-edited-text'] ) ); } ?>" />	
				</p>
				</fieldset>
				<?php if ( function_exists( 'wptp_list_terms' ) ) { wptp_list_terms( $slug, $name ); } ?>
				</div>
				<?php
						}
					} 
				?>
				<div class=' wpt_types wpt_links' id="wpt_links">
					<fieldset>
					<h4>Links</h4>
					
					<div class="clear"></div>
					<div>
						<input type="checkbox" name="jd_twit_blogroll" id="jd_twit_blogroll" value="1" <?php echo wptwtr_chkbox_processing('jd_twit_blogroll')?> />
						<label for="jd_twit_blogroll"><strong>Update Twitter when you post a Blogroll link</strong></label><br />				
						<label for="newlink-published-text">Text for new link updates:</label> <input aria-labelledby="newlink-published-text-label" type="text" class="wpt-template" name="newlink-published-text" id="newlink-published-text" size="60" maxlength="120" value="<?php echo ( esc_attr( stripslashes( get_option( 'newlink-published-text' ) ) ) ); ?>" /><br /><span id="newlink-published-text-label">Available shortcodes: <code>#url#</code>, <code>#title#</code>, and <code>#description#</code>.</span>
					</div>
					</fieldset>
				</div>
				<br class='clear' />
					<div>
			<input type="hidden" name="submit-type" value="options" />
			</div>
		<input type="submit" name="submit" value="Save WP Twitter Autopost Options" class="green_btn" />	
		</div>
		</form>
	</div>
	</div>
	</div>
	</div>
	<style>.postbox h3.pad_top{padding: 8px 12px;}</style>
	
	<div class="ui-sortable meta-box-sortables">
		<div class="postbox">
			<div class="handlediv"></div>
			<h3 class='hndle pad_top'>Shortcodes</h3>
		<div class="inside">
			<p>Available in post update templates:</p>
			<ul>
			<li><code>#title#</code>: the title of your blog post</li>
			<li><code>#blog#</code>: the title of your blog</li>
			<li><code>#post#</code>: a short excerpt of the post content</li>
			<li><code>#url#</code>: the post URL</li>
			<li><code>#author#</code>: the post author (@reference if available, otherwise display name)</li>
			<li><code>#displayname#</code>: post author's display name</li>
			<li><code>#account#</code>: the twitter @reference for the account (or the author, if author settings are enabled and set).</li>
			<li><code>#category#</code>: the first selected category for the post</li>
			<li><code>#cat_desc#</code>: custom value from the category description field</li>			
			<li><code>#date#</code>: the post date</li>
			<li><code>#modified#</code>: the post modified date</li>
			<li><code>#@#</code>: the twitter @reference for the author or blank, if not set</li>
			<li><code>#tags#</code>: your tags modified into hashtags. See options in the Advanced Settings section, below.</li>
			</ul>
		</div>
		</div>
	</div>
	</div>