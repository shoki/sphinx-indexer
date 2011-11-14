<?php 

/**
 * Lock provides simple locking functionality
 * @author Timo Witte <timo.witte@googlemail.com>
 */
class Lock {
	private $lockdir = "/var/lock";

	public function __construct($name = "lock", $lockdir = null) {
		if ($lockdir) $this->lockdir = $lockdir;
		$this->lockfile = $this->lockdir.'/'.$name;
	}

	public function __destrcut() {
	}

	public function lock() {
		if (!$this->isLocked()) {
			if (!file_put_contents($this->lockfile, posix_getpid()))
				throw new LockException("unable to lock");
		} else {
			throw new LockException("already locked");
		}
	}

	public function unlock($force = false) {
		if ($force || $this->isLocked()) {
			unlink($this->lockfile);
		} else {
			throw new LockException("not locked");
		}
	}

	public function isLocked() {
		/* lock is locked when lockfile exsits and the pid in it is still
		 * running */
		if (file_exists($this->lockfile)) {
			if (posix_kill((int)file_get_contents($this->lockfile), 0)) {
				return true;
			} else {
				/* stale lock, process has gone away, force unlock */
				$this->unlock(true);
			}
		} 
		return false;
	}
}

?>
