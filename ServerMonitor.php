<?php
class ServerMonitor {
	public $settings = array(
		'admin_menu_category' => 'General',
		'admin_menu_name' => 'Server Monitor',
		'admin_menu_icon' => '<i class="icon-tasks"></i>',
		'description' => 'Monitor servers and get notifications.',
	);
	function admin_area() {
		global $billic, $db;
		if (isset($_GET['Name'])) {
			$monitor = $db->q('SELECT * FROM `servermonitor_monitors` WHERE `name` = ?', urldecode($_GET['Name']));
			$monitor = $monitor[0];
			if (empty($monitor)) {
				err('Monitor does not exist');
			}
			set_title('Admin/Server Monitor ' . safetext($monitor['name']));
			echo '<h1>Server Monitor ' . safetext($monitor['name']) . '</h1>';
			$types = array(
				'ICMP',
				'TCP',
				//'UDP'
				
			);
			if (isset($_POST['update'])) {
				if (empty($_POST['name'])) {
					$billic->error('Name can not be empty', 'name');
				} else {
					$name_check = $db->q('SELECT COUNT(*) FROM `servermonitor_monitors` WHERE `name` = ? AND `id` != ?', $_POST['name'], $monitor['id']);
					if ($name_check[0]['COUNT(*)'] > 0) {
						$billic->error('Name is already in use by a different monitor', 'name');
					}
				}
				$_POST['ip'] = trim($_POST['ip']);
				if (empty($_POST['ip']) || !filter_var($_POST['ip'], FILTER_VALIDATE_IP)) {
					$billic->error('IP Address is invalid', 'ip');
				}
				$_POST['port'] = trim($_POST['port']);
				if ($_POST['type'] == 'ICMP') {
					$_POST['port'] = '';
				} else {
					if ($_POST['port'] < 1) {
						$billic->error('Port must be greater than 0', 'ip');
					} else if (!ctype_digit($_POST['port'])) {
						$billic->error('Port must be digits', 'ip');
					}
				}
				$_POST['check_every'] = trim($_POST['check_every']);
				if ($_POST['check_every'] < 1) {
					$billic->error('Interval must be greater than 0', 'ip');
				} else if (!ctype_digit($_POST['check_every'])) {
					$billic->error('Interval must be digits', 'ip');
				}
				if (!in_array($_POST['type'], $types)) {
					$billic->error('Type is invalid', 'type');
				}
				if (empty($billic->errors)) {
					$db->q('UPDATE `servermonitor_monitors` SET `name` = ?, `ip` = ?, `port` = ?, `type` = ?, `check_every` = ? WHERE `id` = ?', $_POST['name'], $_POST['ip'], $_POST['port'], $_POST['type'], $_POST['check_every'], $monitor['id']);
					$monitor = $db->q('SELECT * FROM `servermonitor_monitors` WHERE `name` = ?', $monitor['id']);
					$monitor = $monitor[0];
					$billic->status = 'updated';
				}
			}
			$billic->show_errors();
			echo '<form method="POST"><table class="csstable"><tr><th colspan="2">Monitor Settings</th></td></tr>';
			if (isset($_POST['name'])) {
				$monitor['name'] = $_POST['name'];
			}
			echo '<tr><td width="125">Name</td><td><input type="text" name="name" value="' . $monitor['name'] . '"></td></tr>';
			if (isset($_POST['ip'])) {
				$monitor['ip'] = $_POST['ip'];
			}
			echo '<tr><td width="125">IP Address</td><td><input type="text" name="ip" value="' . $monitor['ip'] . '"></td></tr>';
			if (isset($_POST['port'])) {
				$monitor['port'] = $_POST['port'];
			}
			echo '<tr><td width="125">Type</td><td>';
			echo '<select name="type">';
			foreach ($types as $type) {
				echo '<option value="' . $type . '"' . ($monitor['type'] == $type ? ' selected' : '') . '>' . safetext($type) . '</option>';
			}
			echo '</select>';
			echo '<tr><td width="125">Port</td><td><input type="text" name="port" value="' . $monitor['port'] . '" style="width: 100px"> (Leave blank for ICMP)</td></tr>';
			if (isset($_POST['type'])) {
				$monitor['type'] = $_POST['type'];
			}
			if (isset($_POST['check_every'])) {
				$monitor['check_every'] = $_POST['check_every'];
			}
			echo '<tr><td width="125">Interval</td><td>Check every <input type="text" name="check_every" value="' . $monitor['check_every'] . '" style="width: 50px"> minutes</td></tr>';
			echo '</td></tr>';
			echo '<tr><td colspan="4" align="center"><input type="submit" name="update" value="Update &raquo;"></td></tr></table></form>';
			return;
		}
		if (isset($_GET['New'])) {
			$title = 'New Monitor';
			set_title($title);
			echo '<h1>' . $title . '</h1>';
			$form = array(
				'name' => array(
					'label' => 'Name',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			$billic->module('FormBuilder');
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				if (empty($billic->errors)) {
					$db->insert('servermonitor_monitors', array(
						'name' => $_POST['name'],
						'check_every' => 1,
						'status_since' => time() ,
					));
					$billic->redirect('/Admin/ServerMonitor/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Delete'])) {
			$db->q('DELETE FROM `servermonitor_monitors` WHERE `name` = ?', urldecode($_GET['Delete']));
			$billic->status = 'deleted';
		}
		$total = $db->q('SELECT COUNT(*) FROM `servermonitor_monitors`');
		$total = $total[0]['COUNT(*)'];
		$pagination = $billic->pagination($total);
		echo $pagination['menu'];
		$monitors = $db->q('SELECT * FROM `servermonitor_monitors` ORDER BY `name` ASC LIMIT ' . $pagination['start'] . ',' . $pagination['limit']);
		set_title('Admin/Server Monitor');
		echo '<h1><i class="icon-tasks"></i> Server Monitor</h1>';
		$billic->show_errors();
		echo '<p><i class="icon-plus green"></i> <a href="New/">New Monitor</a></p>';
		echo '<div style="float: right;padding-right: 40px;">Showing ' . $pagination['start_text'] . ' to ' . $pagination['end_text'] . ' of ' . $total . ' Monitors</div>';
		echo '<table class="csstable"><tr><th>Name</th><th>IP</th><th>Type</th><th>Status</th><th style="width:5%">Actions</th></tr>';
		if (empty($monitors)) {
			echo '<tr><td colspan="20">No Monitors matching filter.</td></tr>';
		}
		foreach ($monitors as $monitor) {
			echo '<tr><td><a href="/Admin/ServerMonitor/Name/' . urlencode($monitor['name']) . '/">' . safetext($monitor['name']) . '</a></td><td>' . $monitor['ip'] . '</td><td>' . $monitor['type'] . '' . ($monitor['type'] != 'ICMP' ? ' Port ' . $monitor['port'] : '') . '</td><td>' . $monitor['status'] . '</td><td><a href="/Admin/ServerMonitor/Name/' . urlencode($monitor['name']) . '/"><i class="icon-edit"></i></a>'; //  ('.$billic->timeago($monitor['status_since']).')
			echo '&nbsp;<a href="/Admin/ServerMonitor/Delete/' . urlencode($monitor['name']) . '/" title="Delete" onClick="return confirm(\'Are you sure you want to delete?\');"><i class="icon-remove red"></i></a>';
			echo '</td></tr>';
		}
		echo '</table>';
	}
	function cron() {
		global $billic, $db;
		$monitors = $db->q('SELECT * FROM `servermonitor_monitors` WHERE `last_checked` < (' . time() . '-(`check_every`*60))');
		foreach ($monitors as $monitor) {
			if (empty($monitor['ip'])) {
				continue;
			}
			$status = false;
			switch ($monitor['type']) {
				case 'ICMP':
					$ping = @shell_exec('ping ' . $monitor['ip'] . ' -c 1 -W 3 2>&1');
					preg_match('~([0-9]+) received~', $ping, $count);
					if (!empty($count[0])) {
						$count = $count[1];
						if ($count > 0) {
							$status = 'UP';
						} else {
							$status = 'DOWN';
						}
					}
					if ($status === false) {
						/* ICMP ping packet with a pre-calculated checksum */
						$package = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
						$socket = @socket_create(AF_INET, SOCK_RAW, 1);
						@socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array(
							'sec' => $timeout,
							'usec' => 0
						));
						@socket_connect($socket, $monitor['ip'], null);
						$ts = microtime(true);
						@socket_send($socket, $package, strLen($package) , 0);
						if (@socket_read($socket, 255)) {
							$status = 'UP';
						} else {
							$status = 'DOWN';
						}
						@socket_close($socket);
					}
				break;
				case 'TCP':
					$waitTimeoutInSeconds = 3;
					$fp = @fsockopen($monitor['ip'], $monitor['port'], $errCode, $errStr, 3);
					if ($fp) {
						$status = 'UP';
					} else {
						$status = 'DOWN';
					}
					if (is_resource($fp)) {
						fclose($fp);
					}
				break;
				default:
					$status = 'Connection type is unsupported';
				break;
			}
			if ($status == $monitor['status']) {
				// keep the status_since the same because the status has not changed
				$status_since = $monitor['status_since'];
			} else {
				$status_since = time();
			}
			$db->q('UPDATE `servermonitor_monitors` SET `last_checked` = ?, `status` = ?, `status_since` = ? WHERE `id` = ?', time() , $status, $status_since, $monitor['id']);
		}
	}
}
