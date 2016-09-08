define(function(require, exports, module) {

  var Notify = require('common/bootstrap-notify');
  var recordWatchTimeId = null;
  exports.run = function() {

    var $addBtn = $("#addExercise", window.parent.document);
    var player = smvp.players.get();
    var time = 0;
    var $doc;

    $addBtn.on('click', function() {
      
      $doc = $(this);
      player.play();
      player.pause();

    });

    player.ready(function() {
      
      player.pause(function() {

        var url = $doc.data('turl') + "?showtime=" + time;
        
          $.get(url, function(html) {

            $modal = $('#modal', window.parent.document);
            $modal.html(html);
            $modal.modal('show');

        });
      }).progress(function(e) {

        time = e.time;

      });
    });
    
  }

});