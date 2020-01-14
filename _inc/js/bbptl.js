var $ = jQuery.noConflict();

$(document).ready(function($){
    
    /* HANDLE LOCATION BLOCKS
     * be careful, this should work for both frontend & backend*/
    $('.bbptl_location_field').each(function() {
        
        var label = $(this).find('label');
        var input = $(this).find('input:text');
        var location_helper = $('<a class="bpptl_location_helper" href="#"></a>');
        
        
        //add helper to the block
        label.append(location_helper);
        
        //switch text if input is empty or not
        input.change(function() {
            var text;
            if (!input.val()){
                text=bbptlL10n.input_empty_text;
            }else{
                text=bbptlL10n.input_not_empty_text;
            }
            location_helper.html(text);
        });

        //get the text for helper when page loads
        $(input).trigger('change');

        location_helper.click(function(){

            if (!input.val()){ //input is empty : try to get user location
                
                if (!navigator.geolocation){
                    
                    input.bpptl_geolocate_message(bbptlL10n.geo_error_navigator);
                    
                }else{
                    
                    input.addClass('loading');

                    navigator.geolocation.getCurrentPosition(

                        function (position) {
                            input.bpptl_geolocate(position.coords.latitude,position.coords.longitude);
                        }, 
                        // next function is the error callback
                        function (error){
                            
                            var error_msg;
                            
                            input.removeClass('loading');

                            switch(error.code){

                                            case error.TIMEOUT:
                                                            error_msg=bbptlL10n.geo_error_timeout;
                                                            break;
                                            case error.POSITION_UNAVAILABLE:
                                                            error_msg=bbptlL10n.geo_error_unavailable;
                                                            break;
                                            case error.PERMISSION_DENIED:
                                                            error_msg=bbptlL10n.geo_error_capability;
                                                            break;
                                            case error.UNKNOWN_ERROR:
                                                            error_msg=bbptlL10n.geo_error;
                                                            break;
                            }

                            input.bpptl_geolocate_message(error_msg);
                                
                        },{maximumAge:Infinity, timeout:10000}
                    );

                }

            }else{ //input not empty : try to validate address
                
                input.bpptl_geolocate(false,false,input.val());
                
            }
            
            return false;

        });


    });    
	
});

$.fn.extend({
    bpptl_geolocate: function(lat,lng,addr) {
        return this.each(function() {
            
            var input = $(this);
            
            var ajax_data = {
                action: 'bbptl_coords_to_address'
            };
            
            if((lat&&lng)||(addr)){ //we have enough datas


                if(lat&&lng){
                    ajax_data._bbptl_lat=lat;
                    ajax_data._bbptl_lng=lng;
                }else if(addr){
                    ajax_data._bbptl_addr=addr;
                }

                $.ajax({
                        type: "post",url: ajaxurl,data:ajax_data,
                        dataType:   'json',
                        beforeSend: function() {
                            input.addClass('loading');
                        },
                        success: function(json){

                            if(json.success) { //we have found an address
                                console.log(json);
                               input.val(json.Address);
                            }else{

                               input.bpptl_geolocate_message(bbptlL10n.geo_error_unavailable);
                            }
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            input.bpptl_geolocate_message(bbptlL10n.geo_error);
                            console.log('bpptl_geolocate error');
                            console.log(xhr.status);
                            console.log(thrownError);
                        },
                        complete: function() {
                            input.removeClass('loading');
                        }
                 });

            }
       });

   },
    bpptl_geolocate_message: function(message) {

        this.siblings('.bbp-template-notice').remove(); //remove old notices

        var block = $('<div class="bbp-template-notice"></div>');
        var list = $('<ul></ul>');
        block.append(list).insertBefore(this);
        
        return this.each(function() {
            
            console.log(message);

            var list_item = $('<li>'+message+'</li>');
            list.append(list_item);
            
        });

   }
 });
 