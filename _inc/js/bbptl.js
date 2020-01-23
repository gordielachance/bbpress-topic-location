var $ = jQuery.noConflict();

$(document).ready(function($){
    
    /* HANDLE LOCATION BLOCKS
     * be careful, this should work for both frontend & backend*/
    $('.bbptl_location_field').each(function() {
        
        var geoBlock = $(this);
        var label = geoBlock.find('label');
        var searchInput = geoBlock.find('input[name="bbptl_topic_geo[input]"]');
        var dataInputs = geoBlock.find('input.bbptl_topic_geodata');
        var bt_search = geoBlock.find('.bbptl_search_pos_bt');
        var bt_clear = geoBlock.find('.bbptl_clear_pos_bt');
        
        //toggle BTs display
        searchInput.bind('input propertychange', function() {
            var hasText = ( $(this).val() !== '');
            bt_clear.toggleClass('disabled',!hasText );
        });
        searchInput.trigger('propertychange'); //on load

        bt_clear.click(function(e){
            e.preventDefault();
            dataInputs.val('');
            $(this).addClass('disabled');
        });

        bt_search.click(function(e){

            e.preventDefault();
            geoBlock.find('.bbptl-feedback').remove(); //remove old notices

            //browser does not supports GEO
            if (!navigator.geolocation){
                geoBlock.bbptl_feedback(bbptlL10n.no_navigation_support);
                geoBlock.addClass('error');
                return false;
            }

            ////

            //clear existing data
            var inputVal = searchInput.val();
            bt_clear.trigger('click');
            searchInput.val(inputVal); //...restore input value

            if (inputVal){ //search input
                geoBlock.bpptl_geolocate(false,false,inputVal);
            }else{//guess location
                geoBlock.addClass('loading').removeClass('error');

                navigator.geolocation.getCurrentPosition(
                    geoSuccess,
                    geoFailure,
                    {
                        maximumAge:Infinity,
                        timeout:10000
                    }
                );
    
                function geoSuccess(location){
                    geoBlock.removeClass('loading');
                    geoBlock.bpptl_geolocate(location.coords.latitude,location.coords.longitude);
                }
                function geoFailure(error){
                    var error_msg;
                        
                    geoBlock.removeClass('loading').addClass('error');
    
                    switch(error.code){
    
                        case error.TIMEOUT:
                            error_msg=bbptlL10n.error_timeout;
                            break;
                        case error.POSITION_UNAVAILABLE:
                            error_msg=bbptlL10n.position_unavailable;
                            break;
                        case error.PERMISSION_DENIED:
                            error_msg=bbptlL10n.permission_denied;
                            break;
                        case error.UNKNOWN_ERROR:
                            error_msg=bbptlL10n.unknown_error;
                            break;
                    }
    
                    geoBlock.bbptl_feedback(error_msg);
                }
            }
        });
    });
	
});

$.fn.extend({
    bpptl_geolocate: function(lat,lng,addr) {
        return this.each(function() {

            var geoBlock = $(this);
            
            var ajax_data = {
                action: 'bbptl_get_geocoding'
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
                            geoBlock.addClass('loading');
                        },
                        success: function(json){
                            if(json.success) { //we have found an address
                                console.log(json);
                                geoBlock.find('input[name="bbptl_topic_geo[lat]"]').val(json.geodata.lat);
                                geoBlock.find('input[name="bbptl_topic_geo[lon]"]').val(json.geodata.lon);
                                geoBlock.find('input[name="bbptl_topic_geo[input]"]').val(json.geodata.input);
                            }else{
                                if (json.message){
                                    geoBlock.bbptl_feedback(json.message);
                                }else{
                                    geoBlock.bbptl_feedback('AJAX error');
                                }
                            }
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            
                            geoBlock.bbptl_feedback('AJAX error');
                            console.log(xhr.status);
                            console.log(thrownError);
                        },
                        complete: function() {
                            geoBlock.removeClass('loading');
                        }
                 });

            }
       });

   },
    bbptl_feedback: function(message) {

        console.log(message);

        var geoBlock = $(this);

        geoBlock.find('.bbptl-feedback').remove(); //remove old notices

        var block = $('<div class="bbp-template-notice bbptl-feedback"></div>');
        var list = $('<ul></ul>');
        block.append(list).appendTo( geoBlock );
        
        return this.each(function() {

            var list_item = $('<li>'+message+'</li>');
            list.append(list_item);
            
        });

   }
 });
 