<?php

abstract class Logger {
	protected $messages;

	public function __construct(Config $conf) {
		$this->cfg = $conf;
	}

	public function write($msg, $prefix = "")
	{
		if (is_array($msg)) {
			foreach ($msg as $line) {
				$this->output($prefix.$line);
			}
		} else
			$this->output($prefix.$msg);
	}

	public function debug($msg) {
		if ($this->cfg->debug)
			$this->write($msg, "DEBUG: ");
	}

	protected function output($msg) {
	}
}

?>
