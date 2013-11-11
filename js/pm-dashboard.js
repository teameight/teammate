jQuery(document).ready(function($){
		// FLYOUT for MILESTONES and ORPHANS
	$('.dashboard').on("click", "div#planner div.dtask", function(e){
		//console.log(e.target);
		e.preventDefault();
		var target = $( e.target );
		if (!( target.hasClass('dact') ) ) { // don't trigger this if its one of the action buttons
			var task = $(this);
			//var pos = $task.position();
			var setup = $('#orphan-setup').clone().html();
			if( task.hasClass('flyout') ) {
				$('.flyout').removeClass('flyout');
				$('.fadetasks').removeClass('fadetasks');
				return false;
			}
			else {
				// should do a check to see if project already in flyout is same
				$('.flyout').removeClass('flyout');
				$('.fadetasks').removeClass('fadetasks');
				// for punchclock
				var cliid = task.attr('data-cli');
				var proj = task.attr('data-proj-id');
				var tid = task.attr('data-id');
				//for milestones:
				var flyout = task.closest('.leftcol').siblings('.rightcol');
				console.dir(flyout);
				var stage = task.attr('data-stage');
				var nonce = $('input#t8_pm_nonce').val();
				var data = {
					action: 't8_pm_show_stage',
					proj: proj,
					cliid: cliid,
					tid: tid,
					stage: stage,
					nonce : nonce
				};
			/*	if( pos.top > $(window).height()/2) {
					flyout.css({'top': 'auto', 'bottom': '-10px'}).addClass('flyout');
				} else {
					flyout.css({'top': '0px', 'bottom': 'auto'}).addClass('flyout');
				} */	
				$.post(ajaxurl, data, function(response) {
					flyout.children('.flyoutwrap').html(response);
					flyout.addClass('flyout');
					task.addClass('flyout');
					$('.sort').not('.flyout-tasks').addClass('fadetasks');
					var thisdate = $('input#d').val();
					$( "#addpunchdate").val(thisdate);
					$( ".datepicker" ).datepicker({
						changeMonth: true,
						changeYear: true,
						dateFormat: "D, MM d, yy"
					}); 
					sortable();
				});
			}
		}
	});
	$('.dashboard').on("click", '.dtask .handle, .dtask .task-status', function(e) {
		e.stopPropagation();
	});
	//remove task from planner
	$('.dashboard').on("click", 'div.dact.x', function(e) {
		e.stopPropagation();
		$('.flyout').removeClass('flyout');
		$('.fadetasks').removeClass('fadetasks');
		$(this).closest(".dtask").slideUp('fast', function() {
			var movetask = $(this);
			$(this).remove();
			// !!! need to send this to either common or your tasks, not auto to your tasks
			$(movetask).prependTo("#all-your-tasks").show('fast', function() {
				ttlist();
			});
		});
	});
		
	// ADD ORPHAN ASSIGNMENT
	var assign = '<div class="dtask ass"><div class="handle"></div><div class="upper"><h3></h3><div class="rollover"><a class="x dact"></a><a class="person daction"></a><a class="clock daction"></a></div></div><div class="lower"><span></span><span></span></div><a class="arrow daction"></a></div>';
	var setup = $('#orphan-setup').html();
	var count = 1;
	
	$('.add.orphan').click(function (e) {
		$('.flyout').removeClass('flyout');
		$('.fadetasks').removeClass('fadetasks');
		e.preventDefault();
		var $flyout = $(this).parent().siblings('.rightcol');
		var $list = $(this).siblings('.list');
		$list.children('.dtask').last().clone(true).addClass('assign orphan').appendTo($list).find('span').html('????').end().trigger('click');
		sortable();
	});
	
	
	// SORTABLE
	function sortable() {
		$('.sort').sortable( {
			connectWith: '#planner',
			start: function() {
				$('#planner .empty').hide();
				$('.dashboard').addClass('sort-wait');
				// only collapse flyout if not dragging a task from it
				if($(this).hasClass('flyout-tasks')){
									 
				}else{
					$('.flyout').removeClass('flyout');
					$('.fadetasks').removeClass('fadetasks');
				}
			},
			beforeStop: function(event, ui) {
				$(ui.helper).css('z-index', '');
			},
			stop: function(event, ui) {
				$('.dashboard').removeClass('sort-wait');
				ttlist();
			}//, handle: $('.handle') // this will set the graber to only the handle element
		});
	}
	sortable();
	function ttlist_handle_empty() {
		if($('.today-tasks #planner .dtask').length == 0) {
			$('.today-tasks #planner .empty').removeClass('hidden');
		} else {
			$('.today-tasks #planner .empty').addClass('hidden');
		}
	}
	ttlist_handle_empty();
	// Save Today's task list to the user_meta table under user > planner
	function ttlist() {
		ttlist_handle_empty();
		var year = $('.dashboard.today-tasks').data('year');
		var day = $('.dashboard.today-tasks').data('day');
		var tasklist = [];
		var assignlist = [];
		if($('.today-tasks #planner .dtask').not('.flyout-tasks .otask, .hidden').length < 1){
			$('#planner .empty').show();	
		}
		$('.today-tasks #planner .dtask').not('.flyout-tasks .otask, .hidden').each(function () { // cycle through all tasks in planner, but not leftovers in hidden flyout panels
			var id = $(this).data('id');
			var type = $(this).data('type');
			if(type == 'assign'){
				assignlist.push(id);
			}else{
				tasklist.push(id);
			}
			$(this).removeClass('otask');
		});
		var nonce = $('input#t8_pm_nonce').val();
		var data = {
			action: "t8_pm_update_todays_tasks",
			year: year,
			day: day,
			tasklist: tasklist,
			assignlist: assignlist,
			nonce: nonce
		}
		// console.log(tasklist);
		
		var posting = $.post(ajaxurl, data);
		posting.done(function (data) {
			console.log(data);
		});
	}
	// Save Orphan Task Data
	// $('a.cli-status').on('click', function(e){
		// e.preventDefault();
		// var parent = $(this).closest('tr');
		// var cliid = $(this).siblings("input[name='t8_pm_cli_id']").val();
		// var status = '1';
		// if( $(this).hasClass('activate') ) { status = 0; }
		// nonce = $("input#t8_pm_nonce").val(); 
		// var data = {
			// action: 't8_pm_cli_status',
			// cliid: cliid,
			// status: status,
			// nonce : nonce
		// };
		// $.post(ajaxurl, data, function(response) {
			// $(parent).remove();
		// });
	// }); /**/	
// PIE CHART! https://github.com/rendro/easy-pie-chart	

//Dashboard Pclock vars
		var ptimer = $('#pclock-dash .time-readout'),
			punchTimeout = timer = tEst = tPnch = prctPnch = prctTrk = newtime = oldtime = 0,
			minCheck = 1,
			timerActs = $('#timer-actions'),
			timerSels = $('div.manual');
		var cliSel = timerSels.find('select.pc-cli'),
			projSel = timerSels.find('select.pc-proj'),
			taskSel = timerSels.find('select.pc-task');
		var start = $('#pclock-dash .time-readout').data('start'); //will move this to data on readout
		$('.timer').data('prctPnch',prctPnch).easyPieChart({ // setup piechart, with empty
			prctPnch:prctPnch
		});
		timer = window.chart = $('.timer').data('easyPieChart');
		console.log('timerInit');
// Dashboard Pclock Functions

		function displayHours(t){
			var dh = Math.floor(t);
			if(dh) dh += 'h ';
		 return dh + Math.floor( (t*60)%60 ) +'m';	
		}
		function needSelecting(level){
			if (typeof level === 'undefined') {
				timerActs.addClass('punch_timer').removeClass('start_timer').children('.btn').html('Punch Out');
				if( !timerActs.children('.secondary').length ) timerActs.append('<button type="button" class="secondary">X</button>');
			}else if(level == 'Punch In'){
				timerActs.addClass('start_timer').removeClass('punch_timer needselect').children('.btn').html(level)
				.end().children('.secondary').remove();
			}else{
				timerActs.addClass('needselect').removeClass('punch_timer').children('.btn').html('Please Select A '+level);
				if( !timerActs.children('.secondary').length ) timerActs.append('<button type="button" class="secondary">X</button>');
			}
		}
		
		if( start > 0 ) { // already punched in, pick it back up
			//timerActs.append('<button type="button" class="secondary">X</button>');
			var pickup = Math.floor ( Math.round((new Date()).getTime()) / 1000) - start;
			//console.log(pickup);
			if( taskSel.find('option:selected').length ) {
				task = taskSel.find('option:selected').val();
				nonce = $('input#t8_pm_nonce').val();
				$.post(
					ajaxurl, 
					{
						action:"t8_pm_pc_punchin", 
						task: task,
						nonce : nonce
					},
					function (data) {
						tEst = data.est_time;
						tPnch = data.punched;
						prctPnch = (tPnch/tEst)*100;
						prctTrk = 0;
						$('.timer .pnchHrs').html( displayHours( ( tEst - tPnch ) ) + ' left' ).next('.rdts').css('visibility', 'visible'); // .timer should be a global? !!!
						$('.timer .estHrs').html( displayHours( tEst ) );
					/* // moving this to handled with globals in punchTime()
						//this isn't right for unchosen tasks yet !!!
						if(timer) timer.tracker( (tPnch/tEst)*100, prctPnch, ((pickup/60/60)/tEst)*100, prctTrk );
						prctPnch = (tPnch/tEst)*100;
						prctTrk = ((pickup/60/60)/tEst)*100;
					*/
						console.log('timerStart>0');
						punchTime();
					}, "json"
				);
			}else{ //no task set, set to 0 and percent to 1 hour
				tEst = 1;
				tPnch = prctPnch = 0;
				// !!! what if this is more than 1 hour? ??? I think we fixed that in the jquery plugin side						
				prctTrk = ((newtime/60/60)/tEst)*100;	 
		//		if(timer) timer.tracker( 0, 0, prctTrk, 0 );
				console.log('timerInitEmpty');
				punchTime();
			}
		}
		//cancel tracking
	//send task from planner to punchclock
	$('.dashboard').on("click", 'div.dact.send2pc', function(e) {
		var dtask = $(this).closest(".dtask");
		var cli = dtask.data('cli'),
			proj = dtask.data('proj-id'),
			task = dtask.data('id'),
			est = dtask.data('hours'), // !!! use this one to populate donut chart
			container = $('#pclock-dash .manual');
			var projcheck = container.find('select.pc-proj option:selected').val();
			if(proj == projcheck){ // see if project is already in dropdown
				cli=0;
			} else {
				container.find('select.pc-cli').val(cli);
			}
			nonce = $('input#t8_pm_nonce').val();
		$.post(
			ajaxurl, 
			{
				action:"t8_pm_pc_drops", 
				proj: proj,
				cli: cli,
				task: task,
				nonce : nonce
			},
			function (data) {
				if( data.projs ) container.find('select.pc-proj').removeAttr('disabled').html(data.projs);
				container.find('select.pc-task').removeAttr('disabled').html(data.tasks);
				$('.dashboard .dtask').removeClass('punching');
				dtask.addClass('punching');
				//$(container).find('p.pc-proj select').html(data.tasks);
				tEst = data.pTimes.est_time;
				tPnch = data.pTimes.punched;
				$('.timer .pnchHrs').html( displayHours( ( tEst - tPnch ) ) + ' left' ).next('.rdts').css('visibility', 'visible'); // .timer should be a global? !!!
				$('.timer .estHrs').html( displayHours( tEst ) );
				// prctPnch, oldPrctPnch, prctTrk, oldPrctTrk
				console.log(prctTrk);
				var newprctTrk = ((newtime/60/60)/tEst)*100;
				if( (newtime/60/60) + tPnch >= tEst ){ // tracking more hours than left in estimate
					newprctTrk = 100*(1 - (tPnch/tEst));
					console.log('over estimated time');
				}
				if(timer) timer.tracker( (tPnch/tEst)*100, prctPnch, newprctTrk, prctTrk );
				console.log((tPnch/tEst)*100+':'+prctPnch+' | '+ newprctTrk+':'+prctTrk);
				prctPnch = (tPnch/tEst)*100;
				prctTrk = newprctTrk;
				// !!! check to see which sels are dialed in
				if( start ) needSelecting();
			}, "json"
		);
	});
		$('div#timer-actions').on('click', '.secondary', function() {
			var r=confirm("Stop tracking this time?");
			if (r==true) {
				start = 0;
				nonce = $('input#t8_pm_nonce').val();
				$.post(
					ajaxurl, 
					{
						action:"t8_pm_pc_punchin", 
						start_time: 0,
						nonce : nonce
					},
					function (data) {
						if(timer) timer.tracker( prctPnch, 0 );
						console.log('timerCancel');
						timerActs.removeClass('punch_timer needselect').addClass('start_timer').find('.btn').html('Punch In').end().find('.secondary').remove();
						if(typeof punchTimeout == "number") {
						  clearTimeout(punchTimeout);
						  delete punchTimeout;
						}
						start=0;
						ptimer.find('.hour').html('');
						ptimer.find('.min').html('0m');
						ptimer.find('.sec').html('0s');
					}, "json"
				);										
			} else {

			}
		});
		$('#pclock-dash button.btn').on('click', function() {
			var punchbutton = $(this);
			if( timerActs.hasClass('start_timer') ){ // PUNCH IN
				timerActs.removeClass('start_timer').children('.btn').html('Punching...');
				punchTime(); //start tracking time
				nonce = $('input#t8_pm_nonce').val();
				// log starttime as open clock in usermeta
				var setstart = Math.round((new Date()).getTime() / 1000);
				var cli = proj = task = desc = 0;
				if( cliSel.find('option:selected').length && !isNaN( cliSel.find('option:selected').val() ) ) cli = cliSel.find('option:selected').val();
				if( projSel.find('option:selected').length && !isNaN( projSel.find('option:selected').val() ) ) proj = projSel.find('option:selected').val();
				if( taskSel.find('option:selected').length && !isNaN( taskSel.find('option:selected').val() ) ) task = taskSel.find('option:selected').val();
				desc = $('div.manual p.pc-desc input').val();
				$.post(
					ajaxurl, 
					{
						action:"t8_pm_pc_punchin", 
						desc: desc,
						start_time: setstart,
						cli: cli,
						proj: proj,
						task: task,
						nonce : nonce
					},
					function (data) {
						if(task){ // only is task is chosen
							tEst = data.est_time;
							tPnch = data.punched;
							prctPnch = (tPnch/tEst)*100;
							$('.timer .pnchHrs').html( displayHours( ( tEst - tPnch ) ) + ' left' ); 
							$('.timer .estHrs').html( displayHours( tEst ) );
							if(timer) timer.tracker( (tPnch/tEst)*100, prctPnch, ((newtime/60/60)/tEst)*100, prctTrk );
							prctPnch = (tPnch/tEst)*100;
							prctTrk = ((pickup/60/60)/tEst)*100;							
							console.log('timerInit');
						}else{ //no task set, set to 0 and percent to 1 hour
							tEst = 1;
							tPnch = prctPnch = 0;
							//$('.timer .pnchHrs').html( displayHours( ( tEst - tPnch ) ) + ' left' ); 
							//$('.timer .estHrs').html( displayHours( tEst ) );
							// !!! what if this is more than 1 hour?						
							prctTrk = ((newtime/60/60)/tEst)*100;	 
							if(timer) timer.tracker( 0, 0, prctTrk, 0 );
							console.log('timerInitEmpty');
						}
					}, "json"
				);
				if(!cli) needSelecting('Client');
				else if(!proj) needSelecting('Project');
				else if(!task) needSelecting('Task');
				else needSelecting();
			}else if( timerActs.hasClass('punch_timer') ){ // PUNCH OUT
				timerActs.removeClass('punch_timer needselect').find('.secondary').remove();
				punchbutton.html('...Punching...');
				//timer.pause();
				if(typeof punchTimeout == "number") {
				  clearTimeout(punchTimeout);
				  delete punchTimeout;
				}
				// clear starttime from user meta 
				// log completed entry to pm_time
				var setend = Math.round((new Date()).getTime() / 1000);
				var setstart = start;
				var cli = proj = task = 0;
				var cliname = projname = taskname = desc = '';
				nonce = $('input#t8_pm_nonce').val();
				
				if( cliSel.find('option:selected').length ) {
					cli = cliSel.find('option:selected').val();
					cliname = cliSel.find('option:selected').text();
				}
				if( projSel.find('option:selected').length ) {
					proj = projSel.find('option:selected').val();
					projname = projSel.find('option:selected').text();
				}
				if( taskSel.find('option:selected').length ) {
					task = taskSel.find('option:selected').val();
					taskname = taskSel.find('option:selected').text();
				}
				desc = timerSels.find('p.pc-desc input').val();
				$.post(
					ajaxurl, 
					{
						action:"t8_pm_pc_punchout",
						desc: desc,
						start_time: setstart,
						end_time: setend,
						cli: cli,
						proj: proj,
						task: task,
						cliname: cliname,
						projname: projname,
						taskname: taskname,
						nonce : nonce
					},
					function (data) {
						punchbutton.parent().addClass('start_timer').children('.btn').html('Punch In');
						$('#punched-tasks .empty').hide();
						$('#punched-tasks h3.th').after(data);
						start = 0; //reset start time;
					}, "json"
				);
			}
		});
		function punchTime() { // loops through this function every .5 sec to refresh clocks and readouts
			if ( start < 1 ) { // time-readout is not preloaded with a pre-existing start time, start at current time
				startDate = new Date()
				start = Math.floor ( Math.round((startDate).getTime()) / 1000 );
				console.log(start);
				 $('#pclock-dash .time-readout').data('start', start);
				 $('#pclock-dash .manual .time .starttime p, #pclock-dash .manual .time .endtime p').html(formatAMPM(startDate));
				newtime = Math.floor ( Math.round((new Date()).getTime()) / 1000) - start; 
			}else{
				newtime = Math.floor ( Math.round((new Date()).getTime()) / 1000) - start; 
				if(minCheck){
					console.log('tPnch:'+tPnch+' tEst:'+tEst+' prctPnch:'+prctPnch+' prctTrk:'+prctTrk);
					//this isn't right for unchosen tasks yet !!!
					if(timer) timer.tracker( (tPnch/tEst)*100, prctPnch, ((newtime/60/60)/tEst)*100, prctTrk );
					prctPnch = (tPnch/tEst)*100;
					prctTrk = ((newtime/60/60)/tEst)*100;
				}
			}
			var s = newtime % 60;
			var m = 0;	
			var h = 0;	
			if( ( s < 1 || minCheck ) && ( oldtime != newtime ) ){ // every min, firing twice though, should check against newtime
				if( minCheck ){ 
					minCheck = 0; 
				} else { // not on every page load, only on min cycle
					if( timer && newtime > 59 ){ // only after first min
						if( (newtime/60/60) + parseInt(tPnch, 10) >= tEst ){ // tracking more hours than left in estimate
							prctTrk = 100*(1 - (tPnch/tEst));
							console.log('over estimated time');
						}else{
							prctTrk = ((newtime/60/60)/tEst)*100
						}
						timer.tracker( prctPnch, prctPnch, prctTrk );
						console.log('timeCall');
					}
				}
				var remain = Math.floor ( newtime / 60 );
				m = remain;
				// !!! animated adding to chart
				if( m > 59 ){ // on the hour
					m = remain % 60;
					h = Math.floor ( remain / 60 );
				}
				console.log(newtime);
				$('#pclock-dash .manual .time .endtime p').html(formatAMPM(new Date()));
				if(h){ptimer.find('.hour').html(h+'h');}else{ptimer.find('.hour').html('');}
				ptimer.find('.min').html(m+'m');
			}
			oldtime = newtime;
		//	add a zero in front of numbers < 10
			
			ptimer.find('.sec').html(s+'s');
			punchTimeout = setTimeout(function(){punchTime()},500);
		}		
		function checkTime(i) {
			if (i<10) { i="0" + i; }
			return i;
		}
		function formatAMPM(date) {
		  var hours = date.getHours();
		  var minutes = date.getMinutes();
		  var ampm = hours >= 12 ? 'pm' : 'am';
		  hours = hours % 12;
		  hours = hours ? hours : 12; // the hour '0' should be '12'
		  minutes = minutes < 10 ? '0'+minutes : minutes;
		  var strTime = hours + ':' + minutes + ' ' + ampm;
		  return strTime;
		}
/**/
// END PIE CHART
// PUNCHCLOCK
	cliSel.change(function() { 
		var cli = $("option:selected", this).val(),
			cliname = $("option:selected", this).text(),
			nonce = $('input#t8_pm_nonce').val();
			timerSels.find('select').not('select.pc-cli').attr('disabled','disabled');
		$.post(
			ajaxurl, 
			{
				action:"t8_pm_pc_drops", 
				cli: cli,
				proj: 0,
				nonce : nonce
			},
			function (data) {
				projSel.removeAttr('disabled').html(data.projs);
				taskSel.attr('disabled','disabled').html('<option>Task...</option>');
				var mins = Math.floor ( newtime / 60 );
				if( mins > 59 )  mins = min % 60;
				console.log(tEst);
				tEst = 1;
				tPnch = 0;
				$('.timer .pnchHrs').html( '' ).next('.rdts').css('visibility', 'hidden'); // .timer should be a global? !!!
				$('.timer .estHrs').html( '' );
				if(timer) timer.tracker( 0, prctPnch, (mins/(tEst*60))*100, prctTrk );
				prctPnch = 0;
				prctTrk = (mins/(tEst*60))*100;
				$('.dashboard .dtask').removeClass('punching');
				if( start ) needSelecting('Project');
			}, "json"
		);
	});
	projSel.change(function() { 
		var proj = $("option:selected", this).val(),
			projname = $("option:selected", this).text();
			taskSel.attr('disabled','disabled').html('<option>Task...</option>');
			nonce = $('input#t8_pm_nonce').val();
		$.post(
			ajaxurl, 
			{
				action:"t8_pm_pc_drops", 
				proj: proj,
				nonce : nonce
			},
			function (data) {
				taskSel.removeAttr('disabled').html(data.tasks);
				var mins = Math.floor ( newtime / 60 );
				if( mins > 59 )  mins = mins % 60;
				console.log(tEst);
				tEst = 1;
				tPnch = 0;
				$('.timer .pnchHrs').html( '' ).next('.rdts').css('visibility', 'hidden'); // .timer should be a global? !!!
				$('.timer .estHrs').html( '' );
				if(timer) timer.tracker( 0, prctPnch, (mins/(tEst*60))*100, prctTrk );
				prctPnch = 0;
				prctTrk = (mins/(tEst*60))*100;
				$('.dashboard .dtask').removeClass('punching');
				if( start ) needSelecting('Task');
			}, "json"
		);
	});
	taskSel.change(function() { 
		if( $('option:selected', this).length ) {
			task = $('option:selected', this).val();
			nonce = $('input#t8_pm_nonce').val();
			// !!! should probably also update the punchin usermeta here
			$.post(
				ajaxurl, 
				{
					action:"t8_pm_pc_punchin", 
					task: task,
					nonce : nonce
				},
				function (data) {
					tEst = data.est_time;
					tPnch = data.punched;
					$('.timer .pnchHrs').html( displayHours( ( tEst - tPnch ) ) + ' left' ).next('.rdts').css('visibility', 'visible'); // .timer should be a global? !!!
					$('.timer .estHrs').html( displayHours( tEst ) );
					if(timer) timer.tracker( (tPnch/tEst)*100, prctPnch, ((newtime/60/60)/tEst)*100, prctTrk );
					prctPnch = (tPnch/tEst)*100;
					prctTrk = ((newtime/60/60)/tEst)*100;
					$('.dashboard .dtask').removeClass('punching');
					$(".dashboard .dtask[data-id='"+task+"']").addClass('punching');
					if( start ) needSelecting();
						console.log('timeryeah');
				}, "json"
			);
		}
	});



});