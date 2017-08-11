

jQuery(document).ready(function($) {

     // adding color picker in backend  meta box
    //$('._responsivesmartpoll_answer_color').wpColorPicker();

    // style the radio yes no
    $(".cbxpollmetadatepicker").datetimepicker({
            dateFormat: "yy-mm-dd",
            timeFormat:"HH:mm:ss"
        });

    //$(".cb-colorpicker").wpColorPicker();
    $(".cbxpoll_answer_color").wpColorPicker();
    $(".cbxpoll-colorpicker").wpColorPicker();

    //$('.wp-color-picker-field').wpColorPicker();


    $('.chosen-select').chosen();

    /*
    var config = {
        '.chosen'           : {},
        '.chosen-deselect'  : {allow_single_deselect:true},
        '.chosen-no-single' : {disable_search_threshold:10},
        '.chosen-no-results': {no_results_text:'Oops, nothing found!'},
        '.chosen-width'     : {width:'100%'}
    }



    for (var selector in config) {
        $(selector).chosen(config[selector]);
    }

    */

    var selected_array =[];
    $( "select.multiselect" )
        .change(function () {
            var root = $(this).parents('.text');
            $(root).find(".multi-select").each(function(){
                $(this).removeAttr('checked','checked');

            });

            $(root).find( "select option:selected" ).each(function() {
                var str = $( this ).val();

                selected_array.push(str);
                $(root).find(".multi-select").each(function(){
                    if($(this).val() ==str ){
                        // console.log($(this).val());
                        $(this).attr('checked','checked');
                    }
                });
            });
            // $( "div.text" ).text( str );
        })
        .change();


    /*

    //initialize chosen plugin option
    var config = {
        '.chosen-select'           : {},
        '.chosen-select-deselect'  : {allow_single_deselect:true},
        '.chosen-select-no-single' : {disable_search_threshold:10},
        '.chosen-select-no-results': {no_results_text:'Oops, nothing found!'},
        '.chosen-select-width'     : {width:"95%"}
    }

    for (var selector in config) {
        $(selector).chosen(config[selector]);
    }
    */




    // Switches option sections

    $('.group').hide();
    var activetab = '';
    if (typeof(localStorage) != 'undefined' ) {
        activetab = localStorage.getItem("cbpollactivetab");
    }
    if (activetab != '' && $(activetab).length ) {
        $(activetab).fadeIn();
    } else {
        $('.group:first').fadeIn();
    }
    $('.group .collapsed').each(function(){
        $(this).find('input:checked').parent().parent().parent().nextAll().each(
            function(){
                if ($(this).hasClass('last')) {
                    $(this).removeClass('hidden');
                    return false;
                }
                $(this).filter('.hidden').removeClass('hidden');
            });
    });

    if (activetab != '' && $(activetab + '-tab').length ) {
        $(activetab + '-tab').addClass('nav-tab-active');
    }
    else {
        $('.nav-tab-wrapper a:first').addClass('nav-tab-active');
    }
    $('.nav-tab-wrapper a').click(function(evt) {
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active').blur();
        var clicked_group = $(this).attr('href');
        if (typeof(localStorage) != 'undefined' ) {
            localStorage.setItem("cbpollactivetab", $(this).attr('href'));
        }
        $('.group').hide();
        $(clicked_group).fadeIn();
        evt.preventDefault();
    });

    //adding sortable
    if($('#cbx_poll_answers_items').length){


        $("#cbx_poll_answers_items").sortable({
            group: 'no-drop',
            placeholder: "cbx_poll_items cbx_poll_items_placeholder",
            handle: '.cbpollmoveicon',
            onDragStart: function ($item, container, _super) {
                // Duplicate items of the no drop area
                if(!container.options.drop)
                    $item.clone().insertAfter($item);
                _super($item, container);
            }
        });

    }
});