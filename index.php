<?php
include 'Schedule.class.php';
$TabDelimOutput='';
$TabDelimOutputHeader='';
$CSVDelimOutput='';
$CSVDelimOutputHeader='';
$bIsValid=false;
$ErrorMsg="";
$TeamCount=0;
$TeamList=0;
?>
<html>
	<head>
		<title>Schedule Generator - V 01.01.00</title>
		<script src="jquery-1.5.1.min.js" language="javascript"></script>
		<script src="date.js" language="javascript"></script>
		<script src="jquery.cookie.js" language="javascript"></script>
		<script>
			$(document).ready(function() {
				if ( $.cookie('team_list') != null ) {
					$('#team_list').val($.cookie('team_list'));
				};
				if ( $.cookie('team_games') != null ) {
					$('#team_games').val($.cookie('team_games'));
				};
				if ( $.cookie('teams_per_game') != null ) {
					$('#teams_per_game').val($.cookie('teams_per_game'));
				};
				if ( $.cookie('game_duration_min') != null ) {
					$('#game_duration_min').val($.cookie('game_duration_min'));
				};
				if ( $.cookie('game_start_datetime') != null ) {
					$('#game_start_datetime').val($.cookie('game_start_datetime'));
				};
				if ( $.cookie('game_duration_between_min') != null ) {
					$('#game_duration_between_min').val($.cookie('game_duration_between_min'));
				};
				
				$('#frm').submit(function() {
					$.cookie('team_list', $('#team_list').val());
					$.cookie('team_games', $('#team_games').val());
					$.cookie('teams_per_game', $('#teams_per_game').val());
					$.cookie('game_duration_min', $('#game_duration_min').val());
					$.cookie('game_start_datetime', $('#game_start_datetime').val());
					$.cookie('game_duration_between_min', $('#game_duration_between_min').val());
				});
			    $('#game_start_datetime').dblclick(function() {
			    	if ( $('#game_start_datetime').val().length==0 ) {
			    		$('#game_start_datetime').val(nowStr());
			    	}
			    });
			});
			function nowStr() {
				return new Date.now().toString("MM/dd/yyyy HH:mm");
			}
			function addMinutes(MinutesToAdd, DateToAddThemTo) {
				if ( DateToAddThemTo==null ) {
					DateToAddThemTo=nowStr();
				}
				return Date(DateToAddThemTo).addMinutes(MinutesToAdd).toString("MM/dd/yyyy HH:mm");
			}
		</script>
	</head>
	<body>
		<form id="frm" method="post"><input type="hidden" id="act" name="act" value="gen"></input>
		Team List (Comma Delimited.. 30 max):<br /> &nbsp;&nbsp;<textarea rows="4" cols="80" id="team_list" name="team_list" ><?php echo((isset($_POST['team_list']) ? $_POST['team_list'] : 'Team Alpha, Team Beta, Team Omega, Team Smegma, Mung, Yeast Lovers, Intestinal Parasites, Ovarian Sistas, Kind Gore, Evangelical Imps, Scrotes'));?></textarea><br /> 
		Number of Games each team will play (1-30): <input id="team_games" name="team_games" value="<?php echo(isset($_POST['team_games']) ? $_POST['team_games'] : '6')?>"></input><br/>
		Number of teams in each game (2-4): <input id="teams_per_game" name="teams_per_game" value="<?php echo(isset($_POST['teams_per_game']) ? $_POST['teams_per_game'] : '3')?>"></input><br/>
		Game Duration (Min): <input id="game_duration_min" name="game_duration_min" value="<?php echo(isset($_POST['game_duration_min']) ? $_POST['game_duration_min'] : '10')?>"></input><br/>
		Duration Between Games (Min): <input id="game_duration_between_min" name="game_duration_between_min" value="<?php echo(isset($_POST['game_duration_between_min']) ? $_POST['game_duration_between_min'] : '4')?>"></input><br/>
		Start Date/Time: <input id="game_start_datetime" name="game_start_datetime" value="<?php echo(isset($_POST['game_start_datetime']) ? $_POST['game_start_datetime'] : '')?>"></input><br/>
		<input type="submit" id="submit" value="Generate the Schedule"></input>
		</form>
<?php 
if ( isset($_POST['act'])) {
	$TeamList=explode(',', $_POST['team_list']);
	$TeamCount=count($TeamList);
	if ( !isValid() ) {
		echo('<div align="center" style="color:red;"><strong>'.$ErrorMsg.'</strong></div>');
	} else {
		$ret=GenerateSchedule($_POST['team_list'], $_POST['team_games'], $_POST['teams_per_game']);
		echo('Generate Schedule for <b>'.$TeamCount.'</b> Team(s):<br />');
		$RunningGameDateTime=strtotime($_POST['game_start_datetime']);
		//$newDate = DateAdd('n', $_POST['game_duration_min'], strtotime(date( 'Y-m-d H:i:s')));  
		
		for ($iGame = 0; $iGame < count($ret); $iGame++) {
			$HTMLOutput='&nbsp;&nbsp;&nbsp;Game '.str_pad($iGame+1,3,'0',STR_PAD_LEFT);
			$TabDelimOutput.='Game '.str_pad($iGame+1,3,'0',STR_PAD_LEFT)."\t";
			$CSVDelimOutput.='Game '.str_pad($iGame+1,3,'0',STR_PAD_LEFT).",";
			if ($iGame == 0) {
				$TabDelimOutputHeader="Game No\tStart\tEnd\t";
				$CSVDelimOutputHeader='Game No,Start,End,';
			}

			$TabDelimOutput.=date( 'm/d/Y H:i:s', $RunningGameDateTime) ."\t";
			$CSVDelimOutput.=date( 'm/d/Y H:i:s', $RunningGameDateTime) .",";
			$HTMLOutput.='-&nbsp;&nbsp;Start:&nbsp;'.date( 'm/d/Y H:i:s', $RunningGameDateTime);	
			$RunningGameDateTime = DateAdd('n', $_POST['game_duration_min'], $RunningGameDateTime); 
			$TabDelimOutput.=date( 'm/d/Y H:i:s', $RunningGameDateTime) ."\t";
			$CSVDelimOutput.=date( 'm/d/Y H:i:s', $RunningGameDateTime) .",";
			$HTMLOutput.='&nbsp;&nbsp;&nbsp;End:&nbsp;'.date( 'm/d/Y H:i:s', $RunningGameDateTime);	
			$RunningGameDateTime = DateAdd('n', $_POST['game_duration_between_min'], $RunningGameDateTime); 
			$HTMLOutput.='<br/>&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;&nbsp;';
			//echo($HTMLOutput);
			for ($iTeamSlot = 0; $iTeamSlot < count($ret[$iGame]); $iTeamSlot++ ){
				if ($iGame == 0) {
					$TabDelimOutputHeader.='Team '.($iTeamSlot+1)."\t";
					$CSVDelimOutputHeader.='Team '.($iTeamSlot+1).',';
				}
				if ($iTeamSlot > 0) $HTMLOutput.=' vs. ';
				$HTMLOutput.=$ret[$iGame][$iTeamSlot];

				$TabDelimOutput.=$ret[$iGame][$iTeamSlot]."\t";
				$CSVDelimOutput.=$ret[$iGame][$iTeamSlot].",";
			}
			$HTMLOutput.='<br />';
			echo($HTMLOutput);
			$TabDelimOutput.="\n";
			$CSVDelimOutput=substr($CSVDelimOutput, 0, strlen($CSVDelimOutput)-1);
			$CSVDelimOutput.="\n";
			if ($iGame == 0) {
				$TabDelimOutputHeader=substr($TabDelimOutputHeader, 0, strlen($TabDelimOutputHeader)-1);
				$CSVDelimOutputHeader=substr($CSVDelimOutputHeader, 0, strlen($CSVDelimOutputHeader)-1);
				$TabDelimOutputHeader.="\n";
				$CSVDelimOutputHeader.="\n";
			}
		}
		echo('<br /><br />Tab Delimited Output:<br /><textarea rows="30" cols="60">'.$TabDelimOutputHeader.$TabDelimOutput.'</textarea>');
		echo('<br /><br />CSV Delimited Output:<br /><textarea rows="30" cols="60">'.$CSVDelimOutputHeader.$CSVDelimOutput.'</textarea>');
	}
}
?>
<br/>
		This source code has been developed under the GPL.  This means feel free to use it, alter it, etc.  But you can only use it in free software. <a href="http://www.gnu.org/licenses/gpl-3.0.html">GPL 3.0 - Click here for more info</a><br/>
		<a href="ScheduleGen.zip">Click here to download the Source Code</a><br/>
		Any Questions, Comments, Complaints?  <a href="mailto:schedulegen@noctusoft.com">Email me!</a><br />
		Enjoy!<br /><br />
		&nbsp;&nbsp;Ricky Vega

	</body>
</html>
<?php 
function isValid() {
	global $ErrorMsg;
	global $bIsValid;
	global $TeamCount;
	if ( $TeamCount > 30 ) {
		$ErrorMsg='Team Count must be 30 or less';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( $TeamCount == 0 ) {
		$ErrorMsg='You gotta have teams to schedule!';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( $TeamCount  == 1 ) {
		$ErrorMsg='You gotta have more than 1 team to schedule!';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( !is_numeric($_POST['team_games']) ) {
		$ErrorMsg='Number of Games each team will play must be numeric';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( $_POST['team_games'] > 30 ) {
		$ErrorMsg='Number of Games each team will play must be 30 or less';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( $_POST['team_games'] < 1 ) {
		$ErrorMsg='Number of Games must be greater than 1';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( !is_numeric($_POST['teams_per_game']) ) {
		$ErrorMsg='Number of teams in each game must be numeric';
		$bIsValid = false;
		return $bIsValid;
	}
	
	if ( $_POST['teams_per_game'] > 4 ) {
		$ErrorMsg='Number of teams in each game must be 4 or less';
		$bIsValid = false;
		return $bIsValid;
	}
	if ( $_POST['teams_per_game'] < 2 ) {
		$ErrorMsg='You gotta have more than 1 team per game!';
		$bIsValid = false;
		return $bIsValid;
	}
	
	if ( !is_numeric($_POST['game_duration_min']) ) {
		$ErrorMsg='Game duration must be a number';
		$bIsValid = false;
		return $bIsValid;
	} else {
		if ( $_POST['game_duration_min'] <= 0 ) {
			$ErrorMsg='Game duration (minutes) must be greater than zero';
			$bIsValid = false;
			return $bIsValid;
		}
		if ( $_POST['game_duration_min'] > 60 ) {
			$ErrorMsg='Game duation (minutes) must be less than 60 minutes';
			$bIsValid = false;
			return $bIsValid;
		}
	}
	
	if (!(( is_numeric($_POST['game_duration_between_min']) ) &&
		  ( $_POST['game_duration_between_min'] > 0 ) &&
		  ( $_POST['game_duration_between_min'] < 60 )))
	{
		$ErrorMsg='Duration between games must be a number and between 0 and 60';
		$bIsValid = false;
		return $bIsValid;
	}
	
	$game_start_datetime=strtotime( $_POST['game_start_datetime'] );
    if ($game_start_datetime == -1 || $game_start_datetime === false) {
		$ErrorMsg='Invalid game start date/time';
		$bIsValid = false;
		return $bIsValid;
  	}
	return true;
}

function DateAdd($interval, $number, $date) {

    $date_time_array = getdate($date);
    $hours = $date_time_array['hours'];
    $minutes = $date_time_array['minutes'];
    $seconds = $date_time_array['seconds'];
    $month = $date_time_array['mon'];
    $day = $date_time_array['mday'];
    $year = $date_time_array['year'];

    switch ($interval) {
    
        case 'yyyy':
            $year+=$number;
            break;
        case 'q':
            $year+=($number*3);
            break;
        case 'm':
            $month+=$number;
            break;
        case 'y':
        case 'd':
        case 'w':
            $day+=$number;
            break;
        case 'ww':
            $day+=($number*7);
            break;
		case 'h':
			$hours+=$number;
			break;
        case 'n':
            $minutes+=$number;
            break;
        case 's':
            $seconds+=$number;
            break;            
    }
       $timestamp= mktime($hours,$minutes,$seconds,$month,$day,$year);
    return $timestamp;
}

?>