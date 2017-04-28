function setIntervalAndExecute( fn, t ) {
  fn();
  return ( setInterval( fn, t ));
}

( function( $ ) {
  'use strict';

  const PARTIAL_REQUEST_ACTION_NAME = 'pci_process_data_ajax_action';

  var defaultProgressBarText = '',
    animationTimerId = 0,
    textTimerId = 0,
    progress = 1.0,
    textChangingIndex = 0,
    bar = null,
    i = 0,

    ProductsLoadHandler = {
      isRequestAlreadySend: false,

      totalNumber: 0,
      numberOfUsed: 0,
      createdNumber: 0,
      updatedNumber: 0,
      requestsNumber: 0,

      startingTime: false,
      endingTime: false,

      offset: 0,
      limit: 50,

      partialRequestsTimerId: 0,
      statsTimerId: 0,

      logStartingDateTime: function() {
        if ( !this.startingTime && !this.endingTime ) {
          this.startingTime = moment( 'now' );
          return true;
        }
        return false;
      },

      statsTimerStart: function() {
        this.statsTimerId = setIntervalAndExecute( function() {

        }, 500 );
      },

      sendPartialQuery: function() {
        if ( this.isRequestAlreadySend ) {
          return false;
        }

        if ( this.partialRequestsTimerId ) {
          clearTimeout( this.partialRequestsTimerId );
          this.partialRequestsTimerId = 0;
        }

        var self = this,
          data = {
            'action': PARTIAL_REQUEST_ACTION_NAME,
            'limit': self.limit,
            'offset': self.offset,
          };

        if ( this.offset >= this.totalNumber && this.requestsNumber ) {
          return false;
        }

        this.partialRequestsTimerId = setTimeout( function() {
          self.isRequestAlreadySend = true;
          $.post( ajaxurl, data, function( response ) {
            console.dir( response );
            self.savePartialResponse( response );
          });
        }, 250 );
      },

      savePartialResponse: function( response ) {
        this.requestsNumber++;
        self.isRequestAlreadySend = false;
      },
    };

  $( window ).load( function() {
    defaultProgressBarText = $( '#pci-default-progress-bar-text' ).text();
    var resultedBlock = $( '.product-csv-importer-resulted-block' );
    if ( resultedBlock.length ) {
      if ( $( '#product-csv-importer-progress-bar' ).length ) {
        bar = new ProgressBar.Circle( '#product-csv-importer-progress-bar', {
          strokeWidth: 7,
          easing: 'easeInOut',
          duration: 2000,
          fill: '#C8E0F4',
          color: '#E63946',
          trailColor: '#114B5F',
          trailWidth: 1,
          svgStyle: null,
          text: {
            autoStyleContainer: false,
            style: {
              color: '#114B5F',
              position: 'absolute',
              left: '50%',
              top: '50%',
              padding: 0,
              margin: 0,
              transform: {
                prefix: true,
                value: 'translate(-50%, -50%)'
              },
              fontFamily: '\'Raleway\', sans-serif',
              textTransform: 'uppercase',
              fontSize: '17px',
              width: '115px',
            },
          },
        });

        animationTimerId = setIntervalAndExecute( function() {
          bar.animate( progress );
          progress ^= 1;
        }, 2000 );

        textTimerId = setIntervalAndExecute( function() {
          var tempText = defaultProgressBarText;
          textChangingIndex = ( textChangingIndex < 3 )
            ? ( textChangingIndex + 1 )
            : 0;
          for ( i = 0; i < textChangingIndex; i++ ) {
            tempText += '.';
          }
          bar.setText( tempText );
        }, 700 );
      }
    }

    $( '.tooltip' ).tooltipster({
      animation: 'fade',
      delay: 200,
    });

    ProductsLoadHandler.logStartingDateTime();
    ProductsLoadHandler.statsTimerStart();
    ProductsLoadHandler.sendPartialQuery();

  });
})( jQuery );
