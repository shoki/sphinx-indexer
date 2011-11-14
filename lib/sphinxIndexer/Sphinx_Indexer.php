<?php

class Sphinx_Indexer {
	private $cfg;

	public function __construct() {
		$this->cfg = new Config();
		$this->log = new EchoLogger($this->cfg);
	}

	public function run($task) {
		$config = $task[0];
		$indexes = array_slice($task, 1);

		foreach ($indexes as $index) {
			$lockKey = basename($config)."_".$index;
			$lock = new Lock($lockKey, $this->cfg->lockdir);
			try {
				$lock->lock();
			} catch (Exception $e) {
				$this->log->debug("Locked by another process!");
				continue;
			}

			try {
				if ($this->isInTransfer($index)) continue;

				$this->index($config, $index);
				$this->transfer($index);
			} catch (Exception $e) {
				$this->log->write("Exception: ".$e->getMessage());
			}

			$lock->unlock();
		}
	}

	private function index($config, $index) {
		$this->log->debug("indexing");
		$cmd = str_replace(array("[CONFIG]", "[INDEX]"), array($config, $index), $this->cfg->indexerCommand);
		$this->log->debug($cmd);
		$cr = new CommandRunner();
		$ret = $cr->run($cmd);
		if ($ret !== 0) {
			$this->log->write($cr->getLog());
			throw new Exception("indexing failed");
		}
	}

	private function isInTransfer($index) {
		foreach($this->cfg->indexFiletypes as $filetype) {
			if (file_exists($this->cfg->transferDir.'/'.$index.'.new.'.$filetype)) {
				$this->log->debug("transfer already running");
				return true;
			}
		}
		return false;
	}

	private function transfer($index) {
		$this->log->debug("pulling into transfer queue");

		foreach($this->cfg->indexFiletypes as $filetype) {
			$ret = rename(
					$this->cfg->indexDir."/".$index.".".$filetype,
					$this->cfg->transferDir."/".$index.".new.".$filetype
					);
			if(!$ret)
				throw new Exception("move failed for: ".$index." (".$filetype.")");
		}
	}
}

?>
