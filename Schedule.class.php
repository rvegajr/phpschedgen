<?php
/*	Author: Ricardo Vega Jr. - www.noctusoft.com
 *  Copyright (C) 2011 Ricardo Vega Jr.
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/
define('ROOT_PATH',dirname(__FILE__).'/');
define('DUMP_FILENAME',ROOT_PATH.'logs/gamedump.txt');
define('DUMP_SORTED_FILENAME',ROOT_PATH.'logs/gamedump_sorted.txt');
define('SCHED_FILENAME',ROOT_PATH.'logs/schdump.txt');
define('ROUND_ROBIN', -1);
define('DOUBLE_ROUND_ROBIN', -2);
define('RAW_GAME_ARRAY_COUNT', -99999);
date_default_timezone_set('America/Chicago');
function GenerateSchedule($EntityList, $GamesPerEntity, $EntityPerGame) {
	$sch=new Schedule();
	$arrEntityList=explode(',', $EntityList);
	$sch->GamesForEachEntity=$GamesPerEntity;
	$sch->EntityCount=count($arrEntityList);
	$sch->EntitiesPerGame=$EntityPerGame;
	$ret=$sch->generateSchedule();
	shuffle($arrEntityList);
	for ($iGame = 0; $iGame < count($ret); $iGame++) {
		for ($iTeamSlot = 0; $iTeamSlot < count($ret[$iGame]); $iTeamSlot++ ){
			$ret[$iGame][$iTeamSlot]=trim($arrEntityList[$ret[$iGame][$iTeamSlot]-1]);
		}
	}
	return $ret;
	
}
class Schedule {
	var $error = null;
	var $Entity = null; //Team/Player
	var $EntityCount = 18; // The amount of Team/Player Participating 
	var $EntitiesPerGame = 3;// The amount of Team/Players per Game
	var $SubGamePerGame = 1; //The amount of subgames per game (this effectively multiplies the amount of entities in a game)
	/*
	 * $GamesForEachEntity - This is the amount of games an team/player will play.  
	 *   if ROUND_ROBIN,  then the shcedule will be a round robin style
	 *   if DOUBLE_ROUND_ROBIN, then the schedule will be a double round robin  
	 */
	var $GamesForEachEntity = 6; 
	 

	/*
	 * $gamesPlayed - An array, indexed by team, that holds the amount of games they have played
	 */
	var $gamesPlayed=array(); 
	/*
	 * $rawGames - An array that contains the raw games (does not account for the back to backs and distance from last game)
	 */
	var $rawGames=array();  
	
	var $Schedule = array();
	
	/**
	 * Constructor 
	 */
	public function __construct() {
	}
	
	protected function init(){
		$this->gamesPlayed=null;
		//Initialize Game Player
		for ($i = 0; $i < $this->EntityCount-1; $i++) {
    		$this->gamesPlayed[]=0;
		}
	}
	
	public function generateSchedule() {
		$this->init();
		$this->createRawGames();
		$this->Schedule=$this->calcRawSchedule();
		$this->Schedule=$this->evenlyDistEntPos($this->Schedule);
		return $this->Schedule;
	}

	/**
	 * Take a game, and calculate the distances from the last time that teams has played.  This function will be called recusively until it goes through all
	 * the teams in a particular game 
	 *
	 * @var array $CurrentSchedule - an Array representingthe current sorted schedule of game 
	 * @var array $Game - an Array representing a game,  with the entities participating in this game 
	 * @var array [$vData] - an Array containing the distance for that team 
	 * @var int [$teamSlot] - The Current Team slot being evaluated
	 * 
	 * @return Array - An array with each slot corresonding to the distance of each team and when the last time they played 
	 */	
	public function calcDistFromLastPlayed($CurrentSchedule, $Game, $vData=array(), $teamSlot=0) {
		$retGame = null;
		if ( $teamSlot > $this->EntitiesPerGame-1 ) return $vData;
		//$Game=$CurrentSchedule[count($CurrentSchedule)-1];
		$entityToSeachFor=$Game[$teamSlot];
		$dist=0;
		$vData[ $teamSlot ]=count( $this->rawGames );
		for ($iGame = count($CurrentSchedule) - 1; $iGame >= 0; $iGame--) {
			$dist++;
			if ( in_array($entityToSeachFor, $CurrentSchedule[$iGame] )) {
				$vData[ $teamSlot ]=$dist;
				break;
			} 
		}
		return $this->calcDistFromLastPlayed($CurrentSchedule, $Game, $vData, ($teamSlot+1));
	}
	
	/**
	 * Evenly Distribute Entity Team Postions (Sides/Pack Sets/etc)
	 *
	 * @var array $CurrentSchedule - an Array representingthe current sorted schedule of game 
	 * 
	 * @return Array - An array with the current schedule with the teams in evently distributed entity postions (sides/pack sets/etc) 
	 */	
	public function evenlyDistEntPos($CurrentSchedule) {
		//$CurrentScheduleTemp=$CurrentSchedule;
		for ($iGame = 0; $iGame < count($CurrentSchedule); $iGame++) {
			$CurrentScheduleTemp[$iGame]['games']=$CurrentSchedule[$iGame];
			$CurrentScheduleTemp[$iGame]['entposcount']=null;
			for ($iTeamSlot = 0; $iTeamSlot < $this->EntitiesPerGame; $iTeamSlot++ ){
				$CurrentScheduleTemp[$iGame]['entposcount'][$iTeamSlot]=0;
			}
		}
		//dumpGames(LOG_FILENAME, "Sort Schedule", true);
		$CurrentSchedule=null;//Rebuild CurrentSchedule with sorted 
		for ($iGame = 0; $iGame < count($CurrentScheduleTemp); $iGame++) {
			if ( $iGame== 24) {
				$iGame=$iGame;
			}
			$CurrentScheduleTemp[$iGame]['entposcount']=$this->calcEntityTeamSlotCountData($CurrentScheduleTemp, $iGame);
			$CurrentScheduleTemp[$iGame]['eplpdist']=$this->calcDistFromLastPlayedByGameIdx($CurrentScheduleTemp, $iGame);
			//$GamesLastPlayedModifier=$this->calcDistFromLastPlayedByGameIdx($CurrentScheduleTemp, $iGame);
			
			//dumpGames(LOG_FILENAME, $CurrentScheduleTemp, true);
			$CurrentSchedule[]=$this->sortGameByEntityPos($CurrentScheduleTemp[$iGame]);
			//we will need the temp table to have the game sorted,  otherwise future game count analysis will be wrong
			$CurrentScheduleTemp[$iGame]['games']=$CurrentSchedule[count($CurrentSchedule)-1];
			$CurrentScheduleTemp[$iGame]['entposcount']=array();
			$CurrentScheduleTemp[$iGame]['eplpdist']=array();
		}
		return $CurrentSchedule;
	}

	/**
	 * Sort Game by Entity Pos Count
	 *
	 * @var array $GameWithEntPosCount - an Array of $Game=('games'=(1,2,3), 'entposcount'=(array(1,2,3),array(1,2,3),array(1,2,3)), 'eplpdist'=(array(1,2,3),array(1,2,3),array(1,2,3)))
	 * 
	 * @return Array - An array of entities for a game in sorted order  
	 */	
	public function sortGameByEntityPos($GameWithEntPosCount) {
		$arrSort=null;
		//Don't sort if we don't have any entity that has played 2 or more games
		$bGTTwoGames=false;
		for ($EntSlot = 0; $EntSlot < count($GameWithEntPosCount['games']); $EntSlot++) {
			if ( max($GameWithEntPosCount['entposcount'][$EntSlot]) >= 2 ) $bGTTwoGames = true;		
		}
		if (!$bGTTwoGames) return $GameWithEntPosCount['games'];
		
		$EntityCount=count($GameWithEntPosCount['games']);
		$GameEntitiesSorted=null;
		while ( count($GameEntitiesSorted) < $EntityCount ) {
			$arrSort=null;
			if ( count($GameWithEntPosCount['games']) == 1 ) {
				$GameEntitiesSorted[]=$GameWithEntPosCount['games'][0];
				break;
			}
			$EntSlot=count($GameEntitiesSorted);
			for ($ipos = 0; $ipos < count($GameWithEntPosCount['entposcount']); $ipos++) {
				if ( isset($GameWithEntPosCount['games'][$ipos]) ) {
					//$arrSort[]=array('entity'=>$GameWithEntPosCount['games'][$ipos], 'poscount'=>$GameWithEntPosCount['eplpdist'][$ipos][$EntSlot], 'currpos'=>$ipos);
					$arrSort[]=array('entity'=>$GameWithEntPosCount['games'][$ipos], 'poscount'=>$GameWithEntPosCount['entposcount'][$ipos][$EntSlot]-(0.01*$GameWithEntPosCount['eplpdist'][$ipos][$EntSlot]), 'currpos'=>$ipos);
				}
			}
			usort($arrSort, 'comparePosCount');
			//$nextEntity=array_pop($arrSort);
			$nextEntity=array_shift($arrSort);
			$nextEntityOldIdxPos=$nextEntity['currpos'];
			
			//Delete the entity we just worked with (from the game AND the calculated entity position counts)
			array_remove($GameWithEntPosCount['games'], $nextEntityOldIdxPos);
			array_remove($GameWithEntPosCount['entposcount'], $nextEntityOldIdxPos);
			
			$GameEntitiesSorted[]=$nextEntity['entity'];
		}
		return $GameEntitiesSorted;
	}
	
	/**
	 * Take a game, and calculate how many times that tema played in that slot using the Current Schedule.  
	 *   This is useful for figuring out an even distribution of home/away/pack set/etc
	 *
	 * @var array $CurrentSchedule - an Array representingthe current sorted schedule of game 
	 * @var int $GameIdx - Current Index pointing to a game in CurrentSchedule 
	 * @var array [$entCountData] - an Array containing the amount of times the team in that team slot played in that side/pack set/home/visitor/etc 
	 * @var int [$evalTeamSlot] - The Current entity slot being evaluated
	 * 
	 * @return Array - An array with each slot having an array of the sides/pack sets available with a count of how many times the team in
	 *                 Team Slot of $evalTeamSlot has played 
	 */	
	public function calcEntityTeamSlotCountData($CurrentSchedule, $GameIdx, $entCountData=array(), $evalTeamSlot=0) {
		$Game=$CurrentSchedule[$GameIdx]['games'];
		if ( $evalTeamSlot > $this->EntitiesPerGame-1 ) return $entCountData;
		//Assume the current entity that is playing has not played in any side/packset before
		for ($iTeamSlot = 0; $iTeamSlot < $this->EntitiesPerGame; $iTeamSlot++ ){
			$entCountData[$evalTeamSlot][$iTeamSlot]=0;
		}
		$entityToSeachFor=$Game[$evalTeamSlot];
		//Go through each previous game and then go through each entity in the game,  if they match,  mark them as playing a game on that side/pack set
		for ($iGame = $GameIdx; $iGame >= 0; $iGame--) {
			for ($iTeamSlot = 0; $iTeamSlot < $this->EntitiesPerGame; $iTeamSlot++ ){
				if ( $CurrentSchedule[$iGame]['games'][$iTeamSlot]==$entityToSeachFor ) {
					$entCountData[$evalTeamSlot][$iTeamSlot]++;
				}
			}
		}
		return $this->calcEntityTeamSlotCountData($CurrentSchedule, $GameIdx, $entCountData, ($evalTeamSlot+1));
	}

	/**
	 * Take a Game ($Schedule[$gameidx]), and calculate the distances from the last time that player/team has played.  This function will be called recusively until it goes through all
	 * the players/teams in a particular game 
	 *
	 * @var array $Schedule - an Array representingthe current sorted schedule of game as a child array
	 * 								for example  [0]=(games=>[1,2,3], entposdata=>[1,2,3], [1]=(games=>[1,2,3], entposdata=>[1,2,3], ...
	 * @var array $Game - an Array representing a game,  with the entities participating in this game 
	 * @var int [$defaultValue] - The default value if a previous game has not been found,  default vlaue is RAW_GAME_ARRAY_COUNT
	 * @var array [$arrDist] - an Array containing the distance for that team 
	 * @var int [$teamSlot] - The Current Team slot being evaluated
	 * 
	 * @return Array - An array with each slot corresonding to the distance of each team (at Schedule[$GameIdx]) and when the last time they played,
	 *   the array will have sub arrays for each entity slot for that team games(1,2,3)->(team1_slot(1,2,3), team2_slot(1,2,3), team3_slot(1,2,3))  
	 */	
	public function calcDistFromLastPlayedByGameIdx($Schedule, $GameIdx, $defaultValue=RAW_GAME_ARRAY_COUNT, $arrDist=array(), $teamSlot=0) {
		if ( $teamSlot > $this->EntitiesPerGame-1 ) return $arrDist;
		$entityToSeachFor=$Schedule[$GameIdx]['games'][$teamSlot];
		$dist=0;
		//$arrDistLocal[ $teamSlot ]=(($defaultValue==RAW_GAME_ARRAY_COUNT) ? count( $this->rawGames ) : $defaultValue);
		for ($iTeamSlot=0; $iTeamSlot < $this->EntitiesPerGame; $iTeamSlot++) {
			$arrDistLocal[ $iTeamSlot ]=-1;
			//$arrDistLocal[ $iTeamSlot ]=(($defaultValue==RAW_GAME_ARRAY_COUNT) ? count( $this->rawGames ) : $defaultValue);
		} 
		for ($iGame = $GameIdx - 1; $iGame >= 0; $iGame--) {
			$dist++;
			for ($iTeamSlot=0; $iTeamSlot < $this->EntitiesPerGame; $iTeamSlot++) {
				if (($arrDistLocal[ $iTeamSlot ]==-1) && ( $entityToSeachFor==$Schedule[$iGame]['games'][$iTeamSlot] )) {
					$arrDistLocal[ $iTeamSlot ]=$dist;
					break;
				}
			} 
		}
		$arrDist[$teamSlot]=$arrDistLocal;
		return $this->calcDistFromLastPlayedByGameIdx($Schedule, $GameIdx, $defaultValue, $arrDist, ($teamSlot+1));
	}
		
	/**
	 * Calculate a modifer that will differentiate same games (because we played the same entity already).  Loop through the 
	 * past games and subtract the raw game amount.  This will force games of teams that have already been played to be sorted before teams that have not played each other   
	 *
	 * @var array $CurrentSchedule - an Array representingthe current sorted schedule of game 
	 * @var array $Game - an Array representing a game,  with the entities participating in this game 
	 * 
	 * @return float - Returns a floating point modifier that will change the sort 
	 */	
	public function calcEntityPrevPlayedModifier($CurrentSchedule, $Game ) {
		$ret = 0;
		//$Modifer=count( $this->rawGames );
		$Modifer=count( $CurrentSchedule );
		//Subtract raw games,  this will sort games that do not have duplicates later than games that do,  the weight of this will be affected by
		//  How many teams are simlar
		for ($iGame=0; $iGame < count($CurrentSchedule); $iGame++) {
			$similarEntityCount=((count($Game)-1) - count(array_diff($Game, $CurrentSchedule[$iGame])));
			if ( $similarEntityCount < 0 ) $similarEntityCount=0;
			$ret=$ret-($Modifer * $similarEntityCount);
			//if ( $Game == $CurrentSchedule[$iGame] ) {
			//	//$pctDiff=count(array_diff($Game, $CurrentSchedule[$iGame]))/count($Game);
			//	$pctDiff=(count($Game) - count(array_diff($Game, $CurrentSchedule[$iGame])));
			//	$ret=$ret-($Modifer * $pctDiff);
			//}
		}
		//weigh higher game numbers so that schedule for later remainging games will get sorted later when there are games
		//  that are close candidates
		return $ret;
	}
	
	/**
	 * Take a game, and calculate the counts that each of the entities in a particular game has played
	 *
	 * @var array $CurrentSchedule - an Array representingthe current sorted schedule of game 
	 * @var array $Game - an Array representing a game,  with the entities participating in this game 
	 * @var array [$vData] - an Array containing the distance for that team 
	 * @var int [$teamSlot] - The Current Team slot being evaluated
	 * 
	 * @return Array - An array with each slot corresonding to the distance of each team and when the last time they played 
	 */	
	public function calcGamesPlayed($CurrentSchedule, $Game, $countdata=array(), $teamSlot=0) {
		$retGame = null;
		if ( $teamSlot > $this->EntitiesPerGame-1 ) return $countdata;
		//$Game=$CurrentSchedule[count($CurrentSchedule)-1];
		$entityToSeachFor=$Game[$teamSlot];
		$count=0;
		for ($iGame = count($CurrentSchedule) - 1; $iGame >= 0; $iGame--) {
			if ( in_array($entityToSeachFor, $CurrentSchedule[$iGame] )) {
				$count++;
			} 
		}
		$countdata[ $teamSlot ]=$count;
		return $this->calcGamesPlayed($CurrentSchedule, $Game, $countdata, ($teamSlot+1));
	}
	
	/**
	 * Calculate the amount of games an Entity has played
	 *
	 * @var int $Entity - The entity to calculate the game count sum for  
	 * @var array [$CurrentSchedule] - Current Schedule to calculate from,  otherwise use the current schedule 
	 * 
	 * @return Array - An array with each slot corresonding to the distance of each team and when the last time they played 
	 */	
	public function getGamesPlayedCount($Entity, $CurrentSchedule=null) {
		if ( $CurrentSchedule== null ) {
			$CurrentSchedule=$this->Schedule;
		}
		$arr=$this->calcGamesPlayed($CurrentSchedule, array($Entity));
		return $arr[0];
	}
	
	
	/**
	 * Out of the remainging games in the schedule,  return the next game while taking into account back to back games 
	 *
	 * @var int $RemainingGames - an Array with the following:
	 * //, 'countdata'=>null, 'entdist'=>null
	 * 		[ 'games'=array(n1,n2,n...), 'value'=<sort value>, 'vdata'=array(t1,t2,t...), 'countdata'=(y1,y1,...y), 'entdist'=(z1,z2,...z)), 'entposcount'=(e1,e2,...e) ]
	 * 		n=team schedule
	 *      t=distance from the last game that team played
	 *      y=the amount of times a team has played 
	 *      z=the distance between each teams (used to force games with teams close together more apart)
	 *      e=the count that a team is in a entity position (for example, pos1 vs pos2 may represent home/visitor, or pos1 vs pos2 vs pos3 may represent pack sets)
	 * 
	 * @return Array - the next game, will return null of no more games are remainging
	 */	
	public function nextGame($CurrentSchedule, &$RemainingGames) {
		$retGame = null;
		if ( count($RemainingGames)==0 ) return null;
		$RemainingGamesCount=count($RemainingGames);
		//Generate DistancefromLastGamePlayed Data (vdata) and gamecount data
		for ($i = 0; $i <= $RemainingGamesCount-1; $i++) {
			$RemainingGames[$i]['vdata']=$this->calcDistFromLastPlayed($CurrentSchedule, $RemainingGames[$i]['games']);
			
			//Calculate Entity numerical distances from each other 
			$entdist=null;
			for ($g = 1; $g < count($RemainingGames[$i]['games']); $g++) {
				$entdist=$entdist+($RemainingGames[$i]['games'][$g]-$RemainingGames[$i]['games'][$g-1]);
			}
			$RemainingGames[$i]['entdist']=$entdist/(count($RemainingGames[$i]['games'])-1);
			$gameDistModifier=0;
			//if ( $RemainingGames[$i]['entdist'] > 5 ) {
			//	$gameDistModifier=(($i % 2 == 0) ? 0 : 1 );
			//	$gameDistModifier=$gameDistModifier*-5;
			//}
			
			//Create sortable value based on distance since last played, game already played and postional distance the other teams in the game
			$RemainingGames[$i]['value']=array_sum($RemainingGames[$i]['vdata'])
							+$this->calcEntityPrevPlayedModifier($CurrentSchedule, $RemainingGames[$i]['games'])+
							+$gameDistModifier
							//+(.001 * array_sum( $RemainingGames[$i]['games'] ))
							; 
			$RemainingGames[$i]['countdata']=$this->calcGamesPlayed($CurrentSchedule, $RemainingGames[$i]['games']);
		}
		if ( $this->GamesForEachEntity > 0 ) {
			$this->removeGamesBeyondMaxGameCount($RemainingGames, $this->GamesForEachEntity);
		}
		//dumpGames(DUMP_FILENAME, $RemainingGames, true);
		usort($RemainingGames, "compareSortValue");
		//dumpGames(DUMP_SORTED_FILENAME, $RemainingGames, true);
		//dumpGames(SCHED_FILENAME, $CurrentSchedule, true);
		$retGame=array_pop($RemainingGames);
		return $retGame['games']; 
	}
	
	/**
	 * gamecount data should have been calculated (nextGame Function).  this function will 
	 * 	1. sort the Remainging Games by Max Gamed Played Count
	 *  2. Remove ineligable games by popping them off the array 
	 *
	 * @var int $RemainingGames - an Array with the following:
	 * 		[ 'games'=array(n1,n2,n...), 'value'=<sort value>, 'vdata'=array(t1,t2,t...),  ]
	 * 		n=team schedule
	 *      t=distance from the last game that team played
	 * @var int [$MaxGameCount] - if 0 (and the object level GamesForEachEntity==0), then this
	 *   function will essentially be skipped,  otherwise,  evaluate the game to see if it should be removed because
	 *   an entity that is participating in it has played all their games.
	 * 
	 * @return int - the amount of games removed
	 */	
	public function removeGamesBeyondMaxGameCount(&$RemainingGames, $MaxGameCount = 0) {
		$removedCount=0;
		if ( $MaxGameCount == 0 ) return $MaxGameCount = $this->GamesForEachEntity;
		if (( $MaxGameCount == 0 ) || ( count($RemainingGames)==0 )) return $removedCount;

		//dumpGames(LOG_FILENAME, $RemainingGames, true);
		usort($RemainingGames, "compareGameCountMaxValue");
		//dumpGames(LOG_FILENAME, $RemainingGames);
		$RemainingGamesCount=count($RemainingGames);
		for ($i = $RemainingGamesCount-1; $i >=0 ; $i--) {
			if ( max($RemainingGames[$i]['countdata'])>=$this->GamesForEachEntity ) {
				unset($RemainingGames[$i]);
				$removedCount++;
			}
		}
		//dumpGames(LOG_FILENAME, $RemainingGames);
		//$RemainingGames=array_values($input);
		return $removedCount; 
	}
	
	/**
	 * Will go through the raw schedule of games and sort the games, spreading out the games evenly accross the tournament for each team.  
	 * It will also distribute the home/visitor/pack-set/team slot as fairly as possible.  The function will go through each game and assign a 
	 * sort value which will be a function the distance from the last game played.
	 *
	 * @return Array - The final Raw Schedule, with games distributed evenly accross the event
	 */	
	public function calcRawSchedule() {
		$RawGamesWork=null;  //This array will constantly get rebuilt as we try to figure out what game will be next
		$ScheduleSorted=null; //This is the temporary repository of the sorted schedule 
		//foreach ($this->rawGames as $game)
		for ($i = 0; $i <= count($this->rawGames)-1; $i++) {
			$game=$this->rawGames[$i];
			$RawGamesWork[]=array('games'=>$game, 'value'=>0, 'vdata'=>null, 'countdata'=>null, 'entdist'=>null);
		}
		$nextGame=$RawGamesWork[0]['games'];
		array_shift($RawGamesWork);
		do {
			$ScheduleSorted[]=$nextGame;
			$nextGame=$this->nextGame($ScheduleSorted, $RawGamesWork);
		} while ($nextGame!=null);
		return $ScheduleSorted;
	}
	
	/**
	 * Increment Game - This function will increment the current game.  Once you have reached the number of games per team,  
	 *  The will call itself recursively to increment the higher order entity column.  The goal is 
	 *  to go through every possible game that can be played.  Sure there will be some instances where
	 *  we will encounter games where we will play itself, but we will filter those instances out later
	 *  
	 * @var int $CurrentGame
	 * @var int [$currentEntityColumn]
	 *
	 * @return Array - an Array that represents the current game, each element in the array is the player/team in that current game
	 */	
	public function incGame($CurrentGame, $currentEntityColumn = -1) {
		if ( $currentEntityColumn==-1) $currentEntityColumn=$this->EntitiesPerGame;
		if ( $currentEntityColumn==0 ) return null; 
		if ( $CurrentGame[$currentEntityColumn-1] == $this->EntityCount ) {
			if ( $currentEntityColumn >= 1 ) {
				$CurrentGame = $this->incGame( $CurrentGame, $currentEntityColumn - 1);
				if ( $CurrentGame==null ) return null;
				$CurrentGame[$currentEntityColumn-1]=1;
			} else {
				return $CurrentGame;
				return null;
			}
		}
		$CurrentGame[$currentEntityColumn-1]++;
		return $CurrentGame;	
	}
	
	/**
	 *	This function will create an unsorted round robbin schedule of the games that will be played. 
	 *	This does not have to be in any order, as the order and sides will be determined in a downstream function.
	 *  Duplicate games will be removed.
	 *
	 * @return int - Game count created
	 */	
	public function createRawGames() {
		$CurrentGame=null;
		for ($iEntityNumberInGame = 0; $iEntityNumberInGame < $this->EntitiesPerGame; $iEntityNumberInGame++) {
			$CurrentGame[]=1;
		}
		$this->rawGames=null;
		do {
			//Only add this game to the current list of raw games if there are no duplicates contained within it
			if (count(array_unique($CurrentGame))==$this->EntitiesPerGame) {
				//sort($CurrentGame);
				$this->rawGames[]=$CurrentGame;
			}
			$CurrentGame = $this->incGame($CurrentGame);
		} while ($CurrentGame!=null);
		//resort individual games so future all-games sort will allow us to find duplicates
		for ($i = 0; $i <= count($this->rawGames)-1; $i++) {
			sort($this->rawGames[$i]);
		}
		//Sort on all raw games so we can find the duplicates
		usort($this->rawGames, "cmp");
		
		//Remove all duplicate games
		$rawGamesWork=super_unique($this->rawGames);
		//Reindex games
		$this->rawGames=array_values($rawGamesWork);
		//foreach($rawGamesWork as $game) {
		//	$this->rawGames[]=$game;
		//}
		$RawListMultiplier=1; //Assume single round robin
		//Figure out how many times we multiply the raw schedule,  then append it to the raw games list
		if ($this->GamesForEachEntity > 0) {
			$RawListMultiplier=(int)ceil($this->GamesForEachEntity/($this->EntityCount-1));
		} else if (($this->GamesForEachEntity < 0) && ($this->GamesForEachEntity >= -4)) {  //Max Quad Round Robbin :)  ..do we really need more?
			$RawListMultiplier=abs($this->GamesForEachEntity);
		}
		if ( $RawListMultiplier > 1 ) {
			$rawGamesImage=$this->rawGames;
			for ($i = 2; $i <= $RawListMultiplier; $i++) {
				foreach ($rawGamesImage as $Game) {
					$this->rawGames[]=$Game;
				}
			}
		}
		return count($this->rawGames);
	}

	/**
	 * allGamesPlayed
	 *	This function will determine if all the games have been played 
	 *
	 * @return bool
	 */	
	public function allGamesPlayed() {
		for ($i = 1; $i <= $this->gamesPlayed; $i++) {
    		if ( $this->gamesPlayed[i] < $GamesForEachEntity ) {
    			return false;
    		}
		}
		return true;
	}
}	
//define('ROOT_PATH',dirname(__FILE__).'/');
//define('LOG_FILENAME',ROOT_PATH.'logs/gamedump.txt');

function GameToString($game){
	$ret="";
	foreach ($game as $entity) {
		$ret.=str_pad($entity,4,'0',STR_PAD_LEFT);	
	}
	return $ret;
}
function cmp($a, $b){
    $l = GameToString($a);
    $r = GameToString($b);
    if($l == $r){
    	return 0;
    }
    return $l < $r ? -1 : 1;
}
function compareSortValue($a, $b){
    $l = $a['value'];
    $r = $b['value'];
    if($l == $r){
    	return 0;
    }
    return $l < $r ? -1 : 1;
}
function comparePosCount($a, $b){
    $l = $a['poscount'];
    $r = $b['poscount'];
    if($l == $r){
    	return 0;
    }
    return $l < $r ? -1 : 1;
}
function compareGameCountMaxValue($a, $b){
    $l = max($a['countdata']);
    $r = max($b['countdata']);
    if($l == $r){
    	return 0;
    }
    return $l < $r ? -1 : 1;
}

function super_unique($array)
{
  $result = array_map("unserialize", array_unique(array_map("serialize", $array)));

  foreach ($result as $key => $value)
  {
    if ( is_array($value) )
    {
      $result[$key] = super_unique($value);
    }
  }

  return $result;
}

function array_remove(&$array, $indexToRemove)
{
	unset($array[$indexToRemove]);
	$array=array_values($array);
}

function WriteString($FileName, $StringToWrite, $append = true) {
	$fh = fopen($FileName, ($append ? 'a':'w')) or die("can't open file");
	fwrite($fh, $StringToWrite);
	fclose($fh);
}

function dumpGames($fileName, $GamesArrayToDump, $newFile=false) {
	if ( $newFile ) {
		if(is_file("$fileName")) {
			unlink("$fileName");
		}
	}
	WriteString($fileName, "Game Dump\n", true);
	
	$cnt=0;
	$str='';
	foreach ($GamesArrayToDump as $Game) {
		$str="[".str_pad($cnt,3,'0',STR_PAD_LEFT)."]:\n";
		if ( isset($Game['value'])) {
			$str.="      value:".$Game['value'];
		}
		$bDisplayed=false;
		if ( isset($Game['games'])) {
			$str.="\n      games:";
		
			foreach ($Game['games'] as $entityInGame ) {
				$str.=' '.str_pad($entityInGame,2,'0',STR_PAD_LEFT).',';
			}
			$str=substr($str, 0, strlen($str)-1);
			$bDisplayed=true;
		}
		if ( isset($Game['vdata'])) {
			$str.="\n      vdata:";
			foreach ($Game['vdata'] as $valueData ) {
				$str.=' '.str_pad($valueData,2,'0',STR_PAD_LEFT).',';
			}
			$str=substr($str, 0, strlen($str)-1);
			$bDisplayed=true;
		}
		if ( isset($Game['countdata'])) {
			$str.="\n  countdata:";
			foreach ($Game['countdata'] as $countData ) {
				$str.=' '.str_pad($countData,2,'0',STR_PAD_LEFT).',';
			}
			$str=substr($str, 0, strlen($str)-1);
			$bDisplayed=true;
		}
		
		if ( isset($Game['entposcount'])) {
			$str.="\n  entposcount: ( ";
			foreach ($Game['entposcount'] as $entposcountData ) {
				if ( (isset($entposcountData)) && (is_array($entposcountData)) ) {
					$str.="[";
					foreach ($entposcountData as $entPosCount ) {
						$str.=' '.str_pad($entPosCount,2,'0',STR_PAD_LEFT).',';
					}
					$str=substr($str, 0, strlen($str)-1);
					$str.='],';
				}
			}
			$str=substr($str, 0, strlen($str)-1);
			$bDisplayed=true;
		}
		if ( isset($Game['eplpdist'])) {
			$str.="\n  eplpdist: ( ";
			foreach ($Game['eplpdist'] as $eplpDispData ) {
				if ( (isset($eplpDispData)) && (is_array($eplpDispData)) ) {
					$str.="[";
					foreach ($eplpDispData as $eplpDisp ) {
						$str.=' '.str_pad($eplpDisp,2,'0',STR_PAD_LEFT).',';
					}
					$str=substr($str, 0, strlen($str)-1);
					$str.='],';
				}
			}
			$str=substr($str, 0, strlen($str)-1);
			$bDisplayed=true;
		}
		if ( !$bDisplayed ) {
			foreach ($Game as $entity ) {
				$str.=' '.$entity.',';
			}
			$str=substr($str, 0, strlen($str)-1);
		}
		$str.="\n";
		$cnt++;
		WriteString($fileName, $str);
	}
}