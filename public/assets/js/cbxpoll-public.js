jQuery(document).ready(function ($) {

    //$(".cbxpoll_busy_icon").hide();

    // vote button click
    $('.cbxpoll-listing-wrap').find('.cbxpoll-listing-trig').click(function (e) {
        e.preventDefault();

        var $this       = $(this);
        var parent      = $this.parents('.cbxpoll-listing-wrap');

      

        var busy            = $this.attr("data-busy");
        var page_no         = $this.attr("data-page-no");
        var per_page        = $this.attr("data-per-page");
        var nonce           = $this.attr("data-security");

        if (parseInt(busy) == 0) {
            $this.attr("data-busy", 1);
            
            $this.find(".cbvoteajaximage").removeClass('cbvoteajaximagecustom');

            $.ajax({

                type: "post",
                dataType: "json",
                url: cbxpollpublic.ajaxurl,
                data: {
                    action: "cbxpoll_list_pagination", 
                    page_no: page_no, 
                    per_page: per_page, 
                    security: nonce
                },
                success: function (data, textStatus, XMLHttpRequest) {
                    //console.log(data);
                    $this.attr("data-busy", 0);
                    
                    
                    
                    if(data.found){
                        var content = data.content;
                        parent.find('.cbxpoll-listing').append(content);
                    }
                    
                    
                    //check if we reached at last page
                    var max_num_pages = data.max_num_pages;
                    if( (page_no == max_num_pages) || (data.found == 0) ){
                        $this.parent('.cbxpoll-listing-more').remove();
                    }
                    
                    page_no++;
                    $this.attr("data-page-no", page_no);
                    
                    $this.find(".cbvoteajaximage").addClass('cbvoteajaximagecustom');                                      
                    
                }
            });

        }

    });

      


    // vote button click
    $('.cbxpoll_answer_wrapper, .cbxpoll-listing-wrap').on('click', '.cbxpoll_vote_btn', function (e) {

        e.preventDefault();

        var _this           = $(this);
        var wrapper         = _this.parents('.cbxpoll_wrapper');
        var _this_busy      = parseInt(_this.attr("data-busy"));
        var poll_id         = _this.attr("data-post-id");
        var reference       = _this.attr("data-reference");
        var chart_type      = _this.attr("data-charttype");
        var security        = _this.attr("data-security");
        //var user_answer     = wrapper.find('input[name=cbxpoll_user_answer]:checked').serialize();
        var user_answer     = wrapper.find('input.cbxpoll_single_answer:checked').serialize();

        //console.log(user_answer);


        


        if (_this_busy == 0) {

            _this.attr("data-busy", '1');
            _this.prop("disabled", true);

            wrapper.find(".cbvoteajaximage").removeClass('cbvoteajaximagecustom');


            if (typeof user_answer != 'undefined') { // if one answer given


                wrapper.find('.cbxpoll-qresponse').hide();

                $.ajax({
                    type: "post",
                    dataType: "json",
                    url: cbxpollpublic.ajaxurl,
                    data: {
                        action: "cbxpoll_user_vote",
                        reference: reference,
                        poll_id: poll_id,
                        chart_type: chart_type,
                        user_answer: user_answer,
                        nonce: security
                    },
                    success: function (data, textStatus, XMLHttpRequest) {

                        //console.log(data);
                        if (parseInt(data.error) == 0) {

                            try { //the data for all graphs


                                if (data.show_result == '1') {

                                    wrapper.append(data.html);

                                }

                                wrapper.find('.cbxpoll-qresponse').show();
                                wrapper.find('.cbxpoll-qresponse').removeClass('cbxpoll-qresponse-alert cbxpoll-qresponse-error cbxpoll-qresponse-success');
                                wrapper.find('.cbxpoll-qresponse').addClass('cbxpoll-qresponse-success');
                                wrapper.find('.cbxpoll-qresponse').html('<p>' + data.text + '</p>');

                                wrapper.find('.cbxpoll_answer_wrapper').hide();
                            }
                            catch (e) {

                            }

                        }// end of if not voted
                        else {


                            wrapper.find('.cbxpoll-qresponse').show();
                            wrapper.find('.cbxpoll-qresponse').removeClass('cbxpoll-qresponse-alert cbxpoll-qresponse-error cbxpoll-qresponse-success');
                            wrapper.find('.cbxpoll-qresponse').addClass('cbxpoll-qresponse-error');
                            wrapper.find('.cbxpoll-qresponse').html('<p>' + data.text + '</p>');
                            wrapper.find('.cbxpoll_answer_wrapper').hide();
                        }

                        _this.attr("data-busy", '0');
                        _this.prop("disabled", false);
                        wrapper.find(".cbvoteajaximage").addClass('cbvoteajaximagecustom');
                    }//end of success
                })//end of ajax

            }
            else {
                
                //if no answer given
                _this.show();
                _this.attr("data-busy", 0);
                _this.prop("disabled", false);
                wrapper.find(".cbvoteajaximage").addClass('cbvoteajaximagecustom');

                var error_result = cbxpollpublic.noanswer_error;
                

                wrapper.find('.cbxpoll-qresponse').show();
                wrapper.find('.cbxpoll-qresponse').removeClass('cbxpoll-qresponse-alert cbxpoll-qresponse-error cbxpoll-qresponse-success');
                wrapper.find('.cbxpoll-qresponse').addClass('cbxpoll-qresponse-alert');
                wrapper.find('.cbxpoll-qresponse').html(error_result);


            }
        }// end of this data busy
    })

});

//}(jQuery));