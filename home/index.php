<?php
# check for existence of cookie

if(!isset($_COOKIE['iff-id'])) header('Location: http://www.ifantasyfitness.com');
include('../php/db.php');  # include data base
$id = $_COOKIE['iff-id'];

$check_q = @mysqli_query($db, "SELECT * FROM users WHERE id=$id");
if(mysqli_num_rows($check_q) > 0) {
	$user = mysqli_fetch_array($check_q);
	# confirm with social token
	$valid = false;
	if(isset($_COOKIE['iff-google']) and $_COOKIE['iff-google'] === $user['google']) $valid = true;
	if(isset($_COOKIE['iff-facebook']) and $_COOKIE['iff-facebook'] === $user['facebook']) $valid = true;
	if(!$valid) header('Location: http://www.ifantasyfitness.com');
	
	# now grab the user's team number
	# If no team number is in use, use 0.
	$team_grabber = @mysqli_query($db, "SELECT * FROM tMembers WHERE user=$id ORDER BY team DESC");
	if(mysqli_num_rows($team_grabber) >= 1) {
		$team_data = mysqli_fetch_array($team_grabber);
		$myTeam = $team_data['team'];
	} else {
		$myTeam = 0;
	}
} else {
	setcookie('iff-id',0,4,'/','.ifantasyfitness.com');
	header('Location: http://www.ifantasyfitness.com');
}

$now = time();
$season_start = $now + (7*24*60*60);
$season_end = $now - (14*24*60*60);
$seasons = array();
$seasonDataQ = @mysqli_query($db, "SELECT * FROM seasons WHERE reg_start <= $season_start AND comp_end >= $season_end");
while($seasonData = mysqli_fetch_array($seasonDataQ)) {
	if($seasonData['reg_start'] <= $now and $seasonData['reg_end'] >= $now) {
		# Registration for this Season is open!
		$seasons[$seasonData['name']] = 'r_open_'.$seasonData['display_name'];
	} elseif ($seasonData['comp_start'] <= $now and $seasonData['comp_end'] >= $now) {
		# Competition for this Season is open!
		$day = ceil(($now - $seasonData['dailygoal_start']) / (24*60*60));
		$seasons[$seasonData['name']] = 'c_open_'.$seasonData['display_name'];
	} else {
		# Season is inactive
		if($seasonData['reg_start'] > $now) {
			$seasons[$seasonData['name']] = 'rg_soon'.$seasonData['display_name'];
		} elseif ($seasonData['comp_end'] < $now) {
			$seasons[$seasonData['name']] = 'com_end'.$seasonData['display_name'];
		}
	}
}

$title = 'Home';
$connected = true;
include('../php/head-auth.php');
?>
<div class="row">
	<div class="col-xs-12">
		<?php
		if(isset($_COOKIE['total']) and $_COOKIE['total'] > 0) {
			echo '<div class="alert alert-success">
			<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
			<h4><i class="fa fa-check"></i> Huzzah! Your record has been saved.</h4>';
			if($_COOKIE['total'] == 1) {
				echo '<p>1 point has been deposited to your account.</p>';
			} else {
				echo '<p>'.$_COOKIE['total'].' points have been deposited to your account.</p>';
			}
			echo 'You can view your record below, and edit it on your <a href="/records" class="alert-link">record transcript</a>.';
			if($day > 0) echo ' This record has been posted to the <a href="/leaderboard" class="alert-link">leaderboard</a>.';
			echo '</div>';
		}
		if(isset($_COOKIE['reg-confirmed'])) echo '<div class="alert alert-success">
			<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
			<i class="fa fa-check"></i> You have successfully registered for the '.$_COOKIE['reg-confirmed'].' season!</div>';
		if(isset($_COOKIE['reg-exists'])) echo '<div class="alert alert-info">
			<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
			<h4><i class="fa fa-info"></i> It looks like you\'re already registered for the '.$_COOKIE['reg-exists'].' season.</h4> Need to change your settings or drop out? Do that <a class="alert-link" href="/settings/goals">here</a>.</div>';
		if(isset($_COOKIE['cap'])) echo '<div class="alert alert-warning">
			<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
			<h4><i class="fa fa-warning"></i> Cap Exceeded</h4>
			Your record has been saved. However, it exceeded the '.$_COOKIE['cap'].' cap, and your point totals have been adjusted accordingly.</div>';
		?>
	</div>
</div>
<?php
# If registration is open give link to registration file
if(!empty($seasons)) {
	# Valid seasons
	foreach($seasons as $key => $status) {
		$season_status = substr($status, 0, 7);
		if($season_status == 'r_open_') {
			# registration is open
			# Only give the link if user IS NOT ALREADY REGISTERED TO THAT SEASON
			$season_checker = @mysqli_query($db, "SELECT * FROM tMembers WHERE user=$id AND season='$key'");
			if(mysqli_num_rows($season_checker) == 0) echo '<div class="row"><div class="col-xs-12">
			<p class="lead">Registration is open for the '.substr($status,7).' season!<a href="/register?season='.$key.'" class="btn btn-primary pull-right">Register for '.$key.'</a></p>
			</div></div>';
		} elseif ($season_status == 'rg_soon') {
			echo '<div class="row"><div class="col-xs-12">
			<p class="lead">Registration for the '.substr($status,7).' season will open soon!</p></div></div>';
		} elseif ($season_status == 'com_end') {
			echo '<div class="row"><div class="col-xs-12">
			<p class="lead">Competition in the '.substr($status,7).' season has ended.<a href="/leaderboard?filter=ind&season='.$key.'" class="btn btn-primary pull-right">Go to '.$key.' leaderboard</a></p></div></div>';
		}
	}
}
?>
<div class="row">
	<div class="col-xs-12 col-sm-7 col-md-8">
		<h2>Activity</h2>
		<?php
		$activities = @mysqli_query($db, "SELECT * FROM records ORDER BY timestamp DESC LIMIT 25");
		$record_types = array("run" => "Running", "run_team" => "Running at Monument", "rollerski" => "Rollerskiing", "walk" => "Walking", "hike" => "Hiking with packs", "bike" => "Biking", "swim" => "Swimming", "paddle" => "Paddling, Rowing or Kayaking", "strength" => "Strength or core training", "sports" => "Aerobic sports");
		$use_minutes = array('paddle','strength','sports');
		while($record = mysqli_fetch_array($activities)) {
			echo '<div class="panel';
			if($record['user'] == $id) { # this is your record
				echo ' panel-success';
			} elseif ($record['team'] == $myTeam and $myTeam != 0) { # made by a teammate
				echo ' panel-info';
			} else {
				echo ' panel-default';
			}
			echo '">
				<div class="panel-heading">
					<h3 class="panel-title">';
			$record_user = $record['user'];
			$retr_name = @mysqli_query($db, "SELECT * FROM users WHERE id=$record_user");
			$record_u_info = mysqli_fetch_array($retr_name);
			echo $record_u_info['first'].' '.$record_u_info['last'].'<span class="pull-right">';
			if($record['user'] == $id) { # this is your record
				echo '<abbr title="This record was posted by you."><i class="fa fa-user"></i></abbr> ';
			} elseif ($record['team'] == $myTeam and $myTeam != 0) { # made by a teammate
				echo '<abbr title="This record was posted by a teammate."><i class="fa fa-users"></i></abbr> ';
			}
			echo round($record['total'],2).'</span></h3>
			</div>
			<div class="panel-body">
			<table class="table">
				<thead>
					<tr>
						<th class="col-xs-6">Activity</th>
						<th class="col-xs-3">Duration</th>
						<th class="col-xs-3">Points</th>
					</tr>
				</thead>
				<tbody>';
			# Record Data
			foreach($record_types as $data=>$disp) {
				$points = $data . '_p';
				if($record[$data] != 0) {
					echo '<tr>
					<td>'.$disp.'</td>
					<td>'.round($record[$data],2);
					if(in_array($data, $use_minutes)) {
						echo ' minute';
					} else {
						echo ' mile';
					}
					if($record[$data] != 1) echo 's';
					echo '</td>
					<td>'.round($record[$points],2).'</td>
					</tr>';
				}
			}
			echo '</tbody>
			</table>';
			if($record['altitude'] != 1) echo '<p><strong>Altitude bonus awarded:</strong> x'.$record['altitude'].'</p>';
			if(!empty($record['comments'])) echo '<p><strong>Comment:</strong> '.$record['comments'].'</p>';
			echo 'Total: '.round($record['total'],2).' point';
			if($record['total'] != 1) echo 's';
			echo '<span class="pull-right">Posted: '.date('F j, Y g:i:s a',$record['timestamp']).'</span>';
			echo '</div>
			</div>';
		}
		?>
	</div>
	<div class="col-xs-12 col-sm-5 col-md-4">
		<h2>Quick Add Points</h2>
		<?php
		if($user['profile'] == 0) {
			echo ('
		<form name="quick-add" action="/add/index.php" method="post">
			<div class="row">
				<div class="col-xs-6">
					<select name="type" class="form-control">
						<option value="run">Running</option>
						<option value="run_team">Monument Running</option>
						<option value="rollerski">Rollerskiing</option>
						<option value="walk">Walking</option>
						<option value="hike">Hiking with Packs</option>
						<option value="swim">Swimming</option>
						<option value="bike">Biking</option>
					</select>
				</div>
				<div class="col-xs-6">
					<div class="input-group">
						<input type="text" class="form-control" name="distance">
						<span class="input-group-addon">miles</span>
					</div>
					<br>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12">
					<input type="text" name="comments" placeholder="Comments (will be shared)" class="form-control"><br>
				</div>
			</div>
			<div class="row">
				<div class="col-xs-12">
					<input type="hidden" name="submitted" value="quick">
					<input type="submit" class="btn btn-primary btn-block" value="Save Record"><br><p>If you want to record aerobic sports, strength training or paddling, if you are at altitude, or if you want to add multiple workout types in one record, please use the <a href="/add">full Add Points</a> page.</p>
				</div>
			</div>
		</form>');
		} else {
			echo '<p>You need to set up your profile first!</p><a href="/settings/profile" class="btn btn-primary btn-block">Set up profile</a>';
		}
		?>
		<hr>
		<h2>Goals</h2>
		<?php
		if($team_data['prediction'] == 0) {
			$prog_value = 0;
			echo '<p><strong>Season goal:</strong> No prediction set!</p>';
		} else {
			$prog_value = ($team_data['season_total'] / $team_data['prediction']) * 100;
			echo '<p><strong>Season goal:</strong> '.$team_data['season_total'].' of '.$team_data['prediction'].' points scored</p>';
		}
		echo '<div class="progress">
			<div class="progress-bar';
		if($team_data['season_total'] > $team_data['prediction']) {
			echo ' progress-bar-info';
		} elseif ($team_data['season_total'] > 0.9 * $team_data['prediction']) {
			echo ' progress-bar-success';
		} elseif ($team_data['season_total'] >= 0.5 * $$team_data['prediction']) {
			echo ' progress-bar-danger';
		}
		echo '" aria-valuenow="'.$prog_value.'" aria-valuemin="0" aria-valuemax="100" style="width: '.$prog_value.'%;"></div>
		</div>';
		if($day > 0) {
			$daily_goal_category = ($team_data['daily_goal']);
			$daily_goal_fetcher = @mysqli_query($db, "SELECT * FROM dailygoals WHERE day=$day");
			$daily_goal_data = mysqli_fetch_array($daily_goal_fetcher);
			$daily_goal = $daily_goal_data[$daily_goal_category];
		} else {
			$daily_goal = 0;
		}
		if($daily_goal == 0) {
			$dg_value = 0;
			echo '<p><strong>Daily goal:</strong> No daily goal today!';
		} else {
			$dg_value = ($team_data['day_run'] / $daily_goal) * 100;
			echo '<p><strong>Daily goal:</strong> '.$team_data['day_run'].' of '.$daily_goal.' miles ran';
		}
		echo '<div class="progress">
			<div class="progress-bar';
		if($team_data['day_run'] > $daily_goal) {
			echo ' progress-bar-info';
		} elseif ($team_data['day_run'] > 0.9 * $daily_goal) {
			echo ' progress-bar-success';
		} elseif ($team_data['day_run'] >= 0.5 * $daily_goal) {
			echo ' progress-bar-danger';
		}
		echo '" aria-valuenow="'.$dg_value.'" aria-valuemin="0" aria-valuemax="100" style="width: '.$dg_value.'%;"></div>
		</div>';
		?>
		<hr>
		<h2>Quick Links</h2>
		<ul>
			<li><a href="/add">Add points</a></li>
			<li><a href="/leaderboard">View leaderboard</a></li>
			<li><a href="/records">View records</a></li>
			<li><a href="/print">Print reports</a></li>
			<li><a href="/import">Import records</a></li>
			<li><a href="/rules/ask">Message Rules Committee</a></li>
			<li><a href="/settings">Account settings</a></li>
			<li><a href="/settings/profile">Add/remove social networks</a></li>
			<li><a href="http://www.dreamhost.com/donate.cgi?id=17581">Support us</a> (Help offset our hosting bill)</li>
			<li><a href="/logout">Sign out</a></li>
		</ul>
	</div>
</div>
<?php
include('../php/foot.php');
?>