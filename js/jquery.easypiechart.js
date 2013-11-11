/**!
 * easyPieChart
 * Lightweight plugin to render simple, animated and retina optimized pie charts
 *
 * @license Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php) and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 * @author Robert Fleischmann <rendro87@gmail.com> (http://robert-fleischmann.de)
 * @version 2.0.5
 **/

(function($) {
/**
 * Renderer to render the chart on a canvas object
 * @param {DOMElement} el      DOM element to host the canvas (root of the plugin)
 * @param {object}     options options object of the plugin
 */
var CanvasRenderer = function(el, options) {
	var cachedBackground;
	var canvas = document.createElement('canvas');

	if (typeof(G_vmlCanvasManager) !== 'undefined') {
		G_vmlCanvasManager.initElement(canvas);
	}

	var ctx = canvas.getContext('2d');

	canvas.width = canvas.height = options.size + 10;

	el.appendChild(canvas);

	// canvas on retina devices
	var scaleBy = 1;
	if (window.devicePixelRatio > 1) {
		scaleBy = window.devicePixelRatio;
		canvas.style.width = canvas.style.height = [options.size, 'px'].join('');
		canvas.width = canvas.height = options.size * scaleBy;
		ctx.scale(scaleBy, scaleBy);
	}

	// move 0,0 coordinates to the center
	ctx.translate(options.size / 2, options.size / 2);

	// rotate canvas -90deg
	ctx.rotate((-1 / 2 + options.rotate / 180) * Math.PI);

	var radius = (options.size - options.lineWidth) / 2;

	// IE polyfill for Date
	Date.now = Date.now || function() {
		return +(new Date());
	};

	/**
	 * Draw a circle around the center of the canvas
	 * @param  {strong} color     Valid CSS color string
	 * @param  {number} lineWidth Width of the line in px
	 * @param  {number} percent   Percentage to draw (float between 0 and 1)
	 */
	var drawCircle = function(color, lineWidth, percent, start) {
		percent = Math.min(Math.max(0, percent || 1), 1);
		if(!start) start = 0;
		start = Math.min(Math.max(0, start), 1);
		ctx.beginPath();
		ctx.arc( -5, 5, radius, Math.PI * 2 * start, Math.PI * 2 * percent, false);

		ctx.strokeStyle = color;
		ctx.lineWidth = options.lineWidth;
		ctx.shadowOffsetX = 0.5;
		 ctx.shadowOffsetY = 1;
		 ctx.shadowBlur = 5;
		 ctx.shadowColor = '#ccc';

		ctx.stroke();
	};

	/**
	 * Request animation frame wrapper with polyfill
	 * @return {function} Request animation frame method or timeout fallback
	 */
	var reqAnimationFrame = (function() {
		return  window.requestAnimationFrame ||
				window.webkitRequestAnimationFrame ||
				window.mozRequestAnimationFrame ||
				function(callback) {
					window.setTimeout(callback, 1000 / 60);
				};
	}());


	/**
	 * Clear the complete canvas
	 */
	this.clear = function() {
		ctx.clearRect( (canvas.width / -2) -5, (canvas.height / -2 ) + 5, canvas.width, canvas.height );
	};

	/**
	 * Draw the complete chart
	 * @param  {number} prctPnch Percent shown by the chart between 0 and 100
	 * @param  {number} prctTrk Percent shown by the chart between 0 and 100
	 */
	this.draw = function(prctPnch, prctTrk) {
		// do we need to render a background
		this.clear();
		ctx.lineCap = options.lineCap;

		// if pnchColor is a function execute it and pass the percent as a value
		var color;
		if (typeof(options.pnchColor) === 'function') {
			color = options.pnchColor(prctPnch);
		} else {
			color = options.pnchColor;
		}

		// draw bar
		if (prctPnch > 0) {
			drawCircle(color, options.lineWidth, prctPnch / 100);
		}
		if (prctTrk > 0) {
			drawCircle(options.trackColor, options.lineWidth, (prctTrk + prctPnch) / 100, prctPnch / 100);
		}
	}.bind(this);

	/**
	 * Animate from some percent to some other percentage
	 * @param  {number} from Starting percentage
	 * @param  {number} to   Final percentage
	 */
	this.animate = function(prctPnch, oldPrctPnch, prctTrk, oldPrctTrk) {
		var startTime = Date.now();
//		options.onStart(from, to); //onstart callback
		var animation = function() {
			var process = Math.min(Date.now() - startTime, options.animate);
			var currentValue = options.easing(this, process, oldPrctPnch, prctPnch - oldPrctPnch, options.animate);
			var currentTrack = options.easing(this, process, oldPrctTrk, prctTrk - oldPrctTrk, options.animate);
			this.draw(currentValue, currentTrack);
//			options.onStep(from, to, currentValue); //step callback
			if (process >= options.animate) {
				options.onStop(oldPrctPnch, prctPnch);
			} else {
				reqAnimationFrame(animation);
			}
		}.bind(this);

		reqAnimationFrame(animation);
	}.bind(this);
};

var EasyPieChart = function(el, opts) {
	var defaultOptions = {
		pnchColor:'#bbbbbb',
		trackColor:'#d54e21',
		lineCap:'butt',
		pickup: 0,
		pickupStart: 0,
		lineWidth:19,
		size:148,
		prctPnch:0,
		rotate: 0,
		animate: 1000,
		easing: function (x, t, b, c, d) { // more can be found here: http://gsgd.co.uk/sandbox/jquery/easing/
			t = t / (d/2);
			if (t < 1) {
					return c / 2 * t * t + b;
			}
			return -c/2 * ((--t)*(t-2) - 1) + b;
		},
		onStart: function(from, to) {
			return;
		},
		onStep: function(from, to, currentValue) {
			return;
		},
		onStop: function(from, to) {
			return;
		}
	};

	// detect present renderer
	if (typeof(CanvasRenderer) !== 'undefined') {
		defaultOptions.renderer = CanvasRenderer;
	} else if (typeof(SVGRenderer) !== 'undefined') {
		defaultOptions.renderer = SVGRenderer;
	} else {
		throw new Error('Please load either the SVG- or the CanvasRenderer');
	}

	var options = {};
	var currentValue = 0;

	/**
	 * Initialize the plugin by creating the options object and initialize rendering
	 */
	var init = function() {
		this.el = el;
		this.options = options;

		// merge user options into default options
		for (var i in defaultOptions) {
			if (defaultOptions.hasOwnProperty(i)) {
				options[i] = opts && typeof(opts[i]) !== 'undefined' ? opts[i] : defaultOptions[i];
				if (typeof(options[i]) === 'function') {
					options[i] = options[i].bind(this);
				}
			}
		}

		// check for jQuery easing
		if (typeof(options.easing) === 'string' && typeof(jQuery) !== 'undefined' && jQuery.isFunction(jQuery.easing[options.easing])) {
			options.easing = jQuery.easing[options.easing];
		} else {
			options.easing = defaultOptions.easing;
		}

		// create renderer
		this.renderer = new options.renderer(el, options);

		// initial draw
		this.renderer.draw(currentValue);

		// initial update
		if(options.prctPnch){ el.dataset.prctPnch = options.prctPnch };
		if (el.dataset && el.dataset.prctPnch) {
			this.update(parseInt(el.dataset.prctPnch, 10));
		}
	}.bind(this);

	/**
	 * Update the value of the chart
	 * @param  {number} newValue Number between 0 and 100
	 * @return {object}          Instance of the plugin for method chaining
	 */
	this.update = function(newValue) {
		newValue = parseInt(newValue, 10);
		if (options.animate) {
			this.renderer.animate(currentValue, newValue);
		} else {
			this.renderer.draw(newValue);
		}
		currentValue = newValue;
		return this;
	}.bind(this);
	
	/**
	 * Update the value of the tracked time and keep the est time
	 * @param  {number} newValue Number between 0 and 100
	 * @return {object}          Instance of the plugin for method chaining
	 */
	this.tracker = function(prctPnch, oldPrctPnch, prctTrk, oldPrctTrk) {
//		prctPnch = parseInt(prctPnch, 10);
//		prctTrk = parseInt(prctTrk, 10);
		if(prctTrk > 100) prctTrk = prctTrk%100;
		if(oldPrctTrk > 100) oldPrctTrk = oldPrctTrk%100;
		console.log( 'trackerCall'+prctPnch+':'+prctTrk);
		if(typeof oldPrctTrk !== 'undefined') {
			this.renderer.animate(prctPnch, oldPrctPnch, prctTrk, oldPrctTrk);
		} else {
			this.renderer.draw(prctPnch, prctTrk);
		}
		options:onStop = function(oldPrctPnch, prctPnch) {
			return;
		}
		currentValue = prctPnch;
		return this;
	}.bind(this);
	init();
};

$.fn.easyPieChart = function(options) {
	return this.each(function() {
		if (!$.data(this, 'easyPieChart')) {
			$.data(this, 'easyPieChart', new EasyPieChart(this, options));
		}
	});
};

}(jQuery));