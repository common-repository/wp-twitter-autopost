<div id="wpt_settings_page" class="postbox-container jcd-wide">
<div class="metabox-holder">
<div class="ui-sortable meta-box-sortables">
<div class="postbox" >
<div class="handlediv"></div>
<h3 class='hndle'><span>Advanced Settings</span></h3>
<div class="inside">
<form method="post" action="">
<table class="form-table">
<tbody>
<tr>
<th>
Replace spaces in tags  with:
</th>
<td>
<input type="text" name="wptp_replace_chars_disp" id="wptp_replace_chars_disp" value="<?php echo esc_attr( get_option('wptp_replace_chars_disp') ); ?>" size="3" />
</td>
</tr>
<tr>
</tr><tr>
<th>
Maximum number of tags to be include:
</th>
<td>
<input aria-labelledby="wptp_max_chars_disp_label" type="text" name="wptp_max_num_tags" id="wptp_max_num_tags" value="<?php echo esc_attr( get_option('wptp_max_num_tags') ); ?>" size="3" />

</td>
</tr>
<tr>
<th>
Length of post excerpt :
</th>
<td>
<input aria-labelledby="wptp_excerpt_post_label" type="text" name="wptp_excerpt_post" id="wptp_excerpt_post" size="3" maxlength="3" value="<?php echo ( esc_attr( get_option( 'wptp_excerpt_post' ) ) ) ?>" /> (<em id="wptp_excerpt_post_label">Extracted from the post. If you use the 'Excerpt' field, it will be used instead.</em>)
</td>
</tr>
<tr valign="top">
<th scope="row">Strip nonalphanumeric characters from tags:</th>
<td>
								<input type="checkbox" name="wptp_remove_noalphnum" id="wptp_remove_noalphnum" value="1" <?php echo wptwtr_chkbox_processing('wptp_remove_noalphnum'); ?> />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Use tag slug as hashtag value:</th>
							<td>
								<input type="checkbox" name="wptp_tag_usuage" id="wptp_tag_usuage" value="slug" <?php echo check_url_shortner_opt( 'wptp_tag_usuage', 'slug', 'checkbox' ); ?> /> 
							</td>
						</tr>
						<tr>
							<th>
								Max characters length for tags :
							</th>
							<td>
							 <input type="text" name="wptp_max_chars_disp" id="wptp_max_chars_disp" value="<?php echo esc_attr( get_option('wptp_max_chars_disp') ); ?>" size="3" />
							</td>
						</tr>
						<tr>
							<th>
								WP Twitter Autopost Date Format :
							</th>
							<td>
							<input type="text" aria-labelledby="date_format_label" name="wptp_twitter_date_frmat" id="wptp_twitter_date_frmat" size="12" maxlength="12" value="<?php if (get_option('wptp_twitter_date_frmat')=='') { echo ( esc_attr( stripslashes( get_option('date_format') ) ) ); } else { echo ( esc_attr( get_option( 'wptp_twitter_date_frmat' ) ) ); }?>" /> <?php if ( get_option( 'wptp_twitter_date_frmat' ) != '' ) { echo date_i18n( get_option( 'wptp_twitter_date_frmat' ) ); } else { echo "<em>".date_i18n( get_option( 'date_format' ) )."</em>"; } ?> (<em id="date_format_label">As set in general settings.</em>)
							</td>
						</tr>
						
						<tr>
							<th>
								Specify Custom field for alternate URL to be Tweeted:
							</th>
							<td>
							<input type="text" name="wptp_tweet_custom_link" id="wptp_tweet_custom_link" size="40" maxlength="120" value="<?php echo ( esc_attr( stripslashes( get_option( 'wptp_tweet_custom_link' ) ) ) ) ?>" />
							</td>
						</tr>
						
						<tr>
							<th>
								WordPress Tweet post Behaviour :
							</th>
							<td>
							
				
					<input type="checkbox" name="wptp_send_tweet_default" id="wptp_send_tweet_default" value="1" <?php echo wptwtr_chkbox_processing('wptp_send_tweet_default')?> />
					<label for="wptp_send_tweet_default">Do not post Tweets by default</label><br />
					<input type="checkbox" name="wptp_tweet_edit_action" id="wptp_tweet_edit_action" value="1" <?php echo wptwtr_chkbox_processing('wptp_tweet_edit_action')?> />
					<label for="wptp_tweet_edit_action">Do not post Tweets by default (editing only)</label><br />
					<input type="checkbox" name="wptp_post_quick_updates" id="wptp_post_quick_updates" value="1" <?php echo wptwtr_chkbox_processing('wptp_post_quick_updates')?> />
					<label for="wptp_post_quick_updates">Status updates at Quick Edit</label><br />
				</td>
						</tr>
						<tr>
							<th>
								@Author Settings :
							</th>
							<td>
				<p>
					<input aria-labelledby="author_twitter_users_label" type="checkbox" name="wptp_twitter_per_user_api" id="wptp_twitter_per_user_api" value="1" <?php echo wptwtr_chkbox_processing('wptp_twitter_per_user_api')?> />
					<label for="wptp_twitter_per_user_api">Authors have individual Twitter accounts</label>
				</p>
				<p id="author_twitter_users_label">Authors could add their username in their user profile by using <code>#account#</code> shortcode.
				</p>
							</td>
						</tr>
						
										
						<tr>
							<td colspan="2">
							<?php $nonce = wp_nonce_field('wp-twitter-autopost-nonce', '_wpnonce', true, false).wp_referer_field(false);  echo "<div>$nonce</div>"; ?>	
								<input type="hidden" name="submit-type" value="advanced" />
								<input type="submit" name="submit" value="Save Advanced Options" class="green_btn" />	
							</td>
						</tr>
						
					</tbody></table>
		<div>		

			
			<div>
			
			</div>
		
		</div>
		</form>
	</div>
	</div>
	</div>
	</div>
	</div>