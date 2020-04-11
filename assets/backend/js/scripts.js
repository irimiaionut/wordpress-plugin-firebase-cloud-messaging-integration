( function( $ ) {
    $( document ).ready( function() {
        $( '.push_notification_button' ).on( 'click', null, function( event ) {
            event.preventDefault();
            let $button = $(this);

            let modal = getModalLoading();

            requestPushNotification($button.data( 'post_id' ),
                (message) =>{
                    setModalResponse(modal, message);
                },
                (message) => {
                    setModalResponse(modal, message);
                });
        })
    });

    function requestPushNotification(post_id, success, fail) {
        // set ajax data
        let data = {
            'action' : 'request_push_notification',
            'post_id': post_id
        };

        $.post( settings.ajaxurl, data)
        .done(function(response){
            if(response.success === true){
                success('Notification sent. Action completed.');
            }else{
                fail('Could not send notification. Please check settings in FCM Integration settings page.');
            }
        })
        .fail(function(xhr, status, error) {
            fail( 'Could not send notification. Please check logs for more details.')
        });
    }

    function getModalLoading(){
        let element = $( `
            <div class='FCM_message_holder'>
                <div class="FCM_message">
                    <span id='FCM_message'> Please wait... </span>
                    <div class="FCM_button_holder">
                        <div class="FCM_button">
                            <span id="FCM_close">Close</span>              
                        </div>
                    </div>
                </div>
            </div>` ).insertBefore( "body > div:first-of-type" )[0];
        $(element).find('#FCM_close').on( 'click', null, function( event ) {
            $(element).remove();
        });
        return element;
    }

    function setModalResponse(element, message){
        $(element).find('#FCM_message').html(message);
        $(element).find('.FCM_button').css('visibility',  'visible');
    }

})( jQuery );