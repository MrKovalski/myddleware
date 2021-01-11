// Loading animation
var loading = $( '<p><span>Waiting for server response</span></p><div class="spinner-border text-success" role="status"><span class="sr-only">Loading...</span></div>');
  $( "form" ).on('submit', function( event ) {
    $(loading).appendTo('form');
  });