const PARTIAL_REQUEST_ACTION_NAME = 'pci_process_data_ajax_action';
const NOT_FOUNDED_REQUEST_ACTION_NAME = 'pci_get_not_founded_products_action';

var defaultProgressBarText = '',
  animationTimerId = 0,
  textTimerId = 0,
  progress = 1.0,
  textChangingIndex = 0,
  bar = false,
  created_products_dialog = null,
  updated_products_dialog = null;

( function( $ ) {
  'use strict';

  function setIntervalAndExecute( fn, t ) {
    fn();
    return ( setInterval( fn, t ));
  }

  var ProductsLoadHandler = {
    isRequestAlreadySend: false,

    totalNumber: 0,
    processedNumber: 0,
    createdNumber: 0,
    updatedNumber: 0,
    requestsNumber: 0,

    createdSKU: [],
    updatedSKU: [],

    startingTime: false,
    endingTime: false,

    offset: 0,
    limit: 50,

    partialRequestsTimerId: 0,
    statsTimerId: 0,

    logStartingDateTime: function() {
      if ( !this.startingTime && !this.endingTime ) {
        this.startingTime = moment();
        return true;
      }
      return false;
    },

    sendPartialQuery: function() {
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
        this.endingTime = moment();
      }

      this.updateUI();

      if ( !this.endingTime ) {
        this.partialRequestsTimerId = setTimeout( function() {
          $.post( ajaxurl, data, function( response ) {
            self.savePartialResponse( response );
          });
        }, 250 );
      }
    },

    savePartialResponse: function( response ) {
      this.requestsNumber++;
      if ( typeof response.error !== 'undefined' ) {
        this.endingTime = moment();
      }
      if ( typeof response.count !== 'undefined' ) {
        this.totalNumber = response.count;
        this.createdNumber += response.count_created_products;
        this.updatedNumber += response.count_updated_products;
        this.createdSKU = this.createdSKU.concat( response.created_products_sku );
        this.updatedSKU = this.updatedSKU.concat( response.updated_products_sku );
        this.processedNumber += response.number;
        this.offset = this.offset + this.limit;
      }
      this.sendPartialQuery();
    },

    getNotFoundedProducts: function() {
      var self = this,
        data = {
          'action': NOT_FOUNDED_REQUEST_ACTION_NAME,
          'sku': self.createdSKU.concat( self.updatedSKU ),
        };
      var notFoundedList = $( '.pci-not-founded-list' );
      if ( notFoundedList.length ) {
        $.post( ajaxurl, data, function( response ) {
          console.dir( response );
          if ( Array.isArray( response )) {
            response.forEach( function( val, i, arr ) {
              var li_el = document.createElement( 'li' );
              li_el.innerHTML = '<p>' + val + '</p>';
              notFoundedList.append( li_el );
            });
          }
        });
      }
    },

    updateUI: function() {
      if ( $( '#pci-stats-total-number' ).length ) {
        /* Set importing process parameters: */
        $( '#pci-stats-total-number' ).text( this.totalNumber );
        $( '#pci-stats-processed-number' ).text( this.processedNumber );
        $( '#pci-stats-requests-number' ).text( this.requestsNumber );
        $( '#pci-stats-created-number' ).text( this.createdNumber );
        $( '#pci-stats-updated-number' ).text( this.updatedNumber );

        /* Show parsing starting time: */
        if ( this.startingTime ) {
          $( '#pci-started-time' ).text( this.startingTime.format( 'HH:mm:ss' ));
        }

        /* End of processing: */
        if ( this.endingTime ) {
          $( '#pci-ended-time' ).text( this.endingTime.format( 'HH:mm:ss' ));
          $( '.pci-working-box h2 span' ).text( 'Импорт завершен!' );
          if ( bar ) {
            clearInterval( animationTimerId );
            clearInterval( textTimerId );
            bar.text.style.fontFamily = '"Raleway", sans-serif';
            bar.text.style.fontSize = '17px';
            bar.text.style.color = '#44AF69';
            bar.path.setAttribute( 'fill', '#BBDEF0' );
            bar.path.setAttribute( 'stroke', '#44AF69' );
            bar.setText( $( '#pci-default-progress-bar-text-success' ).text());
            bar.set( 1.0 );
          }
          this.getNotFoundedProducts();
        }

        /* Errors block handle */
        $( '#pci-stats-block div' ).show();
        $( '.pci-stats-error' ).hide();
        if ( this.error ) {
          $( '#pci-stats-error' ).text( this.error );
          $( '.pci-stats-error' ).show();
        }

        /* Setting up marking code dialoges: */
        created_products_dialog = $( '#pci-created-dialog' );
        created_products_dialog.find( 'ul > li' ).remove();
        this.createdSKU.forEach( function( val, i, arr ) {
          var li_el = document.createElement( 'li' );
          li_el.innerHTML = '<p>' + val + '</p>';
          created_products_dialog.find( 'ul' ).append( li_el );
        });
        updated_products_dialog = $( '#pci-updated-dialog' );
        updated_products_dialog.find( 'ul > li' ).remove();
        this.updatedSKU.forEach( function( val, i, arr ) {
          var li_el = document.createElement( 'li' );
          li_el.innerHTML = '<p>' + val + '</p>';
          updated_products_dialog.find( 'ul' ).append( li_el );
        });
      }
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
              fontFamily: '\'Open Sans\', sans-serif',
              fontWeight: 'bold',
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
          for ( var i = 0; i < textChangingIndex; i++ ) {
            tempText += '.';
          }
          bar.setText( tempText );
        }, 700 );
      }

      $( '.tooltip' ).tooltipster({
        animation: 'fade',
        delay: 200,
      });

      $( '.pci-created-dialog-open' ).click( function() {
        $( '#pci-created-dialog' ).dialog({ width: '700px', minHeight: '500px', maxHeight: '500px' });
      });
      $( '.pci-updated-dialog-open' ).click( function() {
        $( '#pci-updated-dialog' ).dialog({ width: '700px', minHeight: '500px', maxHeight: '500px' });
      });

      ProductsLoadHandler.logStartingDateTime();
      ProductsLoadHandler.sendPartialQuery();
    }
  });
})( jQuery );
