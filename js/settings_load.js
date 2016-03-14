
/*  loads the the template whith settings of remote database connection */
	
jQuery(document).ready(function($) {
	
	if($('#connect_remote').is(':checked')){
		$('#pch_show').css('display', 'block');
	}
	
	$('#connect_remote').click(function(){
		
	/*	$('#pch_show').css('display', 'block');  */
	
		if($('#connect_remote').is(':checked')){
		
			$("#settings_ajax").load( "http://localhost/wp44widget/wp-admin/admin.php?page=settings_ajax.php #ajax_submenu_page_callback" );

			$('#settings_ajax').css('display', 'block');
			$('#pch_show').css('display', 'none');	
		}

	});
	
	$('#connect_local').click(function(){
	
		if($('#connect_local').is(':checked')){

			$('#settings_ajax').css('display', 'none');
			$('#pch_show').css('display', 'none');
		}

	});
	
	
});
