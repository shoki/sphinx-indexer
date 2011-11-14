<?php

class SyslogLogger extends Logger {
	public function __construct(Config $conf, $myname) {
		parent::__construct($conf);
		define_syslog_variables();
		openlog($myname, LOG_PID, LOG_DAEMON);
	}

	protected function output($msg) {
		syslog(LOG_WARNING, $msg);
	}
}
?>
