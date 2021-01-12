// First page of install (requirements checker) : only display content once user clicked 'start'
var startButton = $('<button type="button" class="btn btn-success btn-lg start-btn">Start</button>');
$('.requirements-tab').hide();
  $(function() {
    $(startButton).insertBefore('.requirements-tab');
    $(startButton).css('margin-top', '20px').css('min-width', '120px');
    $(startButton).on('click', function(){
        $(startButton).hide();
        $('.requirements-tab').show();
    });
});

// Loading animation on form submission on database page & user setup page
var loading = $( '<p><span>Waiting for server response</span></p><div class="spinner-border text-success" role="status"><span class="sr-only">Loading...</span></div>');
  $( "form" ).on('submit', function( event ) {
    $(loading).appendTo('form');
  });
 