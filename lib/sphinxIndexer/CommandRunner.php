<?php
class CommandRunner {
	protected $log = array();
	protected $logger;

	public function __construct($logger = null) {
		if ($logger) $this->logger = $logger;
	}

	protected function logit($msg) {
		if (is_array($msg)) {
			foreach($msg as $entry) {
				if ($entry)
					$this->addLogEntry($entry);
			}
		} else
			$this->addLogEntry($msg);
	}

	protected function addLogEntry($msg) {
		if ($this->logger)
			$this->logger->write(rtrim($msg));
		else 
			$this->log[] = $msg;
	}

	public function getLog() {
		return $this->log;
		$this->log = array();
	}

	public function run($cmd) {
		$pipes;
		$ret = -1;
		
		$desc = array (
			0 => array ("pipe", "r"),
			1 => array ("pipe", "w"),
			2 => array ("pipe", "w")
			);

		$proc = proc_open($cmd, $desc, $pipes);
		if (is_resource($proc)) {
			if ($out = stream_get_contents($pipes[1])) 
				$this->logit(explode("\n", $out));
			if ($out = stream_get_contents($pipes[2]))
				$this->logit(explode("\n", $out));

			foreach ($pipes as $idx => $value) {
				fclose($pipes[$idx]);
			}

			$ret = proc_close($proc);
		}
		return $ret;
	}

	/* this won't work if sigchld is masked */
	public function exec($cmd) {
		$ret = -1;
		exec($cmd." 2>&1", $output, $ret);
		$this->logit($output);
		return $ret;
	}

	public function pexec($cmd) {
		$ret = -1;
		$p = popen($cmd.' 2>&1', 'r');
		while (!feof($p)) {
			$this->logit(fgets($p, 1024));
		}
		$ret = pclose($p);
		$this->logit(pcntl_wexitstatus($ret));
		return $ret;
	}

}

?>
