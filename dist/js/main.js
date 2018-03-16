console.log('PLugin JS loaded');

(function($) {
    $( document ).ready(function() {

        $('.datepicker').datepicker();
        $('.datepicker').datepicker( "option", "dateFormat", "dd/mm/yy" );

        $('#add-member-to-group').on('click', function( e ) {
            e.preventDefault();
            var visible = false;

            if(visible === true){
                $('#add-member-form').fadeOut();
                visible = false;
            }
            else {
                $('#add-member-form').fadeIn();
                visible = true;
            }
        });


        //Init the modal
        $( ".dialog" ).dialog({
            autoOpen: false,
            show: {
                effect: "blind",
                duration: 500
            },
            hide: {
                effect: "explode",
                duration: 500
            }
        });

        $( "#add-event-form" ).dialog({
            autoOpen: false,
            show: {
                effect: "blind",
                duration: 500
            },
            hide: {
                effect: "explode",
                duration: 500
            }
        });

        $( ".trigger-modal" ).on( "click", function( e ) {
            e.preventDefault();
            var modalID = $(this).data('trigger');
            $( "#" + modalID ).dialog( "open" );
        });


        //Show the modal with the user information.
        $( ".trigger-user-modal" ).on( "click", function( e ) {
            e.preventDefault();
            var userID = $(this).data('userid');


            //console.log('Open the modal for user ' + userID);

            $('.user-info').remove();
            $( "#dialog" ).dialog( "open" );
            $('.loader').show();

            //Call the ajax method to the cie_user_info action
            $.ajax({
                type: 'POST',
                data: 'action=cie_user_info&userID=' + userID,
                url: '<?php echo admin_url();?>/admin-ajax.php',
                success: function(response) {
                    console.log(response);

                    $(".loader").hide();
                    $( "#dialog" ).html(response);
                }
            });
        });
    });
})( jQuery );