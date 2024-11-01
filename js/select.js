jQuery(document).ready(function(){
jQuery('#wptp_url_stripper').change(function(){
		var val=jQuery(this).val();
		if(val==2)
		{
			jQuery('.panel').fadeIn("normal",function(){
				jQuery('.panel').removeClass('hidden');
				jQuery('.not_req').addClass('hidden');
			});
		}
		else
			{
				if(!jQuery('.panel').hasClass('hidden'))
					jQuery('.panel').fadeOut("normal",function(){
						jQuery('.panel').addClass('hidden');
						jQuery('.not_req').removeClass('hidden');
						
					});
			
			}
	});
});