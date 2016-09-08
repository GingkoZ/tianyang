define(function(require, exports, module) {

    var Widget = require('widget');

    var Screenfull = require('screenfull');

    var SlidePlayer = Widget.extend({
        attrs: {
            slides: [],
            index: 0,
            total: 0,
            placeholder : "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAANSURBVBhXYzh8+PB/AAffA0nNPuCLAAAAAElFTkSuQmCC",
            watermark: ''
        },

        events: {
            "click .goto-next": "onGotoNext",
            "click .goto-prev": "onGotoPrev",
            "click .goto-first": "onGotoFirst",
            "click .goto-last": "onGotoLast",
            "click .fullscreen": "onGotoFullscreen",
            "change .goto-index": "onGotoIndex"
        },

        lazyLoad: function(currentIndex) {
            for (var i = currentIndex; i < currentIndex + 4; i++) {
                if (i > this.get('total')) {
                    break;
                }
                var $slide = this._getSlide(i);
                if (!$slide.attr('src')) {
                    $slide.attr('src', $slide.data('src'));
                }
            };
        },

        _getSlide: function(index) {
            return this.$('.slide-player-body .slide:eq(' + (index - 1) + ')');
        },

        setup: function() {

            this.set('total', this.get('slides').length);
            this.$('.total').text(this.get('total'));
            var html = '';
            var placeholder = this.get('placeholder');
            var self = this;

            $.each(this.get('slides'), function(i, src) {
                html += '<img data-src="' + src + '" class="slide" data-index="' + (i+1) + '">';
            });

            this.$('.slide-player-body').html(html);

            if (this.get('watermark')) {
                this.element.append('<div class="slide-player-watermark">' + this.get('watermark') + '</div>');
            }

            $(document).on('keydown', function(event){  

                if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                  return;
                }
   
                switch(event.keyCode) {

                    case 37: {
                        self.onGotoPrev();
                        break;
                    }

                    case 39: {
                        self.onGotoNext();
                        break;
                    }

                    case 35: {
                        self.onGotoLast();
                        break;
                    }

                    case 36: {
                        self.onGotoFirst();
                        break;
                    }
                }

            });

            this.onGotoFirst();

        },

        onGotoNext: function() {
            if (this.get('index') == this.get('total')) {
                this.trigger('end');
                return ;
            }
            this.set('index', this.get('index') + 1);
        },

        onGotoPrev: function() {
            if (this.get('index') == 1) {
                return ;
            }
            this.set('index', this.get('index') - 1);
        },

        onGotoFirst: function() {
            this.set('index', 1);
        },

        onGotoLast: function() {
            this.set('index', this.get('total'));
        },

        onGotoIndex: function(e) {
            this.set('index', $(e.target).val());
        },

        onGotoFullscreen: function(event) {

            if (Screenfull) {
                if (!Screenfull.isFullscreen) {
                    Screenfull.request(this.element[0]);
                } else {
                    Screenfull.exit();
                }
            } else {
                if ($('body').hasClass('slide-player-full-window')) {
                    $('body').removeClass('slide-player-full-window');
                    this.element.removeClass('slide-player-fullscreen');
                } else {
                    $('body').addClass('slide-player-full-window');
                    this.element.addClass('slide-player-fullscreen');
                }
            }

        },

        _onChangeIndex: function(current, before) {
            var self = this;
            var current = parseInt(current);
            var before = parseInt(before);

            var placeholder = this.get('placeholder');

            if (current > this.get('total')) {
                this.$('.goto-index').val(before);
                this.set('index', before, {silent: true});
                return ;
            }

            if (current < 1) {
                this.$('.goto-index').val(before);
                this.set('index', before, {silent: true});
                return ;
            }

            if (before) {
                this.$('.slide-player-body .slide:eq(' + (before - 1) + ')').removeClass('active');
            }

            var $currentSlide = this._getSlide(current);

            if ($currentSlide.attr('src')) {
                $currentSlide.addClass('active');
            } else {
                $currentSlide.load(function() {
                    if (self.get('index') != $currentSlide.data('index')) {
                        return ;
                    }
                    $currentSlide.addClass('active');
                }); 
                $currentSlide.attr('src', $currentSlide.data('src'));
            }

            this.lazyLoad(current);

            this.$('.goto-index').val(current);

            this.set('index', current);

            this.trigger('change', {current:current, before:before});
        }

    });

    module.exports = SlidePlayer;

});