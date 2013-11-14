jQuery(document).ready(function($){

	// Create our graph from the data table and specify a container to put the graph in
	var chart1 = $('#chart1'),
		chart2 = $('#capchart1');
	if(	chart1.length > 0 ) {
		createGraph('#data-table1', '#chart1');
	}
	if(	chart2.length > 0 ) {
		createCapCal('#data-table1', '#capchart1');
		$('.bar-group .bar div').hover(function(){
			var cont = 	$("span", this).html();								
			$(this).parent().addClass('change').attr('data-content', cont)
		}, function() {
			$(this).parent().removeClass('change');
		});
	}
	// Here be graphs
	function createCapCal(data, container) {
		// Declare some common variables and container elements	
		var bars = [];
		var figureContainer = $('<div id="figure"></div>');
		var graphContainer = $('<div class="graph"></div>');
		var scrollContainer = $('<div class="scroll"></div>');
		var barContainer = $('<div class="bars"></div>');
		var data = $(data);
		var container = $(container);
		var chartData;		
		var chartYMax;
		var columnGroups;
		
		// Timer variables
		var barTimer;
		var graphTimer;
		// Create table data object
		var tableData = {
			// Get numerical data from table cells
			chartData: function() {
				var chartData = [];
				data.find('tbody td').each(function() {
					var total = $(this).data('total');
					chartData.push(total);
				});
				return chartData;
			},
			// Get heading data from table caption
			chartHeading: function() {
				var chartHeading = data.find('caption').text();
				return chartHeading;
			},
			// Get legend data from table body
			chartLegend: function() {
				var chartLegend = [];
				// Find th elements in table body - that will tell us what items go in the main legend
				data.find('tbody th').each(function() {
					chartLegend.push($(this).text());
				});
				return chartLegend;
			},
			// Get highest value for y-axis scale
			chartYMax: function() {
				var chartData = this.chartData();
				// Round off the value
				var chartYMax = Math.ceil(Math.max.apply(Math, chartData));
				return chartYMax;
			},
			// Get y-axis data from table cells
			yLegend: function() {
				var chartYMax = this.chartYMax();
				var yLegend = [];
				// Number of divisions on the y-axis
				var yAxisMarkings = chartYMax + 1;
				// Add required number of y-axis markings in order from 0 - max
				for (var i = 0; i < yAxisMarkings; i++) {
					yLegend.unshift(((chartYMax * i) / (yAxisMarkings - 1)) );
				}
				return yLegend;
			},
			// Get x-axis data from table header
			xLegend: function() {
				var xLegend = [];
				// Find th elements in table header - that will tell us what items go in the x-axis legend
				data.find('thead th').each(function() {
					xLegend.push( $(this).text() );
				});
				return xLegend;
			},
			xClass: function() {
				var xClass = [];
				// Find th elements in table header - that will tell us what items go in the x-axis legend
				data.find('thead th').each(function() {
					xClass.push( $(this).attr('class') );
				});
				return xClass;
			},
			yClass: function() {
				var yClass = [];
				// Find th elements in table header - that will tell us what items go in the x-axis legend
				data.find('tbody th').each(function() {
					yClass.push( $(this).attr('class') );
				});
				return yClass;
			},
			// Sort data into groups based on number of columns
			columnGroups: function() {
				var columnGroups = [];
				// Get number of columns from first row of table body
				var columns = data.find('tbody tr:eq(0) td').length;
				for (var i = 0; i < columns; i++) {
					columnGroups[i] = [];
					data.find('tbody tr').each(function() {
						var barObj = {};
						barObj.total = $(this).find('td').eq(i).data('total');
						barObj.html = $(this).find('td').eq(i).html();
						columnGroups[i].push( barObj );
					});
				}
				return columnGroups;
			}
		}
		// Useful variables for accessing table data		
		chartData = tableData.chartData();		
		chartYMax = tableData.chartYMax();
		columnGroups = tableData.columnGroups();
		yClass = tableData.yClass();
		xClass = tableData.xClass();

		// Construct the graph
		// Loop through column groups, adding bars as we go
		$.each(columnGroups, function(i) {
			// Create bar group container
			var barGroup = $('<div class="bar-group ' + xClass[i] + '"></div>');
			// Add bars inside each column
			for (var j = 0, k = columnGroups[i].length; j < k; j++) {
				// Create bar object to store properties (label, height, code etc.) and add it to array
				// Set the height later in displayGraph() to allow for left-to-right sequential display
				var barObj = {};
				barObj.label = this[j]['html'];
				barObj.total = this[j]['total'];
				barObj.bar = $('<div class="bar">' + barObj.label + '</div>')
					.appendTo(barGroup);
				bars.push(barObj);
			}
			// Add bar groups to graph
			barGroup.appendTo(barContainer);			
		});
		
		// Add bars to graph
		barContainer.appendTo(scrollContainer);		
		
		// Add heading to graph
		var chartHeading = tableData.chartHeading();
		var heading = $('<h4>' + chartHeading + '</h4>');
		heading.appendTo(figureContainer);
		
		// Add x-axis to graph
		var xLegend	= tableData.xLegend();		
		var xAxisList	= $('<ul class="x-axis"></ul>');
		$.each(xLegend, function(i) {			
			var listItem = $('<li><span>' + this + '</span></li>')
				.appendTo(xAxisList);
		});
		xAxisList.appendTo(scrollContainer);
		
		// Add y-axis to graph	
		var yLegend	= tableData.yLegend();
		var yAxisList	= $('<ul class="y-axis"></ul>');
		var yLiClass = ' class="max"';
		$.each(yLegend, function(i) {	
			if(this < 7) yLiClass = ''; // set class for unhealthy hours
			var listItem = $('<li'+ yLiClass +'><span>' + this + '</span></li>')
				.appendTo(yAxisList);
		});
		yAxisList.appendTo(graphContainer);		
		
		// Add graph to graph container		
		graphContainer.appendTo(figureContainer);
		
		// Add legend to graph
		var chartLegend	= tableData.chartLegend();
		var legendList	= $('<ul class="legend"></ul>');
		$.each(chartLegend, function(i) {			
			var listItem = $('<li><span class="icon ' + yClass[i] + '"></div></span>' + this + '</li>')
				.appendTo(legendList);
		});
		legendList.appendTo(figureContainer);
		
		// Add graph container to main container
		figureContainer.appendTo(container);

		// Add scrollcontainer to graph container		
		scrollContainer.appendTo(graphContainer);

		var	yHeight = (175 - chartYMax - 1 )/(chartYMax);
		// 100/chartYMax+"%";
		//alert(yHeight);
		$(".y-axis li").height( yHeight );
		
		var barGWidth = 13*yClass.length;
		$('.bar-group, .x-axis li').width(barGWidth);
		barContainer.width((columnGroups.length*(barGWidth+8))+30);
		xAxisList.width((columnGroups.length*(barGWidth+8))+30);

// Set individual height of bars
		function displayGraph(bars, i) {
			// Changed the way we loop because of issues with $.each not resetting properly
			if (i < bars.length) {
				// Add transition properties and set height via CSS
				var barHeight = bars[i].total / chartYMax * 100,
					barTop = 100 - barHeight;
				$(bars[i].bar).css({'height' : barHeight + '%', 'top': barTop + '%', '-webkit-transition': 'all 0.3s ease-out'}).find('div').each(function() {
					var spanheight = $("strong", this).text();
					$(this).height( ( spanheight / bars[i].total * 100) + '%' ).html('<span>'+ $(this).text() +'</span>'); //'
				});
				// Wait the specified time then run the displayGraph() function again for the next bar
				barTimer = setTimeout(function() {
					i++;				
					displayGraph(bars, i);
				}, 20);
			}
		}
		
		// Reset graph settings and prepare for display
		function resetGraph() {
			// Set bar height to 0 and clear all transitions
			$.each(bars, function(i) {
				$(bars[i].bar).stop().css({'height': 0, '-webkit-transition': 'none'});
			});
			
			// Clear timers
			clearTimeout(barTimer);
			clearTimeout(graphTimer);
			
			// Restart timer		
			graphTimer = setTimeout(function() {		
				displayGraph(bars, 0);
			}, 200);
		}
		
		// Helper functions
		
		// Call resetGraph() when button is clicked to start graph over
		$('#reset-graph-button').click(function() {
			resetGraph();
			return false;
		});
		
		// Finally, display graph via reset function
		resetGraph();
	} // end capacity calendar
	
	
	// Here be graphs
	function createGraph(data, container) {
		// Declare some common variables and container elements	
		var bars = [];
		var figureContainer = $('<div id="figure"></div>');
		var graphContainer = $('<div class="graph"></div>');
		var scrollContainer = $('<div class="scroll"></div>');
		var barContainer = $('<div class="bars"></div>');
		var data = $(data);
		var container = $(container);
		var chartData;		
		var chartYMax;
		var columnGroups;
		
		// Timer variables
		var barTimer;
		var graphTimer;
		
		// Create table data object
		var tableData = {
			// Get numerical data from table cells
			chartData: function() {
				var chartData = [];
				data.find('tbody td').each(function() {
					chartData.push($(this).text());
				});
				return chartData;
			},
			// Get heading data from table caption
			chartHeading: function() {
				var chartHeading = data.find('caption').text();
				return chartHeading;
			},
			// Get legend data from table body
			chartLegend: function() {
				var chartLegend = [];
				// Find th elements in table body - that will tell us what items go in the main legend
				data.find('tbody th').each(function() {
					chartLegend.push($(this).text());
				});
				return chartLegend;
			},
			// Get highest value for y-axis scale
			chartYMax: function() {
				var chartData = this.chartData();
				// Round off the value
				var chartYMax = Math.ceil(Math.max.apply(Math, chartData));
				return chartYMax;
			},
			// Get y-axis data from table cells
			yLegend: function() {
				var chartYMax = this.chartYMax();
				var yLegend = [];
				// Number of divisions on the y-axis
				var yAxisMarkings = chartYMax + 1;
				// Add required number of y-axis markings in order from 0 - max
				for (var i = 0; i < yAxisMarkings; i++) {
					yLegend.unshift(((chartYMax * i) / (yAxisMarkings - 1)) );
				}
				return yLegend;
			},
			// Get x-axis data from table header
			xLegend: function() {
				var xLegend = [];
				// Find th elements in table header - that will tell us what items go in the x-axis legend
				data.find('thead th').each(function() {
					xLegend.push( $(this).text() );
				});
				return xLegend;
			},
			xClass: function() {
				var xClass = [];
				// Find th elements in table header - that will tell us what items go in the x-axis legend
				data.find('thead th').each(function() {
					xClass.push( $(this).attr('class') );
				});
				return xClass;
			},
			yClass: function() {
				var yClass = [];
				// Find th elements in table header - that will tell us what items go in the x-axis legend
				data.find('tbody th').each(function() {
					yClass.push( $(this).attr('class') );
				});
				return yClass;
			},
			// Sort data into groups based on number of columns
			columnGroups: function() {
				var columnGroups = [];
				// Get number of columns from first row of table body
				var columns = data.find('tbody tr:eq(0) td').length;
				for (var i = 0; i < columns; i++) {
					columnGroups[i] = [];
					data.find('tbody tr').each(function() {
						columnGroups[i].push($(this).find('td').eq(i).text());
					});
				}
				return columnGroups;
			}
		}
		
		// Useful variables for accessing table data		
		chartData = tableData.chartData();		
		chartYMax = tableData.chartYMax();
		columnGroups = tableData.columnGroups();
		yClass = tableData.yClass();
		xClass = tableData.xClass();

		// Construct the graph
		
		// Loop through column groups, adding bars as we go
		$.each(columnGroups, function(i) {
			// Create bar group container
			var barGroup = $('<div class="bar-group ' + xClass[i] + '"></div>');
			// Add bars inside each column
			for (var j = 0, k = columnGroups[i].length; j < k; j++) {
				// Create bar object to store properties (label, height, code etc.) and add it to array
				// Set the height later in displayGraph() to allow for left-to-right sequential display
				var barObj = {};
				barObj.label = this[j];
				barObj.measure = Math.floor(barObj.label / chartYMax * 100);
				barObj.height = barObj.measure + '%';
				barObj.top = 100 - barObj.measure + '%';
				barObj.bar = $('<div class="bar ' + yClass[j] + '"><span>' + barObj.label + '</span></div>')
					.appendTo(barGroup);
				bars.push(barObj);
			}
			// Add bar groups to graph
			barGroup.appendTo(barContainer);			
		});
		
		// Add bars to graph
		barContainer.appendTo(scrollContainer);		
		
		// Add heading to graph
		var chartHeading = tableData.chartHeading();
		var heading = $('<h4>' + chartHeading + '</h4>');
		heading.appendTo(figureContainer);
		
		// Add x-axis to graph
		var xLegend	= tableData.xLegend();		
		var xAxisList	= $('<ul class="x-axis"></ul>');
		$.each(xLegend, function(i) {			
			var listItem = $('<li><span>' + this + '</span></li>')
				.appendTo(xAxisList);
		});
		xAxisList.appendTo(scrollContainer);
		
		// Add y-axis to graph	
		var yLegend	= tableData.yLegend();
		var yAxisList	= $('<ul class="y-axis"></ul>');
		var yLiClass = ' class="max"';
		$.each(yLegend, function(i) {	
			if(this < 7) yLiClass = ''; // set class for unhealthy hours
			var listItem = $('<li'+ yLiClass +'><span>' + this + '</span></li>')
				.appendTo(yAxisList);
		});
		yAxisList.appendTo(graphContainer);		
		
		// Add graph to graph container		
		graphContainer.appendTo(figureContainer);
		
		// Add legend to graph
		var chartLegend	= tableData.chartLegend();
		var legendList	= $('<ul class="legend"></ul>');
		$.each(chartLegend, function(i) {			
			var listItem = $('<li><span class="icon ' + yClass[i] + '"></div></span>' + this + '</li>')
				.appendTo(legendList);
		});
		legendList.appendTo(figureContainer);
		
		// Add graph container to main container
		figureContainer.appendTo(container);

		// Add scrollcontainer to graph container		
		scrollContainer.appendTo(graphContainer);

		var	yHeight = (175 - chartYMax - 1 )/(chartYMax);
		// 100/chartYMax+"%";
		//alert(yHeight);
		$(".y-axis li").height( yHeight );
		
		var barGWidth = 13*yClass.length;
		$('.bar-group, .x-axis li').width(barGWidth);
		barContainer.width((columnGroups.length*(barGWidth+8))+30);
		xAxisList.width((columnGroups.length*(barGWidth+8))+30);

// Set individual height of bars
		function displayGraph(bars, i) {
			// Changed the way we loop because of issues with $.each not resetting properly
			if (i < bars.length) {
				// Add transition properties and set height via CSS
				$(bars[i].bar).css({'height': bars[i].height, 'top': bars[i].top, '-webkit-transition': 'all 0.3s ease-out'});
				// Wait the specified time then run the displayGraph() function again for the next bar
				barTimer = setTimeout(function() {
					i++;				
					displayGraph(bars, i);
				}, 1);
			}
		}
		
		// Reset graph settings and prepare for display
		function resetGraph() {
			// Set bar height to 0 and clear all transitions
			$.each(bars, function(i) {
				$(bars[i].bar).stop().css({'height': 0, '-webkit-transition': 'none'});
			});
			
			// Clear timers
			clearTimeout(barTimer);
			clearTimeout(graphTimer);
			
			// Restart timer		
			graphTimer = setTimeout(function() {		
				displayGraph(bars, 0);
			}, 10);
		}
		
		// Helper functions
		
		// Call resetGraph() when button is clicked to start graph over
		$('#reset-graph-button').click(function() {
			resetGraph();
			return false;
		});
		
		// Finally, display graph via reset function
		resetGraph();
	}	



	$( ".datepicker" ).datepicker({
		changeMonth: true,
		changeYear: true,
		dateFormat: "D, MM d, yy"
	}); 
	$( ".smDtPicker" ).datepicker({
		changeMonth: true,
		changeYear: true,
		dateFormat: "yy/mm/dd"
	}); 

	$('select[name=pt-dept-name]').change(function() { // change capabilities  for proj types
		$('#tasks, .proj-type .buttons').show('fast');
		var users = deptUseLst[$(this).attr('value')].split(",");										
			$('#create-proj-type .task .caps li').hide();
		for (var i = 0; i < users.length; i++) {
			$('.caps .'+users[i]+'-cap').show();
			}
	});
	$('select[name=old-dept]').change(function() { //change checkboxes for depts
		$('#edit-dept > input[type="checkbox"]').attr('checked',false);
		$('ul.cust-caps').hide();
		var users = deptUseLst[$(this).attr('value')].split(",");
		for (var i = 0; i < users.length; i++) {
			$('#edit-dept input[value="'+users[i]+'"]').attr('checked','checked').next().next('ul').show();
			}
		var dept = $(this).attr('value');
		var caps = userCaps;
		for (var key in caps) {
			if(caps.hasOwnProperty(key)) {
				caps = $(caps[key]).split(",");
				console.log(caps);
			}
		}
		// console.log(users);
	});
	$('#edit-dept > input[type="checkbox"]').click(function() {
		var check = $(this);
		if(check.is(':checked')){
			check.next().next('ul').show();
		}else{
			check.next().next('ul').hide();
		}
	});
	$('.cli-edit').click(function () {// Reveal client edit form
		$("div.hidden").css('display','none'); 
		$(this).parent().next("div.hidden").css('display','block'); 
	});
	$('#tasks').on("click", ".delete-task", function(e){
		e.preventDefault();
		var tid = $(this).closest('tr.task').data('tid');
		if(confirm('Delete Task? '+tid)){
			if($('#add-mstone').hasClass('editproj')){ // for edit project form create a hidden input for deleted tasks
				if ($(this).closest('tr.task').data('tstat') != 'newtask') {
					$('<input type="hidden" name="deletetask[]" value="'+ tid +'" />').insertAfter('#add-mstone');
				}
			}
			$(this).closest('tr.task').remove();
		}								 
	});
	$('#tasks').on("click", ".delete-mstone", function(e){
		e.preventDefault();
		var mstone = $(this).closest('table.t8-pm-mstone')
		var mid = mstone.data('mid');
		if(confirm('Delete Task? '+ mid)){
			if($('#add-mstone').hasClass('editproj')){ // for edit project form create a hidden input for deleted tasks
				mstone.find('tr.task').each(function() {
					if ($(this).data('tstat') != 'newtask') {
						var tid = $(this).data('tid');
						$('<input type="hidden" name="deletetask[]" value="'+ tid +'" />').insertAfter('#add-mstone');
					}
				});
				$('<input type="hidden" name="deletemstone[]" value="'+ mid +'" />').insertAfter('#add-mstone');
			}
			mstone.fadeOut(600, function() { $(this).remove(); } );
		}								 
	});
	var intRegex = /^\d+$/;
	var floatRegex = /^((\d+(\.\d *)?)|((\d*\.)?\d+))$/;
	$('#tasks').on('change', 'input.mstone-hours', function () {// CREATE/EDIT project types: validate est_hours input and update project total
		$(this).prev("p").remove(); // remove the span that may have been placed in an error
		var str = $(this).val();
		if(intRegex.test(str) || floatRegex.test(str)) {
			var tothours = 0;
			$("input.mstone-hours").each(function () {
				tothours = parseFloat(tothours) + parseFloat($(this).val());
			});
			var multiplier = $('#price span').html();
			var price = parseFloat(tothours) * parseFloat(multiplier);
			$("#tot-hours").html(tothours+'hrs');
			$("#price strong").html('$'+price);
		}else{
			$(this).val("").before("<p>Estimated time must be numeric in increments of 0.25</p>");
			setTimeout(function () { $(this).focus() }, 50);
		} 
	});
	function mstoneHourtotals(){
		$("table.t8-pm-mstone").each(function () { //go through each milestone and update total times
			var tothours = 0;
			var mstoneid = $(this).attr('id');
			$(this).find("input.task-esthours").each(function () {
				var thesehours = $(this).val();
				thesehours = thesehours || 0
				tothours = parseFloat(tothours) + parseFloat(thesehours);
			});
			$("#"+mstoneid+" .mstone-hours span").html(tothours);
			$("#"+mstoneid+" .mstone-hours input").val(tothours);
			//alert(tothours);
		});
	}
	$('#tasks').on('change', 'input.task-esthours', function () {// CREATE/EDIT project types: validate est_hours input and update project total
		$(this).prev("p").remove(); // remove the span that may have been placed in an error
		var str = $(this).val();
		var tMstone = $(this).closest("table.t8-pm-mstone");
		if(intRegex.test(str) || floatRegex.test(str)) {
			mstoneHourtotals();
		}else{
			$(this).val("").before("<p>Estimated time must be numeric in increments of 0.25</p>");
			setTimeout(function () { $(this).focus() }, 50);
		} 
	});
		
if ($("#tasks").length > 0){
	var mstoneTemplate = $('div#tasks table.t8-pm-mstone:last').clone();
	var lastmstoneid = 1;
		$('div#tasks table.t8-pm-mstone').each( function(){
			var eachmid = parseInt($(this).data('mid'),10);								   
			if(lastmstoneid < eachmid){ lastmstoneid = eachmid; }							   
		});
	var taskTemplate = $('div#tasks tr.task:first').clone();
	var lastTaskid = 1;
		$('div#tasks tr.task').each( function(){
			var eachtid = parseInt($(this).data('tid'),10);								   
			if(lastTaskid < eachtid){ lastTaskid = eachtid; }							   
		});
	var tasksCount = parseInt(lastTaskid,10);
	var mstoneCount = parseInt(lastmstoneid,10);
//	$("h2").css('background-color','yellow');
	
	var addMstone = function(){
		mstoneCount++;
		var mstone = mstoneTemplate.clone()
		.children("tbody.the-list").empty().end()
		.find(':input').each(function(){
			this.name = this.name.replace(/mstone\[([0-9]+)\]/, "newmstone["+ mstoneCount +"]");
			if(this.type != 'submit'){ this.value = "";}
		}).end() // back to mstone
		.find("thead th.m-title").html('<input type="text" value="--name--" name="mstone['+ mstoneCount +'][name]" class="item-title">').end()
		.attr('id', 'mstone-' + mstoneCount) // update mstone id
		.data({ mid: mstoneCount })
		.appendTo("#tasks") // add to container
		
		tasksCount++;
		
		var mparlist = $("#mstone-"+mstoneCount).children("tbody.the-list:first");
		var taskRName = 'newtask';
		var task = taskTemplate.clone()
			.find(':input').each(function(){
			this.name = this.name.replace(/task\[([0-9]+)\]/, taskRName+"["+ tasksCount +"]");
			if(this.type != 'submit'){ this.value = "";}
		}).end() // back to .task
		.attr('id', 't8-pm-task-' + tasksCount) // update task id
		.data({ tid: tasksCount, tstat: taskRName })
		.appendTo(mparlist) // add to container
		sortable();
		updatestage();
		mstoneHourtotals();
	};
	$('#add-mstone').click(addMstone); // attach event
	var addTask = function(){
		tasksCount++;
		var mparlist = $(this).closest('table.t8-pm-mstone').children("tbody.the-list:first");
		var taskRName = 'task';
		if($('#add-mstone').hasClass('editproj')){ taskRName = 'newtask'; } //for exisitng projects, new tasks need ther own array
		var task = taskTemplate.clone().find(':input').each(function(){
				this.name = this.name.replace(/task\[([0-9]+)\]/, taskRName+"["+ tasksCount +"]");
				if(this.type != 'submit'){ this.value = "";}
			}).end() // back to .task
			.data({ tid: tasksCount, tstat: taskRName })
			.appendTo(mparlist) // add to container
		updatestage();
		mstoneHourtotals();
	};
	$('#tasks').on("click", ".add-task", addTask); // attach event
	
	// SORTABLE Tasks
	function sortable() {
		$('.t8-pm-form tbody.the-list').sortable( {
			connectWith: '.t8-pm-mstone tbody.the-list',
			start: function() {
				
			},
			beforeStop: function(event, ui) {
				$(ui.helper).css('z-index', '');
			},
			stop: function(event, ui) {
				updatestage();
				mstoneHourtotals();
			}
			// handle: $('.handle')
		});
	}
	sortable();
	// Update stage's in Task list
	function updatestage() {
		$('table.t8-pm-mstone').each( function(){
		console.log('updatestage');
			var mid = $(this).data('mid');
			$(this).find('.task-assign input').each(function(){
				$(this).val(mid);
				console.log(mid);
			});
		});
	}
}

// AJAX FUNCTIONS
	// on projects list screen, move a proj to trash and delete its schedule
	$('div.t8-pm .trashproj').click(function(e) {
		var link = $(this);	
		e.preventDefault();
		var proj_id = link.data('proj'),
			nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: 't8_pm_projtotrash',
			proj_id: proj_id,
			nonce : nonce
		};
		$.post(ajaxurl, data, function(response) {
				$('tr#projrow-'+proj_id ).fadeOut( 600, function(){ $(this).remove(); });
				$('div.wrap.t8-pm').prepend('<div class="updated">' + response + '</div>');
		});
			
	});
	$('div.t8-pm').on("click", "td a.edittime", function(e) {
		var link = $(this);
		var timerow = link.closest("tr");
		e.preventDefault();
		var time_id = link.data('time');
			cli = 		timerow.find(".cli").data('cli'),
			proj = 		timerow.find(".proj").data('proj'),
			task = 		timerow.find(".task").data('task'),
			assign = 	timerow.find(".assign").data('assign');
			hours = 	timerow.find(".hours span").html(),
			start = 	timerow.find(".hours span").data('start'),
			notes = 	timerow.find(".notes").html(),
			date = 		timerow.find(".date").html(),
			nonce = $('input#t8_pm_nonce').val();
		$.post(ajaxurl, 
			{
				action: 't8_pm_pc_drops',
				cli: cli,
				proj: proj,
				task: task,
				nonce : nonce
			},
			function (data) {
				var rowclone = 	$('tr.time-new').clone();
				rowclone.addClass('editrow').removeClass('time-new')
					.find('select.clisel').val(cli).end()
					.find('select.projsel').html(data.projs).end()
					.find('select.tasksel').html(data.tasks).end()
					.find('select.assign').val(assign).end()
					.find('input.notes').val(notes).end()
					.find('input.date').val(date).end()
					.find('.hours input').val(hours).data('start', start).end()
					.data('time', time_id ).insertAfter(timerow);
				timerow.hide();
				rowclone.find('select.projsel, select.tasksel').attr('disabled', 'disabled');
				if(  data.projsshow ) rowclone.find('select.projsel').removeAttr('disabled');
				if(  data.tasksshow ) rowclone.find('select.tasksel').removeAttr('disabled');
				//$('tr#projrow-'+proj_id ).fadeOut( 600, function(){ $(this).remove(); });
				//$('div.wrap.t8-pm').prepend('<div class="updated">' + response + '</div>');
			}, "json"
		);
	});
	$('div.t8-pm').on("change", "tr.editrow .cli select", function() { 
		var cli = $(this).val(),
			timerow = $(this).closest("tr"),
			nonce = $('input#t8_pm_nonce').val();
		$.post(
			ajaxurl, 
			{
				action:"t8_pm_pc_drops", 
				cli: cli,
				proj: 0,
				nonce : nonce
			},
			function (data) {
				timerow.find('select.projsel').removeAttr('disabled').html(data.projs);
				timerow.find('select.tasksel').attr('disabled','disabled').html('<option>Task...</option>');
			}, "json"
		);
	});
	$('div.t8-pm').on("change", "tr.editrow .proj select", function() { 
		var proj = $(this).val(),
			timerow = $(this).closest("tr"),
			nonce = $('input#t8_pm_nonce').val();
		var cli = timerow.find('select.clisel').val();
		$.post(
			ajaxurl, 
			{
				action:"t8_pm_pc_drops", 
				cli: cli,
				proj: proj,
				nonce : nonce
			},
			function (data) {
				timerow.find('select.tasksel').removeAttr('disabled').html(data.tasks);
			}, "json"
		);
	});
	$('div.t8-pm').on("click", "td a.savetime", function(e) {
		var link = $(this);
		var timerow = link.closest("tr");
		e.preventDefault();
		var time_id = timerow.data('time');
			cli 	= timerow.find('select.clisel').val(),
			proj = timerow.find('select.projsel').val(),
			task = timerow.find('select.tasksel').val(),
			assign 	= timerow.find('select.assign').val(),
			cliname 	= timerow.find('select.clisel option:selected').html(),
			projname = timerow.find('select.projsel option:selected').html(),
			taskname = timerow.find('select.tasksel option:selected').html(),
			assignname 	= timerow.find('select.assign option:selected').html(),
			notes 	= timerow.find('input.notes').val(),
			date 	= timerow.find('input.date').val(),
			hours 	= timerow.find('.hours input').val(),
			start 	= timerow.find('.hours input').data('start'), 
			nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: 't8_pm_update_time_entry',
			time_id: time_id,
			cli: cli,
			proj: proj,
			task: task,
			assign : assign,
			notes : notes,
			date : date,
			hours : hours,
			start : start,
			nonce : nonce
		};
		$.post(ajaxurl, data, function(response) {
			if(response.tid){
				var altclass = 'alternate',
				assignclass = assignname.toLowerCase();
				if( timerow.next('tr').hasClass('alternate') ) altclass = '';
				var rowclone = 	timerow.clone();
				rowclone.removeClass('time-new editrow').addClass( assignclass+' '+altclass ).attr('id', 'time-'+response.tid)
					.find(".cli").data('cli', cli ).html(cliname).end()
					.find(".proj").data('proj', proj ).html(projname).end()
					.find(".task").data('task', task ).html(taskname).end()
					.find(".assign").data('assign', assign ).html(assignname).end()
					.find(".hours").html('<span class="start" data-start="'+ start +'">'+ hours +'</span>').end()
					// .find(".hours span.end").data('start'), // !!! need to change start time if date changed and change endtime if hours change, or don't show times at all?
					.find(".notes").html(notes).end()
					.find(".date").html(date).end()
				.insertAfter(timerow);
				$('div.wrap.t8-pm').prepend('<div class="updated">' + response.message + '</div>');
			}else if(response.message){
				timerow.prev('tr')
					.find(".cli").data('cli', cli ).html(cliname).end()
					.find(".proj").data('proj', proj ).html(projname).end()
					.find(".task").data('task', task ).html(taskname).end()
					.find(".assign").data('assign', assign ).html(assignname).end()
					.find(".hours span").html(hours).end()
					// .find(".hours span.end").data('start'), // !!! need to change start time if date changed and change endtime if hours change, or don't show times at all?
					.find(".notes").html(notes).end()
					.find(".date").html(date).end()
					.show();
				timerow.remove();
				$('div.wrap.t8-pm').prepend('<div class="updated">' + response.message + '</div>');
			}else{
				$('div.wrap.t8-pm').prepend('<div class="warning">' + response.warning + '</div>');
			}
		}, "json");
			
	});
	$('div.t8-pm').on("click", "tr.editrow td a.cancel", function(e) {
		e.preventDefault();
		$(this).closest("tr").prev().show().end().remove();

		
	});


	// Task Completion Checkboxes
	$('.dashboard, #tasks').on("change", "input.t8-pm-task-status", function(e){
		e.preventDefault();
		var checked = 'incomplete';
		var level = '1';
		if($(this).attr('name') == 'complete[]'){
			level = '2';
		}
		if($(this).is(':checked')){
			checked = '1';
			if($(this).attr('name') == 'complete[]'){
				checked = '2';
			}
		}
	var taskid = $(this).val();
		var parSpan = $(this).prev('span'),
			nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: 't8_pm_tasksubmit',
			taskid: taskid,
			checked: checked,
			level : level,
			nonce : nonce
		};
		$.post(ajaxurl, data, function(response) {
			$(parSpan).html(response);
		});
	}); /**/
	// Task Completion Checkboxes (old one for view screen)
	$('input.t8-pm-task-review').on('change', function(e){
		e.preventDefault();
		var checked = 'incomplete';
		var level = '1';
		if($(this).attr('name') == 'complete[]'){
			level = '2';
		}
		if($(this).is(':checked')){
			checked = '1';
			if($(this).attr('name') == 'complete[]'){
				checked = '2';
			}
		}
	var taskid = $(this).val(),
		proj_id = $('#tasks').data('proj'),
		parSpan = $(this).next('span'),
		nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: 't8_pm_tasksubmit',
			taskid: taskid,
			checked: checked,
			proj_id : proj_id,
			level : level,
			nonce : nonce
		};
		//alert('ye');
		$.post(ajaxurl, data, function(response) {
			$(parSpan).html(response);
			// need to refresh the schedule graph somehow
		});
	}); /**/
	// Save Task Notes
	$('a.t8-pm-savenotes').on('click', function(e){
		e.preventDefault();
		var taskClass = $(this).attr('class').split("task-");
		var taskid = parseInt(taskClass[1],10);
		var messageSpan = $(this).parent('td').prev('th').children('span');
		notes = $('textarea.task-'+taskid+'-notes').val(); 
		nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: 't8_pm_notessave',
			taskid: taskid,
			notes: notes,
			nonce : nonce
		};
		$.post(ajaxurl, data, function(response) {
			$(messageSpan).html(response);
		});
	}); /**/
	$('a.t8_pm_panel_notes').click( function(e){
		e.preventDefault();
		$(this).parent().find('form.t8_pm_panel_notes').toggle(400);
	}); /**/
		// Move active client to inactive
	$('a.cli-status').on('click', function(e){
		e.preventDefault();
		var parent = $(this).closest('tr');
		var cliid = $(this).siblings("input[name='t8_pm_cli_id']").val();
		var status = '1';
		if( $(this).hasClass('activate') ) { status = 0; }
		nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: 't8_pm_cli_status',
			cliid: cliid,
			status: status,
			nonce : nonce
		};
		$.post(ajaxurl, data, function(response) {
			$(parent).remove();
		});
	}); /**/	
/*	$('div.manual p.pc-task select').change(function() { 
		var taskname = $("option:selected", this).text();
		$(this).closest("p").removeClass('disabled').find('span').html(taskname);
	});


task[0][task-title]
$('#add-task').click(function() {// CREATE/EDIT project types:
		var click = $(this);
		var task = $('#tasks').children('.task:last-child'); //store the last task
		var tclasses = $(task).attr('class').split("pt-task-"); //get classname unique to this task subtask, what if this element gets a classname added, that may screw the number extraction
		var index = parseInt(tclasses[1],10); // set index to number from class
		
		$(task)
			.clone(true)
			.find('.subtask:not(".sbt-0")')
			.remove()
			.end()
			.find('.sbt-0')
			.addClass('hidden')
			.end() 
			.addClass('pt-task-'+parseInt(++index,10)).appendTo('#tasks') //clone task section, inc up class names, append to wrapper
			.removeClass('pt-task-'+parseInt(index-1,10)) //remove cloned class names
			.children('h3').html('Task Title')
			.parent().find('input').each(function() { //inc up input names, clear data
				var t = $(this);
				var newname = t.attr('name').toString().replace('task['+parseInt(index-1,10), 'task['+index);
				t.attr('name', newname).attr('value', '');
			}).end()
			.find('textarea').each(function() {
				var t = $(this);
				var newname = t.attr('name').toString().replace('task['+parseInt(index-1,10), 'temp-task['+index);
				t.attr('name', newname).html('');
			});
		console.log(task.find('textarea'));
	});*/


}); 