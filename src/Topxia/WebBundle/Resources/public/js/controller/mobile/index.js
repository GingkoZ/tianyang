define(function(require, exports, module) {

    require("jquery.waypoints");

    exports.run = function() {

        $(".js-mobile-item").waypoint(function(){
            $(this).addClass('active');
        },{offset:500});

        $(".es-mobile .btn-mobile").click(function(){
            $('html,body').animate({
                scrollTop: $($(this).attr('data-url')).offset().top + 100
            },300);
        });

    };

});