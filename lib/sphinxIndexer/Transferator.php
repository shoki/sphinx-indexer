<?php

class Transferator extends Daemon {
	protected $fileQueue = array();
	protected $sendQueue = array();
	protected $completeFiles = 0;

	public function __construct() {
		$this->cfg = new Config();
		$this->workdir = $this->cfg->transferDir;
		$this->lastSendTime = time();

		$this->pidFileLocation = $this->cfg->pidfile;
		$this->userID = $this->cfg->userid;
		$this->groupID = $this->cfg->groupid;

		if ($this->cfg->daemonize) {
			parent::__construct();
			$this->log = new SyslogLogger($this->cfg, basename(__FILE__));
		} else {
			$this->log = new EchoLogger($this->cfg);
		}
	}

	/* glue for daemon class */
	public function _doTask() {
		$this->run();
	}

	public function _logMessage($msg, $level = DLOG_NOTICE) {
		$this->log->write($msg);
	}

	public function start() {
		if ($this->cfg->daemonize) {
			ini_set('display_errors', 0);
			//ini_set('error_log', '/tmp/error.log');
			declare(ticks = 1);
			pcntl_signal(SIGTERM, array(&$this, 'sigHandler'));
			/* need to keep this off or exec() won't return return values */
			//pcntl_signal(SIGCHLD, SIG_IGN);
			return parent::start();
		} else {
			$this->run();
		}
	}

	public function sigHandler($signo) {
		switch ($signo) {
			case SIGTERM:  
				$this->_logMessage('Shutdown signal');
				exit();
				break;
			default:
				$this->_logMessage('unknown signal: '.$signo);
				break;
		}
	}

	public function run() {
		/* add files left in workdir to sendqueue */
		$this->requeueStaleFiles();

		$notifyfd = inotify_init();
		$watchfd = inotify_add_watch($notifyfd, $this->workdir, IN_MOVED_TO);

		/* not required, but I like it */
		stream_set_blocking($notifyfd, 0);

		while (true) {
			$read = array($notifyfd);
			$write = $except = null;
			$fd = stream_select($read, $write, $except, 1);
			if ($fd > 0) {
				$events = inotify_read($notifyfd);
				$this->processEvents($events);
			}
			$this->processFileQueue();
			$this->processSendQueue();
		}
		fclose($notifyfd);
	}

	protected function requeueStaleFiles() {
		$i = new DirectoryIterator($this->workdir);
		foreach ($i as $file) {
			if ($file->isFile()) {
				$this->log->debug("requeue file: ".$file->getFilename());
				$this->enqueueFile($file->getFilename());
			}
		}
	}

	protected function processEvents($events) {
		foreach($events as $event) {
			//$this->log->debug("new file: ".$event['name']);
			$this->enqueueFile($event['name']);
		}
	}

	protected function enqueueFile($filename) {
		$file = explode(".", $filename);
		if (false !== array_search($file[2], $this->cfg->indexFiletypes)) {
			$fname = $file[0].'.'.$file[1];
			$this->fileQueue[$fname][$file[2]] = 1;
		} else {
			$this->log->write("invalid file: ".$filename);
		}
	}

	protected function processFileQueue() {
		foreach ($this->fileQueue as $file => $types) {
			$complete = true;
			foreach ($this->cfg->indexFiletypes as $type) {
				if (!isset($types[$type])) {
					$complete = false;
					break;
				}
			}
			if ($complete) {
				$this->enqueueSendFile($file);
			} else {
				//$this->log->debug("incomplete file: ".$file);
			}
		}
	}

	protected function enqueueSendFile($file) {
		if (isset($this->sendQueue[$file])) return;
		$this->sendQueue[$file] = 1;
		$this->completeFiles++;
	}

	protected function processSendQueue() {
		if (!$this->completeFiles) return;

		$this->nextSendTime = $this->lastSendTime + $this->cfg->sendInterval;

		$time = time();
		if ($this->nextSendTime < $time || $this->completeFiles > $this->cfg->sendCompleteFiles) {
			if ($this->completeFiles > $this->cfg->sendCompleteFiles) {
				$this->log->debug("trigger upload because of completeFiles limit: ".$this->completeFiles);
			} else {
				$this->log->debug("trigger upload because of time limit: ".($time - $this->nextSendTime)." seconds");
			}
			try {
				$this->sendFiles($this->sendQueue, $this->cfg->indexFiletypes);
				foreach ($this->sendQueue as $fileToSend => $crap) {
					$this->dequeueFile($fileToSend);
					$this->completeFiles--;
				}
				$this->sendQueue = array();
			} catch (Exception $e) {
				$this->log->write("send failed, not flushing send queue");
			}
		}

	}

	protected function sendFiles($files, $types) {
		$this->lastSendTime = time();
		$filecount = $totalsize = 0;

		foreach ($files as $file => $crap) {
			foreach ($types as $type) {
				$allfiles .= $this->workdir.'/'.$file.'.'.$type.' ';
				$filecount++;
				$totalsize += filesize($this->workdir.'/'.$file.'.'.$type);
			}
		}
		//$this->log->debug("Uploading ".$allfiles);
		$cmd = $this->cfg->uploadCmd." ".$allfiles;
		$cr = new CommandRunner($this->log);
		/* use exec instead of run() to avoid signal clashes when in daemon
		 * mode */
		$ret = $cr->exec($cmd);
		if ($ret === 0) {
			$this->log->write("sent files: ".$filecount." size: ".$this->formatBytes($totalsize)." duration: ".(time() - $this->lastSendTime)." seconds");
		} else {
			$this->log->write("sending failed: ".$ret);
		}
		if ($ret !== 0) {
			throw new Exception("sending failed");
		}
	}

	protected function dequeueFile($file) {
		foreach ($this->fileQueue[$file] as $type => $crap) {
			unlink($this->workdir.'/'.$file.'.'.$type);
		}
		unset($this->fileQueue[$file]);
	}

	protected function formatBytes($size) {
		$sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
		if ($size == 0) { 
			return('n/a'); 
		} else {
			return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]); 
		}
	}


}


?>
