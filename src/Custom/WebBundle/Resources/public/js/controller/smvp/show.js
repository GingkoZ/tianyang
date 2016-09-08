define(function(require, exports, module) {

  var Notify = require('common/bootstrap-notify');
  var recordWatchTimeId = null;
  exports.run = function() {
    $lessonId = $("#lessonId").val();
    $courseId = $("#courseId").val();
    // var btn = parent.document.getElementById("finish-btn");
    var jqbtn = $(".finish-btn", window.parent.document);
    jqbtn.hide();

    var player = smvp.players.get();
    player.ready(function() {
      player.start(function() {
      	
        clearInterval(recordWatchTimeId);
      
        recordWatchTimeId = setInterval(recordWatchTime, 120000);
       
      }).complete(function() {
        jqbtn.show();
        $.post("/course/" + $lessonId + "/watch/paused");
      }).play(function() {
        $.post("/course/" + $lessonId + '/watch/play');
      }).pause(function() {
        $.post("/course/" + $lessonId + "/watch/paused");
      });
    });


    function recordWatchTime() {
      url = "/course/" + $lessonId + '/watch/time/2';
      $.post(url);
    }



  }

});